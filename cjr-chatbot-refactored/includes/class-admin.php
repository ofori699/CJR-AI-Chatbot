<?php
/**
 * Admin Class
 *
 * Handles admin interface and settings
 *
 * @package CJR_Chatbot
 */

if (!defined('ABSPATH')) {
    exit;
}

class CJR_Chatbot_Admin {
    
    /**
     * Register admin menu
     */
    public function register_admin_menu() {
        // Main menu
        add_menu_page(
            __('CJR Chatbot', 'cjr-chatbot'),
            __('CJR Chatbot', 'cjr-chatbot'),
            'manage_options',
            'cjr-chatbot',
            array($this, 'render_settings_page'),
            'dashicons-format-chat',
            30
        );
        
        // Settings submenu
        add_submenu_page(
            'cjr-chatbot',
            __('Settings', 'cjr-chatbot'),
            __('Settings', 'cjr-chatbot'),
            'manage_options',
            'cjr-chatbot',
            array($this, 'render_settings_page')
        );
        
        // Q&A Management submenu
        add_submenu_page(
            'cjr-chatbot',
            __('Q&A Management', 'cjr-chatbot'),
            __('Q&A Management', 'cjr-chatbot'),
            'manage_options',
            'cjr-chatbot-qa',
            array($this, 'render_qa_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('cjr_chatbot_settings', 'cjr_chatbot_api_key');
        register_setting('cjr_chatbot_settings', 'cjr_chatbot_enabled');
        register_setting('cjr_chatbot_settings', 'cjr_chatbot_model');
        register_setting('cjr_chatbot_settings', 'cjr_chatbot_maxtokens');
        register_setting('cjr_chatbot_settings', 'cjr_chatbot_temperature');
        register_setting('cjr_chatbot_settings', 'cjr_chatbot_indexed_types');
        register_setting('cjr_chatbot_settings', 'cjr_chatbot_rate_limit');
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'cjr-chatbot') === false) {
            return;
        }
        
        wp_enqueue_style(
            'cjr-chatbot-admin',
            CJR_CHATBOT_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            CJR_CHATBOT_VERSION
        );
        
        wp_enqueue_script(
            'cjr-chatbot-admin',
            CJR_CHATBOT_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            CJR_CHATBOT_VERSION,
            true
        );
        
        wp_localize_script('cjr-chatbot-admin', 'cjrChatbotAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'indexNonce' => wp_create_nonce('cjr_chatbot_index'),
            'qaNonce' => wp_create_nonce('cjr_chatbot_qa'),
            'strings' => array(
                'indexing' => __('Indexing content...', 'cjr-chatbot'),
                'indexComplete' => __('Indexing complete!', 'cjr-chatbot'),
                'indexError' => __('Indexing error. Please try again.', 'cjr-chatbot'),
                'confirmClear' => __('Are you sure you want to clear the entire index? This cannot be undone.', 'cjr-chatbot'),
                'confirmDelete' => __('Are you sure you want to delete this Q&A?', 'cjr-chatbot'),
            )
        ));
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        include CJR_CHATBOT_PLUGIN_DIR . 'admin/settings-page.php';
    }
    
    /**
     * Render Q&A management page
     */
    public function render_qa_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        include CJR_CHATBOT_PLUGIN_DIR . 'admin/qa-page.php';
    }
    
    /**
     * AJAX handler to save Q&A
     */
    public function ajax_save_qa() {
        check_ajax_referer('cjr_chatbot_qa', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'cjr-chatbot')));
        }
        
        $qa_id = isset($_POST['qa_id']) ? intval($_POST['qa_id']) : 0;
        $question = isset($_POST['question']) ? sanitize_text_field($_POST['question']) : '';
        $answer = isset($_POST['answer']) ? wp_kses_post($_POST['answer']) : '';
        
        if (empty($question) || empty($answer)) {
            wp_send_json_error(array('message' => __('Question and answer are required.', 'cjr-chatbot')));
        }
        
        $post_data = array(
            'post_title' => $question,
            'post_content' => $answer,
            'post_type' => 'cjr_faq',
            'post_status' => 'publish',
        );
        
        if ($qa_id > 0) {
            // Update existing
            $post_data['ID'] = $qa_id;
            $result = wp_update_post($post_data);
        } else {
            // Create new
            $result = wp_insert_post($post_data);
        }
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        // Index the Q&A
        $indexer = new CJR_Chatbot_Indexer();
        $indexer->index_single_post($result);
        
        wp_send_json_success(array(
            'message' => __('Q&A saved successfully.', 'cjr-chatbot'),
            'qa_id' => $result
        ));
    }
    
    /**
     * AJAX handler to delete Q&A
     */
    public function ajax_delete_qa() {
        check_ajax_referer('cjr_chatbot_qa', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'cjr-chatbot')));
        }
        
        $qa_id = isset($_POST['qa_id']) ? intval($_POST['qa_id']) : 0;
        
        if ($qa_id <= 0) {
            wp_send_json_error(array('message' => __('Invalid Q&A ID.', 'cjr-chatbot')));
        }
        
        $result = wp_delete_post($qa_id, true);
        
        if (!$result) {
            wp_send_json_error(array('message' => __('Failed to delete Q&A.', 'cjr-chatbot')));
        }
        
        wp_send_json_success(array('message' => __('Q&A deleted successfully.', 'cjr-chatbot')));
    }
}
