<?php
/**
 * Indexer Class
 *
 * Handles content indexing with batch processing
 *
 * @package CJR_Chatbot
 */

if (!defined('ABSPATH')) {
    exit;
}

class CJR_Chatbot_Indexer {
    
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
     * Register FAQ custom post type
     */
    public function register_faq_post_type() {
        register_post_type('cjr_faq', array(
            'labels' => array(
                'name' => __('Chatbot Q&A', 'cjr-chatbot'),
                'singular_name' => __('Q&A', 'cjr-chatbot'),
                'add_new_item' => __('Add New Q&A', 'cjr-chatbot'),
                'edit_item' => __('Edit Q&A', 'cjr-chatbot'),
                'new_item' => __('New Q&A', 'cjr-chatbot'),
                'view_item' => __('View Q&A', 'cjr-chatbot'),
                'search_items' => __('Search Q&A', 'cjr-chatbot'),
                'not_found' => __('No Q&A found', 'cjr-chatbot'),
            ),
            'public' => false,
            'show_ui' => false, // We'll create custom UI
            'supports' => array('title', 'editor'),
            'capability_type' => 'post',
        ));
    }
    
    /**
     * Index post on save
     */
    public function index_post_on_save($post_id, $post, $update) {
        // Skip autosave and revisions
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (wp_is_post_revision($post_id)) {
            return;
        }
        
        // Only index published posts
        if ($post->post_status !== 'publish') {
            return;
        }
        
        // Check if post type is indexed
        $indexed_types = get_option('cjr_chatbot_indexed_types', array('post', 'page', 'cjr_faq'));
        if (!in_array($post->post_type, $indexed_types)) {
            return;
        }
        
        // Index the post
        $this->index_single_post($post_id, $post);
    }
    
    /**
     * Index a single post
     */
    public function index_single_post($post_id, $post = null) {
        if ($post === null) {
            $post = get_post($post_id);
        }
        
        if (!$post) {
            return false;
        }
        
        $api_key = get_option('cjr_chatbot_api_key', '');
        if (empty($api_key)) {
            return false;
        }
        
        // Combine title and content for embedding
        $text = $post->post_title . ' ' . wp_strip_all_tags($post->post_content);
        $text = wp_trim_words($text, 500); // Limit to ~500 words
        
        $embedding = $this->embeddings->generate_embedding($text, $api_key);
        
        if ($embedding) {
            update_post_meta($post_id, '_cjr_content_vector', wp_json_encode($embedding));
            update_post_meta($post_id, '_cjr_indexed_date', current_time('mysql'));
            error_log(sprintf(
                /* translators: 1: post ID, 2: post title */
                __('CJR Chatbot: Indexed post %1$d - %2$s', 'cjr-chatbot'),
                $post_id,
                $post->post_title
            ));
            return true;
        }
        
        error_log(sprintf(
            /* translators: 1: post ID */
            __('CJR Chatbot: Failed to index post %d', 'cjr-chatbot'),
            $post_id
        ));
        return false;
    }
    
    /**
     * AJAX handler for batch indexing
     */
    public function ajax_batch_index() {
        // Check nonce
        check_ajax_referer('cjr_chatbot_index', 'nonce');
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'cjr-chatbot')));
        }
        
        try {
            // Check API key
            $api_key = get_option('cjr_chatbot_api_key', '');
            if (empty($api_key)) {
                wp_send_json_error(array('message' => __('API key not configured. Please set it in Settings.', 'cjr-chatbot')));
            }
            
            $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
            $batch_size = 10; // Process 10 posts per request
            
            $indexed_types = get_option('cjr_chatbot_indexed_types', array('post', 'page', 'cjr_faq'));
            
            $posts = get_posts(array(
                'post_type' => $indexed_types,
                'posts_per_page' => $batch_size,
                'offset' => $offset,
                'post_status' => 'publish',
                'orderby' => 'ID',
                'order' => 'ASC'
            ));
            
            $success = 0;
            $failed = 0;
            
            foreach ($posts as $post) {
                try {
                    if ($this->index_single_post($post->ID, $post)) {
                        $success++;
                    } else {
                        $failed++;
                    }
                } catch (Exception $e) {
                    error_log('CJR Chatbot: Error indexing post ' . $post->ID . ' - ' . $e->getMessage());
                    $failed++;
                }
                
                // Small delay to avoid rate limiting
                usleep(100000); // 0.1 second
            }
            
            $processed = count($posts);
            $new_offset = $offset + $processed;
            
            wp_send_json_success(array(
                'processed' => $processed,
                'success' => $success,
                'failed' => $failed,
                'offset' => $new_offset,
                'has_more' => $processed === $batch_size
            ));
        } catch (Exception $e) {
            error_log('CJR Chatbot: Batch index error - ' . $e->getMessage());
            wp_send_json_error(array('message' => __('Indexing error: ', 'cjr-chatbot') . $e->getMessage()));
        }
    }
    
    /**
     * AJAX handler for clearing index
     */
    public function ajax_clear_index() {
        // Check nonce
        check_ajax_referer('cjr_chatbot_index', 'nonce');
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'cjr-chatbot')));
        }
        
        // Delete all embedding meta
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_cjr_content_vector'");
        $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_cjr_indexed_date'");
        
        wp_send_json_success(array(
            'message' => __('Index cleared successfully.', 'cjr-chatbot')
        ));
    }
    
    /**
     * Get index statistics
     */
    public function get_index_stats() {
        global $wpdb;
        
        $indexed_count = $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_cjr_content_vector'"
        );
        
        $indexed_types = get_option('cjr_chatbot_indexed_types', array('post', 'page', 'cjr_faq'));
        $total_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN (" . implode(',', array_fill(0, count($indexed_types), '%s')) . ") AND post_status = 'publish'",
            ...$indexed_types
        ));
        
        return array(
            'indexed' => intval($indexed_count),
            'total' => intval($total_count),
            'percentage' => $total_count > 0 ? round(($indexed_count / $total_count) * 100, 1) : 0
        );
    }
}
