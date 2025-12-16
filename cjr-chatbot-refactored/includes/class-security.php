<?php
/**
 * Security Class
 *
 * Handles rate limiting, validation, and sanitization
 *
 * @package CJR_Chatbot
 */

if (!defined('ABSPATH')) {
    exit;
}

class CJR_Chatbot_Security {
    
    /**
     * Check rate limit for an IP address
     *
     * @param string $ip IP address to check
     * @param int $limit Maximum requests per minute (default: 10)
     * @return bool True if within limit, false if exceeded
     */
    public function check_rate_limit($ip, $limit = null) {
        if ($limit === null) {
            $limit = get_option('cjr_chatbot_rate_limit', 10);
        }
        
        $transient_key = 'cjr_rate_limit_' . md5($ip);
        $requests = get_transient($transient_key);
        
        if ($requests === false) {
            // First request in the time window
            set_transient($transient_key, 1, 60); // 60 seconds
            return true;
        }
        
        if ($requests >= $limit) {
            return false; // Rate limit exceeded
        }
        
        // Increment counter
        set_transient($transient_key, $requests + 1, 60);
        return true;
    }
    
    /**
     * Get client IP address
     *
     * @return string IP address
     */
    public function get_client_ip() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return sanitize_text_field($ip);
    }
    
    /**
     * Validate message input
     *
     * @param string $message User message
     * @return array ['valid' => bool, 'message' => string]
     */
    public function validate_message($message) {
        // Check if empty
        if (empty($message)) {
            return array(
                'valid' => false,
                'message' => __('Message cannot be empty.', 'cjr-chatbot')
            );
        }
        
        // Check length
        if (strlen($message) > 500) {
            return array(
                'valid' => false,
                'message' => __('Message must be less than 500 characters.', 'cjr-chatbot')
            );
        }
        
        // Check for suspicious patterns (basic prompt injection prevention)
        $blocked_patterns = array(
            'ignore previous',
            'ignore all previous',
            'system:',
            'assistant:',
            '<script',
            'javascript:',
        );
        
        foreach ($blocked_patterns as $pattern) {
            if (stripos($message, $pattern) !== false) {
                return array(
                    'valid' => false,
                    'message' => __('Message contains invalid content.', 'cjr-chatbot')
                );
            }
        }
        
        return array(
            'valid' => true,
            'message' => sanitize_text_field($message)
        );
    }
    
    /**
     * Verify nonce for REST API
     *
     * @param WP_REST_Request $request Request object
     * @return bool True if valid, false otherwise
     */
    public function verify_rest_nonce($request) {
        $nonce = $request->get_header('X-WP-Nonce');
        
        if (empty($nonce)) {
            return false;
        }
        
        return wp_verify_nonce($nonce, 'wp_rest');
    }
}
