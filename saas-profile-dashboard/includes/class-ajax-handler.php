<?php

class SPD_Ajax_Handler {
    
    public function __construct() {
        add_action('wp_ajax_spd_load_tab_content', array($this, 'load_tab_content'));
    }
    
    public function load_tab_content() {
        if (!wp_verify_nonce($_POST['nonce'], 'spd_nonce') || !is_user_logged_in()) {
            wp_die(json_encode(array(
                'success' => false,
                'message' => 'Security check failed'
            )));
        }
        
        $tab_slug = sanitize_text_field($_POST['tab_slug']);
        $tab = SPD_Database::get_tab($tab_slug);
        
        if (!$tab) {
            wp_die(json_encode(array(
                'success' => false,
                'message' => 'Tab not found'
            )));
        }
        
        $content = '';
        if ($tab->content_type === 'shortcode') {
            $content = do_shortcode($tab->content_value);
        } elseif ($tab->content_type === 'url') {
            $content = '<iframe src="' . esc_url($tab->content_value) . '" style="width: 100%; height: 600px; border: none;"></iframe>';
        }
        
        wp_die(json_encode(array(
            'success' => true,
            'data' => array(
                'tab_name' => $tab->tab_name,
                'content' => $content
            )
        )));
    }
}
