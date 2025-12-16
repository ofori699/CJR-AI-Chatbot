<?php
/**
 * AI Provider Class
 *
 * Handles interaction with RouteLLM API for chat completions
 *
 * @package CJR_Chatbot
 */

if (!defined('ABSPATH')) {
    exit;
}

class CJR_Chatbot_AI_Provider {
    
    /**
     * Embeddings handler
     */
    private $embeddings;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->embeddings = new CJR_Chatbot_Embeddings();
    }
    
    /**
     * Get AI response for user message
     *
     * @param string $user_message User's question
     * @return array Response data
     */
    public function get_response($user_message) {
        $api_key = get_option('cjr_chatbot_api_key', '');
        
        if (empty($api_key)) {
            return array(
                'success' => false,
                'message' => __('API key not configured. Please check settings.', 'cjr-chatbot')
            );
        }
        
        // Generate embedding for user query
        $query_embedding = $this->embeddings->generate_embedding($user_message, $api_key);
        $context_content = '';
        $sources = array();
        
        if ($query_embedding) {
            // Get indexed post types from settings
            $indexed_types = get_option('cjr_chatbot_indexed_types', array('post', 'page', 'cjr_faq'));
            
            // Find similar posts
            $matches = $this->embeddings->find_similar_posts($query_embedding, $indexed_types, 3, 0.7);
            
            // For FAQs with very high similarity, return direct answer
            if (!empty($matches) && $matches[0]['score'] > 0.85 && $matches[0]['post']->post_type === 'cjr_faq') {
                return array(
                    'success' => true,
                    'message' => wp_kses_post($matches[0]['post']->post_content),
                    'sources' => array(
                        array(
                            'title' => $matches[0]['post']->post_title,
                            'url' => '',
                            'type' => 'faq'
                        )
                    )
                );
            }
            
            // Build context from top matches
            $context_parts = array();
            foreach ($matches as $match) {
                $post = $match['post'];
                $content = wp_strip_all_tags($post->post_content);
                $excerpt = wp_trim_words($content, 100);
                $context_parts[] = sprintf(
                    /* translators: 1: post title, 2: post excerpt */
                    __('From "%1$s": %2$s', 'cjr-chatbot'),
                    $post->post_title,
                    $excerpt
                );
                
                // Add to sources
                $sources[] = array(
                    'title' => $post->post_title,
                    'url' => get_permalink($post->ID),
                    'type' => $post->post_type
                );
            }
            
            if (!empty($context_parts)) {
                $context_content = "\n\n" . __('Relevant CJR content:', 'cjr-chatbot') . "\n" . implode("\n\n", $context_parts);
            }
        }
        
        // Call RouteLLM API with context
        $response = $this->call_llm_api($user_message, $context_content);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => __('Sorry, I encountered an error. Please try again.', 'cjr-chatbot')
            );
        }
        
        return array(
            'success' => true,
            'message' => $response,
            'sources' => $sources
        );
    }
    
    /**
     * Call RouteLLM API
     *
     * @param string $user_message User message
     * @param string $context_content Context from site content
     * @return string|WP_Error Response text or error
     */
    private function call_llm_api($user_message, $context_content) {
        $api_key = get_option('cjr_chatbot_api_key', '');
        $api_url = 'https://api.abacus.ai/routeLLM';
        
        $system_prompt = __('You are a helpful chatbot for the Center for Justice Research (CJR). Answer questions about the organization\'s research, projects, criminal justice, crime data, and legal issues. Be informative and neutral. Always clarify that you are not giving legal advice. Use the provided context from CJR\'s website to give accurate, specific answers when possible.', 'cjr-chatbot') . $context_content;
        
        $payload = array(
            'model' => get_option('cjr_chatbot_model', 'openai:gpt-3.5-turbo'),
            'messages' => array(
                array('role' => 'system', 'content' => $system_prompt),
                array('role' => 'user', 'content' => $user_message)
            ),
            'max_tokens' => intval(get_option('cjr_chatbot_maxtokens', 400)),
            'temperature' => floatval(get_option('cjr_chatbot_temperature', 0.7))
        );
        
        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ),
            'body' => wp_json_encode($payload),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('CJR Chatbot: API call failed - ' . $response->get_error_message());
            return new WP_Error('api_error', 'Failed to connect to AI service');
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // CRITICAL FIX: Changed from data['output_text'] to data['choices'][0]['message']['content']
        // RouteLLM returns OpenAI-compatible format
        if ($code !== 200 || !isset($data['choices'][0]['message']['content'])) {
            error_log('CJR Chatbot: API Error: ' . $code . ' - ' . substr($body, 0, 200));
            return new WP_Error('bad_response', 'Unexpected AI response');
        }
        
        return trim($data['choices'][0]['message']['content']);
    }
}
