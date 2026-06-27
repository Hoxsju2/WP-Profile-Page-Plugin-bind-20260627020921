<?php

class SPD_Database {
    
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabs table
        $table_name = $wpdb->prefix . 'spd_tabs';
        
        // Notice the strictly formatted 'PRIMARY KEY  (id)' which dbDelta requires
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            tab_name varchar(255) NOT NULL,
            tab_slug varchar(255) NOT NULL,
            icon varchar(255) DEFAULT NULL,
            icon_type varchar(50) DEFAULT 'dashicons',
            tab_order int(11) DEFAULT 0,
            content_type varchar(50) DEFAULT 'shortcode',
            content_value text,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY tab_slug (tab_slug)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Role configurations table
        $role_config_table = $wpdb->prefix . 'spd_role_configs';
        
        $sql_role = "CREATE TABLE IF NOT EXISTS $role_config_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            role_slug varchar(100) NOT NULL,
            role_name varchar(255) NOT NULL,
            allowed_tabs text,
            default_tab varchar(255) DEFAULT 'dashboard',
            menu_layout varchar(50) DEFAULT 'sidebar',
            primary_color varchar(7) DEFAULT '#3498db',
            secondary_color varchar(7) DEFAULT '#2c3e50',
            custom_welcome_message text,
            show_currency_selector tinyint(1) DEFAULT 1,
            show_order_stats tinyint(1) DEFAULT 1,
            quick_action_tabs text,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY role_slug (role_slug)
        ) $charset_collate;";
        
        dbDelta($sql_role);
        
        // Create default tabs if none exist
        $existing_tabs = self::get_tabs(false);
        if (empty($existing_tabs)) {
            self::create_default_tabs();
        }
        
        self::create_default_role_configs();
    }
    
    private static function create_default_tabs() {
        $default_tabs = array(
            array(
                'tab_name' => 'Dashboard',
                'tab_slug' => 'dashboard',
                'icon' => 'dashicons-dashboard',
                'icon_type' => 'dashicons',
                'tab_order' => 1,
                'content_type' => 'shortcode',
                'content_value' => '[spd_dashboard]',
                'is_active' => 1
            ),
            array(
                'tab_name' => 'Profile',
                'tab_slug' => 'profile',
                'icon' => 'dashicons-admin-users',
                'icon_type' => 'dashicons',
                'tab_order' => 2,
                'content_type' => 'shortcode',
                'content_value' => '[spd_profile_form]',
                'is_active' => 1
            ),
            array(
                'tab_name' => 'Settings',
                'tab_slug' => 'settings',
                'icon' => 'dashicons-admin-settings',
                'icon_type' => 'dashicons',
                'tab_order' => 3,
                'content_type' => 'shortcode',
                'content_value' => '[spd_user_settings]',
                'is_active' => 1
            )
        );
        
        if (class_exists('WooCommerce')) {
            $default_tabs[] = array(
                'tab_name' => 'Orders',
                'tab_slug' => 'orders',
                'icon' => 'dashicons-cart',
                'icon_type' => 'dashicons',
                'tab_order' => 4,
                'content_type' => 'shortcode',
                'content_value' => '[spd_woo_orders]',
                'is_active' => 1
            );
        }
        
        foreach ($default_tabs as $tab) {
            self::save_tab($tab);
        }
    }
    
    private static function create_default_role_configs() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'spd_role_configs';
        
        $existing = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        if ($existing > 0) {
            return;
        }
        
        $wp_roles = wp_roles();
        $roles = $wp_roles->roles;
        
        $tabs = self::get_tabs(false);
        $all_tab_slugs = array();
        foreach ($tabs as $tab) {
            $all_tab_slugs[] = $tab->tab_slug;
        }
        
        foreach ($roles as $role_slug => $role_data) {
            if ($role_slug === 'administrator') {
                continue;
            }
            
            $config = array(
                'role_slug' => $role_slug,
                'role_name' => $role_data['name'],
                'allowed_tabs' => maybe_serialize($all_tab_slugs),
                'default_tab' => 'dashboard',
                'menu_layout' => 'sidebar',
                'primary_color' => '#3498db',
                'secondary_color' => '#2c3e50',
                'custom_welcome_message' => '',
                'show_currency_selector' => 1,
                'show_order_stats' => 1,
                'quick_action_tabs' => maybe_serialize(array('profile', 'orders', 'settings')),
                'is_active' => 1
            );
            
            $wpdb->insert($table_name, $config);
        }
    }
    
    public static function get_tabs($active_only = true) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'spd_tabs';
        
        $where = $active_only ? "WHERE is_active = 1" : "";
        $results = $wpdb->get_results("SELECT * FROM $table_name $where ORDER BY tab_order ASC");
        
        return $results;
    }
    
    public static function get_tab($slug) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'spd_tabs';
        
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE tab_slug = %s AND is_active = 1", $slug));
    }
    
    public static function get_tab_by_id($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'spd_tabs';
        
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
    }
    
    public static function save_tab($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'spd_tabs';
        
        $defaults = array(
            'tab_name' => '',
            'tab_slug' => '',
            'icon' => 'dashicons-dashboard',
            'icon_type' => 'dashicons',
            'tab_order' => 1,
            'content_type' => 'shortcode',
            'content_value' => '',
            'is_active' => 1
        );
        
        $data = wp_parse_args($data, $defaults);
        
        if (isset($data['id']) && !empty($data['id'])) {
            $id = intval($data['id']);
            unset($data['id']);
            $result = $wpdb->update($table_name, $data, array('id' => $id));
            return $result !== false ? $id : false;
        } else {
            $result = $wpdb->insert($table_name, $data);
            return $result !== false ? $wpdb->insert_id : false;
        }
    }
    
    public static function delete_tab($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'spd_tabs';
        
        return $wpdb->delete($table_name, array('id' => intval($id)));
    }
    
    public static function slug_exists($slug, $exclude_id = 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'spd_tabs';
        
        $sql = $wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE tab_slug = %s", $slug);
        
        if ($exclude_id > 0) {
            $sql .= $wpdb->prepare(" AND id != %d", $exclude_id);
        }
        
        return $wpdb->get_var($sql) > 0;
    }
    
    public static function get_max_order() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'spd_tabs';
        
        $max_order = $wpdb->get_var("SELECT MAX(tab_order) FROM $table_name");
        return $max_order ? intval($max_order) : 0;
    }
    
    public static function get_role_config($role_slug) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'spd_role_configs';
        
        $config = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE role_slug = %s AND is_active = 1", $role_slug));
        
        if ($config) {
            $config->allowed_tabs = maybe_unserialize($config->allowed_tabs);
            $config->quick_action_tabs = maybe_unserialize($config->quick_action_tabs);
        }
        
        return $config;
    }
    
    public static function get_all_role_configs() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'spd_role_configs';
        
        $configs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY role_name ASC");
        
        if ($configs) {
            foreach ($configs as $config) {
                $config->allowed_tabs = maybe_unserialize($config->allowed_tabs);
                $config->quick_action_tabs = maybe_unserialize($config->quick_action_tabs);
            }
        }
        
        return $configs;
    }
    
    public static function save_role_config($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'spd_role_configs';
        
        if (isset($data['allowed_tabs']) && is_array($data['allowed_tabs'])) {
            $data['allowed_tabs'] = maybe_serialize($data['allowed_tabs']);
        }
        
        if (isset($data['quick_action_tabs']) && is_array($data['quick_action_tabs'])) {
            $data['quick_action_tabs'] = maybe_serialize($data['quick_action_tabs']);
        }
        
        if (isset($data['id']) && !empty($data['id'])) {
            $id = intval($data['id']);
            unset($data['id']);
            $result = $wpdb->update($table_name, $data, array('id' => $id));
            return $result !== false ? $id : false;
        } else {
            $result = $wpdb->insert($table_name, $data);
            return $result !== false ? $wpdb->insert_id : false;
        }
    }
    
    public static function delete_role_config($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'spd_role_configs';
        
        return $wpdb->delete($table_name, array('id' => intval($id)));
    }
    
    public static function get_user_role_config($user_id) {
        $user = get_userdata($user_id);
        
        if (!$user || empty($user->roles)) {
            return null;
        }
        
        $user_role = $user->roles[0];
        
        return self::get_role_config($user_role);
    }
}
