<?php
/*
  Plugin Name: Sales Manager For WooCommerce
  Plugin URI: https://wordpress.org/plugins/sales-manager-for-woocommerce
  Description: Create & Schedule Special % Sales/Discounts Easily
  Author: J2FB
  Author URI: https://www.j2fb.com
  Version: 2.0
  WC tested up to: 4.8
  License: GPLv3
  License URI: https://www.gnu.org/licenses/gpl-3.0.html
  Text Domain: sales-manager-for-woocommerce
 */

if (!defined('ABSPATH')) {
    return;
}

/**
 * Check if WooCommerce is active
 */
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) && !array_key_exists('woocommerce/woocommerce.php', apply_filters('active_plugins', get_site_option('active_sitewide_plugins', array())))) { // deactive if woocommerce in not active
    require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    deactivate_plugins(plugin_basename(__FILE__));
}

/**
 * Include all other code
 */
include_once('includes/wsm-settings-page.php');
include_once('includes/wsm-activation.php');
include_once('includes/wsm-logic.php');
include_once('includes/wsm-custom-post-type.php');

/**
 * register activation hooks
 */
register_activation_hook(__FILE__, 'wsm_setup_cron');

/**
 * add settings link to plugin page
 */
function my_plugin_settings_link($links)
{
    $settings_link = '<a href="' . admin_url('') . 'edit.php?post_type=wsm-scheduled-sale&page=wsm-scheduled-sale-settings">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter("plugin_action_links_" . plugin_basename(__FILE__), 'my_plugin_settings_link');

/**
 * Enqueue styles & scripts
 */
function wsm_enqueue_scripts()
{

    if (!wp_script_is('select2css', 'registered')) {
        wp_register_style('select2css', plugin_dir_url(__FILE__) . 'assets/css/select2.min.css', false, '1.0', 'all');
    }

    if (!wp_script_is('select2', 'registered')) {
        wp_register_script('select2', plugin_dir_url(__FILE__) . 'assets/js/select2.min.js', array('jquery'), '1.0', true);
    }

    wp_enqueue_style('select2css');
    wp_enqueue_script('select2');

    wp_register_style('wsm-style', plugin_dir_url(__FILE__) . 'assets/css/wsm-style.css', false, '1.0', 'all');
    wp_enqueue_style('wsm-style');

    wp_register_script('wsm-sales-manager-js',  plugin_dir_url(__FILE__) . 'assets/js/wsm-sales-manager.js', ['jquery'], false, true);
    wp_enqueue_script('wsm-sales-manager-js');

    $taxs = get_object_taxonomies('product', 'objects');
    unset($taxs['product_shipping_class']);
    unset($taxs['product_type']);
    unset($taxs['product_visibility']);

    wp_localize_script('wsm-sales-manager-js', 'ajax_object', array(
        'nonce' => wp_create_nonce('wsm_nonce'),
        'taxs' => $taxs
    ));
}
add_action('admin_enqueue_scripts', 'wsm_enqueue_scripts');


/**
 * Add admin menu option under 'WooCommerce'
 */
function wsm_add_settings_menu()
{
    add_submenu_page(
        'edit.php?post_type=wsm-scheduled-sale',
        'Settings',
        'Settings',
        'manage_options',
        'wsm-scheduled-sale-settings',
        'wsm_settings_page'
    );
}
add_action('admin_menu', "wsm_add_settings_menu");


/**
 * AJAX functions
 */
add_action("wp_ajax_search_ignore_products", "search_ignore_products");
add_action("wp_ajax_wsm_get_tax_terms", "wsm_get_tax_terms");
