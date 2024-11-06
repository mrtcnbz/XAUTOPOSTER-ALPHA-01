<?php
if (!defined('ABSPATH')) {
    exit;
}

// Get current tab
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'settings';

// Pagination ve Sıralama parametreleri
$paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$posts_per_page = 10;
$orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'date';
$order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC';
$category = isset($_GET['category']) ? intval($_GET['category']) : 0;
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <nav class="nav-tab-wrapper">
        <a href="?page=xautoposter&tab=settings" 
           class="nav-tab <?php echo $current_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
            <?php _e('API Settings', 'xautoposter'); ?>
        </a>
        <a href="?page=xautoposter&tab=auto-share" 
           class="nav-tab <?php echo $current_tab === 'auto-share' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Auto Share', 'xautoposter'); ?>
        </a>
        <a href="?page=xautoposter&tab=manual-share" 
           class="nav-tab <?php echo $current_tab === 'manual-share' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Manual Share', 'xautoposter'); ?>
        </a>
        <a href="?page=xautoposter&tab=metrics" 
           class="nav-tab <?php echo $current_tab === 'metrics' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Metrics', 'xautoposter'); ?>
        </a>
    </nav>
    
    <div class="tab-content">
        <?php if ($current_tab === 'settings'): ?>
            <form method="post" action="options.php">
                <?php
                    settings_fields('xautoposter_options');
                    do_settings_sections('xautoposter-api-settings');
                    submit_button();
                ?>
            </form>
            
        <?php elseif ($current_tab === 'auto-share'): ?>
            <form method="post" action="options.php">
                <?php
                    settings_fields('xautoposter_options');
                    do_settings_sections('xautoposter-auto-share');
                    submit_button();
                ?>
            </form>

        <?php elseif ($current_tab === 'manual-share'): ?>
            <div class="card">
                <!-- Filtreleme Formu -->
                <div class="tablenav top">
                    <form method="get" class="alignleft actions">
                        <input type="hidden" name="page" value="xautoposter">
                        <input type="hidden" name="tab" value="manual-share">
                        
                        <select name="category">
                            <option value=""><?php _e('All Categories', 'xautoposter'); ?></option>
                            <?php
                            $categories = get_categories(['hide_empty' => false]);
                            foreach ($categories as $cat) {
                                printf(
                                    '<option value="%s"%s>%s</option>',
                                    $cat->term_id,
                                    selected($category, $cat->term_id, false),
                                    esc_html($cat->name)
                                );
                            }
                            ?>
                        </select>
                        
                        <select name="orderby">
                            <option value="date" <?php selected($orderby, 'date'); ?>>
                                <?php _e('Date', 'xautoposter'); ?>
                            </option>
                            <option value="title" <?php selected($orderby, 'title'); ?>>
                                <?php _e('Title', 'xautoposter'); ?>
                            </option>
                        </select>
                        
                        <select name="order">
                            <option value="DESC" <?php selected($order, 'DESC'); ?>>
                                <?php _e('Descending', 'xautoposter'); ?>
                            </option>
                            <option value="ASC" <?php selected($order, 'ASC'); ?>>
                                <?php _e('Ascending', 'xautoposter'); ?>
                            </option>
                        </select>
                        
                        <?php submit_button(__('Filter', 'xautoposter'), 'secondary', 'filter', false); ?>
                    </form>
                </div>

                <table class="widefat">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="select-all-posts"></th>
                            <th><?php _e('Image', 'xautoposter'); ?></th>
                            <th><?php _e('Title', 'xautoposter'); ?></th>
                            <th><?php _e('Category', 'xautoposter'); ?></th>
                            <th><?php _e('Date', 'xautoposter'); ?></th>
                            <th><?php _e('Status', 'xautoposter'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $args = [
                            'post_type' => 'post',
                            'post_status' => 'publish',
                            'posts_per_page' => $posts_per_page,
                            'paged' => $paged,
                            'orderby' => $orderby,
                            'order' => $order
                        ];

                        if ($category > 0) {
                            $args['cat'] = $category;
                        }

                        $query = new WP_Query($args);
                        
                        foreach ($query->posts as $post):
                            $is_shared = get_post_meta($post->ID, '_xautoposter_shared', true);
                            $share_time = get_post_meta($post->ID, '_xautoposter_share_time', true);
                            $tweet_id = get_post_meta($post->ID, '_xautoposter_tweet_id', true);
                            $metrics = get_post_meta($post->ID, '_xautoposter_tweet_metrics', true);
                            $thumbnail = get_the_post_thumbnail_url($post->ID, 'thumbnail');
                            $categories = get_the_category($post->ID);
                        ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="posts[]" value="<?php echo $post->ID; ?>" 
                                       <?php echo $is_shared ? 'disabled' : ''; ?>>
                            </td>
                            <td>
                                <?php if ($thumbnail): ?>
                                    <img src="<?php echo esc_url($thumbnail); ?>" 
                                         alt="<?php echo esc_attr($post->post_title); ?>"
                                         style="width: 50px; height: 50px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="no-image">No Image</div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($post->post_title); ?></td>
                            <td>
                                <?php
                                if (!empty($categories)) {
                                    $cat_names = array_map(function($cat) {
                                        return esc_html($cat->name);
                                    }, $categories);
                                    echo implode(', ', $cat_names);
                                }
                                ?>
                            </td>
                            <td><?php echo get_the_date('', $post); ?></td>
                            <td>
                                <?php if ($is_shared): ?>
                                    <span class="dashicons dashicons-yes-alt"></span> 
                                    <?php 
                                    echo sprintf(
                                        __('Shared on %s', 'xautoposter'), 
                                        $share_time
                                    );
                                    if ($tweet_id && $metrics) {
                                        echo '<br><small>';
                                        echo sprintf(
                                            __('Likes: %d, Retweets: %d', 'xautoposter'),
                                            $metrics['like_count'] ?? 0,
                                            $metrics['retweet_count'] ?? 0
                                        );
                                        echo '</small>';
                                    }
                                    ?>
                                <?php else: ?>
                                    <span class="dashicons dashicons-minus"></span>
                                    <?php _e('Not shared', 'xautoposter'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php
                $total_pages = $query->max_num_pages;
                if ($total_pages > 1) :
                ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links([
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => __('&laquo;'),
                            'next_text' => __('&raquo;'),
                            'total' => $total_pages,
                            'current' => $paged
                        ]);
                        ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <p>
                    <button type="button" id="share-selected" class="button button-primary">
                        <?php _e('Share Selected Posts', 'xautoposter'); ?>
                    </button>
                </p>
            </div>

        <?php elseif ($current_tab === 'metrics'): ?>
            <!-- Metrics tab content remains the same -->
        <?php endif; ?>
    </div>
</div>

<style>
.wrap .card {
    max-width: none;
    margin-top: 20px;
    padding: 20px;
}
.wrap .widefat {
    margin: 15px 0;
}
.wrap .dashicons-yes-alt {
    color: #46b450;
}
.wrap .dashicons-minus {
    color: #dc3232;
}
.no-image {
    width: 50px;
    height: 50px;
    background: #f0f0f0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    color: #666;
}
.tab-content {
    margin-top: 20px;
}
.category-checkboxes {
    max-height: 200px;
    overflow-y: auto;
    padding: 10px;
    border: 1px solid #ddd;
}
.api-lock-controls {
    margin: 15px 0;
}
.tablenav.top {
    margin-bottom: 1em;
}
.tablenav select {
    margin-right: 5px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Select All Posts
    $('#select-all-posts').on('change', function() {
        $('input[name="posts[]"]:not(:disabled)').prop('checked', $(this).prop('checked'));
    });
    
    // Share Selected Posts
    $('#share-selected').on('click', function() {
        var posts = $('input[name="posts[]"]:checked').map(function() {
            return $(this).val();
        }).get();
        
        if (posts.length === 0) {
            alert('<?php _e('Please select posts to share.', 'xautoposter'); ?>');
            return;
        }
        
        var $button = $(this);
        $button.prop('disabled', true).text('<?php _e('Sharing...', 'xautoposter'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'xautoposter_share_posts',
                posts: posts,
                nonce: '<?php echo wp_create_nonce('xautoposter_share_posts'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert(response.data.message || '<?php _e('An error occurred.', 'xautoposter'); ?>');
                }
            },
            error: function() {
                alert('<?php _e('An error occurred while sharing posts.', 'xautoposter'); ?>');
            },
            complete: function() {
                $button.prop('disabled', false)
                    .text('<?php _e('Share Selected Posts', 'xautoposter'); ?>');
            }
        });
    });
});
</script>