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
                'rateLimit' => __('⚠️ Too many requests. Please wait a moment and try again.', 'cjr-chatbot'),
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
        
        try {
            $template_path = CJR_CHATBOT_PLUGIN_DIR . 'includes/templates/chat-widget.php';
            if (file_exists($template_path)) {
                include $template_path;
            } else {
                error_log('CJR Chatbot: Widget template not found at ' . $template_path);
            }
        } catch (Exception $e) {
            error_log('CJR Chatbot: Error rendering widget - ' . $e->getMessage());
        }
    }
    
    /**
     * Chatbot shortcode
     */
    public function chatbot_shortcode($atts) {
        ob_start();
        try {
            $template_path = CJR_CHATBOT_PLUGIN_DIR . 'includes/templates/chat-widget.php';
            if (file_exists($template_path)) {
                include $template_path;
            } else {
                error_log('CJR Chatbot: Widget template not found at ' . $template_path);
                echo '<p>' . esc_html__('Chatbot widget unavailable.', 'cjr-chatbot') . '</p>';
            }
        } catch (Exception $e) {
            error_log('CJR Chatbot: Error rendering shortcode - ' . $e->getMessage());
            echo '<p>' . esc_html__('Chatbot widget error.', 'cjr-chatbot') . '</p>';
        }
        return ob_get_clean();
    }
}
