<?php

class SPD_Profile_Handler {
    
    private $profile_page_url = null;
    private $user_role_config = null;
    private $tabs_cache = null;
    
    public function __construct() {
        add_shortcode('saas_profile_dashboard', array($this, 'profile_dashboard_shortcode'));
        
        // AJAX hooks
        add_action('wp_ajax_spd_load_tab_content', array($this, 'ajax_load_tab_content'));
        add_action('wp_ajax_spd_update_settings', array($this, 'ajax_update_settings'));
        add_action('wp_ajax_spd_update_profile', array($this, 'ajax_update_profile'));
        add_action('wp_ajax_spd_send_otp', array($this, 'ajax_send_otp'));
        add_action('wp_ajax_spd_verify_otp_change_pwd', array($this, 'ajax_verify_otp_change_pwd'));
        
        add_shortcode('spd_dashboard', array($this, 'dashboard_shortcode'));
        add_shortcode('spd_profile_form', array($this, 'profile_form_shortcode'));
        add_shortcode('spd_user_settings', array($this, 'user_settings_shortcode'));
        add_shortcode('spd_woo_orders', array($this, 'woo_orders_shortcode'));
        
        add_action('admin_init', array($this, 'admin_init'));
        add_action('wp_head', array($this, 'output_custom_css'));
    }
    
    public function admin_init() {
        register_setting('spd_settings', 'spd_profile_page_id');
        register_setting('spd_settings', 'spd_primary_color', array('default' => '#3498db'));
        register_setting('spd_settings', 'spd_secondary_color', array('default' => '#2c3e50'));
        register_setting('spd_settings', 'spd_accent_color', array('default' => '#e74c3c'));
        register_setting('spd_settings', 'spd_menu_layout', array('default' => 'sidebar'));
        register_setting('spd_settings', 'spd_quick_action_tabs', array('default' => array('profile', 'settings')));
        
        register_setting('spd_settings', 'spd_header_bg_color', array('default' => '#2c3e50'));
        register_setting('spd_settings', 'spd_menu_text_color', array('default' => '#ecf0f1'));
        register_setting('spd_settings', 'spd_menu_hover_color', array('default' => '#3498db'));
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
        
        if ($role_config && $role_config->is_active && isset($role_config->$setting) && !empty($role_config->$setting)) {
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
        if ($this->tabs_cache !== null) {
            return $this->tabs_cache;
        }
        
        $role_config = $this->get_user_role_config();
        
        if ($role_config && $role_config->is_active && !empty($role_config->allowed_tabs)) {
            $all_tabs = $this->get_user_tabs();
            $allowed_tabs = array();
            
            foreach ($all_tabs as $tab) {
                if (in_array($tab->tab_slug, $role_config->allowed_tabs)) {
                    $allowed_tabs[] = $tab;
                }
            }
            
            if (empty($allowed_tabs)) {
                $this->tabs_cache = array($this->get_default_tabs()[0]);
                return $this->tabs_cache;
            }
            
            $this->tabs_cache = $allowed_tabs;
            return $this->tabs_cache;
        }
        
        $this->tabs_cache = $this->get_user_tabs();
        return $this->tabs_cache;
    }
    
    private function get_user_tabs() {
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
        
        return $this->get_default_tabs();
    }
    
    public function get_user_custom_avatar($user_id, $size = 80) {
        $avatar_id = get_user_meta($user_id, 'spd_custom_avatar', true);
        if ($avatar_id) {
            $image_url = wp_get_attachment_image_url($avatar_id, array($size, $size));
            if ($image_url) {
                return '<img src="' . esc_url($image_url) . '" width="' . esc_attr($size) . '" height="' . esc_attr($size) . '" alt="Profile Picture" class="avatar">';
            }
        }
        return get_avatar($user_id, $size);
    }
    
    public function output_custom_css() {
        $primary_color = $this->get_role_setting('primary_color', get_option('spd_primary_color', '#3498db'));
        $secondary_color = $this->get_role_setting('secondary_color', get_option('spd_secondary_color', '#2c3e50'));
        $accent_color = get_option('spd_accent_color', '#e74c3c');
        
        $header_bg = get_option('spd_header_bg_color', '#2c3e50');
        $menu_text = get_option('spd_menu_text_color', '#ecf0f1');
        $menu_hover = get_option('spd_menu_hover_color', '#3498db');
        
        echo '<style id="spd-custom-colors">
        :root {
            --spd-primary-color: ' . esc_attr($primary_color) . ';
            --spd-secondary-color: ' . esc_attr($secondary_color) . ';
            --spd-accent-color: ' . esc_attr($accent_color) . ';
            --spd-header-bg-color: ' . esc_attr($header_bg) . ';
            --spd-menu-text-color: ' . esc_attr($menu_text) . ';
            --spd-menu-hover-color: ' . esc_attr($menu_hover) . ';
        }
        </style>';
    }
    
    public function ajax_load_tab_content() {
        check_ajax_referer('spd_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => esc_html__('Session expired. Please log in again. [ERR_AUTH_01]', 'saas-profile-dashboard')));
        }
        
        $tab_slug = sanitize_text_field(wp_unslash($_POST['tab_slug']));
        $allowed_tabs = $this->get_allowed_tabs_for_user();
        $allowed_slugs = array_map(function($tab) { return $tab->tab_slug; }, $allowed_tabs);
        
        if (!in_array($tab_slug, $allowed_slugs)) {
            wp_send_json_error(array('message' => esc_html__('Access denied [ERR_DENIED_01]', 'saas-profile-dashboard')));
        }
        
        $content = $this->load_tab_content($tab_slug);
        
        wp_send_json_success(array('content' => $content));
    }
    
    public function ajax_update_profile() {
        check_ajax_referer('spd_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => esc_html__('Session expired. [ERR_AUTH_02]', 'saas-profile-dashboard')));
        }
        
        $first_name = sanitize_text_field(wp_unslash($_POST['first_name']));
        $last_name = sanitize_text_field(wp_unslash($_POST['last_name']));
        $email = sanitize_email(wp_unslash($_POST['email']));
        $description = sanitize_textarea_field(wp_unslash($_POST['description']));
        
        $updated = wp_update_user(array(
            'ID' => get_current_user_id(),
            'first_name' => $first_name,
            'last_name' => $last_name,
            'user_email' => $email,
            'description' => $description,
        ));
        
        if (!is_wp_error($updated)) {
            $current_user = get_userdata(get_current_user_id());
            wp_send_json_success(array(
                'message' => esc_html__('Profile updated successfully!', 'saas-profile-dashboard'),
                'display_name' => $current_user->display_name,
                'user_email' => $current_user->user_email
            ));
        } else {
            wp_send_json_error(array('message' => esc_html__('Error updating profile: ', 'saas-profile-dashboard') . $updated->get_error_message() . ' [ERR_PROF_01]'));
        }
    }
    
    public function ajax_update_settings() {
        check_ajax_referer('spd_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => esc_html__('Session expired. [ERR_AUTH_03]', 'saas-profile-dashboard')));
        }
        
        $user_id = get_current_user_id();
        $message = esc_html__('Settings saved successfully.', 'saas-profile-dashboard');
        $avatar_updated = false;
        
        if (!empty($_FILES['spd_avatar']['name'])) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            
            $file = $_FILES['spd_avatar'];
            
            $allowed_mime_types = array('image/jpeg', 'image/png', 'image/gif');
            if (!in_array($file['type'], $allowed_mime_types)) {
                wp_send_json_error(array('message' => esc_html__('Invalid file type. Only JPG, PNG, and GIF are allowed. [ERR_UPLOAD_01]', 'saas-profile-dashboard')));
            }
            
            $upload = wp_handle_upload($file, array('test_form' => false));
            
            if (isset($upload['error'])) {
                wp_send_json_error(array('message' => esc_html__('Error uploading image: ', 'saas-profile-dashboard') . $upload['error'] . ' [ERR_UPLOAD_02]'));
            } else {
                $attachment = array(
                    'post_mime_type' => $upload['type'],
                    'post_title'     => sanitize_file_name($file['name']),
                    'post_content'   => '',
                    'post_status'    => 'inherit'
                );
                
                $attachment_id = wp_insert_attachment($attachment, $upload['file']);
                
                if (!is_wp_error($attachment_id)) {
                    $attach_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
                    wp_update_attachment_metadata($attachment_id, $attach_data);
                    
                    update_user_meta($user_id, 'spd_custom_avatar', $attachment_id);
                    $message = esc_html__('Profile picture updated successfully!', 'saas-profile-dashboard');
                    $avatar_updated = true;
                } else {
                    wp_send_json_error(array('message' => esc_html__('Database error saving image. [ERR_DB_01]', 'saas-profile-dashboard')));
                }
            }
        }
        
        wp_send_json_success(array(
            'message' => $message,
            'avatar_updated' => $avatar_updated,
            'avatar_html_small' => $this->get_user_custom_avatar($user_id, 60),
            'avatar_html_large' => $this->get_user_custom_avatar($user_id, 80),
            'avatar_html_preview' => $this->get_user_custom_avatar($user_id, 100),
        ));
    }

    public function ajax_send_otp() {
        check_ajax_referer('spd_nonce', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => esc_html__('Session expired. Please reload. [ERR_AUTH_04]', 'saas-profile-dashboard')));
        }

        $user_id = get_current_user_id();
        $user = get_userdata($user_id);
        
        $otp = sprintf("%06d", mt_rand(1, 999999));
        
        update_user_meta($user_id, 'spd_pwd_otp', wp_hash_password($otp));
        update_user_meta($user_id, 'spd_pwd_otp_expiry', time() + (15 * 60));

        $subject = esc_html__('Your Password Reset OTP', 'saas-profile-dashboard');
        $message = sprintf(esc_html__("Hello %s,\n\nYour One-Time Password (OTP) to change your password is: %s\n\nThis code will expire in 15 minutes. If you did not request this, please ignore this email.", 'saas-profile-dashboard'), $user->display_name, $otp);

        $sent = wp_mail($user->user_email, $subject, $message);

        if ($sent) {
            wp_send_json_success(array('message' => esc_html__('OTP sent to your email successfully.', 'saas-profile-dashboard')));
        } else {
            wp_send_json_error(array('message' => esc_html__('Failed to send OTP email. Please try again. [ERR_MAIL_01]', 'saas-profile-dashboard')));
        }
    }

    public function ajax_verify_otp_change_pwd() {
        check_ajax_referer('spd_nonce', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => esc_html__('Session expired. Please reload. [ERR_AUTH_05]', 'saas-profile-dashboard')));
        }

        $user_id = get_current_user_id();
        $otp_input = sanitize_text_field(wp_unslash($_POST['otp']));
        $new_pwd = wp_unslash($_POST['new_pwd']);
        $confirm_pwd = wp_unslash($_POST['confirm_pwd']);

        if (empty($otp_input) || empty($new_pwd)) {
            wp_send_json_error(array('message' => esc_html__('All fields are required. [ERR_VAL_01]', 'saas-profile-dashboard')));
        }

        if ($new_pwd !== $confirm_pwd) {
            wp_send_json_error(array('message' => esc_html__('New passwords do not match. [ERR_PWD_01]', 'saas-profile-dashboard')));
        }

        $saved_otp_hash = get_user_meta($user_id, 'spd_pwd_otp', true);
        $expiry = get_user_meta($user_id, 'spd_pwd_otp_expiry', true);

        if (!$saved_otp_hash || time() > $expiry) {
            wp_send_json_error(array('message' => esc_html__('OTP has expired or is invalid. Please request a new one. [ERR_OTP_01]', 'saas-profile-dashboard')));
        }

        if (!wp_check_password($otp_input, $saved_otp_hash)) {
            wp_send_json_error(array('message' => esc_html__('Incorrect OTP code entered. [ERR_OTP_02]', 'saas-profile-dashboard')));
        }

        wp_set_password($new_pwd, $user_id);
        
        wp_clear_auth_cookie();
        wp_set_auth_cookie($user_id, false, is_ssl());
        wp_set_current_user($user_id);

        delete_user_meta($user_id, 'spd_pwd_otp');
        delete_user_meta($user_id, 'spd_pwd_otp_expiry');

        wp_send_json_success(array('message' => esc_html__('Password successfully updated!', 'saas-profile-dashboard')));
    }
    
    public function profile_dashboard_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<div class="spd-login-required">
                <p>' . esc_html__('Please log in to access your profile dashboard.', 'saas-profile-dashboard') . '</p>
                <p><a href="' . esc_url(wp_login_url(get_permalink())) . '" class="spd-btn spd-btn-primary">' . esc_html__('Login', 'saas-profile-dashboard') . '</a></p>
            </div>';
        }
        
        global $post;
        if ($post && $post->ID) {
            update_option('spd_profile_page_id', $post->ID);
        }
        
        $current_user = wp_get_current_user();
        $user_id = get_current_user_id();
        
        $menu_layout = $this->get_role_setting('menu_layout', get_option('spd_menu_layout', 'top')); 
        $tabs = $this->get_allowed_tabs_for_user();
        
        $role_config = $this->get_user_role_config();
        $default_tab = ($role_config && $role_config->is_active && !empty($role_config->default_tab)) 
            ? $role_config->default_tab 
            : 'dashboard';
        
        $current_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : $default_tab;
        
        $valid_tabs = array_map(function($tab) { return $tab->tab_slug; }, $tabs);
        if (!in_array($current_tab, $valid_tabs)) {
            $current_tab = !empty($valid_tabs) ? $valid_tabs[0] : 'dashboard';
        }
        
        wp_enqueue_style('spd-frontend');
        wp_enqueue_script('spd-frontend');
        
        wp_localize_script('spd-frontend', 'spd_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('spd_nonce'),
        ));
        
        ob_start();
        ?>
        <div class="spd-profile-wrapper spd-layout-<?php echo esc_attr($menu_layout); ?>">
            <div class="spd-profile-container">
                
                <?php if ($menu_layout === 'top') : ?>
                    <div class="spd-profile-header-top">
                        <div class="spd-user-info-top">
                            <div class="spd-user-profile-group">
                                <div class="spd-avatar-small" onclick="document.querySelector('.spd-tab-link[data-tab=\'settings\']').click();">
                                    <?php echo $this->get_user_custom_avatar($user_id, 60); ?>
                                    <div class="spd-avatar-plus">+</div>
                                </div>
                                <div class="spd-user-details">
                                    <h3><?php echo esc_html($current_user->display_name); ?></h3>
                                    <p class="spd-user-email"><?php echo esc_html($current_user->user_email); ?></p>
                                </div>
                            </div>
                            
                            <div class="spd-logout-top">
                                <a href="<?php echo esc_url(wp_logout_url(get_permalink())); ?>" class="spd-logout-link">
                                    <span class="dashicons dashicons-exit"></span>
                                    <?php esc_html_e('Sign Out', 'saas-profile-dashboard'); ?>
                                </a>
                            </div>
                        </div>
                        
                        <nav class="spd-profile-nav-top">
                            <ul>
                                <?php foreach ($tabs as $tab) : ?>
                                    <?php
                                    $is_link = ($tab->content_type === 'link');
                                    $href = $is_link ? esc_url($tab->content_value) : '#';
                                    $link_class = $is_link ? 'spd-tab-direct-link' : 'spd-tab-link';
                                    $data_attr = $is_link ? '' : 'data-tab="' . esc_attr($tab->tab_slug) . '"';
                                    ?>
                                    <li class="<?php echo ($current_tab === $tab->tab_slug && !$is_link) ? 'active' : ''; ?>">
                                        <a href="<?php echo $href; ?>" <?php echo $data_attr; ?> class="<?php echo $link_class; ?>">
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
                    
                    <div class="spd-profile-main-full">
                        <div class="spd-profile-content-full">
                            <div id="spd-tab-content" class="spd-tab-content">
                                <?php echo $this->load_tab_content($current_tab); ?>
                            </div>
                        </div>
                    </div>
                    
                <?php else : ?>
                    <div class="spd-profile-sidebar">
                        <div class="spd-profile-header">
                            <div class="spd-avatar" onclick="document.querySelector('.spd-tab-link[data-tab=\'settings\']').click();" style="cursor:pointer;">
                                <?php echo $this->get_user_custom_avatar($user_id, 80); ?>
                                <div class="spd-avatar-plus" style="bottom: 5px; right: 5px;">+</div>
                            </div>
                            <div class="spd-user-info">
                                <h3><?php echo esc_html($current_user->display_name); ?></h3>
                                <p class="spd-user-email"><?php echo esc_html($current_user->user_email); ?></p>
                            </div>
                        </div>
                        
                        <nav class="spd-profile-nav">
                            <ul>
                                <?php foreach ($tabs as $tab) : ?>
                                    <?php
                                    $is_link = ($tab->content_type === 'link');
                                    $href = $is_link ? esc_url($tab->content_value) : '#';
                                    $link_class = $is_link ? 'spd-tab-direct-link' : 'spd-tab-link';
                                    $data_attr = $is_link ? '' : 'data-tab="' . esc_attr($tab->tab_slug) . '"';
                                    ?>
                                    <li class="<?php echo ($current_tab === $tab->tab_slug && !$is_link) ? 'active' : ''; ?>">
                                        <a href="<?php echo $href; ?>" <?php echo $data_attr; ?> class="<?php echo $link_class; ?>">
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
                            <a href="<?php echo esc_url(wp_logout_url(get_permalink())); ?>" class="spd-logout-link">
                                <span class="dashicons dashicons-exit"></span>
                                <?php esc_html_e('Sign Out', 'saas-profile-dashboard'); ?>
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
            // Bind AJAX loading strictly to elements that have .spd-tab-link class
            document.querySelectorAll('.spd-tab-link').forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const tabSlug = this.getAttribute('data-tab');
                    
                    document.querySelectorAll('.spd-profile-nav li, .spd-profile-nav-top li').forEach(function(li) {
                        li.classList.remove('active');
                    });
                    this.parentElement.classList.add('active');
                    
                    loadTabContent(tabSlug);
                    
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
            
            contentArea.innerHTML = '<div class="spd-loading"><p><?php esc_html_e('Loading...', 'saas-profile-dashboard'); ?></p></div>';
            
            const formData = new FormData();
            formData.append('action', 'spd_load_tab_content');
            formData.append('tab_slug', tabSlug);
            formData.append('nonce', spd_ajax.nonce);
            
            fetch(spd_ajax.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(res => {
                if (res.success) {
                    contentArea.innerHTML = res.data.content;
                } else {
                    contentArea.innerHTML = '<p>' + (res.data.message || '<?php esc_html_e('Access denied or error loading content. [ERR_TAB_01]', 'saas-profile-dashboard'); ?>') + '</p>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                contentArea.innerHTML = '<p><?php esc_html_e('Error loading content. [ERR_TAB_02]', 'saas-profile-dashboard'); ?></p>';
            });
        }
        </script>
        <?php
        return ob_get_clean();
    }
    
    private function load_tab_content($tab_slug) {
        if (class_exists('SPD_Database')) {
            try {
                $tab = SPD_Database::get_tab($tab_slug);
                if ($tab && $tab->content_type === 'shortcode') {
                    return do_shortcode($tab->content_value);
                } elseif ($tab && $tab->content_type === 'url') {
                    return '<iframe src="' . esc_url($tab->content_value) . '" style="width: 100%; height: 600px; border: none;"></iframe>';
                } elseif ($tab && $tab->content_type === 'link') {
                    // Fallback in case a direct link tab gets requested via backend AJAX
                    return '<script>window.location.href="' . esc_url($tab->content_value) . '";</script>';
                }
            } catch (Exception $e) {
                error_log('SPD load_tab_content Error: ' . $e->getMessage());
            }
        }
        
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
            return '<p>' . esc_html__('Please log in to view your dashboard.', 'saas-profile-dashboard') . '</p>';
        }
        
        $current_user = wp_get_current_user();
        $registration_date = date('F j, Y', strtotime($current_user->user_registered));
        
        $welcome_name = $current_user->first_name ?: $current_user->display_name;
        
        $role_config = $this->get_user_role_config();
        if ($role_config && !empty($role_config->custom_welcome_message)) {
            $custom_welcome = str_replace('{user_name}', $welcome_name, $role_config->custom_welcome_message);
        } else {
            $custom_welcome = sprintf(esc_html__('Welcome back, %s!', 'saas-profile-dashboard'), $welcome_name);
        }
        
        $quick_action_tabs = $this->get_role_setting('quick_action_tabs', array('profile', 'settings'));
        
        ob_start();
        ?>
        <div class="spd-dashboard-widgets">
            <div class="spd-widget">
                <h3><?php echo esc_html($custom_welcome); ?></h3>
                <p><?php printf(esc_html__('Member since %s', 'saas-profile-dashboard'), esc_html($registration_date)); ?></p>
            </div>
            
            <?php if (!empty($quick_action_tabs) && is_array($quick_action_tabs)) : ?>
            <div class="spd-widget">
                <h3><?php esc_html_e('Quick Actions', 'saas-profile-dashboard'); ?></h3>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <?php
                    $all_tabs = $this->get_allowed_tabs_for_user();
                    foreach ($all_tabs as $tab) {
                        if (in_array($tab->tab_slug, $quick_action_tabs)) {
                            // If it's a direct link, map the href natively rather than AJAX trigger
                            if ($tab->content_type === 'link') {
                                echo '<a href="' . esc_url($tab->content_value) . '" class="spd-btn spd-btn-primary spd-btn-sm">' . esc_html($tab->tab_name) . '</a>';
                            } else {
                                echo '<a href="#" data-tab="' . esc_attr($tab->tab_slug) . '" class="spd-btn spd-btn-primary spd-btn-sm spd-quick-action">' . esc_html($tab->tab_name) . '</a>';
                            }
                        }
                    }
                    ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.spd-quick-action').forEach(function(button) {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const tabSlug = this.getAttribute('data-tab');
                    
                    document.querySelectorAll('.spd-profile-nav li, .spd-profile-nav-top li').forEach(function(li) {
                        li.classList.remove('active');
                    });
                    
                    const targetTab = document.querySelector('.spd-tab-link[data-tab="' + tabSlug + '"]');
                    if (targetTab) {
                        targetTab.parentElement.classList.add('active');
                        
                        if (typeof loadTabContent === 'function') {
                            loadTabContent(tabSlug);
                        }
                        
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
            return '<p>' . esc_html__('Please log in to edit your profile.', 'saas-profile-dashboard') . '</p>';
        }
        
        $current_user = wp_get_current_user();
        
        return '<form id="spd-profile-update-form" class="spd-profile-form">
            <div class="spd-form-row">
                <label for="first_name">' . esc_html__('First Name', 'saas-profile-dashboard') . '</label>
                <input type="text" id="first_name" name="first_name" value="' . esc_attr($current_user->first_name) . '">
            </div>
            
            <div class="spd-form-row">
                <label for="last_name">' . esc_html__('Last Name', 'saas-profile-dashboard') . '</label>
                <input type="text" id="last_name" name="last_name" value="' . esc_attr($current_user->last_name) . '">
            </div>
            
            <div class="spd-form-row">
                <label for="email">' . esc_html__('Email Address', 'saas-profile-dashboard') . '</label>
                <input type="email" id="email" name="email" value="' . esc_attr($current_user->user_email) . '" required>
            </div>
            
            <div class="spd-form-row">
                <label for="description">' . esc_html__('Bio/Description', 'saas-profile-dashboard') . '</label>
                <textarea id="description" name="description" rows="4">' . esc_textarea($current_user->description) . '</textarea>
            </div>
            
            <div class="spd-form-actions">
                <input type="submit" value="' . esc_attr__('Update Profile', 'saas-profile-dashboard') . '" class="spd-btn spd-btn-primary">
            </div>
        </form>';
    }
    
    public function user_settings_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('Please log in to view your settings.', 'saas-profile-dashboard') . '</p>';
        }
        
        $user_id = get_current_user_id();
        
        ob_start();
        ?>
        <div class="spd-settings-form">
            <h3><?php esc_html_e('Account Settings', 'saas-profile-dashboard'); ?></h3>
            
            <form id="spd-settings-form" enctype="multipart/form-data" class="spd-profile-form">
                <div class="spd-form-row">
                    <label><?php esc_html_e('Profile Picture', 'saas-profile-dashboard'); ?></label>
                    <div class="spd-avatar-preview">
                        <?php echo $this->get_user_custom_avatar($user_id, 100); ?>
                        <div class="spd-upload-control">
                            <input type="file" name="spd_avatar" accept="image/jpeg,image/png,image/gif" style="border:none; padding:0; background:transparent;">
                            <p style="font-size: 12px; color: #666; margin-top:5px;"><?php esc_html_e('Max file size: 2MB. Allowed types: JPG, PNG.', 'saas-profile-dashboard'); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="spd-form-actions" id="spd-settings-actions">
                    <input type="submit" value="<?php esc_attr_e('Save Settings', 'saas-profile-dashboard'); ?>" class="spd-btn spd-btn-primary">
                    <button type="button" id="spd-init-pwd-change" class="spd-btn spd-btn-secondary" style="margin-left: 10px;">
                        <?php esc_html_e('Change Password', 'saas-profile-dashboard'); ?>
                    </button>
                </div>
            </form>

            <div id="spd-pwd-otp-section" class="spd-otp-box" style="display:none;">
                <h4><?php esc_html_e('Change Password via OTP', 'saas-profile-dashboard'); ?></h4>
                <p style="font-size: 0.9rem; margin-bottom: 15px; color: var(--spd-text-secondary);"><?php esc_html_e('Please check your email. We just sent a 6-digit verification code.', 'saas-profile-dashboard'); ?></p>
                
                <form id="spd-otp-password-form">
                    <div class="spd-form-row">
                        <label><?php esc_html_e('Enter OTP Code', 'saas-profile-dashboard'); ?></label>
                        <input type="text" id="spd_otp_code" name="spd_otp_code" required placeholder="123456">
                    </div>
                    
                    <div class="spd-form-row">
                        <label><?php esc_html_e('New Password', 'saas-profile-dashboard'); ?></label>
                        <input type="password" id="spd_new_pwd" name="spd_new_pwd" required minlength="6">
                    </div>
                    
                    <div class="spd-form-row">
                        <label><?php esc_html_e('Confirm New Password', 'saas-profile-dashboard'); ?></label>
                        <input type="password" id="spd_confirm_pwd" name="spd_confirm_pwd" required minlength="6">
                    </div>
                    
                    <div class="spd-form-actions">
                        <input type="submit" value="<?php esc_attr_e('Update Password', 'saas-profile-dashboard'); ?>" class="spd-btn spd-btn-primary" id="spd-submit-pwd-change">
                        <button type="button" id="spd-cancel-pwd-change" class="spd-btn spd-btn-secondary" style="margin-left: 10px;"><?php esc_html_e('Cancel', 'saas-profile-dashboard'); ?></button>
                    </div>
                </form>
            </div>
            
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function woo_orders_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('Please log in to view your orders.', 'saas-profile-dashboard') . '</p>';
        }
        
        if (!class_exists('WooCommerce')) {
            return '<p>' . esc_html__('WooCommerce is not installed.', 'saas-profile-dashboard') . '</p>';
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
                        <div><strong>#' . esc_html($order->get_id()) . '</strong></div>
                        <div>' . esc_html($order->get_date_created()->date('M j, Y')) . '</div>
                        <div><span class="status-' . esc_attr($order->get_status()) . '">' . esc_html(ucfirst($order->get_status())) . '</span></div>
                        <div>' . wp_kses_post(wc_price($order->get_total())) . '</div>
                    </div>';
                }
                $output .= '</div></div>';
                return $output;
            } else {
                return '<div class="spd-woo-orders" style="text-align: center; padding: 2rem;">
                    <p>' . esc_html__('You have no orders yet.', 'saas-profile-dashboard') . '</p>
                    <a href="' . esc_url(wc_get_page_permalink('shop')) . '" class="spd-btn spd-btn-primary">' . esc_html__('Start Shopping', 'saas-profile-dashboard') . '</a>
                </div>';
            }
        } catch (Exception $e) {
            error_log('SPD WooCommerce Error: ' . $e->getMessage());
            return '<div class="spd-woo-orders"><p>' . esc_html__('Error loading orders. Please try again. [ERR_WOO_01]', 'saas-profile-dashboard') . '</p></div>';
        }
    }
}
