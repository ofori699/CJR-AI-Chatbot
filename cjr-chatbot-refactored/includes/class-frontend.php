<?php
/**
 * Frontend Class
 *
 * Handles frontend display and assets
 *
 * @package CJR_Chatbot
 */

if (!defined('ABSPATH')) {
    exit;
}

class CJR_Chatbot_Frontend {
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        if (!get_option('cjr_chatbot_enabled', 1)) {
            return;
        }
        
        // Enqueue CSS
        wp_enqueue_style(
            'cjr-chatbot-frontend',
            CJR_CHATBOT_PLUGIN_URL . 'assets/css/chatbot.css',
            array(),
            CJR_CHATBOT_VERSION
        );
        
        // Enqueue JavaScript
        wp_enqueue_script(
            'cjr-chatbot-frontend',
            CJR_CHATBOT_PLUGIN_URL . 'assets/js/chatbot.js',
            array('jquery'),
            CJR_CHATBOT_VERSION,
            true
        );
        
        // Localize script with settings
        wp_localize_script('cjr-chatbot-frontend', 'cjrChatbot', array(
            'ajaxUrl' => rest_url('cjr-chatbot/v1/ask'),
            'nonce' => wp_create_nonce('wp_rest'),
            'strings' => array(
                'typing' => __('Bot is typing...', 'cjr-chatbot'),
                'error' => __('⚠️ Error, please try again.', 'cjr-chatbot'),
                'networkError' => __('⚠️ Network error. Please check your connection.', 'cjr-chatbot'),
                'placeholder' => __('Ask a question...', 'cjr-chatbot'),
                'send' => __('Send', 'cjr-chatbot'),
                'welcome' => __('👋 Hello! How can I help you with the Center for Justice Research today?', 'cjr-chatbot'),
            )
        ));
    }
    
    /**
     * Render chat widget in footer
     */
    public function render_chat_widget() {
        if (!get_option('cjr_chatbot_enabled', 1)) {
            return;
        }
        
        include CJR_CHATBOT_PLUGIN_DIR . 'includes/templates/chat-widget.php';
    }
    
    /**
     * Chatbot shortcode
     */
    public function chatbot_shortcode($atts) {
        ob_start();
        include CJR_CHATBOT_PLUGIN_DIR . 'includes/templates/chat-widget.php';
        return ob_get_clean();
    }
}
