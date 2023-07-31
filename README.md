# wp-graphql-custom-pagination

Adds traditional custom pagination support to WPGraphQL. This is useful only
when you need to implement:

-   Numbered links to the "pages"

**You should not use this plugin if you can avoid it.** The cursors in the
wp-graphql core are faster and more efficient although this plugin should perform
comparatively to a traditional WordPress pagination implementation.

This plugin implements offset pagination for post object (build-in and custom
ones), content nodes, category, tag, and user connections. 

PRs welcome.

## Usage

```graphql
query Posts {
    posts(where: {customPagination: {paged: 2, posts_per_page: 10}}) {
        pageInfo {
            customPagination {
                total
                hasPreviousPage
                hasNextPage
                previousPage
                currentPage
                nextPage
                totalPages
            }
        }
        nodes {
            title
        }
    }
}

```

The where argument is the same for `contentNodes` and `users`.

## Installation

Use must have WPGraphQL v0.8.4 or later installed.

You can clone it from Github to your plugins using the stable branch

    cd wp-content/plugins
    git clone --branch stable https://github.com/agusmu/wp-graphql-custom-pagination.git

## Credits

This a reimplementation of 

- [darylldoyle/wp-graphql-offset-pagination][] by Daryll Doyle. 
- [valu-digital/wp-graphql-offset-pagination][] by Valu Digital Oy. 

[darylldoyle/wp-graphql-offset-pagination]: https://github.com/darylldoyle/wp-graphql-offset-pagination

[valu-digital/wp-graphql-offset-pagination]: https://github.com/valu-digital/wp-graphql-offset-pagination
