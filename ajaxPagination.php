<?php 

// AJAX Load More Pagination with Filters for Shop and Category Pages
// Remove the default WooCommerce pagination on shop and category archive pages
add_action('wp', function() {
    if (is_shop() || is_product_category()) {
        remove_action('woocommerce_after_shop_loop', 'woocommerce_pagination', 10);
    }
});

// Add the custom Load More button only on shop and category archive pages
add_action('woocommerce_after_shop_loop', function() {
    if (is_shop() || is_product_category()) {
        global $wp_query;

        $total_products = $wp_query->found_posts;
        $products_per_page = $wp_query->query_vars['posts_per_page'];
        $current_page = max(1, $wp_query->query_vars['paged']);
        $total_pages = ceil($total_products / $products_per_page);

        if ($total_products > 0) {
            if ($current_page < $total_pages) {
                echo '<div id="load-more-container" style="text-align: center; margin-top: 20px;">
                        <button id="load-more-button" style="display: inline-block; width: auto; padding: 10px 30px;">Load More Products</button>
                    </div>';
            } else {
                echo '<div id="end-of-list" style="text-align: center; margin-top: 20px;">No More Products</div>';
            }
        }
    }
}, 15);

// Add custom sorting dropdown on shop and category archive pages
add_action('woocommerce_before_shop_loop', function() {
    if (is_shop() || is_product_category()) {
        ?>
        <div id="custom-sorting-dropdown" style="margin-bottom: 20px; text-align: center;">
            <select id="custom-sorting" style="padding: 5px 10px; width: 60%; margin: auto;">
                <option value="default">Default Sorting</option>
                <option value="latest">Sort by Latest</option>
                <option value="price_low_to_high">Price: Low to High</option>
                <option value="price_high_to_low">Price: High to Low</option>
            </select>
        </div>
        <?php
    }
}, 20);

// AJAX handler for loading more products and filtering
add_action('wp_ajax_load_products', 'load_products');
add_action('wp_ajax_nopriv_load_products', 'load_products');

function load_products() {
    check_ajax_referer('load_products_nonce', 'nonce');

    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $filter = isset($_POST['filter']) ? sanitize_text_field($_POST['filter']) : 'default';

    $args = [
        'post_type' => 'product',
        'posts_per_page' => get_option('posts_per_page'),
        'paged' => $page,
        'post_status' => 'publish',
    ];

    switch ($filter) {
        case 'latest':
            $args['orderby'] = 'date';
            $args['order'] = 'DESC';
            break;
        case 'price_low_to_high':
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = '_price';
            $args['order'] = 'ASC';
            break;
        case 'price_high_to_low':
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = '_price';
            $args['order'] = 'DESC';
            break;
        default:
            $args['orderby'] = 'date';
            $args['order'] = 'DESC';
            break;
    }

    $query = new WP_Query($args);
    ob_start();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            wc_get_template_part('content', 'product');
        }
    }

    $response = [
        'html' => ob_get_clean(),
        'more_products' => $query->max_num_pages > $page,
    ];

    wp_reset_postdata();
    wp_send_json_success($response);
}

// JavaScript to handle AJAX requests on shop and category archive pages
add_action('wp_footer', function() {
    if (is_shop() || is_product_category()) {
        ?>
        <script>
            jQuery(document).ready(function($) {
                let currentPage = 1;
                let currentFilter = 'default';
                let isLoading = false;

                // Initialize filtering on page load
                loadProducts(false);

                // Handle filter changes
                $('#custom-sorting').on('change', function() {
                    if (isLoading) return;

                    currentFilter = $(this).val();
                    currentPage = 1;
                    loadProducts(false);
                });

                // Handle "Load More" button clicks
                $('#load-more-button').on('click', function() {
                    if (isLoading) return;

                    currentPage++;
                    loadProducts(true);
                });

                function loadProducts(isLoadMore) {
                    isLoading = true;

                    if (isLoadMore) {
                        $('#load-more-button').text('Loading...');
                    } else {
                        $('.products').html('<p>Loading...</p>');
                    }

                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'load_products',
                            page: currentPage,
                            filter: currentFilter,
                            is_load_more: isLoadMore,
                            nonce: '<?php echo wp_create_nonce('load_products_nonce'); ?>',
                        },
                        success: function(response) {
                            if (response.success) {
                                if (isLoadMore) {
                                    $('.products').append(response.data.html);
                                    $('#load-more-button').text('Load More Products');
                                } else {
                                    $('.products').html(response.data.html);
                                }

                                // Update Load More button visibility
                                if (response.data.more_products) {
                                    $('#load-more-container').show();
                                    $('#end-of-list').remove();
                                } else {
                                    $('#load-more-container').hide();
                                    if (!$('#end-of-list').length) {
                                        $('#load-more-container').after(
                                            '<div id="end-of-list" style="text-align: center; margin-top: 20px;">No More Products</div>'
                                        );
                                    }
                                }

                                // Trigger WooCommerce events
                                $(document.body).trigger('wc_fragments_loaded');
                                $(document.body).trigger('added_to_cart');
                            }
                            isLoading = false;
                        },
                        error: function() {
                            isLoading = false;
                            if (isLoadMore) {
                                $('#load-more-button').text('Load More Products');
                            }
                        }
                    });
                }
            });
        </script>
        <?php
    }
});

?>