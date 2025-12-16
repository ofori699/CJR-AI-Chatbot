<?php
/**
 * Uninstall CJR Chatbot
 *
 * This file is triggered on plugin deletion.
 *
 * @package CJR_Chatbot
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete options
$options = array(
    'cjr_chatbot_api_key',
    'cjr_chatbot_enabled',
    'cjr_chatbot_model',
    'cjr_chatbot_maxtokens',
    'cjr_chatbot_temperature',
    'cjr_chatbot_indexed_types',
    'cjr_chatbot_rate_limit',
);

foreach ($options as $option) {
    delete_option($option);
}

// Delete post meta for all posts
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_cjr_content_vector'");
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_cjr_indexed_date'");

// Delete custom Q&A posts
$qa_posts = get_posts(array(
    'post_type' => 'cjr_faq',
    'numberposts' => -1,
    'post_status' => 'any'
));

if (!empty($qa_posts)) {
    foreach ($qa_posts as $post) {
        wp_delete_post($post->ID, true); // true = bypass trash
    }
}

// Clear any cached transients
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_cjr_rate_limit_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_cjr_rate_limit_%'");
