<?php
/**
 * Plugin Name: Product Stock Manager
 * Description: سیستم مدیریت موجودی ووکامرس جهت اسکن بارکد یا ورود دستی کد محصول برای به‌روزرسانی موجودی.
 * Version: 1.1.0
 * Author: hashemipy
 * Text Domain: product-stock-manager
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PSM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PSM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PSM_VERSION', '1.1.0');

// Load plugin files
require_once PSM_PLUGIN_DIR . 'includes/functions.php';
require_once PSM_PLUGIN_DIR . 'includes/admin-menu.php';
require_once PSM_PLUGIN_DIR . 'includes/ajax-handlers.php';

// Initialize the plugin
function psm_init() {
    // Add plugin initialization code here if needed
}
add_action('plugins_loaded', 'psm_init');

// Enqueue scripts and styles
function psm_enqueue_scripts($hook) {
    if ($hook !== 'toplevel_page_psm-stock-manager' && $hook !== 'product_page_psm-product-list') {
        return;
    }

    // Enqueue Quagga for barcode scanning
    wp_enqueue_script('quagga', 'https://cdnjs.cloudflare.com/ajax/libs/quagga/0.12.1/quagga.min.js', array(), '0.12.1', true);
    
    // Enqueue jQuery UI for autocomplete
    wp_enqueue_script('jquery-ui-autocomplete', 'https://code.jquery.com/ui/1.12.1/jquery-ui.min.js', array('jquery'), '1.12.1', true);
    wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css', array(), '1.12.1');

    // Enqueue plugin CSS
    wp_enqueue_style('psm-admin-style', PSM_PLUGIN_URL . 'assets/css/admin.css', array(), PSM_VERSION);
    
    // Enqueue plugin JS
    wp_enqueue_script('psm-admin-script', PSM_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), PSM_VERSION, true);
}
add_action('admin_enqueue_scripts', 'psm_enqueue_scripts');
?>
