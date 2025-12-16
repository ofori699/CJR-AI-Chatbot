/**
 * Frontend Chatbot JavaScript
 *
 * @package CJR_Chatbot
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        const chatbot = {
            container: $('#chatbot-container'),
            toggle: $('#chatbot-toggle'),
            messages: $('#chatbot-messages'),
            input: $('#chatbot-input'),
            sendButton: $('#chatbot-send-button'),
            
            /**
             * Initialize chatbot
             */
            init: function() {
                this.bindEvents();
            },
            
            /**
             * Bind event handlers
             */
            bindEvents: function() {
                this.toggle.on('click', this.toggleChat.bind(this));
                this.sendButton.on('click', this.sendMessage.bind(this));
                this.input.on('keypress', this.handleKeypress.bind(this));
            },
            
            /**
             * Toggle chat visibility
             */
            toggleChat: function() {
                if (this.container.is(':visible')) {
                    this.container.fadeOut(200);
                } else {
                    this.container.fadeIn(200);
                    this.input.focus();
                }
            },
            
            /**
             * Handle keypress in input
             */
            handleKeypress: function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            },
            
            /**
             * Send message to chatbot
             */
            sendMessage: function() {
                const message = this.input.val().trim();
                
                if (!message) {
                    return;
                }
                
                // Add user message to chat
                this.addMessage(message, 'user');
                
                // Clear input
                this.input.val('');
                
                // Disable input while processing
                this.setLoading(true);
                
                // Show typing indicator
                const typingId = this.addMessage(cjrChatbot.strings.typing, 'bot', true);
                
                // Send to API
                this.callAPI(message)
                    .done((response) => {
                        // Remove typing indicator
                        this.removeMessage(typingId);
                        
                        if (response.success) {
                            this.addMessage(response.message, 'bot', false, response.sources);
                        } else {
                            this.addMessage(response.message || cjrChatbot.strings.error, 'bot');
                        }
                    })
                    .fail((xhr) => {
                        // Remove typing indicator
                        this.removeMessage(typingId);
                        
                        if (xhr.status === 429) {
                            this.addMessage(cjrChatbot.strings.rateLimit || 'Too many requests. Please wait.', 'bot');
                        } else if (xhr.status === 403) {
                            this.addMessage('Security check failed. Please refresh the page.', 'bot');
                        } else {
                            this.addMessage(cjrChatbot.strings.networkError, 'bot');
                        }
                    })
                    .always(() => {
                        this.setLoading(false);
                    });
            },
            
            /**
             * Call chatbot API
             */
            callAPI: function(message) {
                return $.ajax({
                    url: cjrChatbot.ajaxUrl,
                    method: 'POST',
                    headers: {
                        'X-WP-Nonce': cjrChatbot.nonce
                    },
                    data: JSON.stringify({message: message}),
                    contentType: 'application/json',
                    dataType: 'json'
                });
            },
            
            /**
             * Add message to chat
             */
            addMessage: function(text, sender, isTyping, sources) {
                const messageId = 'msg-' + Date.now();
                const messageClass = sender === 'user' ? 'user-message' : 'bot-message';
                const typingClass = isTyping ? ' typing' : '';
                
                let messageHtml = '<div class="chat-message ' + messageClass + typingClass + '" id="' + messageId + '">' + this.escapeHtml(text);
                
                // Add sources if available
                if (sources && sources.length > 0) {
                    messageHtml += '<div class="chat-sources">';
                    messageHtml += '<div class="chat-sources-title">Sources:</div>';
                    sources.forEach(function(source) {
                        if (source.url) {
                            messageHtml += '<div class="chat-source"><a href="' + source.url + '" target="_blank">' + chatbot.escapeHtml(source.title) + '</a></div>';
                        } else {
                            messageHtml += '<div class="chat-source">' + chatbot.escapeHtml(source.title) + ' (FAQ)</div>';
                        }
                    });
                    messageHtml += '</div>';
                }
                
                messageHtml += '</div>';
                
                this.messages.append(messageHtml);
                this.scrollToBottom();
                
                return messageId;
            },
            
            /**
             * Remove message from chat
             */
            removeMessage: function(messageId) {
                $('#' + messageId).remove();
            },
            
            /**
             * Set loading state
             */
            setLoading: function(loading) {
                this.input.prop('disabled', loading);
                this.sendButton.prop('disabled', loading);
            },
            
            /**
             * Scroll to bottom of messages
             */
            scrollToBottom: function() {
                this.messages.scrollTop(this.messages[0].scrollHeight);
            },
            
            /**
             * Escape HTML
             */
            escapeHtml: function(text) {
                const map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return text.replace(/[&<>"']/g, function(m) { return map[m]; });
            }
        };
        
        // Initialize chatbot
        chatbot.init();
    });
})(jQuery);
