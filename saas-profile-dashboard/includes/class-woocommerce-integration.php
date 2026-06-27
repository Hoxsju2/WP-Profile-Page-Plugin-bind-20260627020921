<?php

class SPD_WooCommerce_Integration {
    
    public function __construct() {
        if (class_exists('WooCommerce')) {
            add_action('init', array($this, 'init'));
        }
    }
    
    public function init() {
        add_shortcode('spd_woo_orders', array($this, 'orders_shortcode'));
        add_shortcode('spd_woo_wishlist', array($this, 'wishlist_shortcode'));
        add_shortcode('spd_woo_downloads', array($this, 'downloads_shortcode'));
    }
    
    public function orders_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to view your orders.', 'saas-profile-dashboard') . '</p>';
        }
        
        $atts = shortcode_atts(array(
            'limit' => 10,
            'status' => 'any'
        ), $atts);
        
        $orders = wc_get_orders(array(
            'customer_id' => get_current_user_id(),
            'limit' => intval($atts['limit']),
            'status' => $atts['status']
        ));
        
        ob_start();
        ?>
        <div class="spd-woo-orders">
            <h3><?php _e('Your Orders', 'saas-profile-dashboard'); ?></h3>
            
            <?php if ($orders) : ?>
                <div class="spd-orders-table">
                    <div class="spd-orders-header">
                        <div class="order-number"><?php _e('Order', 'saas-profile-dashboard'); ?></div>
                        <div class="order-date"><?php _e('Date', 'saas-profile-dashboard'); ?></div>
                        <div class="order-status"><?php _e('Status', 'saas-profile-dashboard'); ?></div>
                        <div class="order-total"><?php _e('Total', 'saas-profile-dashboard'); ?></div>
                        <div class="order-actions"><?php _e('Actions', 'saas-profile-dashboard'); ?></div>
                    </div>
                    
                    <?php foreach ($orders as $order) : ?>
                        <div class="spd-order-row">
                            <div class="order-number">#<?php echo $order->get_order_number(); ?></div>
                            <div class="order-date"><?php echo $order->get_date_created()->format('M j, Y'); ?></div>
                            <div class="order-status">
                                <span class="status-badge status-<?php echo $order->get_status(); ?>">
                                    <?php echo wc_get_order_status_name($order->get_status()); ?>
                                </span>
                            </div>
                            <div class="order-total"><?php echo $order->get_formatted_order_total(); ?></div>
                            <div class="order-actions">
                                <a href="<?php echo $order->get_view_order_url(); ?>" class="spd-btn spd-btn-sm">
                                    <?php _e('View', 'saas-profile-dashboard'); ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p><?php _e('No orders found.', 'saas-profile-dashboard'); ?></p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function downloads_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to view your downloads.', 'saas-profile-dashboard') . '</p>';
        }
        
        $downloads = WC()->customer->get_downloadable_products();
        
        ob_start();
        ?>
        <div class="spd-woo-downloads">
            <h3><?php _e('Your Downloads', 'saas-profile-dashboard'); ?></h3>
            
            <?php if ($downloads) : ?>
                <div class="spd-downloads-grid">
                    <?php foreach ($downloads as $download) : ?>
                        <div class="spd-download-item">
                            <h4><?php echo esc_html($download['product_name']); ?></h4>
                            <p class="download-remaining">
                                <?php
                                if ($download['downloads_remaining']) {
                                    printf(__('Downloads remaining: %s', 'saas-profile-dashboard'), $download['downloads_remaining']);
                                } else {
                                    _e('Downloads remaining: Unlimited', 'saas-profile-dashboard');
                                }
                                ?>
                            </p>
                            <a href="<?php echo esc_url($download['download_url']); ?>" class="spd-btn spd-btn-primary">
                                <?php _e('Download', 'saas-profile-dashboard'); ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p><?php _e('No downloads available.', 'saas-profile-dashboard'); ?></p>';
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function wishlist_shortcode($atts) {
        // Basic wishlist implementation using user meta
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to view your wishlist.', 'saas-profile-dashboard') . '</p>';
        }
        
        $user_id = get_current_user_id();
        $wishlist = get_user_meta($user_id, 'spd_wishlist', true);
        
        if (!is_array($wishlist)) {
            $wishlist = array();
        }
        
        ob_start();
        ?>
        <div class="spd-wishlist">
            <h3><?php _e('Your Wishlist', 'saas-profile-dashboard'); ?></h3>
            
            <?php if ($wishlist) : ?>
                <div class="spd-wishlist-grid">
                    <?php foreach ($wishlist as $product_id) : 
                        $product = wc_get_product($product_id);
                        if ($product) : ?>
                            <div class="spd-wishlist-item">
                                <div class="product-image">
                                    <?php echo $product->get_image(); ?>
                                </div>
                                <h4><?php echo $product->get_name(); ?></h4>
                                <p class="price"><?php echo $product->get_price_html(); ?></p>
                                <div class="wishlist-actions">
                                    <a href="<?php echo $product->get_permalink(); ?>" class="spd-btn spd-btn-primary">
                                        <?php _e('View Product', 'saas-profile-dashboard'); ?>
                                    </a>
                                    <button type="button" class="spd-btn spd-btn-secondary spd-remove-wishlist" data-product-id="<?php echo $product_id; ?>">
                                        <?php _e('Remove', 'saas-profile-dashboard'); ?>
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p><?php _e('Your wishlist is empty.', 'saas-profile-dashboard'); ?></p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
