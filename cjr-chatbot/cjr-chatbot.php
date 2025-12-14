<?php
/**
 * Plugin Name: CJR Chatbot
 * Description: AI-powered chatbot for the Center for Justice Research with full site content indexing, vector embeddings, admin Q&A, and RouteLLM integration.
 * Version: 1.0
 * Author: FOP Studios
 */

if (!defined('ABSPATH')) exit;

/* -------------------------------
   REST API Endpoint
--------------------------------*/
add_action('rest_api_init', function () {
    register_rest_route('cjr-chatbot/v1', '/ask', array(
        'methods' => 'POST',
        'callback' => 'cjr_chatbot_handle_question',
        'permission_callback' => '__return_true',
    ));
});

function cjr_chatbot_handle_question($request) {
    $user_message = sanitize_text_field($request->get_param('message'));
    if (empty($user_message)) {
        return new WP_Error('no_message', 'No message provided', array('status' => 400));
    }

    $ai_response = cjr_get_ai_response($user_message);
    if (is_wp_error($ai_response)) {
        return new WP_REST_Response(['success' => false,'message' => 'Sorry, something went wrong.'], 500);
    }

    return new WP_REST_Response(['success' => true,'message' => $ai_response], 200);
}

/* -------------------------------
   Cosine Similarity Helper
--------------------------------*/
function cosine_similarity($vecA, $vecB) {
    $dot = 0.0; $normA = 0.0; $normB = 0.0;
    $len = min(count($vecA), count($vecB));
    for ($i=0; $i<$len; $i++) {
        $dot += $vecA[$i] * $vecB[$i];
        $normA += $vecA[$i] ** 2;
        $normB += $vecB[$i] ** 2;
    }
    return $normA && $normB ? $dot / (sqrt($normA) * sqrt($normB)) : 0.0;
}

/* -------------------------------
   Generate Embedding via RouteLLM
--------------------------------*/
function cjr_generate_embedding($text, $api_key) {
    $api_url = "https://api.abacus.ai/routeLLM";
    $payload = [
        "model" => "openai:text-embedding-3-small",
        "input" => $text
    ];

    $response = wp_remote_post($api_url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json'
        ],
        'body' => json_encode($payload),
        'timeout' => 30
    ]);

    if (is_wp_error($response)) {
        error_log("Embedding generation failed: " . $response->get_error_message());
        return null;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    return isset($data['embedding']) ? $data['embedding'] : null;
}

/* -------------------------------
   RouteLLM AI Call with Content Search
--------------------------------*/
function cjr_get_ai_response($user_message) {
    $api_key = get_option('cjr_chatbot_api_key', '');
    if (!$api_key) return new WP_Error('missing_api_key', 'No API key set');

    // Generate embedding for user query
    $query_embedding = cjr_generate_embedding($user_message, $api_key);
    $context_content = "";

    if ($query_embedding) {
        // Get indexed post types from settings
        $indexed_types = get_option('cjr_chatbot_indexed_types', ['post', 'page', 'cjr_faq']);
        
        $all_posts = get_posts([
            'post_type' => $indexed_types,
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ]);

        $matches = [];
        foreach ($all_posts as $post) {
            $vector_json = get_post_meta($post->ID, '_cjr_content_vector', true);
            if (!$vector_json) continue;
            
            $post_vec = json_decode($vector_json, true);
            if (!$post_vec) continue;

            $score = cosine_similarity($query_embedding, $post_vec);
            if ($score > 0.7) { // similarity threshold
                $matches[] = [
                    'post' => $post,
                    'score' => $score
                ];
            }
        }

        // Sort by similarity score (highest first)
        usort($matches, function($a, $b) { return $b['score'] <=> $a['score']; });

        // For FAQs with very high similarity, return direct answer
        if (!empty($matches) && $matches[0]['score'] > 0.85 && $matches[0]['post']->post_type === 'cjr_faq') {
            return wp_strip_all_tags($matches[0]['post']->post_content);
        }

        // Build context from top matches
        $context_parts = [];
        foreach (array_slice($matches, 0, 3) as $match) { // top 3 matches
            $post = $match['post'];
            $content = wp_strip_all_tags($post->post_content);
            $excerpt = wp_trim_words($content, 100);
            $context_parts[] = "From '{$post->post_title}': {$excerpt}";
        }
        
        if (!empty($context_parts)) {
            $context_content = "\n\nRelevant CJR content:\n" . implode("\n\n", $context_parts);
        }
    }

    // Call RouteLLM API with context
    $api_url = 'https://api.abacus.ai/routeLLM';
    $system_prompt = "You are a helpful chatbot for the Center for Justice Research (CJR). 
    Answer questions about the organization's research, projects, criminal justice, crime data, and legal issues. 
    Be informative and neutral. Always clarify that you are not giving legal advice.
    Use the provided context from CJR's website to give accurate, specific answers when possible." . $context_content;

    $payload = [
        "model"       => get_option('cjr_chatbot_model', 'openai:gpt-3.5-turbo'),
        "messages"    => [
            ["role" => "system", "content" => $system_prompt],
            ["role" => "user", "content" => $user_message]
        ],
        "max_tokens"  => intval(get_option('cjr_chatbot_maxtokens', 400)),
        "temperature" => floatval(get_option('cjr_chatbot_temperature', 0.7))
    ];

    $response = wp_remote_post($api_url, [
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        ],
        'body' => json_encode($payload),
        'timeout' => 30
    ]);

    if (is_wp_error($response)) return new WP_Error('api_error', 'Failed to connect');
    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if ($code !== 200 || !isset($data['output_text'])) {
        error_log("CJR Chatbot API Error: $code - $body");
        return new WP_Error('bad_response', 'Unexpected AI response');
    }

    return trim($data['output_text']);
}

/* -------------------------------
   Register Settings Page
--------------------------------*/
add_action('admin_menu', function () {
    add_options_page(
        'CJR Chatbot Settings',
        'CJR Chatbot',
        'manage_options',
        'cjr-chatbot',
        'cjr_chatbot_settings_page'
    );
});

function cjr_chatbot_settings_page() {
    if (!current_user_can('manage_options')) return;

    if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'cjr_chatbot_settings')) {
        update_option('cjr_chatbot_api_key', sanitize_text_field($_POST['cjr_chatbot_api_key']));
        update_option('cjr_chatbot_enabled', isset($_POST['cjr_chatbot_enabled']) ? 1 : 0);
        update_option('cjr_chatbot_model', sanitize_text_field($_POST['cjr_chatbot_model']));
        update_option('cjr_chatbot_maxtokens', intval($_POST['cjr_chatbot_maxtokens']));
        update_option('cjr_chatbot_temperature', floatval($_POST['cjr_chatbot_temperature']));
        
        // Save indexed post types
        $indexed_types = isset($_POST['cjr_chatbot_indexed_types']) ? array_map('sanitize_text_field', $_POST['cjr_chatbot_indexed_types']) : [];
        update_option('cjr_chatbot_indexed_types', $indexed_types);
        
        echo '<div class="updated"><p>Settings saved!</p></div>';
    }

    // Handle bulk indexing
    if (isset($_POST['bulk_index']) && wp_verify_nonce($_POST['_wpnonce'], 'cjr_chatbot_settings')) {
        cjr_bulk_index_content();
        echo '<div class="updated"><p>Content indexing started! Check your error log for progress.</p></div>';
    }

    // Load existing settings
    $api_key = get_option('cjr_chatbot_api_key', '');
    $enabled = get_option('cjr_chatbot_enabled', 1);
    $selected_model = get_option('cjr_chatbot_model', 'openai:gpt-3.5-turbo');
    $max_tokens = get_option('cjr_chatbot_maxtokens', 400);
    $temperature = get_option('cjr_chatbot_temperature', 0.7);
    $indexed_types = get_option('cjr_chatbot_indexed_types', ['post', 'page', 'cjr_faq']);
    
    // Get all available post types
    $post_types = get_post_types(['public' => true], 'objects');
    $post_types['cjr_faq'] = (object)['name' => 'cjr_faq', 'label' => 'Chatbot Q&A'];
    ?>
    <div class="wrap">
        <h1>CJR Chatbot Settings</h1>
        <form method="post">
            <?php wp_nonce_field('cjr_chatbot_settings'); ?>
            <table class="form-table">
                <tr>
                    <th>Enable Chatbot</th>
                    <td><input type="checkbox" name="cjr_chatbot_enabled" value="1" <?php checked($enabled, 1); ?>> Show chatbot on site</td>
                </tr>
                <tr>
                    <th>RouteLLM API Key</th>
                    <td>
                        <input type="text" name="cjr_chatbot_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text">
                        <p><a href="https://abacus.ai/app/route-llm-apis" target="_blank">Get your key here</a></p>
                    </td>
                </tr>
                <tr>
                    <th>Model</th>
                    <td>
                        <select name="cjr_chatbot_model">
                            <option value="openai:gpt-3.5-turbo" <?php selected($selected_model, 'openai:gpt-3.5-turbo'); ?>>OpenAI: GPT‑3.5 Turbo</option>
                            <option value="openai:gpt-4" <?php selected($selected_model, 'openai:gpt-4'); ?>>OpenAI: GPT‑4</option>
                            <option value="anthropic:claude-3-opus" <?php selected($selected_model, 'anthropic:claude-3-opus'); ?>>Anthropic: Claude 3 Opus</option>
                            <option value="anthropic:claude-3-sonnet" <?php selected($selected_model, 'anthropic:claude-3-sonnet'); ?>>Anthropic: Claude 3 Sonnet</option>
                            <option value="anthropic:claude-3-haiku" <?php selected($selected_model, 'anthropic:claude-3-haiku'); ?>>Anthropic: Claude 3 Haiku</option>
                            <option value="meta:llama-3-70b" <?php selected($selected_model, 'meta:llama-3-70b'); ?>>Meta: LLaMA 3‑70B</option>
                            <option value="meta:llama-3-8b" <?php selected($selected_model, 'meta:llama-3-8b'); ?>>Meta: LLaMA 3‑8B</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Max Tokens</th>
                    <td><input type="number" name="cjr_chatbot_maxtokens" value="<?php echo esc_attr($max_tokens); ?>" min="50" max="2000"></td>
                </tr>
                <tr>
                    <th>Temperature</th>
                    <td><input type="number" step="0.1" name="cjr_chatbot_temperature" value="<?php echo esc_attr($temperature); ?>" min="0" max="1"></td>
                </tr>
                <tr>
                    <th>Index Content Types</th>
                    <td>
                        <?php foreach ($post_types as $post_type): ?>
                            <label>
                                <input type="checkbox" name="cjr_chatbot_indexed_types[]" value="<?php echo esc_attr($post_type->name); ?>" 
                                       <?php checked(in_array($post_type->name, $indexed_types)); ?>>
                                <?php echo esc_html($post_type->label); ?>
                            </label><br>
                        <?php endforeach; ?>
                        <p class="description">Select which content types to index for chatbot responses.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        
        <hr>
        <h2>Content Indexing</h2>
        <form method="post">
            <?php wp_nonce_field('cjr_chatbot_settings'); ?>
            <p>Click to generate embeddings for all existing content of selected types:</p>
            <input type="submit" name="bulk_index" value="Index All Content" class="button-secondary">
        </form>
    </div>
    <?php
}

/* -------------------------------
   Bulk Index Existing Content
--------------------------------*/
function cjr_bulk_index_content() {
    $api_key = get_option('cjr_chatbot_api_key', '');
    if (!$api_key) return;

    $indexed_types = get_option('cjr_chatbot_indexed_types', ['post', 'page', 'cjr_faq']);
    
    $posts = get_posts([
        'post_type' => $indexed_types,
        'posts_per_page' => -1,
        'post_status' => 'publish'
    ]);

    foreach ($posts as $post) {
        cjr_index_post_content($post->ID, $post, false, $api_key);
        // Small delay to avoid rate limiting
        usleep(100000); // 0.1 second
    }
    
    error_log("CJR Chatbot: Indexed " . count($posts) . " posts");
}

/* -------------------------------
   Index Individual Post Content
--------------------------------*/
function cjr_index_post_content($post_id, $post, $update, $api_key = null) {
    if ($post->post_status !== 'publish') return;
    
    // Skip if not in indexed types
    $indexed_types = get_option('cjr_chatbot_indexed_types', ['post', 'page', 'cjr_faq']);
    if (!in_array($post->post_type, $indexed_types)) return;

    if (!$api_key) $api_key = get_option('cjr_chatbot_api_key', '');
    if (!$api_key) return;

    // Combine title and content for embedding
    $text = $post->post_title . " " . wp_strip_all_tags($post->post_content);
    $text = wp_trim_words($text, 500); // Limit to ~500 words to avoid token limits

    $embedding = cjr_generate_embedding($text, $api_key);
    
    if ($embedding) {
        update_post_meta($post_id, '_cjr_content_vector', wp_json_encode($embedding));
        error_log("CJR Chatbot: Indexed post {$post_id} - {$post->post_title}");
    }
}

/* -------------------------------
   Auto-Index on Post Save
--------------------------------*/
add_action('save_post', function ($post_id, $post, $update) {
    cjr_index_post_content($post_id, $post, $update);
}, 10, 3);

/* -------------------------------
   Custom Post Type for Q&A
--------------------------------*/
add_action('init', function() {
    register_post_type('cjr_faq', [
        'labels' => [
            'name' => 'Chatbot Q&A',
            'singular_name' => 'Chatbot Q&A',
            'add_new_item' => 'Add New Question/Answer',
            'edit_item'    => 'Edit Question/Answer'
        ],
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => 'options-general.php',
        'supports' => ['title','editor'],
    ]);
});

/* -------------------------------
   Inject Chatbot HTML in Footer
--------------------------------*/
add_action('wp_footer', function() {
    if (!get_option('cjr_chatbot_enabled', 1)) return; ?>
    <button id="chatbot-toggle">💬</button>
    <div id="chatbot-container">
      <div id="chatbot-header">CJR Chatbot</div>
      <div id="chatbot-messages">
        <div class="chat-message bot-message">👋 Hello! How can I help you with the Center for Justice Research today?</div>
      </div>
      <div id="chatbot-input-area">
        <input type="text" id="chatbot-input" placeholder="Ask a question..." />
        <button id="chatbot-send-button">Send</button>
      </div>
    </div>
<?php });

/* -------------------------------
   Enqueue CSS & JS
--------------------------------*/
add_action('wp_enqueue_scripts', function() {
    if (!get_option('cjr_chatbot_enabled', 1)) return;

    wp_register_style('cjr-chatbot-style', false);
    wp_enqueue_style('cjr-chatbot-style');
    wp_add_inline_style('cjr-chatbot-style', "
        #chatbot-toggle { position:fixed; bottom:20px; right:20px; background:#940035; color:#fff; width:60px; height:60px; border-radius:50%; border:none; cursor:pointer; font-size:24px; display:flex; align-items:center; justify-content:center; z-index:1001; }
        #chatbot-toggle:hover { background:#700029; }
        #chatbot-container { position:fixed; bottom:90px; right:20px; width:350px; height:450px; border:1px solid #ccc; border-radius:10px; box-shadow:0 4px 8px rgba(0,0,0,0.2); font-family:Arial,sans-serif; display:none; flex-direction:column; background:#fff; z-index:1000; }
        #chatbot-header { background:#940035; color:white; padding:10px; border-top-left-radius:9px; border-top-right-radius:9px; text-align:center; font-weight:bold; }
        #chatbot-messages { flex-grow:1; padding:10px; overflow-y:auto; background:#f9f9f9; border-bottom:1px solid #eee; }
        .chat-message { margin-bottom:10px; padding:8px 12px; border-radius:15px; max-width:80%; word-wrap:break-word; }
        .user-message { background:#dcf8c6; margin-left:auto; text-align:right; }
        .bot-message { background:#e0e0e0; margin-right:auto; text-align:left; }
        #chatbot-input-area { display:flex; padding:10px; border-top:1px solid #eee; }
        #chatbot-input { flex:1; padding:8px; border:1px solid #ccc; border-radius:5px; margin-right:10px; }
        #chatbot-send-button { background:#940035; color:white; border:none; padding:8px 15px; border-radius:5px; cursor:pointer; }
        #chatbot-send-button:hover { background:#700029; }
    ");

    wp_add_inline_script('jquery', "
        document.addEventListener('DOMContentLoaded', function() {
            const chatbotContainer=document.getElementById('chatbot-container');
            const toggleButton=document.getElementById('chatbot-toggle');
            const messagesDiv=document.getElementById('chatbot-messages');
            const inputField=document.getElementById('chatbot-input');
            const sendButton=document.getElementById('chatbot-send-button');
            toggleButton.addEventListener('click',()=>{chatbotContainer.style.display=chatbotContainer.style.display==='flex'?'none':'flex';});
            function addMessage(text,sender){const el=document.createElement('div');el.classList.add('chat-message',sender+'-message');el.textContent=text;messagesDiv.appendChild(el);messagesDiv.scrollTop=messagesDiv.scrollHeight;}
            async function sendMessage(){const userMessage=inputField.value.trim();if(userMessage==='')return;addMessage(userMessage,'user');inputField.value='';const botTyping=document.createElement('div');botTyping.classList.add('chat-message','bot-message');botTyping.textContent='Bot is typing...';messagesDiv.appendChild(botTyping);messagesDiv.scrollTop=messagesDiv.scrollHeight;try{const r=await fetch('/wp-json/cjr-chatbot/v1/ask',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({message:userMessage})});const d=await r.json();messagesDiv.removeChild(botTyping);if(d.success)addMessage(d.message,'bot');else addMessage('⚠️ Error, please try again.','bot');}catch(e){messagesDiv.removeChild(botTyping);addMessage('⚠️ Oops, something went wrong.','bot');}}
            sendButton.addEventListener('click',sendMessage);
            inputField.addEventListener('keypress',e=>{if(e.key==='Enter')sendMessage();});
        });
    ");
});