// SaaS Profile Dashboard Frontend JavaScript

jQuery(document).ready(function($) {
    
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

    // Helper method for notifications
    function showSpdNotice(container, type, message) {
        container.find('.spd-notice').remove();
        container.prepend('<div class="spd-notice spd-notice-' + type + '">' + message + '</div>');
    }

    // Handle AJAX Settings & Avatar Update Form
    $(document).on('submit', '#spd-settings-form', function(e) {
        e.preventDefault();
        var form = $(this);
        var submitBtn = form.find('input[type="submit"]');
        var originalText = submitBtn.val();
        var container = $('.spd-settings-form');
        
        submitBtn.val('Saving...').prop('disabled', true);
        
        var formData = new FormData(this);
        formData.append('action', 'spd_update_settings');
        formData.append('nonce', spd_ajax.nonce);
        
        $.ajax({
            url: spd_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                submitBtn.val(originalText).prop('disabled', false);
                if (response.success) {
                    showSpdNotice(container, 'success', response.data.message);
                    
                    if (response.data.avatar_updated) {
                        $('.spd-avatar-small').html(response.data.avatar_html_small + '<div class="spd-avatar-plus">+</div>');
                        $('.spd-avatar').html(response.data.avatar_html_large + '<div class="spd-avatar-plus" style="bottom: 5px; right: 5px;">+</div>');
                        $('.spd-avatar-preview img').replaceWith(response.data.avatar_html_preview);
                        form.find('input[type="file"]').val('');
                    }
                } else {
                    showSpdNotice(container, 'error', response.data.message || 'Error saving settings. [ERR_SET_01]');
                }
            },
            error: function() {
                submitBtn.val(originalText).prop('disabled', false);
                showSpdNotice(container, 'error', 'A network error occurred. Please try again. [ERR_NET_01]');
            }
        });
    });

    // Handle AJAX Profile Update Form (Name, Bio, etc)
    $(document).on('submit', '#spd-profile-update-form', function(e) {
        e.preventDefault();
        var form = $(this);
        var submitBtn = form.find('input[type="submit"]');
        var originalText = submitBtn.val();
        
        submitBtn.val('Updating...').prop('disabled', true);
        
        var formData = new FormData(this);
        formData.append('action', 'spd_update_profile');
        formData.append('nonce', spd_ajax.nonce);
        
        $.ajax({
            url: spd_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                submitBtn.val(originalText).prop('disabled', false);
                if (response.success) {
                    showSpdNotice(form, 'success', response.data.message);
                    if (response.data.display_name) {
                        $('.spd-user-details h3').text(response.data.display_name);
                        $('.spd-user-info h3').text(response.data.display_name);
                    }
                    if (response.data.user_email) {
                        $('.spd-user-details .spd-user-email').text(response.data.user_email);
                        $('.spd-user-info .spd-user-email').text(response.data.user_email);
                    }
                } else {
                    showSpdNotice(form, 'error', response.data.message || 'Error updating profile. [ERR_PROF_02]');
                }
            },
            error: function() {
                submitBtn.val(originalText).prop('disabled', false);
                showSpdNotice(form, 'error', 'A network error occurred. Please try again. [ERR_NET_02]');
            }
        });
    });

    // ----------------------------------------------------
    // NEW OTP PASSWORD CHANGE WORKFLOW 
    // ----------------------------------------------------

    // 1. Send OTP Request
    $(document).on('click', '#spd-init-pwd-change', function(e) {
        e.preventDefault();
        var btn = $(this);
        var container = $('.spd-settings-form');
        
        btn.text('Sending OTP...').prop('disabled', true);
        
        $.ajax({
            url: spd_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'spd_send_otp',
                nonce: spd_ajax.nonce
            },
            success: function(response) {
                btn.text('Change Password').prop('disabled', false);
                if (response.success) {
                    // Hide original buttons, show the hidden OTP form, and show success notice
                    $('#spd-settings-actions').slideUp();
                    $('#spd-pwd-otp-section').slideDown();
                    showSpdNotice($('#spd-pwd-otp-section'), 'success', response.data.message);
                } else {
                    showSpdNotice(container, 'error', response.data.message || 'Failed to send OTP. [ERR_OTP_03]');
                }
            },
            error: function() {
                btn.text('Change Password').prop('disabled', false);
                showSpdNotice(container, 'error', 'Network error while requesting OTP. [ERR_NET_03]');
            }
        });
    });

    // 2. Cancel OTP Form
    $(document).on('click', '#spd-cancel-pwd-change', function(e) {
        e.preventDefault();
        $('#spd-otp-password-form')[0].reset();
        $('#spd-pwd-otp-section').slideUp();
        $('#spd-settings-actions').slideDown();
        $('#spd-pwd-otp-section .spd-notice').remove();
    });

    // 3. Submit OTP Form and Change Password
    $(document).on('submit', '#spd-otp-password-form', function(e) {
        e.preventDefault();
        var form = $(this);
        var submitBtn = form.find('input[type="submit"]');
        var container = $('#spd-pwd-otp-section');
        
        var otp = $('#spd_otp_code').val();
        var newPwd = $('#spd_new_pwd').val();
        var confirmPwd = $('#spd_confirm_pwd').val();

        // JS Front-end validation before hitting the server
        if (newPwd !== confirmPwd) {
            showSpdNotice(container, 'error', 'Passwords do not match. [ERR_VAL_02]');
            return;
        }

        submitBtn.val('Verifying...').prop('disabled', true);

        $.ajax({
            url: spd_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'spd_verify_otp_change_pwd',
                nonce: spd_ajax.nonce,
                otp: otp,
                new_pwd: newPwd,
                confirm_pwd: confirmPwd
            },
            success: function(response) {
                submitBtn.val('Update Password').prop('disabled', false);
                
                if (response.success) {
                    // Reset form and UI
                    form[0].reset();
                    $('#spd-pwd-otp-section').slideUp();
                    $('#spd-settings-actions').slideDown();
                    
                    // Show success block globally on the settings tab
                    showSpdNotice($('.spd-settings-form'), 'success', response.data.message);
                } else {
                    showSpdNotice(container, 'error', response.data.message || 'Error updating password. [ERR_PWD_02]');
                }
            },
            error: function() {
                submitBtn.val('Update Password').prop('disabled', false);
                showSpdNotice(container, 'error', 'Network error. Could not verify OTP. [ERR_NET_04]');
            }
        });
    });
});
