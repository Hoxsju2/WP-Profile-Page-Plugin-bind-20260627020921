jQuery(document).ready(function($) {
    let currencyDropdownOpen = false;
    
    // Toggle currency dropdown
    $(document).on('click', '#spd-currency-toggle', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const dropdown = $('#spd-currency-dropdown');
        const button = $(this);
        
        if (currencyDropdownOpen) {
            dropdown.hide();
            button.removeClass('active');
            currencyDropdownOpen = false;
        } else {
            dropdown.show();
            button.addClass('active');
            currencyDropdownOpen = true;
            
            // Focus on search input
            $('#spd-currency-search').focus();
        }
    });
    
    // Close dropdown when clicking outside
    $(document).on('click', function(e) {
        if (currencyDropdownOpen && !$(e.target).closest('#spd-currency-selector').length) {
            $('#spd-currency-dropdown').hide();
            $('#spd-currency-toggle').removeClass('active');
            currencyDropdownOpen = false;
        }
    });
    
    // Currency search functionality
    $(document).on('input', '#spd-currency-search', function() {
        const searchTerm = $(this).val().toLowerCase();
        const options = $('.spd-currency-option');
        
        options.each(function() {
            const currency = $(this).data('currency').toLowerCase();
            const name = $(this).find('.spd-currency-info small').text().toLowerCase();
            
            if (currency.includes(searchTerm) || name.includes(searchTerm)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
    
    // Currency selection
    $(document).on('click', '.spd-currency-option', function(e) {
        e.preventDefault();
        
        if ($(this).hasClass('active')) {
            return; // Already selected
        }
        
        const selectedCurrency = $(this).data('currency');
        
        // Show loading state
        showCurrencyLoading();
        
        // AJAX request to update currency
        $.ajax({
            url: spd_currency_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'spd_update_user_currency',
                currency: selectedCurrency,
                save_permanently: false, // Session-based for now
                nonce: spd_currency_ajax.nonce
            },
            success: function(response) {
                const data = JSON.parse(response);
                
                if (data.success) {
                    // Update UI
                    updateCurrencyUI(data.currency, data.symbol);
                    
                    // Close dropdown
                    $('#spd-currency-dropdown').hide();
                    $('#spd-currency-toggle').removeClass('active');
                    currencyDropdownOpen = false;
                    
                    // Show confirmation message
                    showCurrencyNotification(data.message || 'Currency updated successfully!');
                    
                    // Reload page to update all prices
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showCurrencyError(data.message || 'Failed to update currency');
                    hideCurrencyLoading();
                }
            },
            error: function() {
                showCurrencyError('Network error. Please try again.');
                hideCurrencyLoading();
            }
        });
    });
    
    // Show loading state
    function showCurrencyLoading() {
        const button = $('#spd-currency-toggle');
        button.addClass('loading').html(
            '<span class="spd-loading-spinner">⟳</span> ' +
            '<span>Loading...</span>'
        );
    }
    
    // Hide loading state
    function hideCurrencyLoading() {
        const currentCurrency = $('.spd-currency-option.active').data('currency');
        const currentSymbol = $('.spd-currency-option.active .spd-currency-symbol').text();
        updateCurrencyUI(currentCurrency, currentSymbol);
    }
    
    // Update currency UI
    function updateCurrencyUI(currency, symbol) {
        const button = $('#spd-currency-toggle');
        button.removeClass('loading').html(
            '<span class="spd-currency-symbol">' + symbol + '</span>' +
            '<span class="spd-currency-code">' + currency + '</span>' +
            '<span class="spd-currency-arrow">▼</span>'
        );
        
        // Update active state in dropdown
        $('.spd-currency-option').removeClass('active').find('.spd-currency-check').remove();
        $('.spd-currency-option[data-currency="' + currency + '"]')
            .addClass('active')
            .append('<span class="spd-currency-check">✓</span>');
    }
    
    // Show currency notification
    function showCurrencyNotification(message) {
        // Remove existing notifications
        $('.spd-currency-notification').remove();
        
        // Create notification
        const notification = $('<div class="spd-currency-notification">' + message + '</div>');
        $('body').append(notification);
        
        // Show notification
        setTimeout(function() {
            notification.addClass('show');
        }, 100);
        
        // Hide notification
        setTimeout(function() {
            notification.removeClass('show');
            setTimeout(function() {
                notification.remove();
            }, 300);
        }, 3000);
    }
    
    // Show currency error
    function showCurrencyError(message) {
        showCurrencyNotification('❌ ' + message);
    }
    
    // Add notification styles
    const notificationStyles = `
        <style>
        .spd-currency-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #333;
            color: white;
            padding: 12px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            z-index: 10001;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease;
            max-width: 300px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .spd-currency-notification.show {
            opacity: 1;
            transform: translateX(0);
        }
        
        .spd-currency-btn.loading {
            opacity: 0.7;
            pointer-events: none;
        }
        
        .spd-loading-spinner {
            display: inline-block;
            animation: spd-spin 1s linear infinite;
        }
        
        @keyframes spd-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .spd-currency-notification {
                top: 10px;
                right: 10px;
                left: 10px;
                max-width: none;
            }
        }
        </style>
    `;
    
    // Add styles to head
    if (!$('#spd-currency-notification-styles').length) {
        $('head').append('<div id="spd-currency-notification-styles">' + notificationStyles + '</div>');
    }
    
    // Save currency preference permanently (optional feature)
    $(document).on('click', '.spd-save-currency-pref', function(e) {
        e.preventDefault();
        
        const selectedCurrency = $('.spd-currency-option.active').data('currency');
        
        if (!selectedCurrency) return;
        
        $.ajax({
            url: spd_currency_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'spd_update_user_currency',
                currency: selectedCurrency,
                save_permanently: true,
                nonce: spd_currency_ajax.nonce
            },
            success: function(response) {
                const data = JSON.parse(response);
                if (data.success) {
                    showCurrencyNotification('✅ Currency preference saved permanently!');
                } else {
                    showCurrencyError(data.message || 'Failed to save preference');
                }
            },
            error: function() {
                showCurrencyError('Network error. Please try again.');
            }
        });
    });
    
    // Keyboard navigation for dropdown
    $(document).on('keydown', function(e) {
        if (!currencyDropdownOpen) return;
        
        const options = $('.spd-currency-option:visible');
        const currentActive = options.filter('.active');
        let nextIndex = 0;
        
        if (e.keyCode === 27) { // Escape key
            $('#spd-currency-dropdown').hide();
            $('#spd-currency-toggle').removeClass('active').focus();
            currencyDropdownOpen = false;
        } else if (e.keyCode === 38) { // Up arrow
            e.preventDefault();
            if (currentActive.length) {
                nextIndex = Math.max(0, options.index(currentActive) - 1);
            }
            options.removeClass('hover-active').eq(nextIndex).addClass('hover-active');
        } else if (e.keyCode === 40) { // Down arrow
            e.preventDefault();
            if (currentActive.length) {
                nextIndex = Math.min(options.length - 1, options.index(currentActive) + 1);
            }
            options.removeClass('hover-active').eq(nextIndex).addClass('hover-active');
        } else if (e.keyCode === 13) { // Enter key
            e.preventDefault();
            const hoveredOption = options.filter('.hover-active');
            if (hoveredOption.length) {
                hoveredOption.click();
            }
        }
    });
});
