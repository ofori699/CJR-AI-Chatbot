/**
 * Admin JavaScript
 *
 * @package CJR_Chatbot
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        /**
         * Batch Indexing
         */
        $('#cjr-start-indexing').on('click', function() {
            const button = $(this);
            const progressContainer = $('.cjr-progress-container');
            const progressFill = $('.cjr-progress-fill');
            const progressText = $('.cjr-progress-text');
            
            // Disable button
            button.prop('disabled', true).text(cjrChatbotAdmin.strings.indexing);
            
            // Show progress bar
            progressContainer.show();
            progressFill.css('width', '0%').text('0%');
            progressText.text('Starting indexing...');
            
            let offset = 0;
            let totalProcessed = 0;
            let totalSuccess = 0;
            let totalFailed = 0;
            
            function processBatch() {
                $.ajax({
                    url: cjrChatbotAdmin.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'cjr_chatbot_batch_index',
                        nonce: cjrChatbotAdmin.indexNonce,
                        offset: offset
                    },
                    success: function(response) {
                        if (response.success) {
                            const data = response.data;
                            totalProcessed += data.processed;
                            totalSuccess += data.success;
                            totalFailed += data.failed;
                            offset = data.offset;
                            
                            // Update progress text
                            progressText.text(
                                'Processed: ' + totalProcessed + ' | ' +
                                'Success: ' + totalSuccess + ' | ' +
                                'Failed: ' + totalFailed
                            );
                            
                            // Continue if more to process
                            if (data.has_more) {
                                processBatch();
                            } else {
                                // Complete
                                progressFill.css('width', '100%').text('100%');
                                progressText.text(cjrChatbotAdmin.strings.indexComplete + ' (' + totalSuccess + ' indexed, ' + totalFailed + ' failed)');
                                button.prop('disabled', false).text('Start Indexing');
                                
                                // Reload page after 2 seconds to update stats
                                setTimeout(function() {
                                    location.reload();
                                }, 2000);
                            }
                        } else {
                            progressText.text(cjrChatbotAdmin.strings.indexError);
                            button.prop('disabled', false).text('Start Indexing');
                        }
                    },
                    error: function() {
                        progressText.text(cjrChatbotAdmin.strings.indexError);
                        button.prop('disabled', false).text('Start Indexing');
                    }
                });
            }
            
            // Start processing
            processBatch();
        });
        
        /**
         * Clear Index
         */
        $('#cjr-clear-index').on('click', function() {
            if (!confirm(cjrChatbotAdmin.strings.confirmClear)) {
                return;
            }
            
            const button = $(this);
            button.prop('disabled', true);
            
            $.ajax({
                url: cjrChatbotAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'cjr_chatbot_clear_index',
                    nonce: cjrChatbotAdmin.indexNonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + response.data.message);
                        button.prop('disabled', false);
                    }
                },
                error: function() {
                    alert('Network error. Please try again.');
                    button.prop('disabled', false);
                }
            });
        });
        
        /**
         * Q&A Form Submission
         */
        $('#cjr-qa-form').on('submit', function(e) {
            e.preventDefault();
            
            const form = $(this);
            const messageDiv = $('#cjr-qa-message');
            const submitButton = form.find('button[type="submit"]');
            
            // Get form data
            const qaId = $('#qa_id').val();
            const question = $('#qa_question').val().trim();
            const answer = typeof tinyMCE !== 'undefined' && tinyMCE.get('qa_answer') 
                ? tinyMCE.get('qa_answer').getContent() 
                : $('#qa_answer').val();
            
            // Validate
            if (!question || !answer) {
                messageDiv.removeClass('success').addClass('error')
                    .text('Question and answer are required.').show();
                return;
            }
            
            // Disable button
            submitButton.prop('disabled', true);
            
            // Submit via AJAX
            $.ajax({
                url: cjrChatbotAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'cjr_chatbot_save_qa',
                    nonce: cjrChatbotAdmin.qaNonce,
                    qa_id: qaId,
                    question: question,
                    answer: answer
                },
                success: function(response) {
                    if (response.success) {
                        messageDiv.removeClass('error').addClass('success')
                            .text(response.data.message).show();
                        
                        // Reload page after 1 second
                        setTimeout(function() {
                            window.location.href = '?page=cjr-chatbot-qa';
                        }, 1000);
                    } else {
                        messageDiv.removeClass('success').addClass('error')
                            .text(response.data.message).show();
                        submitButton.prop('disabled', false);
                    }
                },
                error: function() {
                    messageDiv.removeClass('success').addClass('error')
                        .text('Network error. Please try again.').show();
                    submitButton.prop('disabled', false);
                }
            });
        });
        
        /**
         * Delete Q&A
         */
        $('.cjr-delete-qa').on('click', function() {
            if (!confirm(cjrChatbotAdmin.strings.confirmDelete)) {
                return;
            }
            
            const button = $(this);
            const qaId = button.data('qa-id');
            const row = button.closest('tr');
            
            button.prop('disabled', true);
            
            $.ajax({
                url: cjrChatbotAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'cjr_chatbot_delete_qa',
                    nonce: cjrChatbotAdmin.qaNonce,
                    qa_id: qaId
                },
                success: function(response) {
                    if (response.success) {
                        row.fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        alert('Error: ' + response.data.message);
                        button.prop('disabled', false);
                    }
                },
                error: function() {
                    alert('Network error. Please try again.');
                    button.prop('disabled', false);
                }
            });
        });
        
    });
})(jQuery);
