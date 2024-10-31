<?php

/**
 * Add custom post type "Scheduled Sales"
 */
function create_posttype()
{

    // Set UI labels for Custom Post Type
    $labels = array(
        'name'                => __('Scheduled Sales', 'sales-manager-for-woocommerce'),
        'singular_name'       => __('Scheduled Sale', 'sales-manager-for-woocommerce'),
        'menu_name'           => __('Scheduled Sales', 'sales-manager-for-woocommerce'),
        'parent_item_colon'   => __('Parent Scheduled Sale', 'sales-manager-for-woocommerce'),
        'all_items'           => __('All Scheduled Sales', 'sales-manager-for-woocommerce'),
        'view_item'           => __('View Scheduled Sale', 'sales-manager-for-woocommerce'),
        'add_new_item'        => __('Add New Scheduled Sale', 'twentysales-manager-for-woocommercetwenty'),
        'add_new'             => __('Add New', 'sales-manager-for-woocommerce'),
        'edit_item'           => __('Edit Scheduled Sale', 'sales-manager-for-woocommerce'),
        'update_item'         => __('Update Scheduled Sale', 'sales-manager-for-woocommerce'),
        'search_items'        => __('Search Scheduled Sale', 'sales-manager-for-woocommerce'),
        'not_found'           => __('Not Found', 'sales-manager-for-woocommerce'),
        'not_found_in_trash'  => __('Not found in Trash', 'sales-manager-for-woocommerce'),
    );

    $args = array(
        'label'               => __('Scheduled Sale', 'twentytwenty'),
        'description'         => __('Scheduled Sales', 'twentytwenty'),
        'labels'              => $labels,
        // Features this CPT supports in Post Editor
        'supports'            => array('title'),
        'hierarchical'        => false,
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_nav_menus'   => false,
        'show_in_admin_bar'   => true,
        'menu_position'       => 57,
        'menu_icon'           => 'dashicons-calendar',
        'can_export'          => true,
        'has_archive'         => false,
        'exclude_from_search' => true,
        'publicly_queryable'  => false,
        'capability_type'     => 'post',
        'show_in_rest' => false,

    );

    register_post_type(
        'wsm-scheduled-sale',
        $args
    );
}
// Hooking up our function to theme setup
add_action('init', 'create_posttype', 0);

/**
 * Remove custom post type "scheduled sales" bulk actions
 */
add_filter('bulk_actions-edit-wsm-scheduled-sale', '__return_empty_array', 100);

/**
 * Add meta boxes to Scheduled Sales
 */
function add_post_meta_boxes()
{
    add_meta_box(
        "wsm-scheduled-sale-discount", // div id containing rendered fields
        "Discount", // section heading displayed as text
        "wsm_scheduled_sale_discount_html", // callback function to render fields
        "wsm-scheduled-sale", // name of post type on which to render fields
        "advanced", // location on the screen
        "low" // placement priority
    );
    add_meta_box(
        "wsm-scheduled-sale-start", // div id containing rendered fields
        "Start the sale:", // section heading displayed as text
        "wsm_scheduled_sale_start_html", // callback function to render fields
        "wsm-scheduled-sale", // name of post type on which to render fields
        "advanced", // location on the screen
        "low" // placement priority
    );
    add_meta_box(
        "wsm-scheduled-sale-end", // div id containing rendered fields
        "End the sale:", // section heading displayed as text
        "wsm_scheduled_sale_end_html", // callback function to render fields
        "wsm-scheduled-sale", // name of post type on which to render fields
        "advanced", // location on the screen
        "low" // placement priority
    );
    add_meta_box(
        "wsm-scheduled-filters", // div id containing rendered fields
        "Filters:", // section heading displayed as text
        "wsm_scheduled_sale_filters_html", // callback function to render fields
        "wsm-scheduled-sale", // name of post type on which to render fields
        "advanced", // location on the screen
        "low" // placement priority
    );

}
add_action("admin_init", "add_post_meta_boxes");

/**
 * Save changes to scheduled sale EDIT page
 */
function save_post_meta_boxes($post_id, $post)
{

    if ($post->post_type != 'wsm-scheduled-sale') {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (get_post_status($post->ID) === 'auto-draft') {
        return;
    }

    if (get_post_status($post->ID) === 'trash') {
        return;
    }

    date_default_timezone_set(get_option('timezone_string'));

    // process and serialize array of filters

    $filters = array();

    if (isset($_POST['_wsm_filter_ids_array'])) {
        if (($_POST['_wsm_filter_ids_array'] != '') && ($_POST['_wsm_filter_ids_array'] != null)) {

            foreach (explode(",", $_POST['_wsm_filter_ids_array']) as $i) {
                $filters[] = array(
                    'type' => $_POST['_wsm_type_' . $i],
                    'tax' => $_POST['_wsm_tax_' . $i],
                    'tax_values' => $_POST['_wsm_tax_values_' . $i],
                );
            }
        }
    }

    if (empty($post->post_title)) {
        wp_update_post(
            array(
                'ID'         => $post_id,
                'post_title' => 'Untitled Sale'
            )
        );
    }

    $the_post_status = get_post_status($post_id);

    // if we published the scheduled sale, set status from draft to pending
    if ($the_post_status == 'publish') {
        $_POST['_wsm_status'] = 'pending';
    }

    /**
     * If we edited an existing sale, set its status to "pending" again so that the process is re-run
     */
    if ((get_post_meta($post->ID, "_wsm_status", true) != 'pending') && (get_post_meta($post->ID, "_wsm_status", true) != '')) {
        $changes = changed_any_setting($post, $filters);
        if (count($changes)) {
            error_log("changed " . print_r($changes, true) . " in the sale settings");
            $_POST["_wsm_status"] = 'pending';
        } else {
            error_log("changed nothing in the sale settings");
        }
    }

    update_post_meta($post->ID, "_wsm_discount", sanitize_text_field($_POST["_wsm_discount"]));
    update_post_meta($post->ID, "_wsm_status", sanitize_text_field($_POST["_wsm_status"]));

    // process start date
    $start = strtotime(sanitize_text_field($_POST["_wsm_start"]) . " " . sanitize_text_field($_POST["_wsm_start_time"]));
    update_post_meta($post->ID, "_wsm_start", $start);

    // process end date
    $end = strtotime(sanitize_text_field($_POST["_wsm_end"]) . " " . sanitize_text_field($_POST["_wsm_end_time"]));
    update_post_meta($post->ID, "_wsm_end", $end);

    // set status to pending if first time!
    if (get_post_meta($post->ID, '_wsm_status', true) == '') {
        update_post_meta($post->ID, "_wsm_status", 'pending');
    }

    // update filters
    update_post_meta($post->ID, "_wsm_filters", $filters);
}
add_action('save_post', 'save_post_meta_boxes', 10, 2);


/**
 * Display DISCOUNT Meta Box Content in edit/add new page
 */
function wsm_scheduled_sale_discount_html()
{
    global $post;
    $custom = get_post_custom($post->ID);
    if (isset($custom["_wsm_discount"])) {
        $discount = $custom["_wsm_discount"][0];
    } else {
        $discount = 0;
    }

?>

    <?php wp_nonce_field(basename(__FILE__), 'wsm_nonce'); ?>
    <p>
        <label for="_wsm_discount"><?php _e("Set the % Discount for this sale:", 'example'); ?></label>
        <br />
        <input class="widefat" type="number" name="_wsm_discount" min="1" max="99" id="_wsm_discount" value="<?php echo esc_attr($discount); ?>" size="30" />
    </p>

<?php

}

/**
 * Display SALE START Meta Box Content in edit/add new page
 */
function wsm_scheduled_sale_start_html()
{
    global $post;

    date_default_timezone_set(get_option('timezone_string'));

    $custom = get_post_custom($post->ID);
    if (isset($custom["_wsm_start"])) {
        $start = date("Y-m-d H:i:s", $custom["_wsm_start"][0]);
        $startdate = substr($start, 0, 10);
        $starttime = substr($start, 11, 5);
    } else {
        $startdate = date("Y-m-d");
        $starttime = "00:00";
    }

?>

    <?php wp_nonce_field(basename(__FILE__), 'wsm_nonce'); ?>
    <p>
        <label for="_wsm_start"><?php _e("Set the Start date & time for this sale:", 'example'); ?></label>
        <br />
        <input class="" type="date" name="_wsm_start" id="_wsm_start" value="<?php echo esc_attr($startdate); ?>" size="30" />
        <input class="" type="time" name="_wsm_start_time" id="_wsm_start_time" value="<?php echo esc_attr($starttime); ?>" size="30" />
    </p>

<?php

}

/**
 * Display SALE END Meta Box Content in edit/add new page
 */
function wsm_scheduled_sale_end_html()
{
    global $post;

    date_default_timezone_set(get_option('timezone_string'));

    $custom = get_post_custom($post->ID);
    if (isset($custom["_wsm_end"])) {
        $end = date("Y-m-d H:i:s", $custom["_wsm_end"][0]);
        $enddate = substr($end, 0, 10);
        $endtime = substr($end, 11, 5);
    } else {
        $enddate = date("Y-m-d", strtotime('+1 day'));
        $endtime = "00:00";
    }

?>

    <?php wp_nonce_field(basename(__FILE__), 'wsm_nonce'); ?>
    <p>
        <label for="_wsm_end"><?php _e("Set the End date & time for this sale:", 'example'); ?></label>
        <br />
        <input class="large" type="date" name="_wsm_end" id="_wsm_end" value="<?php echo esc_attr($enddate); ?>" size="30" />
        <input class="" type="time" name="_wsm_end_time" id="_wsm_end_time" value="<?php echo esc_attr($endtime); ?>" size="30" />
    </p>

<?php

}


/**
 * Display STATUS Meta Box Content in edit/add new page
 */
function wsm_scheduled_sale_status_html()
{
    global $post;
    $custom = get_post_custom($post->ID);
    if (isset($custom["_wsm_status"])) {
        $status = $custom["_wsm_status"][0];
    } else {
        $status = 'draft';
    }

    switch ($status) {
        case 'draft':
            echo '<div class="badge badge-secondary">Draft</div>';
            break;
        case 'pending':
            $start = get_post_meta($post->ID, '_wsm_start', true);
            $now = strtotime(date("Y-m-d H:i:s"));
            if ($start > $now) {
                echo '<div class="badge badge-warning">Pending</div>';
            } else {
                echo '<div class="badge badge-warning">Awaiting Processing...</div>';
            }
            break;
        case 'await_cancel':
            echo '<div class="badge badge-info">Awaiting Canceling...</div>';
            break;
        case 'updating':
            echo '<div class="badge badge-info">Updating</div>';
            break;
        case 'canceling':
            echo '<div class="badge badge-info">Canceling</div>';
            break;
        case 'running':
            echo '<div class="badge badge-success">Running</div>';
            break;
        case 'ended':
            echo '<div class="badge badge-secondary">Ended</div>';
            break;
        case 'canceled':
            echo '<div class="badge badge-secondary">Canceled</div>';
            break;
        default:
            break;
    }
}

/**
 * Display FILTERS Meta Box Content in edit/add new page
 */
function wsm_scheduled_sale_filters_html()
{
    global $post;
    $custom = get_post_custom($post->ID);
    if (isset($custom["_wsm_filters"])) {
        $filters_string = $custom["_wsm_filters"][0];
    } else {
        $filters_string = '';
    }

    $filters = unserialize($filters_string);

    $taxs = get_object_taxonomies('product', 'objects');
    unset($taxs['product_shipping_class']);
    unset($taxs['product_type']);
    unset($taxs['product_visibility']);

    $filter_count = 0;

    if (!empty($filters)) {
        $filter_count = count($filters);
    }

    $filters_ids_array = array();
    for ($i = 0; $i < $filter_count; $i++) {
        $filters_ids_array[] = $i;
    }

    $filters_ids_array_imploded = implode(",", $filters_ids_array);

?>
    <p>Include only products where:</p>
    <input type="hidden" id="_wsm_filter_count" name="_wsm_filter_count" value="<?php echo $filter_count; ?>">
    <input type="hidden" id="_wsm_filter_ids_array" name="_wsm_filter_ids_array" value="<?php echo $filters_ids_array_imploded; ?>">
    <?php
    if (!empty($filters)) {
        for ($i = 0; $i < $filter_count; $i++) {
    ?>
            <p id="_wsm_filter_row_<?php echo $i; ?>" class="_wsm_filter_row" data-active="yes" data-rowid="<?php echo $i; ?>">
                <?php if ($i > 0 && $i < ($filter_count)) {
                    echo '<span class="andclass">AND</span> ';
                } ?>
                <span>
                    <select class="_wsm_tax" name="_wsm_tax_<?php echo $i; ?>" style="width: 150px;">
                        <?php
                        foreach ($taxs as $key => $value) {
                        ?>
                            <option <?php echo $filters[$i]['tax'] == $key ? 'selected="selected"' : ''; ?> value="<?php echo $value->name; ?>"><?php echo $value->labels->singular_name; ?></option>
                        <?php
                        }
                        ?>
                    </select>
                </span>
                <span>
                    <select name="_wsm_type_<?php echo $i; ?>" class="wsm_type" style="width: 100px;">
                        <option <?php echo $filters[$i]['type'] == 'include' ? 'selected="selected"' : ''; ?> value="include">is</option>
                        <option <?php echo $filters[$i]['type'] == 'exclude' ? 'selected="selected"' : ''; ?> value="exclude">is not</option>
                    </select>
                </span>
                <span>
                    <select name="_wsm_tax_values_<?php echo $i; ?>[]" id="_wsm_tax_values_<?php echo $i; ?>" multiple="multiple" class="wsm_tax_values">
                        <?php
                        $my_tax_terms = get_terms($filters[$i]['tax'], array('hide_empty' => true));
                        if (isset($filters[$i]['tax_values'])) {
                            foreach ($my_tax_terms as $key => $tax_term) {
                        ?>
                                <option <?php echo in_array($tax_term->term_id, $filters[$i]['tax_values']) ? 'selected="selected"' : ''; ?> value="<?php echo $tax_term->term_id; ?>"><?php echo $tax_term->name; ?></option>
                        <?php
                            }
                        }
                        ?>
                    </select>
                </span>
                <span class="wsm_remove">
                    <button id="_wsm_remove_row_<?php echo $i; ?>" class="_wsm_remove_row button primary">Remove</button>
                </span>
            </p>

    <?php
        }
    }

    ?>

    <p>
        <button id="_wsm_add_row" class="button primary">Add</button>
    </p>

<?php

}

/**
 * Add columns to Scheduled Sales Management ADMIN table
 */
add_filter('manage_wsm-scheduled-sale_posts_columns', 'wsm_custom_admin_table_columns');
function wsm_custom_admin_table_columns($columns)
{
    $columns = array(
        'titlecust' => __('Title'),
        'discount' => __('Discount', 'sales-manager-for-woocommerce'),
        'filters' => __('Filters', 'sales-manager-for-woocommerce'),
        'runs' => __('Start/End', 'sales-manager-for-woocommerce'),
        'status' => __('Status', 'sales-manager-for-woocommerce'),
    );

    return $columns;
}

/**
 * Add columns CONTENT to Scheduled Sales Management ADMIN table
 */
add_action('manage_wsm-scheduled-sale_posts_custom_column', 'wsm_custom_admin_table_column', 10, 2);
function wsm_custom_admin_table_column($column, $post_id)
{
    date_default_timezone_set(get_option('timezone_string'));

    if ('titlecust' === $column) {
        $status = get_post_meta($post_id, '_wsm_status', true);
        if ($status == 'draft') {
            echo edit_post_link(get_the_title(), '', '', get_the_id(), 'row-title');
        } else {
            echo '<strong>' . get_the_title() . '</strong>';
        }
    } else if ('discount' === $column) {
        echo '<span style="color:green;font-weight:bold;">' . get_post_meta($post_id, '_wsm_discount', true) . '%</span>';
    } else if ('filters' === $column) {
        $filters = get_post_meta($post_id, '_wsm_filters')[0];
        if (count($filters) > 0) {
            echo '<ul>';
            foreach ($filters as $filter) {
                echo '<li><strong>';
                echo $filter['type'] == 'exclude' ? 'Exclude' : 'Include';
                echo '</strong> products where <strong>' . get_taxonomy($filter['tax'])->labels->singular_name . '</strong> is <strong>';
                foreach ($filter['tax_values'] as $key => $value) {
                    $filter['tax_values'][$key] = get_term($value)->name;
                }
                echo implode(",", $filter['tax_values']) . '</strong>';
                echo '</li>';
            }
            echo '</ul>';
        }
    } else if ('runs' === $column) {
        $start = get_post_meta($post_id, '_wsm_start', true);
        $end = get_post_meta($post_id, '_wsm_end', true);
        echo 'Runs from <strong>' . date("F j, Y, g:i a", $start) . '</strong> to <strong>' . date("F j, Y, g:i a", $end) . '</strong>';
    } else if ('status' === $column) {
        $post_status = get_post_status($post_id);
        $status = get_post_meta($post_id, '_wsm_status', true);

        if ($post_status == 'draft') {
            echo '<div class="badge badge-secondary">Draft</div>';
        } else {
            switch ($status) {
                case 'pending':
                    $start = get_post_meta($post_id, '_wsm_start', true);
                    $now = strtotime(date("Y-m-d H:i:s"));
                    if ($start > $now) {
                        echo '<div class="badge badge-warning">Pending</div>';
                        $diff = abs($now - $start);
                        $tmins = $diff / 60;
                        $hours = floor($tmins / 60);
                        $mins = $tmins % 60;
                        echo '<br/><span style="font-weight:bold;color:maroon;">Starts in ' . convert_seconds($diff);
                    } else {
                        echo '<div class="badge badge-warning">Awaiting Processing...</div>';
                    }
                    break;
                case 'await_cancel':
                    echo '<div class="badge badge-warning">Awaiting Canceling...</div>';
                    break;
                case 'updating':
                    echo '<div class="badge badge-info">Updating Products...</div>';
                    break;
                case 'canceling':
                    echo '<div class="badge badge-info">Canceling Sale...</div>';
                    break;
                case 'running':
                    echo '<div class="badge badge-success">Running</div>';
                    $end = get_post_meta($post_id, '_wsm_end', true);
                    $now = strtotime(date("Y-m-d H:i:s"));
                    $diff = abs($end - $now);
                    $tmins = $diff / 60;
                    $hours = floor($tmins / 60);
                    $mins = $tmins % 60;
                    echo '<br/><span style="font-weight:bold;color:maroon;">Ends in ' . convert_seconds($diff);
                    break;
                case 'ended':
                    echo '<div class="badge badge-secondary">Ended</div>';
                    break;
                case 'canceled':
                    echo '<div class="badge badge-secondary">Canceled</div>';
                    break;
                default:
                    break;
            }
        }
    } else if ('actions' === $column) {
        echo '<a href="' . get_edit_post_link($post_id) . '" class="button primary"><span class="dashicons dashicons-edit"></span></a>';
        $stop_url = add_query_arg(
            array(
                'post_id' => $post_id,
                'my_action' => 'wsm_stop_cancel_sale',
            )
        );
        echo '<a href="' . esc_url($stop_url) . '" class="button primary"><span class="dashicons dashicons-no"></span></a>';
        echo '<a href="' . get_delete_post_link($post_id) . '" class="button primary"><span class="dashicons dashicons-trash"></span></a>';
    }
}

/**
 * convert seconds into days, hours, minutes and seconds
 */
function convert_seconds($seconds)
{
    $dt1 = new DateTime("@0");
    $dt2 = new DateTime("@$seconds");
    return $dt1->diff($dt2)->format('%a days, %h hours, %i minutes and %s seconds');
}

/**
 * This function checks if we modified any settings when submitting a sale
 */

function changed_any_setting($post, $filters)
{

    $changes = array();

    if (get_post_meta($post->ID, '_wsm_discount', true) != sanitize_text_field($_POST["_wsm_discount"])) {
        $changes[] = 'discount';
    }
    $start = strtotime(sanitize_text_field($_POST["_wsm_start"]) . " " . sanitize_text_field($_POST["_wsm_start_time"]));
    if (get_post_meta($post->ID, '_wsm_start', true) != $start) {
        $changes[] = 'start';
    }
    $end = strtotime(sanitize_text_field($_POST["_wsm_end"]) . " " . sanitize_text_field($_POST["_wsm_end_time"]));
    if (get_post_meta($post->ID, '_wsm_end', true) != $end) {
        $changes[] = 'end';
    }
    if (get_post_meta($post->ID, "_wsm_filters", true) != $filters) {
        $changes[] = 'filters';
    }

    return $changes;
}

/**
 * Sets the possible "actions" row on the scheduled sales managemement page.
 */
add_filter('post_row_actions', 'remove_row_actions_post', 10, 2);
function remove_row_actions_post($actions, $post)
{

    if ($post->post_type === 'wsm-scheduled-sale') {

        unset($actions['inline']);
        unset($actions['inline hide-if-no-js']);

        if (get_post_status($post->ID) == 'draft') {
        } else if (get_post_meta($post->ID, "_wsm_status", true) == 'pending') {
            unset($actions['edit']);
        } else {
            if ((get_post_meta($post->ID, "_wsm_status", true) == 'await_cancel') || (get_post_meta($post->ID, "_wsm_status", true) == 'canceling')) {
                unset($actions['trash']);
                unset($actions['edit']);
            } else if ((get_post_meta($post->ID, "_wsm_status", true) != 'ended') && (get_post_meta($post->ID, "_wsm_status", true) != 'canceled')) {
                unset($actions['trash']);
                unset($actions['edit']);
                $url = add_query_arg(
                    array(
                        'post_id' => $post->ID,
                        'my_action' => 'wsm_stop_cancel_sale',
                    )
                );
                $actions['stop'] = '<span class="trash"><a href="' . esc_url($url) . '">Stop Sale</a></span>';
            } else {
                unset($actions['edit']);
            }
        }
    }

    return $actions;
}

/**
 * Stop & cancel a sale
 */
add_action('admin_init', 'wsm_stop_cancel_sale');

function wsm_stop_cancel_sale()
{

    $post_id = null;

    if (isset($_REQUEST['post_id'])) {
        $post_id = $_REQUEST['post_id'];
    }

    if (
        isset($_REQUEST['my_action']) &&
        'wsm_stop_cancel_sale' == $_REQUEST['my_action']
    ) {
        if (get_post_meta($post_id, "_wsm_status", true) == 'running') {
            //error_log("stopped & canceled sale ID:" . $post_id);
            update_post_meta($post_id, "_wsm_status", 'await_cancel');
        }
    }
}

/**
 * Redirect to sales list after publishing or submitting a new sale
 */
add_filter('redirect_post_location', 'wsm_redirect_post_location');
function wsm_redirect_post_location($location)
{

    if ('wsm-scheduled-sale' == get_post_type()) {

        /* Custom code for 'deals' post type. */

        if (isset($_POST['save']) || isset($_POST['publish']))
            return admin_url("edit.php?post_type=wsm-scheduled-sale");
    }
    return $location;
}
