(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Process queue now button
        $('.bbcmo-process-now').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var blogId = $button.data('blog') || 0;
            
            if ($button.hasClass('loading')) {
                return;
            }
            
            if (confirm(bbcmo_admin.process_confirmation || 'Process pending items now?')) {
                $button.addClass('loading').text(bbcmo_admin.processing_text);
                
                $.ajax({
                    type: 'POST',
                    url: bbcmo_admin.ajax_url,
                    data: {
                        action: 'bbcmo_process_now',
                        nonce: bbcmo_admin.nonce,
                        blog_id: blogId
                    },
                    success: function(response) {
                        $button.removeClass('loading').text('Process Now');
                        
                        if (response.success) {
                            alert(response.data.message || bbcmo_admin.process_success);
                        } else {
                            alert(response.data.message || bbcmo_admin.error_message);
                        }
                    },
                    error: function() {
                        $button.removeClass('loading').text('Process Now');
                        alert(bbcmo_admin.error_message);
                    }
                });
            }
        });
        
        // Retry failed button
        $('.bbcmo-retry-failed').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var blogId = $button.data('blog') || 0;
            
            if ($button.hasClass('loading')) {
                return;
            }
            
            if (confirm(bbcmo_admin.retry_confirmation || 'Retry failed items?')) {
                $button.addClass('loading').text(bbcmo_admin.processing_text);
                
                $.ajax({
                    type: 'POST',
                    url: bbcmo_admin.ajax_url,
                    data: {
                        action: 'bbcmo_retry_failed',
                        nonce: bbcmo_admin.nonce,
                        blog_id: blogId
                    },
                    success: function(response) {
                        $button.removeClass('loading').text('Retry Failed');
                        
                        if (response.success) {
                            alert(response.data.message || bbcmo_admin.retry_success);
                        } else {
                            alert(response.data.message || bbcmo_admin.error_message);
                        }
                    },
                    error: function() {
                        $button.removeClass('loading').text('Retry Failed');
                        alert(bbcmo_admin.error_message);
                    }
                });
            }
        });
        
    });
    
})(jQuery);