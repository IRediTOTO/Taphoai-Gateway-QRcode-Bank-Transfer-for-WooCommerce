jQuery(document).ready(function($) {
    // Handle delete code action
    $('.bank-notify-delete-code').on('click', function(e) {
        e.preventDefault();
        
        var code = $(this).data('code');
        var $row = $(this).closest('tr');
        
        if (!confirm('Bạn có chắc chắn muốn xóa mã "' + code + '"?')) {
            return;
        }
        
        // Disable button
        $(this).css('opacity', '0.5').css('pointer-events', 'none');
        
        // Send AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bank_notify_delete_single_code',
                code: code,
                nonce: bankNotifyAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Fade out and remove row
                    $row.fadeOut(300, function() {
                        $(this).remove();
                    });
                    
                    // Show success message
                    $('.wrap h1').after(
                        '<div class="notice notice-success is-dismissible"><p>Đã xóa mã thành công!</p></div>'
                    );
                    
                    // Reload page after 1 second to update stats
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    alert('Lỗi: ' + (response.data.message || 'Không thể xóa mã'));
                }
            },
            error: function() {
                alert('Lỗi: Không thể kết nối đến server');
            }
        });
    });
    
    // Handle release code action
    $('.bank-notify-release-code').on('click', function(e) {
        e.preventDefault();
        
        var code = $(this).data('code');
        var $row = $(this).closest('tr');
        
        if (!confirm('Bạn có chắc chắn muốn giải phóng mã "' + code + '"?')) {
            return;
        }
        
        // Disable button
        $(this).css('opacity', '0.5').css('pointer-events', 'none');
        
        // Send AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bank_notify_release_single_code',
                code: code,
                nonce: bankNotifyAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    $('.wrap h1').after(
                        '<div class="notice notice-success is-dismissible"><p>Đã giải phóng mã thành công!</p></div>'
                    );
                    
                    // Reload page after 1 second
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    alert('Lỗi: ' + (response.data.message || 'Không thể giải phóng mã'));
                }
            },
            error: function() {
                alert('Lỗi: Không thể kết nối đến server');
            }
        });
    });
});
