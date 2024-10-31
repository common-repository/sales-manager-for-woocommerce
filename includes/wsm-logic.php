<?php

/**
 * Checks regularly if any sales need to be launched or if any ended
 */
function wsm_check_launch_sales()
{
    global $wpdb;

    date_default_timezone_set(get_option('timezone_string'));
    $now = strtotime(date("Y-m-d H:i:s"));

    // Find sales that ended
    $args = array(
        'post_type'      => 'wsm-scheduled-sale',
        'post_status'   => 'publish',
        'meta_query' => array(
            array(
                'key' => '_wsm_end',
                'value' => $now,
                'compare' => '<=',
                'type' => 'NUMERIC'
            ),
            array(
                'key' => '_wsm_status',
                'value' => 'ended',
                'compare' => '!=',
            ),
        ),
    );
    $loop = new WP_Query($args);
    if ($loop->have_posts()) {

        while ($loop->have_posts()) {
            $loop->the_post();
            // update scheduled sale STATUS to "ended"
            //error_log("found sale ending before: " . date("Y-m-d H:i:s", $now) . ":" . get_the_id());
            update_post_meta(get_the_id(), "_wsm_status", 'ended');
        }
    } else {
        //error_log("found no sales ending before " . date("Y-m-d H:i:s", $now));
    }

    // Find sales to launch
    $args = array(
        'post_type'      => 'wsm-scheduled-sale',
        'post_status'   => 'publish',
        'meta_query' => array(
            array(
                'key' => '_wsm_start',
                'value' => $now,
                'compare' => '<=',
                'type' => 'NUMERIC'
            ),
            array(
                'key' => '_wsm_status',
                'value' => 'pending',
                'compare' => '=',
            ),
        ),
        'posts_per_page' => 1,
    );
    $loop = new WP_Query($args);
    if ($loop->have_posts()) {
        while ($loop->have_posts()) {
            $loop->the_post();
            //error_log("found sale starting before: " . date("Y-m-d H:i:s", $now) . ":" . get_the_id());
            update_post_meta(get_the_id(), "_wsm_status", 'updating');
            wsm_launch_or_stop_sale(get_the_id(), 'launch');
        }
    } else {
        //error_log("found no sales starting before " . date("Y-m-d H:i:s", $now));
    }

    // Find sales to STOP & Cancel
    $args = array(
        'post_type'      => 'wsm-scheduled-sale',
        'post_status'   => 'publish',
        'meta_query' => array(
            array(
                'key' => '_wsm_status',
                'value' => 'await_cancel',
                'compare' => '=',
            ),
        ),
        'posts_per_page' => 1,
    );
    $loop = new WP_Query($args);
    if ($loop->have_posts()) {
        while ($loop->have_posts()) {
            $loop->the_post();
            //error_log("found a sale to cancel: " . get_the_id());
            update_post_meta(get_the_id(), "_wsm_status", 'canceling');
            wsm_launch_or_stop_sale(get_the_id(), 'stop');
        }
    } else {
        //error_log("found no sales to cancel");
    }
}


/**
 * Launch or stop a scheduled sale
 */
function wsm_launch_or_stop_sale($scheduled_sale_id, $action)
{
    global $wpdb;

    set_time_limit(60 * 60);

    $custom = get_post_custom($scheduled_sale_id);
    $discount = $custom["_wsm_discount"][0];
    $start = $custom["_wsm_start"][0];
    $end = $custom["_wsm_end"][0];
    $filters = unserialize($custom["_wsm_filters"][0]);

    // define basic args array
    $args = array(
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => -1,
    );

    // build tax query from filters
    if (count($filters) > 0) {

        $tax_query_array = [];
        // add taxonomy queries
        foreach ($filters as $filter) {

            $tax_query_array[] = array(
                'taxonomy' => $filter['tax'],
                'field' => 'term_id',
                'terms' => $filter['tax_values'],
                'operator'  => $filter['type'] == 'exclude' ? 'NOT IN' : 'IN',
            );
        }

        $args['tax_query'] = $tax_query_array;
    } else {
    }

    // retrieve ignore product ids from database
    $ignore_product_ids = get_option('wsm_ignore_product_ids');
    if (!$ignore_product_ids) {
        $ignore_product_ids_array = [];
    } else {
        $ignore_product_ids_array = explode(",", $ignore_product_ids);
    }
    $args['post__not_in'] = $ignore_product_ids_array;

    /*
    // add filter date query
    if ($infos['ignore_days'] && $infos['ignore_days'] != '0') {
        $args['date_query'] = array(
            array(
                'before' => $infos['ignore_days'] . ' days ago'
            )
        );
    }
    */

    // query products
    $products = new WP_Query($args);

    foreach ($products->posts as $prod) {
        if ($action == 'launch') {
            wsm_set_sale_single($prod->ID, $discount, $start, $end);
        } else if ($action == 'stop') {
            WSM_remove_sale_single($prod->ID);
        }
    }

    // update scheduled sale STATUS
    if ($action == 'launch') {
        update_post_meta($scheduled_sale_id, "_wsm_status", 'running');
    } else if ($action == 'stop') {
        update_post_meta($scheduled_sale_id, "_wsm_status", 'canceled');
    }
}


/**
 * Ajax call to get products for the "ignore products" form
 */
function search_ignore_products()
{

    check_ajax_referer('wsm_nonce', 'ajax_nonce');

    $args = array(
        'post_type' => 'product',
        'post_status'         => 'publish',
        'posts_per_page' => 20,
        's' => $_GET['search'], // the search query,
        'fields'            => 'ids'
    );

    $posts = get_posts($args);

    $found_products = array();

    if ($posts) foreach ($posts as $post) {
        $product = wc_get_product($post);
        $found_products[$post] = $product->get_formatted_name();
    }

    $found_products = apply_filters('woocommerce_json_search_found_products', $found_products);

    echo json_encode($found_products);
    die();
}

/**
 * Ajax call to get the terms available for a specific taxonomy
 */
function wsm_get_tax_terms()
{

    check_ajax_referer('wsm_nonce', 'ajax_nonce');

    $terms = get_terms(array('taxonomy' => $_POST['tax'], 'hide_empty' => true, 'fields' => 'id=>name'));

    $response = array(
        'rowid' => $_POST['rowid'],
        'terms' => $terms,
    );

    echo json_encode($response);
    die();
}


/**
 * Update sale prices for one single product
 */
function wsm_set_sale_single($product_id, $discount, $start, $end)
{

    global $wpdb;

    $product = wc_get_product($product_id);

    if ($product instanceof WC_Product && $product->is_type('simple')) {
        $discounted_price = round($product->get_regular_price() * (100 - $discount) / 100, 2);

        // don't do anything if no values would be changed
        if (
            $product->get_price() == $discounted_price &&
            $product->get_sale_price() == $discounted_price &&
            $product->get_date_on_sale_from() == $start &&
            $product->get_date_on_sale_to() == $end
        ) {
            return;
        }

        $product->set_sale_price($discounted_price);
        $product->set_price($discounted_price);
        if ($start != '') {
            //$product->set_date_on_sale_from(date("Y-m-d H:i:s", strtotime($start)));
            $product->set_date_on_sale_from($start);
        } else {
            $product->set_date_on_sale_from(null);
        }
        if ($end != '') {
            //$product->set_date_on_sale_to(date("Y-m-d H:i:s", strtotime($end)));
            $product->set_date_on_sale_to($end);
        } else {
            $product->set_date_on_sale_to(null);
        }
        $product->save();
    } elseif ($product instanceof WC_Product_Variable && $product->is_type('variable')) {
        // Product has variations
        $variations = $product->get_available_variations();
        foreach ($variations as $key => $var) {
            $variation_id = $variations[$key]['variation_id'];
            $var = new WC_Product_Variation($variation_id);
            $discounted_price = round($var->get_regular_price() * (100 - $discount) / 100, 2);

            // don't do anything if no values would be changed
            if (
                $var->get_price() == $discounted_price &&
                $var->get_sale_price() == $discounted_price &&
                $var->get_date_on_sale_from() == $start &&
                $var->get_date_on_sale_to() == $end
            ) {
                return;
            }

            $var->set_sale_price($discounted_price);
            $var->set_price($discounted_price);

            if ($start != '') {
                $var->set_date_on_sale_from($start);
            } else {
                $var->set_date_on_sale_from(null);
            }

            if ($end != '') {
                $var->set_date_on_sale_to($end);
            } else {
                $var->set_date_on_sale_to(null);
            }

            $var->save();
        }
    }
}


/**
 * Remove sale for one single product
 */
function WSM_remove_sale_single($product_id)
{
    global $wpdb;

    $product = wc_get_product($product_id);

    if ($product instanceof WC_Product && $product->is_type('simple')) {
        $regular_price = $product->get_regular_price();

        // don't do anything if no values would be changed
        if (
            $product->get_price() == $regular_price &&
            $product->get_sale_price() == $regular_price &&
            $product->get_date_on_sale_from() == null &&
            $product->get_date_on_sale_to() == null
        ) {
            return;
        }

        $product->set_sale_price($regular_price);
        $product->set_price($regular_price);
        $product->set_date_on_sale_from(null);
        $product->set_date_on_sale_to(null);
        $product->save();
    } elseif ($product instanceof WC_Product_Variable && $product->is_type('variable')) {
        // Product has variations
        $variations = $product->get_available_variations();
        foreach ($variations as $key => $var) {
            $variation_id = $variations[$key]['variation_id'];
            $var = new WC_Product_Variation($variation_id);
            $regular_price = $var->get_regular_price();

            // don't do anything if no values would be changed
            if (
                $var->get_price() == $regular_price &&
                $var->get_sale_price() == $regular_price &&
                $var->get_date_on_sale_from() == null &&
                $var->get_date_on_sale_to() == null
            ) {
                return;
            }

            $var->set_sale_price($regular_price);
            $var->set_price($regular_price);
            $var->set_date_on_sale_from(null);
            $var->set_date_on_sale_to(null);
            $var->save();
        }
    }
}
