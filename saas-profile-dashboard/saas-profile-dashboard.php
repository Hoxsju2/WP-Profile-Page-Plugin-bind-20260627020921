<?php
/**
 * Plugin Name: SaaS Profile Dashboard
 * Plugin URI: https://example.com
 * Description: Modernize WordPress user profile pages with a SaaS concept, custom tabs, and WooCommerce integration.
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: saas-profile-dashboard
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SPD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SPD_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SPD_VERSION', '1.0.0');

class SaaSProfileDashboard {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        add_action('admin_init', array($this, 'check_database'));
    }
    
    public function init() {
        load_plugin_textdomain('saas-profile-dashboard', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        $this->includes();
        $this->hooks();
    }
    
    private function includes() {
        require_once SPD_PLUGIN_PATH . 'includes/class-database.php';
        require_once SPD_PLUGIN_PATH . 'includes/class-profile-handler.php';
        require_once SPD_PLUGIN_PATH . 'includes/class-ajax-handler.php';
        
        if (is_admin()) {
            require_once SPD_PLUGIN_PATH . 'admin/class-admin-panel.php';
        }
    }
    
    private function hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        if (class_exists('SPD_Database')) {
            new SPD_Database();
        }
        if (class_exists('SPD_Profile_Handler')) {
            new SPD_Profile_Handler();
        }
        if (class_exists('SPD_Ajax_Handler')) {
            new SPD_Ajax_Handler();
        }
        
        if (is_admin() && class_exists('SPD_Admin_Panel')) {
            new SPD_Admin_Panel();
        }
    }
    
    public function enqueue_scripts() {
        wp_enqueue_style('spd-frontend', SPD_PLUGIN_URL . 'assets/css/frontend.css', array(), SPD_VERSION);
        wp_enqueue_script('spd-frontend', SPD_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), SPD_VERSION, true);
        
        wp_localize_script('spd-frontend', 'spd_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('spd_nonce'),
        ));
    }
    
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'saas-profile') !== false) {
            wp_enqueue_style('spd-admin', SPD_PLUGIN_URL . 'assets/css/admin.css', array(), SPD_VERSION);
            wp_enqueue_script('spd-admin', SPD_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), SPD_VERSION, true);
        }
    }
    
    public function check_database() {
        if (!is_admin()) {
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'spd_tabs';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        
        if (!$table_exists && class_exists('SPD_Database')) {
            SPD_Database::create_tables();
        }
    }
    
    public function activate() {
        require_once SPD_PLUGIN_PATH . 'includes/class-database.php';
        
        if (class_exists('SPD_Database')) {
            SPD_Database::create_tables();
        }
        
        flush_rewrite_rules();
        add_option('spd_plugin_activated', true);
    }
    
    public function deactivate() {
        flush_rewrite_rules();
        delete_option('spd_plugin_activated');
        
        // Clear scheduled events
        $timestamp = wp_next_scheduled('spd_daily_exchange_update');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'spd_daily_exchange_update');
        }
    }
}

// Initialize the plugin
new SaaSProfileDashboard();
