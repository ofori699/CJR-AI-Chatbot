<?php
/**
 * Q&A Management Page Template
 *
 * @package CJR_Chatbot
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle add/edit Q&A
$editing = false;
$qa_id = 0;
$question = '';
$answer = '';

if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['qa_id'])) {
    $qa_id = intval($_GET['qa_id']);
    $qa_post = get_post($qa_id);
    
    if ($qa_post && $qa_post->post_type === 'cjr_faq') {
        $editing = true;
        $question = $qa_post->post_title;
        $answer = $qa_post->post_content;
    }
}

// Get all Q&A entries
$paged = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

$args = array(
    'post_type' => 'cjr_faq',
    'posts_per_page' => 20,
    'paged' => $paged,
    'post_status' => 'publish',
    'orderby' => 'date',
    'order' => 'DESC',
);

if (!empty($search)) {
    $args['s'] = $search;
}

$qa_query = new WP_Query($args);
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="cjr-qa-container">
        <!-- Add/Edit Form -->
        <div class="cjr-qa-form-section">
            <h2><?php echo $editing ? esc_html__('Edit Q&A', 'cjr-chatbot') : esc_html__('Add New Q&A', 'cjr-chatbot'); ?></h2>
            <form id="cjr-qa-form" class="cjr-qa-form">
                <input type="hidden" id="qa_id" name="qa_id" value="<?php echo esc_attr($qa_id); ?>">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="qa_question"><?php esc_html_e('Question', 'cjr-chatbot'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="text" id="qa_question" name="question" value="<?php echo esc_attr($question); ?>" class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="qa_answer"><?php esc_html_e('Answer', 'cjr-chatbot'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <?php
                            wp_editor($answer, 'qa_answer', array(
                                'textarea_name' => 'answer',
                                'textarea_rows' => 10,
                                'media_buttons' => false,
                                'teeny' => true,
                            ));
                            ?>
                            <p class="description"><?php esc_html_e('This answer will be used when the question closely matches a user query.', 'cjr-chatbot'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php echo $editing ? esc_html__('Update Q&A', 'cjr-chatbot') : esc_html__('Add Q&A', 'cjr-chatbot'); ?>
                    </button>
                    <?php if ($editing) : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=cjr-chatbot-qa')); ?>" class="button button-secondary">
                            <?php esc_html_e('Cancel', 'cjr-chatbot'); ?>
                        </a>
                    <?php endif; ?>
                </p>
            </form>
            <div id="cjr-qa-message" style="display: none;"></div>
        </div>
        
        <hr>
        
        <!-- Q&A List -->
        <div class="cjr-qa-list-section">
            <h2><?php esc_html_e('Existing Q&A', 'cjr-chatbot'); ?></h2>
            
            <!-- Search Form -->
            <form method="get" action="">
                <input type="hidden" name="page" value="cjr-chatbot-qa">
                <p class="search-box">
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search Q&A...', 'cjr-chatbot'); ?>">
                    <button type="submit" class="button"><?php esc_html_e('Search', 'cjr-chatbot'); ?></button>
                    <?php if (!empty($search)) : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=cjr-chatbot-qa')); ?>" class="button">
                            <?php esc_html_e('Clear', 'cjr-chatbot'); ?>
                        </a>
                    <?php endif; ?>
                </p>
            </form>
            
            <?php if ($qa_query->have_posts()) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 40%;"><?php esc_html_e('Question', 'cjr-chatbot'); ?></th>
                            <th style="width: 45%;"><?php esc_html_e('Answer', 'cjr-chatbot'); ?></th>
                            <th style="width: 15%;"><?php esc_html_e('Actions', 'cjr-chatbot'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($qa_query->have_posts()) : $qa_query->the_post(); ?>
                            <tr>
                                <td><strong><?php echo esc_html(get_the_title()); ?></strong></td>
                                <td><?php echo esc_html(wp_trim_words(wp_strip_all_tags(get_the_content()), 20)); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(add_query_arg(array('action' => 'edit', 'qa_id' => get_the_ID()))); ?>" class="button button-small">
                                        <?php esc_html_e('Edit', 'cjr-chatbot'); ?>
                                    </a>
                                    <button type="button" class="button button-small cjr-delete-qa" data-qa-id="<?php echo esc_attr(get_the_ID()); ?>">
                                        <?php esc_html_e('Delete', 'cjr-chatbot'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php
                $total_pages = $qa_query->max_num_pages;
                if ($total_pages > 1) {
                    echo '<div class="tablenav"><div class="tablenav-pages">';
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo;', 'cjr-chatbot'),
                        'next_text' => __('&raquo;', 'cjr-chatbot'),
                        'total' => $total_pages,
                        'current' => $paged
                    ));
                    echo '</div></div>';
                }
                ?>
            <?php else : ?>
                <p><?php esc_html_e('No Q&A entries found. Add your first one above!', 'cjr-chatbot'); ?></p>
            <?php endif; ?>
            
            <?php wp_reset_postdata(); ?>
        </div>
    </div>
</div>
