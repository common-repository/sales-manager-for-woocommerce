<?php

/**
 * Display the main settings page + process any submitted form data
 */
function wsm_settings_page()
{

    if (!current_user_can('manage_options')) {
        wp_die("<h2>To view this page you must first log in.</h2>");
    }

    /**
     * Process submitted form data
     */
    if (isset($_GET['task'])) {
        if ($_GET['task'] == 'ignore_products' && isset($_POST['save_ignored_ids'])) {
            /* save ignore product form data to database */
            $ignoreids = array_map('esc_attr', $_POST['ignore_ids_select2']);
            update_option('wsm_ignore_product_ids', implode(",", $ignoreids));
        }
    }

    /* get form data from database and process it */
    $ignore_product_ids_and_names_array = [];

    $ignore_product_ids = get_option('wsm_ignore_product_ids');
    if (!$ignore_product_ids) {
        $ignore_product_ids_array = [];
    } else {
        $ignore_product_ids_array = explode(",", $ignore_product_ids);
        foreach ($ignore_product_ids_array as $post) {
            $product = wc_get_product($post);
            $ignore_product_ids_and_names_array[$post] = $product->get_formatted_name();
        }
    }
    $ignore_product_ids_and_names_array = apply_filters('woocommerce_json_search_found_products', $ignore_product_ids_and_names_array);

?>

    <h1>
        <?php esc_html_e('Sales Manager For WooCommerce', 'sales-manager-for-woocommerce'); ?>
    </h1>

    <div class="wrap">

        <div id="poststuff">

            <div id="post-body">

                <!-- main content -->
                <div id="post-body-content">

                    <h1><?php esc_attr_e('General Settings', 'WpAdminStyle'); ?></h1>
                    <div class="meta-box-sortables ui-sortable">

                        <div class="postbox">

                            <div class="inside">

                                <h3><span><?php esc_attr_e('Always Ignore these products:', 'WpAdminStyle'); ?></span></h3>

                                <p>Any products selected below will be ignored when it comes to launching/scheduling/removing sales.</p>

                                <form method="POST" id='ignore_products_form' action="<?php echo admin_url('') . 'edit.php?post_type=wsm-scheduled-sale&page=wsm-scheduled-sale-settings&task=ignore_products'; ?>">

                                    <p><label for="ignore_ids_select2">Ignore Products:
                                            <select id="ignore_ids_select2" name="ignore_ids_select2[]" style="width: 50%" multiple="multiple">
                                                <?php foreach ($ignore_product_ids_and_names_array as $key => $value) {
                                                    echo '<option selected="selected" value="' . $key . '">' . $value . '</option>';
                                                } ?>
                                            </select>
                                    </p>
                                    <p>
                                        <input class="button-primary" id="save-ignored-ids" type="submit" name="save_ignored_ids" value="<?php esc_attr_e('Save'); ?>" />
                                    </p>

                                </form>

                            </div>
                            <!-- .postbox -->

                        </div>
                        <!-- .meta-box-sortables .ui-sortable -->

                    </div>


                </div>
                <!-- #post-body .metabox-holder .columns-2 -->

                <br class="clear">
            </div>
            <!-- #poststuff -->

        </div> <!-- .wrap -->

    <?php

}
