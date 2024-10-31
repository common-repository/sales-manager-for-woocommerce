<?php

/**
 * Setup the WP cron to regularly check for sales to launch 
 */
function wsm_setup_cron()
{
    if (!wp_next_scheduled('wsm_check_launch_sales_hook')) {
        wp_schedule_event(time(), 'every_minute', 'wsm_check_launch_sales_hook');
    }
}
add_action('wsm_check_launch_sales_hook', 'wsm_check_launch_sales');
