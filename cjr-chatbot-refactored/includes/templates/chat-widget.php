<?php
/**
 * Chat Widget Template
 *
 * @package CJR_Chatbot
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<button id="chatbot-toggle" aria-label="<?php esc_attr_e('Toggle chat', 'cjr-chatbot'); ?>">
    💬
</button>

<div id="chatbot-container" role="dialog" aria-label="<?php esc_attr_e('Chatbot', 'cjr-chatbot'); ?>" style="display: none;">
    <div id="chatbot-header">
        <?php esc_html_e('CJR Chatbot', 'cjr-chatbot'); ?>
    </div>
    <div id="chatbot-messages" role="log" aria-live="polite" aria-atomic="false">
        <div class="chat-message bot-message">
            <?php echo esc_html(cjrChatbot.strings.welcome); ?>
        </div>
    </div>
    <div id="chatbot-input-area">
        <input 
            type="text" 
            id="chatbot-input" 
            placeholder="<?php esc_attr_e('Ask a question...', 'cjr-chatbot'); ?>"
            aria-label="<?php esc_attr_e('Message input', 'cjr-chatbot'); ?>"
        />
        <button id="chatbot-send-button">
            <?php esc_html_e('Send', 'cjr-chatbot'); ?>
        </button>
    </div>
</div>
