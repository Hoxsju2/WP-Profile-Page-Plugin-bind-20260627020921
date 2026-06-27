// SaaS Profile Dashboard Frontend JavaScript

jQuery(document).ready(function($) {
    
    // Handle wishlist item removal
    $(document).on('click', '.spd-remove-wishlist', function(e) {
        e.preventDefault();
        
        var productId = $(this).data('product-id');
        var item = $(this).closest('.spd-wishlist-item');
        
        $.ajax({
            url: spd_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'spd_remove_from_wishlist',
                product_id: productId,
                nonce: spd_ajax.nonce
            },
            beforeSend: function() {
                item.css('opacity', '0.5');
            },
            success: function(response) {
                if (response.success) {
                    item.fadeOut(300, function() {
                        $(this).remove();
                    });
                } else {
                    item.css('opacity', '1');
                    alert('Error removing item from wishlist');
                }
            },
            error: function() {
                item.css('opacity', '1');
                alert('Error removing item from wishlist');
            }
        });
    });
    
    // Handle form submissions with AJAX (optional enhancement)
    $('.spd-profile-form').on('submit', function(e) {
        var form = $(this);
        var submitBtn = form.find('button[type="submit"]');
        var originalText = submitBtn.text();
        
        submitBtn.text('Updating...').prop('disabled', true);
        
        // Re-enable button after 3 seconds to allow form submission
        setTimeout(function() {
            submitBtn.text(originalText).prop('disabled', false);
        }, 3000);
    });
    
    // Smooth transitions for tab content
    $('.spd-tab-content').on('DOMSubtreeModified', function() {
        $(this).hide().fadeIn(300);
    });
    
    // Handle responsive navigation
    if (window.innerWidth <= 768) {
        $('.spd-profile-nav').addClass('mobile-nav');
    }
    
    $(window).resize(function() {
        if (window.innerWidth <= 768) {
            $('.spd-profile-nav').addClass('mobile-nav');
        } else {
            $('.spd-profile-nav').removeClass('mobile-nav');
        }
    });
});
