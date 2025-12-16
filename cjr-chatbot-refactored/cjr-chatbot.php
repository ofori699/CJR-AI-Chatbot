<?php
/**
 * Plugin Name: CJR Chatbot
 * Plugin URI: https://github.com/yourname/cjr-chatbot
 * Description: AI-powered chatbot for WordPress with content indexing, semantic search, Q&A management, and RouteLLM integration.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: FOP Studios
 * Author URI: https://fopstudios.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cjr-chatbot
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('CJR_CHATBOT_VERSION', '1.0.0');
define('CJR_CHATBOT_PLUGIN_FILE', __FILE__);
define('CJR_CHATBOT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CJR_CHATBOT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CJR_CHATBOT_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader for classes
spl_autoload_register(function ($class) {
    // Only load CJR_Chatbot classes
    if (strpos($class, 'CJR_Chatbot') !== 0) {
        return;
    }
    
    // Convert class name to file name
    $class_file = str_replace('_', '-', strtolower($class));
    $class_file = str_replace('cjr-chatbot-', '', $class_file);
    
    // Try includes directory first
    $file = CJR_CHATBOT_PLUGIN_DIR . 'includes/class-' . $class_file . '.php';
    if (file_exists($file)) {
        require_once $file;
        return;
    }
    
    // Try admin directory
    $file = CJR_CHATBOT_PLUGIN_DIR . 'admin/class-' . $class_file . '.php';
    if (file_exists($file)) {
        require_once $file;
        return;
    }
});

// Initialize the plugin
function cjr_chatbot_init() {
    // Load text domain for translations
    load_plugin_textdomain('cjr-chatbot', false, dirname(CJR_CHATBOT_PLUGIN_BASENAME) . '/languages');
    
    // Initialize main plugin class
    $plugin = new CJR_Chatbot_Plugin();
    $plugin->run();
}
add_action('plugins_loaded', 'cjr_chatbot_init');

// Activation hook
register_activation_hook(__FILE__, function() {
    // Set default options
    add_option('cjr_chatbot_enabled', 1);
    add_option('cjr_chatbot_model', 'openai:gpt-3.5-turbo');
    add_option('cjr_chatbot_maxtokens', 400);
    add_option('cjr_chatbot_temperature', 0.7);
    add_option('cjr_chatbot_indexed_types', array('post', 'page', 'cjr_faq'));
    add_option('cjr_chatbot_rate_limit', 10); // 10 requests per minute
    
    // Flush rewrite rules
    flush_rewrite_rules();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Flush rewrite rules
    flush_rewrite_rules();
});
