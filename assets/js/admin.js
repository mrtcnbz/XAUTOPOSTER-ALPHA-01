jQuery(document).ready(function($) {
    // API Ayarlarını Düzenleme
    $('#unlock-api-settings').on('click', function() {
        var $button = $(this);
        var confirmUnlock = confirm(xautoposter.strings.confirm_unlock);
        
        if (!confirmUnlock) {
            return;
        }
        
        $button.prop('disabled', true);
        
        $.ajax({
            url: xautoposter.ajax_url,
            type: 'POST',
            data: {
                action: 'xautoposter_reset_api_verification',
                nonce: xautoposter.nonce
            },
            success: function(response) {
                if (response.success) {
                    // API input alanlarını etkinleştir
                    $('input[name^="xautoposter_options[api_"]').prop('disabled', false);
                    // Başarı mesajını ve kilit kontrollerini gizle
                    $('.notice-success, .api-lock-controls').fadeOut();
                    // Bilgi mesajını göster
                    $('.notice-info').fadeIn();
                } else {
                    alert(response.data.message || xautoposter.strings.error);
                    $button.prop('disabled', false);
                }
            },
            error: function() {
                alert(xautoposter.strings.error);
                $button.prop('disabled', false);
            }
        });
    });

    // Interval değişikliği uyarısı
    $('#interval').on('change', function() {
        alert(xautoposter.strings.interval_warning);
    });

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
            alert(xautoposter.strings.no_posts_selected);
            return;
        }
        
        var $button = $(this);
        $button.prop('disabled', true).text(xautoposter.strings.sharing);
        
        $.ajax({
            url: xautoposter.ajax_url,
            type: 'POST',
            data: {
                action: 'xautoposter_share_posts',
                posts: posts,
                nonce: xautoposter.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert(response.data.message || xautoposter.strings.error);
                }
            },
            error: function() {
                alert(xautoposter.strings.error);
            },
            complete: function() {
                $button.prop('disabled', false)
                    .text(xautoposter.strings.share_selected);
            }
        });
    });

    // Category filter change handler
    $('select[name="category"]').on('change', function() {
        $(this).closest('form').submit();
    });

    // Pagination click handler
    $('.tablenav-pages a').on('click', function(e) {
        e.preventDefault();
        var href = $(this).attr('href');
        if (href) {
            window.location.href = href;
        }
    });
});