<?php
/**
 * REST API Class
 *
 * Handles REST API endpoints for the chatbot
 *
 * @package CJR_Chatbot
 */

if (!defined('ABSPATH')) {
    exit;
}

class CJR_Chatbot_REST_API {
    
    /**
     * Security handler
     */
    private $security;
    
    /**
     * AI provider
     */
    private $ai_provider;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->security = new CJR_Chatbot_Security();
        $this->ai_provider = new CJR_Chatbot_AI_Provider();
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        register_rest_route('cjr-chatbot/v1', '/ask', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_question'),
            'permission_callback' => array($this, 'check_permissions'),
        ));
    }
    
    /**
     * Check permissions for REST API access
     */
    public function check_permissions($request) {
        // Verify nonce
        if (!$this->security->verify_rest_nonce($request)) {
            return new WP_Error(
                'invalid_nonce',
                __('Invalid security token. Please refresh the page.', 'cjr-chatbot'),
                array('status' => 403)
            );
        }
        
        // Check rate limit
        $client_ip = $this->security->get_client_ip();
        if (!$this->security->check_rate_limit($client_ip)) {
            return new WP_Error(
                'rate_limit_exceeded',
                __('Too many requests. Please wait a moment and try again.', 'cjr-chatbot'),
                array('status' => 429)
            );
        }
        
        return true;
    }
    
    /**
     * Handle chatbot question
     */
    public function handle_question($request) {
        $user_message = $request->get_param('message');
        
        // Validate message
        $validation = $this->security->validate_message($user_message);
        if (!$validation['valid']) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => $validation['message']
                ),
                400
            );
        }
        
        $user_message = $validation['message'];
        
        // Get AI response
        $response = $this->ai_provider->get_response($user_message);
        
        if (!$response['success']) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => $response['message']
                ),
                500
            );
        }
        
        return new WP_REST_Response(
            array(
                'success' => true,
                'message' => $response['message'],
                'sources' => isset($response['sources']) ? $response['sources'] : array()
            ),
            200
        );
    }
}
