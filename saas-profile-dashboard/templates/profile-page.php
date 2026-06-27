<?php
get_header();

$current_tab = get_query_var('spd_tab') ?: 'dashboard';
$tabs = SPD_Database::get_tabs();
$current_user = wp_get_current_user();

// Redirect if not logged in
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url(home_url('/profile/')));
    exit;
}
?>

<div class="spd-profile-wrapper">
    <div class="spd-profile-container">
        <!-- Sidebar -->
        <div class="spd-profile-sidebar">
            <div class="spd-profile-header">
                <div class="spd-avatar">
                    <?php echo get_avatar(get_current_user_id(), 80); ?>
                </div>
                <div class="spd-user-info">
                    <h3><?php echo esc_html($current_user->display_name); ?></h3>
                    <p class="spd-user-email"><?php echo esc_html($current_user->user_email); ?></p>
                </div>
            </div>
            
            <nav class="spd-profile-nav">
                <ul>
                    <?php if ($tabs && !empty($tabs)) : ?>
                        <?php foreach ($tabs as $tab) : ?>
                            <li class="<?php echo ($current_tab === $tab->tab_slug) ? 'active' : ''; ?>">
                                <a href="#" data-tab="<?php echo esc_attr($tab->tab_slug); ?>" class="spd-tab-link">
                                    <?php if ($tab->icon) : ?>
                                        <?php if ($tab->icon_type === 'upload') : ?>
                                            <img src="<?php echo esc_url($tab->icon); ?>" alt="" class="spd-tab-icon-img">
                                        <?php else : ?>
                                            <i class="<?php echo esc_attr($tab->icon); ?>"></i>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <span><?php echo esc_html($tab->tab_name); ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <li class="active">
                            <a href="#" data-tab="dashboard" class="spd-tab-link">
                                <i class="dashicons dashicons-dashboard"></i>
                                <span><?php _e('Dashboard', 'saas-profile-dashboard'); ?></span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            
            <div class="spd-sidebar-footer">
                <a href="<?php echo wp_logout_url(home_url()); ?>" class="spd-logout-link">
                    <i class="dashicons dashicons-exit"></i>
                    <?php _e('Sign Out', 'saas-profile-dashboard'); ?>
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="spd-profile-main">
            <div class="spd-profile-header-main">
                <h1 id="spd-current-tab-title">
                    <?php
                    $current_tab_obj = SPD_Database::get_tab($current_tab);
                    echo $current_tab_obj ? esc_html($current_tab_obj->tab_name) : __('Dashboard', 'saas-profile-dashboard');
                    ?>
                </h1>
            </div>
            
            <div class="spd-profile-content">
                <div id="spd-tab-content" class="spd-tab-content">
                    <!-- Content will be loaded here via AJAX -->
                    <div class="spd-loading">
                        <div class="spd-spinner"></div>
                        <p><?php _e('Loading...', 'saas-profile-dashboard'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Load initial tab content
    loadTabContent('<?php echo esc_js($current_tab); ?>');
    
    // Handle tab clicks
    document.querySelectorAll('.spd-tab-link').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const tabSlug = this.getAttribute('data-tab');
            
            // Update active tab
            document.querySelectorAll('.spd-profile-nav li').forEach(function(li) {
                li.classList.remove('active');
            });
            this.parentElement.classList.add('active');
            
            // Load tab content
            loadTabContent(tabSlug);
            
            // Update URL
            if (typeof history.pushState === 'function') {
                history.pushState(null, '', '/profile/' + tabSlug);
            }
        });
    });
});

function loadTabContent(tabSlug) {
    const contentArea = document.getElementById('spd-tab-content');
    const titleElement = document.getElementById('spd-current-tab-title');
    
    if (!contentArea || typeof spd_ajax === 'undefined') {
        return;
    }
    
    // Show loading
    contentArea.innerHTML = '<div class="spd-loading"><div class="spd-spinner"></div><p><?php _e('Loading...', 'saas-profile-dashboard'); ?></p></div>';
    
    // AJAX request
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
            if (titleElement) {
                titleElement.textContent = data.data.tab_name;
            }
        } else {
            contentArea.innerHTML = '<div class="spd-error"><p><?php _e('Error loading content.', 'saas-profile-dashboard'); ?></p></div>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        contentArea.innerHTML = '<div class="spd-error"><p><?php _e('Error loading content.', 'saas-profile-dashboard'); ?></p></div>';
    });
}
</script>

<?php get_footer(); ?>
