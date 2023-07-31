<?php
/**
 * Plugin Name: WPGraphQL Custom Pagination
 * Plugin URI: https://github.com/agusmu/wp-graphql-custom-pagination
 * Description: Adds custom pagination to the wp-graphql plugin
 * Author: Esa-Matti Suuronen, Valu Digital Oy, Agus Muhammad
 * Version: 0.3.0
 *
 */

namespace WPGraphQL\Extensions;

use WPGraphQL\Data\Connection\AbstractConnectionResolver;
use WPGraphQL\Data\Connection\UserConnectionResolver;

class CustomPagination
{
    public static function init()
    {
        define('WP_GRAPHQL_CUSTOM_PAGINATION', 'initialized');
        (new CustomPagination())->bind_hooks();
    }

    function bind_hooks()
    {
        add_action(
            'graphql_register_types',
            [$this, 'cp_action_register_types'],
            9,
            0
        );

        add_filter(
            'graphql_map_input_fields_to_wp_query',
            [$this, 'cp_filter_map_pagination_to_wp_query_args'],
            10,
            2
        );

        add_filter(
            'graphql_map_input_fields_to_wp_user_query',
            [$this, 'cp_filter_map_pagination_to_wp_user_query_args'],
            10,
            2
        );

        add_filter(
            'graphql_connection_page_info',
            [$this, 'cp_filter_graphql_connection_page_info'],
            10,
            2
        );

        add_filter(
            'graphql_connection_query_args',
            [$this, 'cp_filter_graphql_connection_query_args'],
            10,
            5
        );

        add_filter(
            'graphql_connection_amount_requested',
            [$this, 'cp_filter_graphql_connection_amount_requested'],
            10,
            2
        );
    }

    function cp_filter_graphql_connection_amount_requested($amount, $resolver)
    {
        if (self::is_custom_resolver($resolver)) {
            return self::get_posts_per_page($resolver);
        }

        return $amount;
    }

    /**
     * Returns true when the resolver is resolving offset pagination
     */
    static function get_posts_per_page(AbstractConnectionResolver $resolver)
    {
        $args = $resolver->getArgs();
        return intval($args['where']['customPagination']['posts_per_page'] ?? 0);
    }

    static function is_custom_resolver(AbstractConnectionResolver $resolver)
    {
        $args = $resolver->getArgs();
        return isset($args['where']['customPagination']);
    }

    /**
     * Lazily enable total calculations only when they are asked in the
     * selection set.
     */
    function cp_filter_graphql_connection_query_args(
        $query_args,
        AbstractConnectionResolver $resolver
    ) {
        $info = $resolver->getInfo();
        $selection_set = $info->getFieldSelection(2);

        if (!isset($selection_set['pageInfo']['customPagination']['total'])) {
            // get out if not requesting total counting
            return $query_args;
        }

        if ($resolver instanceof UserConnectionResolver) {
            // Enable slow total counting for user connections
            $query_args['count_total'] = true;
        } else {
            // Enable slow total counting for posts connections
            $query_args['no_found_rows'] = false;
        }

        return $query_args;
    }

    static function add_post_type_fields(\WP_Post_Type $post_type_object)
    {
        $type = ucfirst($post_type_object->graphql_single_name);
        register_graphql_fields("RootQueryTo${type}ConnectionWhereArgs", [
            'customPagination' => [
                'type' => 'CustomPagination',
                'description' => "Paginate ${type}s",
            ],
        ]);
    }

    function cp_filter_graphql_connection_page_info(
        $page_info,
        AbstractConnectionResolver $resolver
    ) {
        $posts_per_page = self::get_posts_per_page($resolver);
        $query = $resolver->get_query();
        $args = $resolver->getArgs();
        $offset = $args['where']['customPagination']['offset'] ?? 0;
        $paged = $args['where']['customPagination']['paged'] ?? 1;

        $total = null;

        if ($query instanceof \WP_Query) {
            $total = $query->found_posts;
        } elseif ($query instanceof \WP_User_Query) {
            $total = $query->total_users;
        }

        if ($offset > 0) {
        	$has_previous = $offset > 0;
			$current_page = $offset / $posts_per_page + 1;
        } else {
        	$has_previous = $paged > 1;
			$current_page = $paged;
        }

    	$has_next = count($resolver->get_ids()) > $posts_per_page;
		$next_page = $has_next ? $current_page + 1 : null;
		$previous_page = $has_previous ? $current_page - 1 : null;
		$total_pages = ceil( intval( $total ) / $posts_per_page );

        $page_info['customPagination'] = [
            'total' => $total,
            'hasPreviousPage' => $has_previous,
            'hasNextPage' => $has_next,
            'previousPage' => $previous_page,
            'currentPage' => $current_page,
            'nextPage' => $next_page,
            'totalPages' => $total_pages,
        ];

        return $page_info;
    }

    function cp_filter_map_pagination_to_wp_query_args(
        array $query_args,
        array $where_args
    ) {
        if (isset($where_args['customPagination']['offset'])) {
            $query_args['offset'] = $where_args['customPagination']['offset'];
        }

        if (isset($where_args['customPagination']['paged'])) {
            $query_args['paged'] = $where_args['customPagination']['paged'];
        }

        if (isset($where_args['customPagination']['posts_per_page'])) {
            // Fetch posts_per_page+1 to be able calculate "hasNextPage" field without
            // slowly counting full totals.
            $query_args['posts_per_page'] =
                intval($where_args['customPagination']['posts_per_page']) + 1;
        }

        return $query_args;
    }

    function cp_filter_map_pagination_to_wp_user_query_args(
        array $query_args,
        array $where_args
    ) {
        if (isset($where_args['customPagination']['offset'])) {
            $query_args['offset'] = $where_args['customPagination']['offset'];
        }

        if (isset($where_args['customPagination']['paged'])) {
            $query_args['paged'] = $where_args['customPagination']['paged'];
        }

        if (isset($where_args['customPagination']['posts_per_page'])) {
            $query_args['number'] =
                intval($where_args['customPagination']['posts_per_page']) + 1;
        }

        return $query_args;
    }

    function cp_action_register_types()
    {
        foreach (\WPGraphQL::get_allowed_post_types() as $post_type) {
            self::add_post_type_fields(get_post_type_object($post_type));
        }

        register_graphql_object_type('CustomPaginationPageInfo', [
            'description' => __(
                'Get information about the custom pagination state',
                'wp-graphql-custom-pagination'
            ),
            'fields' => [
                'total' => [
                    'type' => 'Int',
                ],
                'hasPreviousPage' => [
                    'type' => 'Boolean',
                ],
                'hasNextPage' => [
                    'type' => 'Boolean',
                ],
                'previousPage' => [
                    'type' => 'Int',
                ],
                'currentPage' => [
                    'type' => 'Int',
                ],
                'nextPage' => [
                    'type' => 'Int',
                ],
                'totalPages' => [
                    'type' => 'Int',
                ],
            ],
        ]);

        register_graphql_field('WPPageInfo', 'customPagination', [
            'type' => 'CustomPaginationPageInfo',
            'description' => __(
                'Get information about the custom pagination state in the current connection',
                'wp-graphql-custom-pagination'
            ),
        ]);

        register_graphql_input_type('CustomPagination', [
            'description' => __(
                'Custom pagination input type',
                'wp-graphql-custom-pagination'
            ),
            'fields' => [
                'posts_per_page' => [
                    'type' => 'Int',
                    'description' => __(
                        'Number of post to show per page. Passed to posts_per_page of WP_Query.',
                        'wp-graphql-custom-pagination'
                    ),
                ],
                'paged' => [
                    'type' => 'Int',
                    'description' => __(
                        'Number of page. Passed to paged of WP_Query.',
                        'wp-graphql-custom-pagination'
                    ),
                ],
                'offset' => [
                    'type' => 'Int',
                    'description' => __(
                        'Number of post to displace or pass over. Passed to offset of WP_Query.',
                        'wp-graphql-custom-pagination'
                    ),
                ],
            ],
        ]);

        register_graphql_field(
            'RootQueryToContentNodeConnectionWhereArgs',
            'customPagination',
            [
                'type' => 'CustomPagination',
                'description' => 'Paginate content nodes',
            ]
        );

        register_graphql_field(
            'RootQueryToUserConnectionWhereArgs',
            'customPagination',
            [
                'type' => 'CustomPagination',
                'description' => 'Paginate users',
            ]
        );

        register_graphql_field(
            'CategoryToPostConnectionWhereArgs',
            'customPagination',
            [
                'type' => 'CustomPagination',
                'description' => 'Paginate content nodes',
            ]
        );

        register_graphql_field(
            'TagToPostConnectionWhereArgs',
            'customPagination',
            [
                'type' => 'CustomPagination',
                'description' => 'Paginate content nodes',
            ]
        );
    }
}

\WPGraphQL\Extensions\CustomPagination::init();
