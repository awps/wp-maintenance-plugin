<?php
/**
 * Plugin Name: WP Simple Maintenance
 * Plugin URI: https://example.com/wp-simple-maintenance
 * Description: A simple maintenance mode plugin that shows a maintenance page to logged-out users and adds a red notice to the admin bar.
 * Version: 1.0.0
 * Author: Claude
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-simple-maintenance
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Main plugin class
class WP_Simple_Maintenance {
    
    // Plugin instance
    private static $instance = null;
    
    // Constructor
    private function __construct() {
        // Initialize hooks
        add_action('init', array($this, 'init'));
    }
    
    // Get singleton instance
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // Initialize plugin
    public function init() {
        // Add maintenance mode hook
        add_action('template_redirect', array($this, 'maintenance_template'));
        
        // Add admin bar notice
        add_action('admin_bar_menu', array($this, 'add_admin_bar_notice'), 999);
        
        // Add admin styles
        add_action('admin_head', array($this, 'admin_styles'));
        add_action('wp_head', array($this, 'admin_styles'));
        
        // Add settings page
        add_action('admin_menu', array($this, 'add_settings_page'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    // Check if maintenance mode is active
    public function is_maintenance_active() {
        return get_option('wp_simple_maintenance_active', false);
    }
    
    // Display maintenance template for logged-out users
    public function maintenance_template() {
        // Exit if maintenance mode is not active
        if (!$this->is_maintenance_active()) {
            return;
        }
        
        // Only show maintenance page to logged-out users
        if (!is_user_logged_in()) {
            // Set response code
            status_header(503);
            header('Retry-After: 3600');
            
            // Get maintenance message
            $maintenance_message = get_option('wp_simple_maintenance_message', 'We are currently performing scheduled maintenance. Please check back soon.');
            
            // Display maintenance template
            echo '<!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>' . esc_html(get_bloginfo('name')) . ' - Maintenance Mode</title>
                <style>
                    body {
                        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                        background-color: #f1f1f1;
                        color: #333;
                        margin: 0;
                        padding: 0;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        min-height: 100vh;
                    }
                    .maintenance-container {
                        background-color: #fff;
                        border-radius: 5px;
                        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                        padding: 40px;
                        text-align: center;
                        max-width: 80%;
                        width: 600px;
                    }
                    h1 {
                        color: #23282d;
                        margin-top: 0;
                    }
                    .dashicons {
                        font-size: 48px;
                        width: 48px;
                        height: 48px;
                        color: #dc3232;
                    }
                </style>
                ' . wp_head() . '
            </head>
            <body>
                <div class="maintenance-container">
                    <span class="dashicons dashicons-hammer"></span>
                    <h1>Maintenance Mode</h1>
                    <p>' . wp_kses_post($maintenance_message) . '</p>
                </div>
            </body>
            </html>';
            exit;
        }
    }
    
    // Add a red notice to the admin bar
    public function add_admin_bar_notice($wp_admin_bar) {
        // Only show if maintenance mode is active
        if ($this->is_maintenance_active() && is_admin_bar_showing()) {
            $args = array(
                'id'    => 'wp-simple-maintenance-notice',
                'title' => 'Maintenance Mode Active',
                'href'  => admin_url('options-general.php?page=wp-simple-maintenance'),
                'meta'  => array(
                    'class' => 'wp-simple-maintenance-notice'
                )
            );
            $wp_admin_bar->add_node($args);
        }
    }
    
    // Add custom admin styles
    public function admin_styles() {
        if ($this->is_maintenance_active() && is_admin_bar_showing()) {
            echo '<style>
                #wp-admin-bar-wp-simple-maintenance-notice {
                    background-color: #dc3232 !important;
                    color: #fff !important;
                    font-weight: bold;
                }
                #wp-admin-bar-wp-simple-maintenance-notice .ab-item {
                    color: #fff !important;
                }
            </style>';
        }
    }
    
    // Add settings page
    public function add_settings_page() {
        add_options_page(
            'Maintenance Mode Settings',
            'Maintenance Mode',
            'manage_options',
            'wp-simple-maintenance',
            array($this, 'settings_page_content')
        );
    }
    
    // Settings page content
    public function settings_page_content() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('wp_simple_maintenance_options');
                do_settings_sections('wp-simple-maintenance');
                submit_button('Save Settings');
                ?>
            </form>
        </div>
        <?php
    }
    
    // Register settings
    public function register_settings() {
        register_setting(
            'wp_simple_maintenance_options',
            'wp_simple_maintenance_active',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => false,
            )
        );
        
        register_setting(
            'wp_simple_maintenance_options',
            'wp_simple_maintenance_message',
            array(
                'type' => 'string',
                'sanitize_callback' => 'wp_kses_post',
                'default' => 'We are currently performing scheduled maintenance. Please check back soon.',
            )
        );
        
        add_settings_section(
            'wp_simple_maintenance_section',
            'Maintenance Mode Settings',
            array($this, 'settings_section_callback'),
            'wp-simple-maintenance'
        );
        
        add_settings_field(
            'wp_simple_maintenance_active',
            'Enable Maintenance Mode',
            array($this, 'active_field_callback'),
            'wp-simple-maintenance',
            'wp_simple_maintenance_section'
        );
        
        add_settings_field(
            'wp_simple_maintenance_message',
            'Maintenance Message',
            array($this, 'message_field_callback'),
            'wp-simple-maintenance',
            'wp_simple_maintenance_section'
        );
    }
    
    // Settings section description
    public function settings_section_callback() {
        echo '<p>Configure your maintenance mode settings. When enabled, logged-out users will see a maintenance page.</p>';
    }
    
    // Active field callback
    public function active_field_callback() {
        $active = get_option('wp_simple_maintenance_active', false);
        echo '<label for="wp_simple_maintenance_active">
            <input type="checkbox" id="wp_simple_maintenance_active" name="wp_simple_maintenance_active" value="1" ' . checked(1, $active, false) . ' />
            Enable maintenance mode
        </label>';
    }
    
    // Message field callback
    public function message_field_callback() {
        $message = get_option('wp_simple_maintenance_message', 'We are currently performing scheduled maintenance. Please check back soon.');
        echo '<textarea id="wp_simple_maintenance_message" name="wp_simple_maintenance_message" rows="4" cols="50" class="large-text">' . esc_textarea($message) . '</textarea>';
    }
}

// Initialize the plugin
function wp_simple_maintenance_init() {
    WP_Simple_Maintenance::get_instance();
}
add_action('plugins_loaded', 'wp_simple_maintenance_init');
