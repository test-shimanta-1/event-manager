<?php
/*
 * Plugin Name:       IITK Log Manager
 * Description:       Handle the log access info for this iitk sub wevsite.
 * Version:           1.10.3
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Sundew team
 * Author URI:        https://sundewsolutions.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://example.com/my-plugin/
 * Text Domain:       iitk-log-manager
 */

function sdw_event_manager_scripts()
{   
    // wp_enqueue_script( 'my_custom_script', plugins_url('js/jquery.repeatable.js', __FILE__ ), '1.0.0', false );
    wp_enqueue_script( 'bootstra-css', "https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css", '5.0.0', false );
    wp_enqueue_script('bootstrap-js', "https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js", '5.0.0', true);
}
add_action('admin_enqueue_scripts', 'sdw_event_manager_scripts');

function register_options_page()
{
    $plugin_slug = "event_manager";

    add_menu_page( 'Event Manager', 'Event Manager', 'edit', $plugin_slug, null,  plugins_url('/assets/icon/icon.png', __FILE__), '58',);

    add_submenu_page(
        $plugin_slug,
        'Dashboard',
        'Dashboard',
        'manage_options',
        'event_manager_dashboard',
        'event_manager_func'
    );
}
add_action('admin_menu', 'register_options_page');

function event_manager_func()
{
    require (plugin_dir_path(__FILE__) . 'admin/dashboard.php');
}

