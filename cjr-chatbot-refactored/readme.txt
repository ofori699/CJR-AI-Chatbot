=== CJR Chatbot ===
Contributors: fopstudios
Tags: chatbot, ai, llm, embeddings, semantic-search, routellm, abacus.ai
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered chatbot for WordPress with content indexing, semantic search, and RouteLLM integration.

== Description ==

CJR Chatbot adds an intelligent, AI-powered chatbot to your WordPress site. It automatically indexes your site's content (posts, pages, and custom post types) and uses it as a knowledge base to provide accurate, context-aware answers to user questions.

This plugin is ideal for organizations, businesses, and individuals who want to provide instant, 24/7 support and information to their visitors. By leveraging your own content, the chatbot ensures that responses are relevant and aligned with your brand.

**Key Features:**

*   **Automatic Content Indexing:** Scans your posts, pages, and custom post types to build a comprehensive knowledge base.
*   **Semantic Search:** Uses vector embeddings to understand the meaning behind user questions, not just keywords.
*   **RouteLLM Integration:** Powered by Abacus.AI's RouteLLM, allowing you to use a variety of powerful language models (GPT-4, Claude 3, LLaMA 3, etc.).
*   **Custom Q&A Management:** Define specific question-and-answer pairs in the admin dashboard for high-priority topics.
*   **AJAX-Powered Indexing:** A robust batch indexing system handles large sites without timeouts, complete with a progress bar.
*   **Secure & Performant:** Built with security and performance in mind, including rate limiting, nonce protection, and efficient code.
*   **Modern Chat Widget:** A clean, responsive, and easy-to-use chat interface for your visitors.
*   **WordPress Standards:** Follows WordPress coding standards and is ready for translation.

== Installation ==

1.  Upload the plugin files to the `/wp-content/plugins/cjr-chatbot/` directory, or install the plugin through the WordPress plugins screen directly.
2.  Activate the plugin through the 'Plugins' screen in WordPress.
3.  Go to **CJR Chatbot > Settings** in your WordPress admin menu.
4.  Enter your **RouteLLM API Key**. You can get one from the [Abacus.AI RouteLLM APIs page](https://abacus.ai/app/route-llm-apis).
5.  Configure your desired settings (model, indexed content types, etc.) and click **Save Settings**.
6.  In the **Content Indexing** section, click the **Start Indexing** button. Wait for the process to complete.
7.  The chatbot widget will now appear on your site (if enabled).

== Frequently Asked Questions ==

= Where do I get a RouteLLM API key? =

You can sign up and get an API key from the [Abacus.AI RouteLLM APIs page](https://abacus.ai/app/route-llm-apis). This key is required for the chatbot to function.

= How much does it cost? =

The CJR Chatbot plugin itself is free. However, usage of the RouteLLM API is subject to pricing from Abacus.AI. Please check their pricing details for the models you intend to use.

= What content is indexed? =

By default, the plugin indexes Posts, Pages, and any custom Q&A you create. You can customize which public post types are indexed from the **Settings > Content Indexing** section.

= How do I add custom Question/Answer pairs? =

Navigate to **CJR Chatbot > Q&A Management** in your admin dashboard. Here you can add, edit, and delete custom Q&A entries that the chatbot will use.

= Can I customize the chatbot's appearance? =

Currently, the chatbot has a standard design. Future versions may include more customization options. For now, you can override the CSS styles defined in `assets/css/chatbot.css` with your own custom CSS.

== Screenshots ==

1.  The modern, clean frontend chat widget in action.
2.  The main Settings page, showing API key configuration and model selection.
3.  The Content Indexing section with progress bar and statistics.
4.  The Q&A Management interface for adding and editing custom answers.

== Changelog ==

= 1.0.0 =
*   Initial release. Full refactor of the original plugin.
*   **NEW:** Class-based architecture for better maintainability.
*   **NEW:** Comprehensive security features (nonces, rate limiting, input sanitization).
*   **NEW:** AJAX-powered batch indexing with progress bar.
*   **NEW:** Full Q&A management interface with CRUD operations.
*   **NEW:** All strings are internationalized and ready for translation.
*   **NEW:** Added `readme.txt` and `uninstall.php` for WordPress.org compliance.
*   **FIX:** Corrected critical bugs in API response parsing for embeddings and chat completions.
*   **FIX:** Extracted all CSS and JavaScript to separate, properly enqueued files.
*   **IMPROVEMENT:** Restructured admin settings into logical sections.
*   **IMPROVEMENT:** Added source citations to chatbot responses.

== Upgrade Notice ==

= 1.0.0 =
This is the first stable release. It is a complete refactor of any previous versions. Please perform a full content re-index after upgrading.
