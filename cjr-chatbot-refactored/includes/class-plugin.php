<?php
/**
 * Main Plugin Class
 *
 * @package CJR_Chatbot
 */

if (!defined('ABSPATH')) {
    exit;
}

class CJR_Chatbot_Plugin {
    
    /**
     * Plugin components
     */
    private $admin;
    private $frontend;
    private $rest_api;
    private $indexer;
    private $security;
    
    /**
     * Initialize the plugin
     */
    public function __construct() {
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_frontend_hooks();
        $this->define_rest_api_hooks();
        $this->define_indexer_hooks();
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        // Core classes are autoloaded
        $this->admin = new CJR_Chatbot_Admin();
        $this->frontend = new CJR_Chatbot_Frontend();
        $this->rest_api = new CJR_Chatbot_REST_API();
        $this->indexer = new CJR_Chatbot_Indexer();
        $this->security = new CJR_Chatbot_Security();
    }
    
    /**
     * Define admin hooks
     */
    private function define_admin_hooks() {
        // Admin menu and settings
        add_action('admin_menu', array($this->admin, 'register_admin_menu'));
        add_action('admin_init', array($this->admin, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this->admin, 'enqueue_admin_assets'));
        
        // AJAX handlers
        add_action('wp_ajax_cjr_chatbot_batch_index', array($this->indexer, 'ajax_batch_index'));
        add_action('wp_ajax_cjr_chatbot_clear_index', array($this->indexer, 'ajax_clear_index'));
        add_action('wp_ajax_cjr_chatbot_save_qa', array($this->admin, 'ajax_save_qa'));
        add_action('wp_ajax_cjr_chatbot_delete_qa', array($this->admin, 'ajax_delete_qa'));
    }
    
    /**
     * Define frontend hooks
     */
    private function define_frontend_hooks() {
        add_action('wp_footer', array($this->frontend, 'render_chat_widget'));
        add_action('wp_enqueue_scripts', array($this->frontend, 'enqueue_frontend_assets'));
        add_shortcode('cjr_chatbot', array($this->frontend, 'chatbot_shortcode'));
    }
    
    /**
     * Define REST API hooks
     */
    private function define_rest_api_hooks() {
        add_action('rest_api_init', array($this->rest_api, 'register_routes'));
    }
    
    /**
     * Define indexer hooks
     */
    private function define_indexer_hooks() {
        // Auto-index on post save
        add_action('save_post', array($this->indexer, 'index_post_on_save'), 10, 3);
        add_action('init', array($this->indexer, 'register_faq_post_type'));
    }
    
    /**
     * Run the plugin
     */
    public function run() {
        // Plugin is initialized via hooks
    }
}
