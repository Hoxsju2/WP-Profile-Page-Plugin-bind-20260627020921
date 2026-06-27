<?php

class SPD_Admin_Panel {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_post_spd_save_tab', array($this, 'save_tab'));
        add_action('admin_post_spd_delete_tab', array($this, 'delete_tab'));
        add_action('admin_post_spd_save_role_config', array($this, 'save_role_config'));
        add_action('admin_post_spd_delete_role_config', array($this, 'delete_role_config'));
        add_action('admin_head', array($this, 'admin_head_styles'));
        add_action('admin_notices', array($this, 'check_database_notice'));
        add_action('admin_post_spd_save_general_settings', array($this, 'save_general_settings'));
    }
    
    public function admin_head_styles() {
        wp_enqueue_style('dashicons');
    }
    
    public function check_database_notice() {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'saas-profile') === false) {
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'spd_tabs';
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;
        
        if (!$table_exists) {
            echo '<div class="notice notice-error"><p>';
            esc_html_e('Database table is missing. ', 'saas-profile-dashboard');
            echo '<a href="' . esc_url(admin_url('plugins.php')) . '" class="button">' . esc_html__('Go to Plugins', 'saas-profile-dashboard') . '</a> ';
            esc_html_e('and deactivate then reactivate this plugin to fix the issue.', 'saas-profile-dashboard');
            echo '</p></div>';
        }
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('SaaS Profile Dashboard', 'saas-profile-dashboard'),
            __('Profile Dashboard', 'saas-profile-dashboard'),
            'manage_options',
            'saas-profile-dashboard',
            array($this, 'admin_page'),
            'dashicons-admin-users',
            30
        );
        
        add_submenu_page(
            'saas-profile-dashboard',
            __('Manage Tabs', 'saas-profile-dashboard'),
            __('Manage Tabs', 'saas-profile-dashboard'),
            'manage_options',
            'saas-profile-tabs',
            array($this, 'tabs_page')
        );
        
        add_submenu_page(
            'saas-profile-dashboard',
            __('Role Configurations', 'saas-profile-dashboard'),
            __('Role Configurations', 'saas-profile-dashboard'),
            'manage_options',
            'saas-profile-roles',
            array($this, 'roles_page')
        );
        
        add_submenu_page(
            'saas-profile-dashboard',
            __('General Settings', 'saas-profile-dashboard'),
            __('General Settings', 'saas-profile-dashboard'),
            'manage_options',
            'saas-profile-general',
            array($this, 'general_settings_page')
        );
    }
    
    public function admin_page() {
        $tabs_count = count(SPD_Database::get_tabs(false));
        $role_configs_count = count(SPD_Database::get_all_role_configs());
        $profile_page_id = get_option('spd_profile_page_id');
        $profile_page_url = $profile_page_id ? get_permalink($profile_page_id) : null;
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('SaaS Profile Dashboard', 'saas-profile-dashboard'); ?></h1>
            
            <div class="spd-admin-content">
                <div class="spd-admin-card">
                    <h2><?php esc_html_e('Dashboard Overview', 'saas-profile-dashboard'); ?></h2>
                    <p><?php esc_html_e('Welcome to the SaaS Profile Dashboard plugin. This plugin modernizes the WordPress user profile page with a SaaS concept and role-based configurations.', 'saas-profile-dashboard'); ?></p>
                    
                    <div class="spd-stats">
                        <div class="spd-stat-item">
                            <h3><?php echo esc_html($tabs_count); ?></h3>
                            <p><?php esc_html_e('Total Tabs', 'saas-profile-dashboard'); ?></p>
                        </div>
                        <div class="spd-stat-item">
                            <h3><?php echo esc_html($role_configs_count); ?></h3>
                            <p><?php esc_html_e('Role Configurations', 'saas-profile-dashboard'); ?></p>
                        </div>
                        <div class="spd-stat-item">
                            <h3><?php echo $profile_page_url ? '&#10003;' : '&#10007;'; ?></h3>
                            <p><?php esc_html_e('Setup Status', 'saas-profile-dashboard'); ?></p>
                        </div>
                    </div>
                    
                    <?php if (!$profile_page_url) : ?>
                    <div class="notice notice-warning inline">
                        <h3><?php esc_html_e('Setup Required', 'saas-profile-dashboard'); ?></h3>
                        <p><?php esc_html_e('To complete the setup, you need to add the shortcode [saas_profile_dashboard] to a page.', 'saas-profile-dashboard'); ?></p>
                    </div>
                    <?php else : ?>
                    <div class="notice notice-success inline">
                        <h3><?php esc_html_e('Setup Complete!', 'saas-profile-dashboard'); ?></h3>
                        <p><?php printf(esc_html__('Profile dashboard is active on: %s', 'saas-profile-dashboard'), '<a href="' . esc_url($profile_page_url) . '" target="_blank">' . esc_html(get_the_title($profile_page_id)) . '</a>'); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=saas-profile-tabs')); ?>" class="button button-primary">
                            <?php esc_html_e('Manage Tabs', 'saas-profile-dashboard'); ?>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=saas-profile-roles')); ?>" class="button button-primary">
                            <?php esc_html_e('Configure Roles', 'saas-profile-dashboard'); ?>
                        </a>
                        <?php if ($profile_page_url) : ?>
                        <a href="<?php echo esc_url($profile_page_url); ?>" class="button" target="_blank">
                            <?php esc_html_e('View Profile Page', 'saas-profile-dashboard'); ?>
                        </a>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function roles_page() {
        $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : 'list';
        $role_id = isset($_GET['role_id']) ? intval($_GET['role_id']) : 0;
        
        if (isset($_GET['message'])) {
            $message = sanitize_text_field(wp_unslash($_GET['message']));
            switch ($message) {
                case 'saved':
                    echo '<div class="notice notice-success"><p>' . esc_html__('Role configuration saved successfully!', 'saas-profile-dashboard') . '</p></div>';
                    break;
                case 'deleted':
                    echo '<div class="notice notice-success"><p>' . esc_html__('Role configuration deleted successfully!', 'saas-profile-dashboard') . '</p></div>';
                    break;
                case 'error':
                    echo '<div class="notice notice-error"><p>' . esc_html__('Error saving role configuration.', 'saas-profile-dashboard') . '</p></div>';
                    break;
            }
        }
        
        switch ($action) {
            case 'edit':
                $this->edit_role_config_form($role_id);
                break;
            default:
                $this->roles_list();
                break;
        }
    }
    
    private function roles_list() {
        $role_configs = SPD_Database::get_all_role_configs();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Role Configurations', 'saas-profile-dashboard'); ?></h1>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Role Name', 'saas-profile-dashboard'); ?></th>
                        <th><?php esc_html_e('Allowed Tabs', 'saas-profile-dashboard'); ?></th>
                        <th><?php esc_html_e('Default Tab', 'saas-profile-dashboard'); ?></th>
                        <th><?php esc_html_e('Layout', 'saas-profile-dashboard'); ?></th>
                        <th style="width: 80px;"><?php esc_html_e('Status', 'saas-profile-dashboard'); ?></th>
                        <th style="width: 120px;"><?php esc_html_e('Actions', 'saas-profile-dashboard'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($role_configs && !empty($role_configs)) : ?>
                        <?php foreach ($role_configs as $config) : ?>
                            <tr>
                                <td><strong><?php echo esc_html($config->role_name); ?></strong></td>
                                <td>
                                    <?php 
                                    $tabs = is_array($config->allowed_tabs) ? $config->allowed_tabs : array();
                                    echo count($tabs) . ' ' . esc_html__('tabs', 'saas-profile-dashboard'); 
                                    ?>
                                </td>
                                <td><?php echo esc_html($config->default_tab); ?></td>
                                <td><?php echo esc_html(ucfirst($config->menu_layout)); ?></td>
                                <td>
                                    <?php echo $config->is_active ? '<span class="status-active">Active</span>' : '<span class="status-inactive">Inactive</span>'; ?>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=saas-profile-roles&action=edit&role_id=' . $config->id)); ?>" class="button button-small">
                                        <?php esc_html_e('Edit', 'saas-profile-dashboard'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px;">
                                <p><?php esc_html_e('No role configurations found.', 'saas-profile-dashboard'); ?></p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    private function edit_role_config_form($role_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'spd_role_configs';
        
        $config = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $role_id));
        
        if (!$config) {
            echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__('Role configuration not found.', 'saas-profile-dashboard') . '</p></div></div>';
            return;
        }
        
        $config->allowed_tabs = maybe_unserialize($config->allowed_tabs);
        $config->quick_action_tabs = maybe_unserialize($config->quick_action_tabs);
        
        $all_tabs = SPD_Database::get_tabs(false);
        ?>
        <div class="wrap">
            <h1>
                <?php printf(esc_html__('Edit Role Configuration: %s', 'saas-profile-dashboard'), esc_html($config->role_name)); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=saas-profile-roles')); ?>" class="page-title-action">
                    <?php esc_html_e('Back to Roles', 'saas-profile-dashboard'); ?>
                </a>
            </h1>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="spd_save_role_config">
                <input type="hidden" name="id" value="<?php echo intval($config->id); ?>">
                <input type="hidden" name="role_slug" value="<?php echo esc_attr($config->role_slug); ?>">
                <input type="hidden" name="role_name" value="<?php echo esc_attr($config->role_name); ?>">
                <?php wp_nonce_field('spd_save_role_config', 'spd_role_config_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Allowed Tabs', 'saas-profile-dashboard'); ?></th>
                        <td>
                            <?php if (!empty($all_tabs)) : ?>
                                <fieldset>
                                    <?php foreach ($all_tabs as $tab) : ?>
                                        <label style="display: block; margin-bottom: 0.5rem;">
                                            <input type="checkbox" name="allowed_tabs[]" value="<?php echo esc_attr($tab->tab_slug); ?>" 
                                                   <?php checked(in_array($tab->tab_slug, (array)$config->allowed_tabs)); ?>>
                                            <?php echo esc_html($tab->tab_name); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </fieldset>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="default_tab"><?php esc_html_e('Default Tab', 'saas-profile-dashboard'); ?></label>
                        </th>
                        <td>
                            <select id="default_tab" name="default_tab">
                                <?php foreach ($all_tabs as $tab) : ?>
                                    <option value="<?php echo esc_attr($tab->tab_slug); ?>" <?php selected($config->default_tab, $tab->tab_slug); ?>>
                                        <?php echo esc_html($tab->tab_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="menu_layout"><?php esc_html_e('Menu Layout', 'saas-profile-dashboard'); ?></label>
                        </th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="radio" name="menu_layout" value="sidebar" <?php checked($config->menu_layout, 'sidebar'); ?>>
                                    <strong><?php esc_html_e('Sidebar Layout', 'saas-profile-dashboard'); ?></strong>
                                </label><br><br>
                                <label>
                                    <input type="radio" name="menu_layout" value="top" <?php checked($config->menu_layout, 'top'); ?>>
                                    <strong><?php esc_html_e('Top Menu Layout', 'saas-profile-dashboard'); ?></strong>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" class="button-primary" value="<?php esc_attr_e('Save Configuration', 'saas-profile-dashboard'); ?>">
                </p>
            </form>
        </div>
        <?php
    }
    
    public function save_role_config() {
        if (!isset($_POST['spd_role_config_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['spd_role_config_nonce'])), 'spd_save_role_config') || !current_user_can('manage_options')) {
            wp_die(esc_html__('Security check failed', 'saas-profile-dashboard'));
        }
        
        $config_data = array(
            'id' => intval($_POST['id']),
            'role_slug' => sanitize_text_field(wp_unslash($_POST['role_slug'])),
            'role_name' => sanitize_text_field(wp_unslash($_POST['role_name'])),
            'allowed_tabs' => isset($_POST['allowed_tabs']) ? array_map('sanitize_text_field', wp_unslash($_POST['allowed_tabs'])) : array(),
            'default_tab' => sanitize_text_field(wp_unslash($_POST['default_tab'])),
            'menu_layout' => sanitize_text_field(wp_unslash($_POST['menu_layout'])),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        );
        
        $result = SPD_Database::save_role_config($config_data);
        $redirect_url = admin_url('admin.php?page=saas-profile-roles&message=' . ($result !== false ? 'saved' : 'error'));
        
        wp_redirect($redirect_url);
        exit;
    }

    public function tabs_page() {
        $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : 'list';
        $tab_id = isset($_GET['tab_id']) ? intval($_GET['tab_id']) : 0;
        
        if (isset($_GET['message'])) {
            $message = sanitize_text_field(wp_unslash($_GET['message']));
            if ($message === 'saved') {
                echo '<div class="notice notice-success"><p>' . esc_html__('Tab saved successfully!', 'saas-profile-dashboard') . '</p></div>';
            } elseif ($message === 'deleted') {
                echo '<div class="notice notice-success"><p>' . esc_html__('Tab deleted successfully!', 'saas-profile-dashboard') . '</p></div>';
            }
        }
        
        if ($action === 'edit' || $action === 'add') {
            $this->edit_tab_form($tab_id);
        } else {
            $this->tabs_list();
        }
    }
    
    private function tabs_list() {
        $tabs = SPD_Database::get_tabs(false);
        ?>
        <div class="wrap">
            <h1>
                <?php esc_html_e('Manage Profile Tabs', 'saas-profile-dashboard'); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=saas-profile-tabs&action=add')); ?>" class="page-title-action">
                    <?php esc_html_e('Add New Tab', 'saas-profile-dashboard'); ?>
                </a>
            </h1>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 50px;"><?php esc_html_e('Icon', 'saas-profile-dashboard'); ?></th>
                        <th><?php esc_html_e('Tab Name', 'saas-profile-dashboard'); ?></th>
                        <th><?php esc_html_e('Slug', 'saas-profile-dashboard'); ?></th>
                        <th><?php esc_html_e('Content Type', 'saas-profile-dashboard'); ?></th>
                        <th style="width: 80px;"><?php esc_html_e('Status', 'saas-profile-dashboard'); ?></th>
                        <th style="width: 120px;"><?php esc_html_e('Actions', 'saas-profile-dashboard'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($tabs && !empty($tabs)) : ?>
                        <?php foreach ($tabs as $tab) : ?>
                            <tr>
                                <td><span class="dashicons <?php echo esc_attr($tab->icon); ?>"></span></td>
                                <td><strong><?php echo esc_html($tab->tab_name); ?></strong></td>
                                <td><?php echo esc_html($tab->tab_slug); ?></td>
                                <td><?php echo esc_html(ucfirst($tab->content_type)); ?></td>
                                <td><?php echo $tab->is_active ? '<span class="status-active">Active</span>' : '<span class="status-inactive">Inactive</span>'; ?></td>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=saas-profile-tabs&action=edit&tab_id=' . $tab->id)); ?>" class="button button-small">
                                        <?php esc_html_e('Edit', 'saas-profile-dashboard'); ?>
                                    </a>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=spd_delete_tab&tab_id=' . $tab->id), 'spd_delete_tab')); ?>" 
                                       class="button button-small" 
                                       onclick="return confirm('<?php esc_attr_e('Are you sure?', 'saas-profile-dashboard'); ?>')">
                                        <?php esc_html_e('Delete', 'saas-profile-dashboard'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px;">
                                <p><?php esc_html_e('No tabs found.', 'saas-profile-dashboard'); ?></p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    private function edit_tab_form($tab_id) {
        $tab = $tab_id > 0 ? SPD_Database::get_tab_by_id($tab_id) : null;
        
        $tab_name = $tab ? $tab->tab_name : '';
        $tab_slug = $tab ? $tab->tab_slug : '';
        $icon = $tab ? $tab->icon : 'dashicons-dashboard';
        $tab_order = $tab ? $tab->tab_order : 0;
        $content_type = $tab ? $tab->content_type : 'shortcode';
        $content_value = $tab ? $tab->content_value : '';
        $is_active = $tab ? $tab->is_active : 1;
        ?>
        <div class="wrap">
            <h1><?php echo $tab ? esc_html__('Edit Tab', 'saas-profile-dashboard') : esc_html__('Add New Tab', 'saas-profile-dashboard'); ?></h1>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="spd_save_tab">
                <input type="hidden" name="tab_id" value="<?php echo intval($tab_id); ?>">
                <?php wp_nonce_field('spd_save_tab', 'spd_tab_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="tab_name"><?php esc_html_e('Tab Name', 'saas-profile-dashboard'); ?></label></th>
                        <td><input name="tab_name" type="text" id="tab_name" value="<?php echo esc_attr($tab_name); ?>" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tab_slug"><?php esc_html_e('Tab Slug', 'saas-profile-dashboard'); ?></label></th>
                        <td><input name="tab_slug" type="text" id="tab_slug" value="<?php echo esc_attr($tab_slug); ?>" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="icon"><?php esc_html_e('Icon (Dashicon class)', 'saas-profile-dashboard'); ?></label></th>
                        <td><input name="icon" type="text" id="icon" value="<?php echo esc_attr($icon); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="content_type"><?php esc_html_e('Content Type', 'saas-profile-dashboard'); ?></label></th>
                        <td>
                            <select name="content_type" id="content_type">
                                <option value="shortcode" <?php selected($content_type, 'shortcode'); ?>><?php esc_html_e('Shortcode/HTML', 'saas-profile-dashboard'); ?></option>
                                <option value="url" <?php selected($content_type, 'url'); ?>><?php esc_html_e('iFrame URL', 'saas-profile-dashboard'); ?></option>
                                <option value="link" <?php selected($content_type, 'link'); ?>><?php esc_html_e('Direct Link', 'saas-profile-dashboard'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="content_value"><?php esc_html_e('Shortcode / Link URL', 'saas-profile-dashboard'); ?></label></th>
                        <td><textarea name="content_value" id="content_value" class="large-text" rows="5"><?php echo esc_textarea($content_value); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="is_active"><?php esc_html_e('Status', 'saas-profile-dashboard'); ?></label></th>
                        <td>
                            <label><input type="checkbox" name="is_active" value="1" <?php checked($is_active, 1); ?>> <?php esc_html_e('Active', 'saas-profile-dashboard'); ?></label>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" class="button-primary" value="<?php esc_attr_e('Save Tab', 'saas-profile-dashboard'); ?>">
                </p>
            </form>
        </div>
        <?php
    }
    
    public function save_tab() {
        if (!isset($_POST['spd_tab_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['spd_tab_nonce'])), 'spd_save_tab') || !current_user_can('manage_options')) {
            wp_die(esc_html__('Security check failed', 'saas-profile-dashboard'));
        }
        
        $data = array(
            'tab_name' => sanitize_text_field(wp_unslash($_POST['tab_name'])),
            'tab_slug' => sanitize_title(wp_unslash($_POST['tab_slug'])),
            'icon' => sanitize_text_field(wp_unslash($_POST['icon'])),
            'content_type' => sanitize_text_field(wp_unslash($_POST['content_type'])),
            'content_value' => wp_kses_post(wp_unslash($_POST['content_value'])),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        );
        
        if (!empty($_POST['tab_id'])) {
            $data['id'] = intval($_POST['tab_id']);
        }
        
        SPD_Database::save_tab($data);
        wp_redirect(admin_url('admin.php?page=saas-profile-tabs&message=saved'));
        exit;
    }
    
    public function delete_tab() {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'spd_delete_tab') || !current_user_can('manage_options')) {
            wp_die(esc_html__('Security check failed', 'saas-profile-dashboard'));
        }
        
        $tab_id = intval($_GET['tab_id']);
        SPD_Database::delete_tab($tab_id);
        
        wp_redirect(admin_url('admin.php?page=saas-profile-tabs&message=deleted'));
        exit;
    }
    
    public function save_general_settings() {
        if (!isset($_POST['spd_general_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['spd_general_nonce'])), 'spd_save_general_settings') || !current_user_can('manage_options')) {
            wp_die(esc_html__('Security check failed', 'saas-profile-dashboard'));
        }
        
        // Save layout
        update_option('spd_menu_layout', sanitize_text_field(wp_unslash($_POST['menu_layout'])));
        
        // Save Colors securely
        if (isset($_POST['header_bg_color'])) {
            update_option('spd_header_bg_color', sanitize_hex_color($_POST['header_bg_color']));
        }
        if (isset($_POST['menu_text_color'])) {
            update_option('spd_menu_text_color', sanitize_hex_color($_POST['menu_text_color']));
        }
        if (isset($_POST['menu_hover_color'])) {
            update_option('spd_menu_hover_color', sanitize_hex_color($_POST['menu_hover_color']));
        }
        
        $redirect_url = admin_url('admin.php?page=saas-profile-general&message=saved');
        wp_safe_redirect($redirect_url);
        exit;
    }
    
    public function general_settings_page() {
        if (isset($_GET['message']) && $_GET['message'] === 'saved') {
            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved successfully!', 'saas-profile-dashboard') . '</p></div>';
        }
        
        $menu_layout = get_option('spd_menu_layout', 'top'); 
        
        // Color Variables
        $header_bg = get_option('spd_header_bg_color', '#2c3e50');
        $menu_text = get_option('spd_menu_text_color', '#ecf0f1');
        $menu_hover = get_option('spd_menu_hover_color', '#3498db');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('General Settings', 'saas-profile-dashboard'); ?></h1>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="spd_save_general_settings">
                <?php wp_nonce_field('spd_save_general_settings', 'spd_general_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label><?php esc_html_e('Default Layout', 'saas-profile-dashboard'); ?></label></th>
                        <td>
                            <label><input type="radio" name="menu_layout" value="top" <?php checked($menu_layout, 'top'); ?>> <?php esc_html_e('Top Menu (Full Width, Recommended)', 'saas-profile-dashboard'); ?></label><br>
                            <label><input type="radio" name="menu_layout" value="sidebar" <?php checked($menu_layout, 'sidebar'); ?>> <?php esc_html_e('Sidebar', 'saas-profile-dashboard'); ?></label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="header_bg_color"><?php esc_html_e('Header/Sidebar Background Color', 'saas-profile-dashboard'); ?></label>
                        </th>
                        <td>
                            <input type="color" id="header_bg_color" name="header_bg_color" value="<?php echo esc_attr($header_bg); ?>" style="height: 35px; width: 80px; padding: 2px;">
                            <p class="description"><?php esc_html_e('The main background color for the top header or sidebar.', 'saas-profile-dashboard'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="menu_text_color"><?php esc_html_e('Menu Text Color', 'saas-profile-dashboard'); ?></label>
                        </th>
                        <td>
                            <input type="color" id="menu_text_color" name="menu_text_color" value="<?php echo esc_attr($menu_text); ?>" style="height: 35px; width: 80px; padding: 2px;">
                            <p class="description"><?php esc_html_e('Color for the text and icons in the menu and header.', 'saas-profile-dashboard'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="menu_hover_color"><?php esc_html_e('Active & Hover Menu Color', 'saas-profile-dashboard'); ?></label>
                        </th>
                        <td>
                            <input type="color" id="menu_hover_color" name="menu_hover_color" value="<?php echo esc_attr($menu_hover); ?>" style="height: 35px; width: 80px; padding: 2px;">
                            <p class="description"><?php esc_html_e('The background color used when a tab is active or hovered.', 'saas-profile-dashboard'); ?></p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" class="button-primary" value="<?php esc_attr_e('Save Settings', 'saas-profile-dashboard'); ?>">
                </p>
            </form>
        </div>
        <?php
    }
}
