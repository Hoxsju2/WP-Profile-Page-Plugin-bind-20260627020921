<?php

class SPD_Profile_Handler {
    
    private $profile_page_url = null;
    private $user_role_config = null;
    private $tabs_cache = null;
    
    public function __construct() {
        // Only essential hooks
        add_shortcode('saas_profile_dashboard', array($this, 'profile_dashboard_shortcode'));
        
        // AJAX handler for loading tab content
        add_action('wp_ajax_spd_load_tab_content', array($this, 'ajax_load_tab_content'));
        
        // Add shortcodes for tab content
        add_shortcode('spd_dashboard', array($this, 'dashboard_shortcode'));
        add_shortcode('spd_profile_form', array($this, 'profile_form_shortcode'));
        add_shortcode('spd_user_settings', array($this, 'user_settings_shortcode'));
        add_shortcode('spd_woo_orders', array($this, 'woo_orders_shortcode'));
        
        // Basic settings
        add_action('admin_init', array($this, 'admin_init'));
        
        // Add custom CSS for role-based colors
        add_action('wp_head', array($this, 'output_custom_css'));
    }
    
    public function admin_init() {
        register_setting('spd_settings', 'spd_profile_page_id');
        register_setting('spd_settings', 'spd_primary_color', array('default' => '#3498db'));
        register_setting('spd_settings', 'spd_secondary_color', array('default' => '#2c3e50'));
        register_setting('spd_settings', 'spd_accent_color', array('default' => '#e74c3c'));
        register_setting('spd_settings', 'spd_menu_layout', array('default' => 'sidebar'));
        register_setting('spd_settings', 'spd_quick_action_tabs', array('default' => array('profile', 'settings')));
    }
    
    private function get_user_role_config() {
        if ($this->user_role_config !== null) {
            return $this->user_role_config;
        }
        
        if (!is_user_logged_in()) {
            return null;
        }
        
        if (class_exists('SPD_Database')) {
            try {
                $user_id = get_current_user_id();
                $this->user_role_config = SPD_Database::get_user_role_config($user_id);
            } catch (Exception $e) {
                error_log('SPD Role Config Error: ' . $e->getMessage());
                $this->user_role_config = false;
            }
        } else {
            $this->user_role_config = false;
        }
        
        return $this->user_role_config;
    }
    
    private function get_role_setting($setting, $default = null) {
        $role_config = $this->get_user_role_config();
        
        if ($role_config && $role_config->is_active && isset($role_config->$setting)) {
            return $role_config->$setting;
        }
        
        return get_option('spd_' . $setting, $default);
    }
    
    private function get_default_tabs() {
        return array(
            (object) array(
                'id' => 1,
                'tab_name' => 'Dashboard',
                'tab_slug' => 'dashboard',
                'icon' => 'dashicons-dashboard',
                'icon_type' => 'dashicons',
                'content_type' => 'shortcode',
                'content_value' => '[spd_dashboard]',
                'is_active' => 1
            ),
            (object) array(
                'id' => 2,
                'tab_name' => 'Profile',
                'tab_slug' => 'profile',
                'icon' => 'dashicons-admin-users',
                'icon_type' => 'dashicons',
                'content_type' => 'shortcode',
                'content_value' => '[spd_profile_form]',
                'is_active' => 1
            ),
            (object) array(
                'id' => 3,
                'tab_name' => 'Settings',
                'tab_slug' => 'settings',
                'icon' => 'dashicons-admin-settings',
                'icon_type' => 'dashicons',
                'content_type' => 'shortcode',
                'content_value' => '[spd_user_settings]',
                'is_active' => 1
            ),
            (object) array(
                'id' => 4,
                'tab_name' => 'Orders',
                'tab_slug' => 'orders',
                'icon' => 'dashicons-cart',
                'icon_type' => 'dashicons',
                'content_type' => 'shortcode',
                'content_value' => '[spd_woo_orders]',
                'is_active' => 1
            )
        );
    }
    
    private function get_allowed_tabs_for_user() {
        // Use simple caching to avoid repeated calls
        if ($this->tabs_cache !== null) {
            return $this->tabs_cache;
        }
        
        $role_config = $this->get_user_role_config();
        
        // If user has role configuration with specific allowed tabs
        if ($role_config && $role_config->is_active && !empty($role_config->allowed_tabs)) {
            $all_tabs = $this->get_user_tabs();
            $allowed_tabs = array();
            
            foreach ($all_tabs as $tab) {
                if (in_array($tab->tab_slug, $role_config->allowed_tabs)) {
                    $allowed_tabs[] = $tab;
                }
            }
            
            // If no tabs match, fallback to dashboard only
            if (empty($allowed_tabs)) {
                $this->tabs_cache = array($this->get_default_tabs()[0]); // Dashboard only
                return $this->tabs_cache;
            }
            
            $this->tabs_cache = $allowed_tabs;
            return $this->tabs_cache;
        }
        
        // Default: return all available tabs
        $this->tabs_cache = $this->get_user_tabs();
        return $this->tabs_cache;
    }
    
    private function get_user_tabs() {
        // Try to get from database first
        if (class_exists('SPD_Database')) {
            try {
                $db_tabs = SPD_Database::get_tabs();
                if (!empty($db_tabs)) {
                    return $db_tabs;
                }
            } catch (Exception $e) {
                error_log('SPD Database Error: ' . $e->getMessage());
            }
        }
        
        // Fallback to default tabs
        return $this->get_default_tabs();
    }
    
    public function output_custom_css() {
        $primary_color = $this->get_role_setting('primary_color', get_option('spd_primary_color', '#3498db'));
        $secondary_color = $this->get_role_setting('secondary_color', get_option('spd_secondary_color', '#2c3e50'));
        $accent_color = get_option('spd_accent_color', '#e74c3c');
        
        echo '<style id="spd-custom-colors">
        :root {
            --spd-primary-color: ' . esc_attr($primary_color) . ';
            --spd-secondary-color: ' . esc_attr($secondary_color) . ';
            --spd-accent-color: ' . esc_attr($accent_color) . ';
        }
        </style>';
    }
    
    // AJAX handler for loading tab content
    public function ajax_load_tab_content() {
        if (!wp_verify_nonce($_POST['nonce'], 'spd_nonce') || !is_user_logged_in()) {
            wp_die(json_encode(array('success' => false)));
        }
        
        $tab_slug = sanitize_text_field($_POST['tab_slug']);
        
        // Verify user is allowed to access this tab
        $allowed_tabs = $this->get_allowed_tabs_for_user();
        $allowed_slugs = array_map(function($tab) { return $tab->tab_slug; }, $allowed_tabs);
        
        if (!in_array($tab_slug, $allowed_slugs)) {
            wp_die(json_encode(array('success' => false, 'message' => 'Access denied')));
        }
        
        $content = $this->load_tab_content($tab_slug);
        
        wp_die(json_encode(array(
            'success' => true,
            'data' => array('content' => $content)
        )));
    }
    
    public function profile_dashboard_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<div class="spd-login-required">
                <p>Please log in to access your profile dashboard.</p>
                <p><a href="' . wp_login_url(get_permalink()) . '" class="spd-btn spd-btn-primary">Login</a></p>
            </div>';
        }
        
        global $post;
        if ($post && $post->ID) {
            update_option('spd_profile_page_id', $post->ID);
        }
        
        $current_user = wp_get_current_user();
        $user_id = get_current_user_id();
        
        // Get role-based settings
        $menu_layout = $this->get_role_setting('menu_layout', get_option('spd_menu_layout', 'sidebar'));
        $tabs = $this->get_allowed_tabs_for_user();
        
        // Determine default tab from role config
        $role_config = $this->get_user_role_config();
        $default_tab = ($role_config && $role_config->is_active && !empty($role_config->default_tab)) 
            ? $role_config->default_tab 
            : 'dashboard';
        
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : $default_tab;
        
        // Validate current tab exists and user has access
        $valid_tabs = array_map(function($tab) { return $tab->tab_slug; }, $tabs);
        if (!in_array($current_tab, $valid_tabs)) {
            $current_tab = !empty($valid_tabs) ? $valid_tabs[0] : 'dashboard';
        }
        
        // Enqueue assets
        wp_enqueue_style('spd-frontend');
        wp_enqueue_script('spd-frontend');
        
        // Localize script for AJAX
        wp_localize_script('spd-frontend', 'spd_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('spd_nonce'),
        ));
        
        ob_start();
        ?>
        <div class="spd-profile-wrapper spd-layout-<?php echo esc_attr($menu_layout); ?>">
            <div class="spd-profile-container">
                
                <?php if ($menu_layout === 'top') : ?>
                    <!-- Top Menu Layout -->
                    <div class="spd-profile-header-top">
                        <div class="spd-user-info-top">
                            <div class="spd-avatar-small">
                                <?php echo get_avatar($user_id, 50); ?>
                            </div>
                            <div class="spd-user-details">
                                <h3><?php echo esc_html($current_user->display_name); ?></h3>
                                <p class="spd-user-email"><?php echo esc_html($current_user->user_email); ?></p>
                            </div>
                            <div class="spd-logout-top">
                                <a href="<?php echo wp_logout_url(get_permalink()); ?>" class="spd-logout-link">
                                    <span class="dashicons dashicons-exit"></span>
                                    Sign Out
                                </a>
                            </div>
                        </div>
                        
                        <nav class="spd-profile-nav-top">
                            <ul>
                                <?php foreach ($tabs as $tab) : ?>
                                    <li class="<?php echo ($current_tab === $tab->tab_slug) ? 'active' : ''; ?>">
                                        <a href="#" data-tab="<?php echo esc_attr($tab->tab_slug); ?>" class="spd-tab-link">
                                            <?php if ($tab->icon && $tab->icon_type === 'dashicons') : ?>
                                                <span class="dashicons <?php echo esc_attr($tab->icon); ?>"></span>
                                            <?php endif; ?>
                                            <span><?php echo esc_html($tab->tab_name); ?></span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </nav>
                    </div>
                    
                    <!-- Main Content for Top Layout -->
                    <div class="spd-profile-main-full">
                        <div class="spd-profile-content-full">
                            <div id="spd-tab-content" class="spd-tab-content">
                                <?php echo $this->load_tab_content($current_tab); ?>
                            </div>
                        </div>
                    </div>
                    
                <?php else : ?>
                    <!-- Sidebar Layout -->
                    <div class="spd-profile-sidebar">
                        <div class="spd-profile-header">
                            <div class="spd-avatar">
                                <?php echo get_avatar($user_id, 80); ?>
                            </div>
                            <div class="spd-user-info">
                                <h3><?php echo esc_html($current_user->display_name); ?></h3>
                                <p class="spd-user-email"><?php echo esc_html($current_user->user_email); ?></p>
                            </div>
                        </div>
                        
                        <nav class="spd-profile-nav">
                            <ul>
                                <?php foreach ($tabs as $tab) : ?>
                                    <li class="<?php echo ($current_tab === $tab->tab_slug) ? 'active' : ''; ?>">
                                        <a href="#" data-tab="<?php echo esc_attr($tab->tab_slug); ?>" class="spd-tab-link">
                                            <?php if ($tab->icon && $tab->icon_type === 'dashicons') : ?>
                                                <span class="dashicons <?php echo esc_attr($tab->icon); ?>"></span>
                                            <?php endif; ?>
                                            <span><?php echo esc_html($tab->tab_name); ?></span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </nav>
                        
                        <div class="spd-sidebar-footer">
                            <a href="<?php echo wp_logout_url(get_permalink()); ?>" class="spd-logout-link">
                                <span class="dashicons dashicons-exit"></span>
                                Sign Out
                            </a>
                        </div>
                    </div>
                    
                    <div class="spd-profile-main">
                        <div class="spd-profile-header-main">
                            <h1 id="spd-current-tab-title">
                                <?php
                                $current_tab_name = 'Dashboard';
                                foreach ($tabs as $tab) {
                                    if ($tab->tab_slug === $current_tab) {
                                        $current_tab_name = $tab->tab_name;
                                        break;
                                    }
                                }
                                echo esc_html($current_tab_name);
                                ?>
                            </h1>
                        </div>
                        
                        <div class="spd-profile-content">
                            <div id="spd-tab-content" class="spd-tab-content">
                                <?php echo $this->load_tab_content($current_tab); ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.spd-tab-link').forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const tabSlug = this.getAttribute('data-tab');
                    
                    // Update active tab
                    document.querySelectorAll('.spd-profile-nav li, .spd-profile-nav-top li').forEach(function(li) {
                        li.classList.remove('active');
                    });
                    this.parentElement.classList.add('active');
                    
                    // Load content
                    loadTabContent(tabSlug);
                    
                    // Update title (sidebar only)
                    const titleElement = document.getElementById('spd-current-tab-title');
                    if (titleElement) {
                        titleElement.textContent = this.querySelector('span:last-child').textContent;
                    }
                });
            });
        });

        function loadTabContent(tabSlug) {
            const contentArea = document.getElementById('spd-tab-content');
            if (!contentArea || typeof spd_ajax === 'undefined') return;
            
            contentArea.innerHTML = '<div class="spd-loading"><p>Loading...</p></div>';
            
            const formData = new FormData();
            formData.append('action', 'spd_load_tab_content');
            formData.append('tab_slug', tabSlug);
            formData.append('nonce', spd_ajax.nonce);
            
            fetch(spd_ajax.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    contentArea.innerHTML = data.data.content;
                } else {
                    contentArea.innerHTML = '<p>Access denied or error loading content.</p>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                contentArea.innerHTML = '<p>Error loading content.</p>';
            });
        }
        </script>
        <?php
        return ob_get_clean();
    }
    
    private function load_tab_content($tab_slug) {
        // Try database first
        if (class_exists('SPD_Database')) {
            try {
                $tab = SPD_Database::get_tab($tab_slug);
                if ($tab && $tab->content_type === 'shortcode') {
                    return do_shortcode($tab->content_value);
                } elseif ($tab && $tab->content_type === 'url') {
                    return '<iframe src="' . esc_url($tab->content_value) . '" style="width: 100%; height: 600px; border: none;"></iframe>';
                }
            } catch (Exception $e) {
                error_log('SPD load_tab_content Error: ' . $e->getMessage());
            }
        }
        
        // Fallback to built-in shortcodes
        switch ($tab_slug) {
            case 'dashboard':
                return $this->dashboard_shortcode(array());
            case 'profile':
                return $this->profile_form_shortcode(array());
            case 'settings':
                return $this->user_settings_shortcode(array());
            case 'orders':
                return $this->woo_orders_shortcode(array());
            default:
                return $this->dashboard_shortcode(array());
        }
    }
    
    public function dashboard_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>Please log in to view your dashboard.</p>';
        }
        
        $current_user = wp_get_current_user();
        $registration_date = date('F j, Y', strtotime($current_user->user_registered));
        
        $welcome_name = $current_user->first_name ?: $current_user->display_name;
        
        // Get role-based welcome message
        $role_config = $this->get_user_role_config();
        if ($role_config && !empty($role_config->custom_welcome_message)) {
            $custom_welcome = str_replace('{user_name}', $welcome_name, $role_config->custom_welcome_message);
        } else {
            $custom_welcome = 'Welcome back, ' . $welcome_name . '!';
        }
        
        // Get quick action tabs from role config
        $quick_action_tabs = $this->get_role_setting('quick_action_tabs', array('profile', 'settings'));
        
        ob_start();
        ?>
        <div class="spd-dashboard-widgets">
            <div class="spd-widget">
                <h3><?php echo esc_html($custom_welcome); ?></h3>
                <p>Member since <?php echo esc_html($registration_date); ?></p>
            </div>
            
            <?php if (!empty($quick_action_tabs) && is_array($quick_action_tabs)) : ?>
            <div class="spd-widget">
                <h3>Quick Actions</h3>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <?php
                    $all_tabs = $this->get_allowed_tabs_for_user();
                    foreach ($all_tabs as $tab) {
                        if (in_array($tab->tab_slug, $quick_action_tabs)) {
                            echo '<a href="#" data-tab="' . esc_attr($tab->tab_slug) . '" class="spd-btn spd-btn-primary spd-btn-sm spd-quick-action">' . esc_html($tab->tab_name) . '</a>';
                        }
                    }
                    ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle quick action buttons
            document.querySelectorAll('.spd-quick-action').forEach(function(button) {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const tabSlug = this.getAttribute('data-tab');
                    
                    // Update navigation for both layouts
                    document.querySelectorAll('.spd-profile-nav li, .spd-profile-nav-top li').forEach(function(li) {
                        li.classList.remove('active');
                    });
                    
                    const targetTab = document.querySelector('.spd-tab-link[data-tab="' + tabSlug + '"]');
                    if (targetTab) {
                        targetTab.parentElement.classList.add('active');
                        
                        // Load content
                        if (typeof loadTabContent === 'function') {
                            loadTabContent(tabSlug);
                        }
                        
                        // Update title (only for sidebar layout)
                        const titleElement = document.getElementById('spd-current-tab-title');
                        if (titleElement) {
                            titleElement.textContent = targetTab.querySelector('span:last-child').textContent;
                        }
                    }
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    public function profile_form_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>Please log in to edit your profile.</p>';
        }
        
        $current_user = wp_get_current_user();
        
        // Handle form submission
        $message = '';
        if (isset($_POST['spd_update_profile']) && wp_verify_nonce($_POST['spd_profile_nonce'], 'spd_update_profile')) {
            $first_name = sanitize_text_field($_POST['first_name']);
            $last_name = sanitize_text_field($_POST['last_name']);
            $email = sanitize_email($_POST['email']);
            $description = sanitize_textarea_field($_POST['description']);
            
            $updated = wp_update_user(array(
                'ID' => get_current_user_id(),
                'first_name' => $first_name,
                'last_name' => $last_name,
                'user_email' => $email,
                'description' => $description,
            ));
            
            if (!is_wp_error($updated)) {
                $message = '<div class="spd-notice spd-notice-success">Profile updated successfully!</div>';
                $current_user = wp_get_current_user(); // Refresh
            } else {
                $message = '<div class="spd-notice spd-notice-error">Error updating profile.</div>';
            }
        }
        
        return $message . '<form method="post" class="spd-profile-form">
            ' . wp_nonce_field('spd_update_profile', 'spd_profile_nonce', true, false) . '
            
            <div class="spd-form-row">
                <label for="first_name">First Name</label>
                <input type="text" id="first_name" name="first_name" value="' . esc_attr($current_user->first_name) . '">
            </div>
            
            <div class="spd-form-row">
                <label for="last_name">Last Name</label>
                <input type="text" id="last_name" name="last_name" value="' . esc_attr($current_user->last_name) . '">
            </div>
            
            <div class="spd-form-row">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" value="' . esc_attr($current_user->user_email) . '" required>
            </div>
            
            <div class="spd-form-row">
                <label for="description">Bio/Description</label>
                <textarea id="description" name="description" rows="4">' . esc_textarea($current_user->description) . '</textarea>
            </div>
            
            <div class="spd-form-actions">
                <input type="submit" name="spd_update_profile" value="Update Profile" class="spd-btn spd-btn-primary">
            </div>
        </form>';
    }
    
    public function user_settings_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>Please log in to view your settings.</p>';
        }
        
        return '<div class="spd-settings-form">
            <h3>Account Settings</h3>
            <p>Settings management will be implemented here.</p>
            <p><a href="' . wp_lostpassword_url() . '" class="spd-btn spd-btn-secondary">Change Password</a></p>
        </div>';
    }
    
    public function woo_orders_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>Please log in to view your orders.</p>';
        }
        
        if (!class_exists('WooCommerce')) {
            return '<p>WooCommerce is not installed.</p>';
        }
        
        try {
            $user_id = get_current_user_id();
            $orders = wc_get_orders(array(
                'customer_id' => $user_id,
                'limit' => 10,
                'orderby' => 'date',
                'order' => 'DESC',
            ));
            
            if (!empty($orders)) {
                $output = '<div class="spd-woo-orders"><div class="spd-orders-table">';
                foreach ($orders as $order) {
                    $output .= '<div class="spd-order-row">
                        <div><strong>#' . $order->get_id() . '</strong></div>
                        <div>' . $order->get_date_created()->date('M j, Y') . '</div>
                        <div><span class="status-' . $order->get_status() . '">' . ucfirst($order->get_status()) . '</span></div>
                        <div>' . wc_price($order->get_total()) . '</div>
                    </div>';
                }
                $output .= '</div></div>';
                return $output;
            } else {
                return '<div class="spd-woo-orders" style="text-align: center; padding: 2rem;">
                    <p>You have no orders yet.</p>
                    <a href="' . wc_get_page_permalink('shop') . '" class="spd-btn spd-btn-primary">Start Shopping</a>
                </div>';
            }
        } catch (Exception $e) {
            error_log('SPD WooCommerce Error: ' . $e->getMessage());
            return '<div class="spd-woo-orders"><p>Error loading orders. Please try again.</p></div>';
        }
    }
}
