<?php
/**
 * Settings Page Template
 *
 * @package CJR_Chatbot
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle settings save
if (isset($_POST['cjr_chatbot_save_settings']) && check_admin_referer('cjr_chatbot_settings_save', 'cjr_chatbot_settings_nonce')) {
    update_option('cjr_chatbot_api_key', sanitize_text_field($_POST['cjr_chatbot_api_key']));
    update_option('cjr_chatbot_enabled', isset($_POST['cjr_chatbot_enabled']) ? 1 : 0);
    update_option('cjr_chatbot_model', sanitize_text_field($_POST['cjr_chatbot_model']));
    update_option('cjr_chatbot_maxtokens', intval($_POST['cjr_chatbot_maxtokens']));
    update_option('cjr_chatbot_temperature', floatval($_POST['cjr_chatbot_temperature']));
    update_option('cjr_chatbot_rate_limit', intval($_POST['cjr_chatbot_rate_limit']));
    
    $indexed_types = isset($_POST['cjr_chatbot_indexed_types']) ? array_map('sanitize_text_field', $_POST['cjr_chatbot_indexed_types']) : array();
    update_option('cjr_chatbot_indexed_types', $indexed_types);
    
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved successfully!', 'cjr-chatbot') . '</p></div>';
}

// Load settings
$api_key = get_option('cjr_chatbot_api_key', '');
$enabled = get_option('cjr_chatbot_enabled', 1);
$model = get_option('cjr_chatbot_model', 'openai:gpt-3.5-turbo');
$max_tokens = get_option('cjr_chatbot_maxtokens', 400);
$temperature = get_option('cjr_chatbot_temperature', 0.7);
$rate_limit = get_option('cjr_chatbot_rate_limit', 10);
$indexed_types = get_option('cjr_chatbot_indexed_types', array('post', 'page', 'cjr_faq'));

// Get indexer statistics
$indexer = new CJR_Chatbot_Indexer();
$stats = $indexer->get_index_stats();

// Get available post types
$post_types = get_post_types(array('public' => true), 'objects');
$post_types['cjr_faq'] = (object)array('name' => 'cjr_faq', 'label' => __('Chatbot Q&A', 'cjr-chatbot'));
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="cjr-chatbot-settings">
        <form method="post" action="">
            <?php wp_nonce_field('cjr_chatbot_settings_save', 'cjr_chatbot_settings_nonce'); ?>
            
            <div class="cjr-settings-section">
                <h2><?php esc_html_e('General Settings', 'cjr-chatbot'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="cjr_chatbot_enabled"><?php esc_html_e('Enable Chatbot', 'cjr-chatbot'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="cjr_chatbot_enabled" id="cjr_chatbot_enabled" value="1" <?php checked($enabled, 1); ?>>
                                <?php esc_html_e('Show chatbot on site', 'cjr-chatbot'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="cjr-settings-section">
                <h2><?php esc_html_e('AI Provider Settings', 'cjr-chatbot'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="cjr_chatbot_api_key"><?php esc_html_e('RouteLLM API Key', 'cjr-chatbot'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="cjr_chatbot_api_key" id="cjr_chatbot_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text">
                            <p class="description">
                                <?php printf(
                                    /* translators: %s: URL to get API key */
                                    esc_html__('Get your API key from %s', 'cjr-chatbot'),
                                    '<a href="https://abacus.ai/app/route-llm-apis" target="_blank">' . esc_html__('RouteLLM', 'cjr-chatbot') . '</a>'
                                ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cjr_chatbot_model"><?php esc_html_e('Model', 'cjr-chatbot'); ?></label>
                        </th>
                        <td>
                            <select name="cjr_chatbot_model" id="cjr_chatbot_model">
                                <option value="openai:gpt-3.5-turbo" <?php selected($model, 'openai:gpt-3.5-turbo'); ?>>OpenAI: GPT-3.5 Turbo</option>
                                <option value="openai:gpt-4" <?php selected($model, 'openai:gpt-4'); ?>>OpenAI: GPT-4</option>
                                <option value="anthropic:claude-3-opus" <?php selected($model, 'anthropic:claude-3-opus'); ?>>Anthropic: Claude 3 Opus</option>
                                <option value="anthropic:claude-3-sonnet" <?php selected($model, 'anthropic:claude-3-sonnet'); ?>>Anthropic: Claude 3 Sonnet</option>
                                <option value="anthropic:claude-3-haiku" <?php selected($model, 'anthropic:claude-3-haiku'); ?>>Anthropic: Claude 3 Haiku</option>
                                <option value="meta:llama-3-70b" <?php selected($model, 'meta:llama-3-70b'); ?>>Meta: LLaMA 3-70B</option>
                                <option value="meta:llama-3-8b" <?php selected($model, 'meta:llama-3-8b'); ?>>Meta: LLaMA 3-8B</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cjr_chatbot_maxtokens"><?php esc_html_e('Max Tokens', 'cjr-chatbot'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="cjr_chatbot_maxtokens" id="cjr_chatbot_maxtokens" value="<?php echo esc_attr($max_tokens); ?>" min="50" max="2000" class="small-text">
                            <p class="description"><?php esc_html_e('Maximum length of AI response (50-2000)', 'cjr-chatbot'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cjr_chatbot_temperature"><?php esc_html_e('Temperature', 'cjr-chatbot'); ?></label>
                        </th>
                        <td>
                            <input type="number" step="0.1" name="cjr_chatbot_temperature" id="cjr_chatbot_temperature" value="<?php echo esc_attr($temperature); ?>" min="0" max="1" class="small-text">
                            <p class="description"><?php esc_html_e('Creativity level (0 = focused, 1 = creative)', 'cjr-chatbot'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="cjr-settings-section">
                <h2><?php esc_html_e('Security Settings', 'cjr-chatbot'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="cjr_chatbot_rate_limit"><?php esc_html_e('Rate Limit', 'cjr-chatbot'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="cjr_chatbot_rate_limit" id="cjr_chatbot_rate_limit" value="<?php echo esc_attr($rate_limit); ?>" min="1" max="100" class="small-text">
                            <p class="description"><?php esc_html_e('Maximum requests per minute per IP address', 'cjr-chatbot'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="cjr-settings-section">
                <h2><?php esc_html_e('Content Indexing', 'cjr-chatbot'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Index Content Types', 'cjr-chatbot'); ?></th>
                        <td>
                            <?php foreach ($post_types as $post_type) : ?>
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox" name="cjr_chatbot_indexed_types[]" value="<?php echo esc_attr($post_type->name); ?>" <?php checked(in_array($post_type->name, $indexed_types)); ?>>
                                    <?php echo esc_html($post_type->label); ?>
                                </label>
                            <?php endforeach; ?>
                            <p class="description"><?php esc_html_e('Select which content types to index for chatbot responses.', 'cjr-chatbot'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <p class="submit">
                <input type="submit" name="cjr_chatbot_save_settings" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'cjr-chatbot'); ?>">
            </p>
        </form>
        
        <hr>
        
        <div class="cjr-settings-section">
            <h2><?php esc_html_e('Content Indexing', 'cjr-chatbot'); ?></h2>
            <div class="cjr-index-stats">
                <p>
                    <strong><?php esc_html_e('Index Status:', 'cjr-chatbot'); ?></strong>
                    <?php printf(
                        /* translators: 1: indexed count, 2: total count, 3: percentage */
                        esc_html__('%1$d of %2$d posts indexed (%3$s%%)', 'cjr-chatbot'),
                        $stats['indexed'],
                        $stats['total'],
                        $stats['percentage']
                    ); ?>
                </p>
                <div class="cjr-progress-container" style="display: none;">
                    <div class="cjr-progress-bar">
                        <div class="cjr-progress-fill" style="width: 0%;"></div>
                    </div>
                    <p class="cjr-progress-text"></p>
                </div>
            </div>
            <p>
                <button type="button" id="cjr-start-indexing" class="button button-secondary">
                    <?php esc_html_e('Start Indexing', 'cjr-chatbot'); ?>
                </button>
                <button type="button" id="cjr-clear-index" class="button button-secondary">
                    <?php esc_html_e('Clear Index', 'cjr-chatbot'); ?>
                </button>
            </p>
            <p class="description">
                <?php esc_html_e('Click "Start Indexing" to generate embeddings for all content. This may take a few minutes for large sites.', 'cjr-chatbot'); ?>
            </p>
        </div>
    </div>
</div>
