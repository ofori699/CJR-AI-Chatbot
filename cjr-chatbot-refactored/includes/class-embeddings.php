<?php
/**
 * Embeddings Class
 *
 * Handles vector embedding generation and similarity calculations
 *
 * @package CJR_Chatbot
 */

if (!defined('ABSPATH')) {
    exit;
}

class CJR_Chatbot_Embeddings {
    
    /**
     * Generate embedding using RouteLLM API
     *
     * @param string $text Text to embed
     * @param string $api_key RouteLLM API key
     * @return array|null Embedding vector or null on failure
     */
    public function generate_embedding($text, $api_key) {
        if (empty($text) || empty($api_key)) {
            return null;
        }
        
        $api_url = 'https://api.abacus.ai/routeLLM';
        $payload = array(
            'model' => 'openai:text-embedding-3-small',
            'input' => $text
        );
        
        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => wp_json_encode($payload),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('CJR Chatbot: Embedding generation failed - ' . $response->get_error_message());
            return null;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // CRITICAL FIX: Changed from data['embedding'] to data['data'][0]['embedding']
        // RouteLLM returns OpenAI-compatible format
        if ($code === 200 && isset($data['data'][0]['embedding'])) {
            return $data['data'][0]['embedding'];
        }
        
        error_log('CJR Chatbot: Invalid embedding response - ' . substr($body, 0, 200));
        return null;
    }
    
    /**
     * Calculate cosine similarity between two vectors
     *
     * @param array $vec_a First vector
     * @param array $vec_b Second vector
     * @return float Similarity score (0-1)
     */
    public function cosine_similarity($vec_a, $vec_b) {
        if (empty($vec_a) || empty($vec_b)) {
            return 0.0;
        }
        
        $dot = 0.0;
        $norm_a = 0.0;
        $norm_b = 0.0;
        $len = min(count($vec_a), count($vec_b));
        
        for ($i = 0; $i < $len; $i++) {
            $dot += $vec_a[$i] * $vec_b[$i];
            $norm_a += $vec_a[$i] ** 2;
            $norm_b += $vec_b[$i] ** 2;
        }
        
        if ($norm_a === 0.0 || $norm_b === 0.0) {
            return 0.0;
        }
        
        return $dot / (sqrt($norm_a) * sqrt($norm_b));
    }
    
    /**
     * Find most similar posts to query
     *
     * @param array $query_embedding Query embedding vector
     * @param array $post_types Post types to search
     * @param int $limit Maximum number of results
     * @param float $threshold Minimum similarity threshold
     * @return array Array of matching posts with scores
     */
    public function find_similar_posts($query_embedding, $post_types, $limit = 3, $threshold = 0.7) {
        if (empty($query_embedding)) {
            return array();
        }
        
        $all_posts = get_posts(array(
            'post_type' => $post_types,
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        $matches = array();
        
        foreach ($all_posts as $post) {
            $vector_json = get_post_meta($post->ID, '_cjr_content_vector', true);
            
            if (empty($vector_json)) {
                continue;
            }
            
            $post_vec = json_decode($vector_json, true);
            if (!$post_vec) {
                continue;
            }
            
            $score = $this->cosine_similarity($query_embedding, $post_vec);
            
            if ($score >= $threshold) {
                $matches[] = array(
                    'post' => $post,
                    'score' => $score
                );
            }
        }
        
        // Sort by score (highest first)
        usort($matches, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        // Return top N matches
        return array_slice($matches, 0, $limit);
    }
}
