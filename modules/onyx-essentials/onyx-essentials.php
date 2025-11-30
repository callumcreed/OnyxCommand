<?php
/**
 * Onyx Essentials Module for Onyx Command
 * 
 * Module ID: onyx-essentials
 * Module Name: Onyx Essentials
 * Description: Comprehensive security, optimization, and maintenance tools for WordPress
 * Version: 1.0.0
 * Author: Callum Creed
 * 
 * INSTALLATION INSTRUCTIONS:
 * 1. Create directory: C:\Users\castl\Documents\GitHub\OnyxCommand\OnyxCommand\modules\onyx-essentials
 * 2. Place this file there as: onyx-essentials.php
 * 3. Create subdirectories: templates, assets\css, assets\js
 * 4. Place corresponding template and asset files in those directories
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main Onyx Essentials Module Class
 */
class OC_Onyx_Essentials {
    
    private static $instance = null;
    private $options_key = 'oc_essentials_options';
    private $locked_ips_key = 'oc_locked_ips';
    private $locked_users_key = 'oc_locked_users';
    private $login_attempts_key = 'oc_login_attempts';
    private $blocked_records_key = 'oc_blocked_records';
    private $permanent_blocks_key = 'oc_permanent_blocks';
    private $backup_queue_key = 'oc_backup_queue';
    private $backup_cleanup_hook = 'oc_essentials_cleanup_backups';
    private $lock_duration = 259200; // 72 hours
    private $login_alias_var = 'oc_login_alias';
    private $admin_alias_var = 'oc_admin_alias';
    private $announcement_defaults = array(
        'enabled' => 1,
        'text' => '',
        'bg' => '#111111',
        'color' => '#ffffff',
        'font_family' => 'inherit',
        'font_size' => 16,
        'marquee' => 0,
        'button_text' => '',
        'button_url' => '',
        'button_bg' => '#ffffff',
        'button_color' => '#111111',
        'start_at' => '',
        'disable_on' => '',
        'hide_desktop' => 0,
        'hide_tablet' => 0,
        'hide_mobile' => 0,
    );

    private function get_default_announcement_bar() {
        return $this->normalize_saved_announcement_bar($this->announcement_defaults);
    }

    private function recursive_unslash($value) {
        if (is_array($value)) {
            return array_map(array($this, 'recursive_unslash'), $value);
        }
        return wp_unslash($value);
    }

    private function convert_input_datetime_to_gmt($value) {
        if (empty($value)) {
            return '';
        }

        $value = sanitize_text_field($value);
        $value = str_replace('T', ' ', $value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}$/', $value)) {
            $value .= ':00';
        }

        $gmt = get_gmt_from_date($value, 'Y-m-d H:i:s');
        return $gmt ? $gmt : '';
    }

    private function normalize_saved_announcement_bar($bar) {
        $bar = wp_parse_args((array) $bar, $this->announcement_defaults);

        $bar['enabled'] = !empty($bar['enabled']) ? 1 : 0;
        $bar['text'] = wp_kses_post($bar['text']);
        $bar['bg'] = $this->sanitize_color($bar['bg'], '#111111');
        $bar['color'] = $this->sanitize_color($bar['color'], '#ffffff');
        $bar['font_family'] = sanitize_text_field($bar['font_family']);
        $bar['font_size'] = max(10, min(72, intval($bar['font_size'])));
        $bar['marquee'] = !empty($bar['marquee']) ? 1 : 0;
        $bar['button_text'] = sanitize_text_field($bar['button_text']);
        $bar['button_url'] = !empty($bar['button_url']) ? esc_url_raw($bar['button_url']) : '';
        $bar['button_bg'] = $this->sanitize_color($bar['button_bg'], '#ffffff');
        $bar['button_color'] = $this->sanitize_color($bar['button_color'], '#111111');
        $bar['start_at'] = !empty($bar['start_at']) ? sanitize_text_field($bar['start_at']) : '';
        $bar['disable_on'] = !empty($bar['disable_on']) ? sanitize_text_field($bar['disable_on']) : '';
        $bar['hide_desktop'] = !empty($bar['hide_desktop']) ? 1 : 0;
        $bar['hide_tablet'] = !empty($bar['hide_tablet']) ? 1 : 0;
        $bar['hide_mobile'] = !empty($bar['hide_mobile']) ? 1 : 0;

        return $bar;
    }

    private function sanitize_announcement_bar_input($bar) {
        $bar = $this->recursive_unslash($bar);
        $bar['start_at'] = isset($bar['start_at']) ? $this->convert_input_datetime_to_gmt($bar['start_at']) : '';
        $bar['disable_on'] = isset($bar['disable_on']) ? $this->convert_input_datetime_to_gmt($bar['disable_on']) : '';

        return $this->normalize_saved_announcement_bar($bar);
    }

    private function sanitize_announcement_bars_from_post($bars_input) {
        $sanitized = array();

        foreach ($bars_input as $bar) {
            if (!is_array($bar)) {
                continue;
            }

            $clean = $this->sanitize_announcement_bar_input($bar);

            if ('' === trim(wp_strip_all_tags($clean['text']))) {
                continue;
            }

            $sanitized[] = $clean;
        }

        return $sanitized;
    }

    private function get_announcement_bars($options = null) {
        if ($options === null) {
            $options = get_option($this->options_key, array());
        }

        $bars = array();

        if (!empty($options['announcement_bars']) && is_array($options['announcement_bars'])) {
            foreach ($options['announcement_bars'] as $bar) {
                $bars[] = $this->normalize_saved_announcement_bar($bar);
            }
        } elseif (!empty($options['announcement_text'])) {
            $legacy = array(
                'enabled' => !empty($options['announcement_bar_enabled']),
                'text' => $options['announcement_text'],
                'bg' => isset($options['announcement_bg']) ? $options['announcement_bg'] : '#111111',
                'color' => isset($options['announcement_text_color']) ? $options['announcement_text_color'] : '#ffffff',
                'font_family' => isset($options['announcement_font_family']) ? $options['announcement_font_family'] : 'inherit',
                'font_size' => isset($options['announcement_font_size']) ? $options['announcement_font_size'] : 16,
                'marquee' => !empty($options['announcement_marquee']),
                'button_text' => isset($options['announcement_button_text']) ? $options['announcement_button_text'] : '',
                'button_url' => isset($options['announcement_button_url']) ? $options['announcement_button_url'] : '',
                'button_bg' => isset($options['announcement_button_bg']) ? $options['announcement_button_bg'] : '#ffffff',
                'button_color' => isset($options['announcement_button_color']) ? $options['announcement_button_color'] : '#111111',
                'start_at' => isset($options['announcement_start_at']) ? $options['announcement_start_at'] : '',
                'disable_on' => isset($options['announcement_disable_on']) ? $options['announcement_disable_on'] : '',
                'hide_desktop' => !empty($options['announcement_hide_desktop']),
                'hide_tablet' => !empty($options['announcement_hide_tablet']),
                'hide_mobile' => !empty($options['announcement_hide_mobile']),
            );

            $bars[] = $this->normalize_saved_announcement_bar($legacy);
        }

        return $bars;
    }

    private function has_active_announcement_bars($options = null) {
        $bars = $this->get_announcement_bars($options);

        if (empty($bars)) {
            return false;
        }

        $now_gmt = current_time('timestamp', true);

        foreach ($bars as $bar) {
            if (!$bar['enabled']) {
                continue;
            }

            if ($this->announcement_bar_is_within_schedule($bar, $now_gmt)) {
                return true;
            }
        }

        return false;
    }

    private function announcement_bar_is_within_schedule($bar, $now_gmt) {
        $start_ts = !empty($bar['start_at']) ? strtotime($bar['start_at'] . ' UTC') : 0;
        $end_ts = !empty($bar['disable_on']) ? strtotime($bar['disable_on'] . ' UTC') : 0;

        if ($start_ts && $now_gmt < $start_ts) {
            return false;
        }

        if ($end_ts && $now_gmt > $end_ts) {
            return false;
        }

        return true;
    }

    public function force_block_editor($settings) {
        $settings['editor'] = 'block';
        $settings['allow-users'] = 0;
        return $settings;
    }

    public function apply_default_builder($settings) {
        $options = get_option($this->options_key, array());
        $default = !empty($options['builder_default']) ? sanitize_text_field($options['builder_default']) : 'block';
        $settings['editor'] = $default === 'classic' ? 'classic' : 'block';
        return $settings;
    }

    public function add_featured_image_list_column($columns) {
        if (isset($columns['oc_featured_image'])) {
            return $columns;
        }

        $featured_column = array('oc_featured_image' => __('Featured', 'oc-essentials'));

        if (isset($columns['cb'])) {
            $cb_column = array('cb' => $columns['cb']);
            unset($columns['cb']);
            return $cb_column + $featured_column + $columns;
        }

        return $featured_column + $columns;
    }

    public function render_featured_image_list_column($column, $post_id) {
        if ('oc_featured_image' !== $column) {
            return;
        }

        if (has_post_thumbnail($post_id)) {
            echo '<span class="oc-featured-thumb">' . get_the_post_thumbnail($post_id, array(80, 80), array('loading' => 'lazy')) . '</span>';
            return;
        }

        echo '<span class="oc-featured-thumb oc-featured-thumb--empty">' . esc_html__('None', 'oc-essentials') . '</span>';
    }

    public function featured_image_list_column_styles() {
        if (!function_exists('get_current_screen')) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, array('edit-post', 'edit-page'), true)) {
            return;
        }
        ?>
        <style>
            .column-oc_featured_image {
                width: 90px;
                text-align: center;
            }
            .oc-featured-thumb {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 72px;
                height: 72px;
            }
            .oc-featured-thumb img {
                width: 72px;
                height: 72px;
                object-fit: cover;
                border-radius: 6px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.18);
            }
            .oc-featured-thumb--empty {
                border: 1px dashed #c3c4c7;
                color: #7c848c;
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }
        </style>
        <?php
    }

    public function maybe_show_clone_notice() {
        if (!is_admin() || empty($_GET['oc_clone_created'])) {
            return;
        }

        $post_id = intval($_GET['oc_clone_created']);
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            return;
        }

        $post = get_post($post_id);
        if (!$post) {
            return;
        }

        $link = add_query_arg(
            array(
                'post' => $post_id,
                'action' => 'edit'
            ),
            admin_url('post.php')
        );
        ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                echo wp_kses_post(
                    sprintf(
                        __('Draft clone <strong>%1$s</strong> created successfully. <a href="%2$s">Open in editor</a>', 'oc-essentials'),
                        esc_html($post->post_title),
                        esc_url($link)
                    )
                );
                ?>
            </p>
        </div>
        <?php
    }
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Admin hooks
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('admin_menu', array($this, 'add_blocked_accounts_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('restrict_manage_posts', array($this, 'render_media_library_toolbar'));
        add_action('show_user_profile', array($this, 'render_unlock_password_field'));
        add_action('edit_user_profile', array($this, 'render_unlock_password_field'));
        add_action('user_profile_update_errors', array($this, 'validate_unlock_password_field'), 10, 3);
        add_action('personal_options_update', array($this, 'save_unlock_password_field'));
        add_action('edit_user_profile_update', array($this, 'save_unlock_password_field'));
        add_action('admin_notices', array($this, 'maybe_show_clone_notice'));
        
        // Load enabled features
        $this->load_enabled_features();
        
        // AJAX handlers
        add_action('wp_ajax_oc_essentials_action', array($this, 'handle_ajax_actions'));
        add_action('wp_ajax_oc_download_database', array($this, 'download_database'));
        add_action('wp_ajax_oc_download_site', array($this, 'download_site'));
        add_action('wp_ajax_oc_download_files_only', array($this, 'download_files_only'));
        add_action('wp_ajax_oc_regenerate_thumbnails', array($this, 'regenerate_thumbnails'));
        add_action('wp_ajax_oc_delete_all_thumbnails', array($this, 'delete_all_thumbnails'));
        add_action('wp_ajax_oc_delete_unattached', array($this, 'delete_unattached_media'));
        add_action('wp_ajax_oc_clear_cache', array($this, 'clear_site_cache'));
        add_action('wp_ajax_oc_scan_orphaned', array($this, 'scan_orphaned_content'));
        add_action('wp_ajax_oc_scan_meta', array($this, 'scan_missing_meta'));
        add_action('wp_ajax_oc_scan_alt_text', array($this, 'scan_missing_alt_text'));
        add_action('wp_ajax_oc_generate_alt_from_scan', array($this, 'generate_alt_via_ai'));
        add_action('wp_ajax_oc_rewrite_content', array($this, 'rewrite_highlighted_content'));
        add_action('wp_ajax_oc_unlock_ip', array($this, 'unlock_ip'));
        add_action('wp_ajax_oc_unlock_user', array($this, 'unlock_user'));
        add_action('wp_ajax_oc_upload_ssl', array($this, 'upload_ssl_certificate'));
        add_action('wp_ajax_oc_submit_nonindexed', array($this, 'submit_nonindexed_content'));
        add_action('wp_ajax_oc_block_record_action', array($this, 'handle_blocked_record_action'));
        add_action('wp_ajax_oc_scan_broken_urls', array($this, 'scan_broken_urls'));
        
        // Post meta box for comments
        add_action('add_meta_boxes', array($this, 'add_comment_meta_box'));
        add_action('add_meta_boxes', array($this, 'add_broken_url_metabox'));
        add_action('save_post', array($this, 'save_comment_meta'));
        
        // Post/page cache clearing
        add_action('post_updated', array($this, 'clear_post_cache'), 10, 3);

        // General hooks
        add_action('init', array($this, 'prune_expired_locks'));
        add_action('init', array($this, 'schedule_backup_cleanup'));
        add_action('login_form', array($this, 'render_unlock_password_input'));
        add_filter('query_vars', array($this, 'register_custom_query_vars'));
        add_action('init', array($this, 'register_alias_rewrite_rules'));
        add_action('login_enqueue_scripts', array($this, 'brand_login_screen'));
        add_filter('login_headerurl', array($this, 'filter_login_header_url'));
        add_filter('login_headertext', array($this, 'filter_login_header_text'));
        add_filter('wp_robots', array($this, 'maybe_noindex_drafts'), 10, 2);
        add_filter('post_row_actions', array($this, 'add_clone_link'), 10, 2);
        add_filter('page_row_actions', array($this, 'add_clone_link'), 10, 2);
        add_action('admin_action_oc_clone_post', array($this, 'handle_clone_action'));
        add_filter('get_the_author_display_name', array($this, 'maybe_override_author_display'), 10, 2);
        add_filter('the_author', array($this, 'maybe_override_author_display'), 10, 2);
        add_action('save_post', array($this, 'auto_clear_cache'), 20, 2);
        add_action('add_attachment', array($this, 'auto_clear_cache'));
        add_action('upgrader_process_complete', array($this, 'auto_clear_cache_after_upgrade'), 10, 2);
        add_action($this->backup_cleanup_hook, array($this, 'cleanup_backup_queue'));
    }
    
    /**
     * Load enabled features based on settings
     */
    private function load_enabled_features() {
        $options = get_option($this->options_key, array());
        
        // Feature 3: Disable file editing
        if (!empty($options['disable_file_editing'])) {
            add_action('init', array($this, 'disable_file_editing'));
        }

        if (!empty($options['disable_theme_file_editing'])) {
            add_action('admin_menu', array($this, 'disable_theme_file_editing'), 199);
        }
        
        // Feature 4: Disable XML-RPC
        if (!empty($options['disable_xmlrpc'])) {
            add_filter('xmlrpc_enabled', '__return_false');
            add_filter('wp_headers', array($this, 'remove_xmlrpc_headers'));
        }
        
        // Feature 5: Disable wp-config editing
        if (!empty($options['disable_wpconfig_edit'])) {
            add_action('init', array($this, 'disable_wpconfig_editing'));
        }
        
        // Feature 6: Hide WordPress version
        if (!empty($options['hide_wp_version'])) {
            remove_action('wp_head', 'wp_generator');
            add_filter('the_generator', '__return_empty_string');
            add_filter('style_loader_src', array($this, 'remove_version_strings'), 9999);
            add_filter('script_loader_src', array($this, 'remove_version_strings'), 9999);
            add_action('admin_footer', array($this, 'hide_footer_upgrade_div'), 999);
        }
        
        // Feature 7: Keep admins logged in
        if (!empty($options['keep_admin_logged_in'])) {
            add_filter('auth_cookie_expiration', array($this, 'extend_admin_login'), 10, 3);
        }
        
        // Feature 9: Disable comments sitewide
        if (!empty($options['disable_comments'])) {
            add_action('admin_init', array($this, 'disable_comments_admin'));
            add_filter('comments_open', array($this, 'filter_comments_status'), 20, 2);
            add_filter('pings_open', '__return_false');
            add_action('admin_menu', array($this, 'remove_comment_menu'));
            add_action('init', array($this, 'remove_comment_support'));
        }
        
        // Feature 12: Enforce strong passwords
        if (!empty($options['enforce_strong_passwords'])) {
            add_action('user_profile_update_errors', array($this, 'validate_strong_password'), 10, 3);
            add_action('validate_password_reset', array($this, 'validate_strong_password_reset'), 10, 2);
            add_action('registration_errors', array($this, 'validate_registration_password'), 10, 3);
            add_filter('wp_authenticate_user', array($this, 'enforce_password_on_login'), 10, 2);
            add_action('password_reset', array($this, 'clear_password_reset_flag'), 10, 2);
        }
        
        // Feature 13 & 14: Login attempt limiting and blocking
        if (!empty($options['limit_login_attempts']) || !empty($options['block_invalid_usernames'])) {
            add_action('wp_login_failed', array($this, 'handle_login_failure'));
            add_filter('authenticate', array($this, 'check_login_attempts'), 30, 3);
        }
        
        // Feature 24: Disable emojis
        if (!empty($options['disable_emojis'])) {
            add_action('init', array($this, 'disable_emojis'));
        }

        if (!empty($options['under_construction'])) {
            add_action('template_redirect', array($this, 'maybe_show_under_construction'));
        }

        if ($this->has_active_announcement_bars($options)) {
            $hook = has_action('wp_body_open') ? 'wp_body_open' : 'wp_footer';
            add_action($hook, array($this, 'render_announcement_bar'));
        }

        if (!empty($options['hotlink_protection'])) {
            add_action('template_redirect', array($this, 'enforce_hotlink_protection'), 0);
        }

        if (!empty($options['disable_right_click'])) {
            add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        }

        if (!empty($options['draft_preview_share'])) {
            add_filter('map_meta_cap', array($this, 'grant_preview_access'), 10, 4);
        }

        if (!empty($options['disable_gutenberg'])) {
            add_filter('use_block_editor_for_post_type', '__return_false', 100);
        }

        if (!empty($options['disable_classic_editor'])) {
            add_filter('classic_editor_plugin_settings', array($this, 'force_block_editor'));
        }

        if (!empty($options['builder_default'])) {
            add_filter('classic_editor_plugin_settings', array($this, 'apply_default_builder'), 20);
        }

        if (!empty($options['featured_image_column'])) {
            add_filter('manage_edit-post_columns', array($this, 'add_featured_image_list_column'));
            add_filter('manage_edit-page_columns', array($this, 'add_featured_image_list_column'));
            add_action('manage_post_posts_custom_column', array($this, 'render_featured_image_list_column'), 10, 2);
            add_action('manage_page_posts_custom_column', array($this, 'render_featured_image_list_column'), 10, 2);
            add_action('admin_head-edit.php', array($this, 'featured_image_list_column_styles'));
        }

        if (!empty($options['custom_login_slug'])) {
            add_action('template_redirect', array($this, 'maybe_handle_custom_login_alias'));
            add_filter('login_url', array($this, 'filter_login_url'), 10, 3);
            add_filter('site_url', array($this, 'filter_login_site_url'), 10, 4);
            add_filter('network_site_url', array($this, 'filter_login_site_url'), 10, 4);
            add_action('login_init', array($this, 'maybe_block_default_login'), 0);
        }

        if (!empty($options['custom_admin_slug'])) {
            add_action('template_redirect', array($this, 'maybe_handle_admin_alias_request'));
        }
    }
    
    /**
     * Add admin menu page
     */
    public function add_menu_page() {
        add_submenu_page(
            'onyx-command',
            'Onyx Essentials',
            'Onyx Essentials',
            'manage_options',
            'onyx-essentials',
            array($this, 'render_settings_page')
        );
    }

    public function add_blocked_accounts_menu() {
        add_submenu_page(
            'onyx-command',
            'Blocked Accounts',
            'Blocked Accounts',
            'manage_options',
            'oc-blocked-accounts',
            array($this, 'render_blocked_accounts_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('oc_essentials_group', $this->options_key);
    }

    public function render_unlock_password_field($user) {
        if (!current_user_can('edit_user', $user->ID)) {
            return;
        }

        $has_password = (bool) get_user_meta($user->ID, 'oc_unlock_password_hash', true);
        ?>
        <h2><?php esc_html_e('Onyx Essentials Security', 'oc-essentials'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="oc_unlock_password_new"><?php esc_html_e('Unlock Password', 'oc-essentials'); ?></label></th>
                <td>
                    <input type="password" name="oc_unlock_password_new" id="oc_unlock_password_new" class="regular-text" autocomplete="new-password">
                    <p class="description">
                        <?php esc_html_e('Set an optional unlock password that can be used to immediately lift a temporary lock on your account from the login screen.', 'oc-essentials'); ?>
                    </p>
                    <input type="password" name="oc_unlock_password_confirm" id="oc_unlock_password_confirm" class="regular-text" autocomplete="new-password" placeholder="<?php esc_attr_e('Confirm unlock password', 'oc-essentials'); ?>">
                    <?php if ($has_password) : ?>
                        <p>
                            <label>
                                <input type="checkbox" name="oc_unlock_password_remove" value="1">
                                <?php esc_html_e('Remove existing unlock password', 'oc-essentials'); ?>
                            </label>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }

    public function validate_unlock_password_field($errors, $update, $user) {
        if (empty($_POST['oc_unlock_password_new'])) {
            return;
        }

        $password = sanitize_text_field(wp_unslash($_POST['oc_unlock_password_new']));
        $confirm = isset($_POST['oc_unlock_password_confirm']) ? sanitize_text_field(wp_unslash($_POST['oc_unlock_password_confirm'])) : '';

        if ($password !== $confirm) {
            $errors->add('oc_unlock_password_mismatch', __('<strong>Error:</strong> Unlock passwords do not match.', 'oc-essentials'));
        }
    }

    public function save_unlock_password_field($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }

        if (!empty($_POST['oc_unlock_password_remove'])) {
            delete_user_meta($user_id, 'oc_unlock_password_hash');
        }

        if (!empty($_POST['oc_unlock_password_new']) && !empty($_POST['oc_unlock_password_confirm'])) {
            $password = sanitize_text_field(wp_unslash($_POST['oc_unlock_password_new']));
            $confirm = sanitize_text_field(wp_unslash($_POST['oc_unlock_password_confirm']));

            if ($password === $confirm) {
                update_user_meta($user_id, 'oc_unlock_password_hash', wp_hash_password($password));
            }
        }
    }

    public function render_unlock_password_input() {
        ?>
        <p>
            <label for="oc_unlock_password"><?php esc_html_e('Unlock Password (optional)', 'oc-essentials'); ?></label>
            <input type="password" name="oc_unlock_password" id="oc_unlock_password" class="input" size="20" autocomplete="off">
        </p>
        <?php
    }

    public function register_custom_query_vars($vars) {
        $vars[] = $this->login_alias_var;
        $vars[] = $this->admin_alias_var;
        return $vars;
    }

    public function register_alias_rewrite_rules() {
        $options = get_option($this->options_key, array());

        if (!empty($options['custom_login_slug'])) {
            $slug = $this->normalize_slug($options['custom_login_slug']);
            if ($slug) {
                add_rewrite_rule('^' . preg_quote($slug, '/') . '/?$', 'index.php?' . $this->login_alias_var . '=1', 'top');
            }
        }

        if (!empty($options['custom_admin_slug'])) {
            $slug = $this->normalize_slug($options['custom_admin_slug']);
            if ($slug) {
                add_rewrite_rule('^' . preg_quote($slug, '/') . '/?$', 'index.php?' . $this->admin_alias_var . '=1', 'top');
            }
        }
    }

    private function normalize_slug($value) {
        if (empty($value)) {
            return '';
        }
        return trim(sanitize_title_with_dashes($value));
    }

    private function get_custom_login_slug() {
        $options = get_option($this->options_key, array());
        return !empty($options['custom_login_slug']) ? $this->normalize_slug($options['custom_login_slug']) : '';
    }

    private function get_custom_admin_slug() {
        $options = get_option($this->options_key, array());
        return !empty($options['custom_admin_slug']) ? $this->normalize_slug($options['custom_admin_slug']) : '';
    }

    public function maybe_handle_custom_login_alias() {
        if (intval(get_query_var($this->login_alias_var)) !== 1) {
            return;
        }

        define('OC_CUSTOM_LOGIN_REQUEST', true);
        require_once ABSPATH . 'wp-login.php';
        exit;
    }

    public function maybe_block_default_login() {
        $slug = $this->get_custom_login_slug();
        if (!$slug || defined('OC_CUSTOM_LOGIN_REQUEST')) {
            return;
        }

        if (!isset($_GET['interim-login'])) {
            $query = '';
            if (!empty($_SERVER['QUERY_STRING'])) {
                $raw_query = wp_unslash($_SERVER['QUERY_STRING']);
                $query = '?' . ltrim($raw_query, '?');
            }
            wp_safe_redirect(home_url(trailingslashit($slug)) . $query);
            exit;
        }
    }

    public function filter_login_url($login_url, $redirect, $force_reauth) {
        $slug = $this->get_custom_login_slug();
        if (!$slug) {
            return $login_url;
        }

        $url = home_url(trailingslashit($slug));

        if (!empty($redirect)) {
            $url = add_query_arg('redirect_to', rawurlencode($redirect), $url);
        }

        if ($force_reauth) {
            $url = add_query_arg('reauth', '1', $url);
        }

        return $url;
    }

    public function filter_login_site_url($url, $path, $scheme, $blog_id) {
        $slug = $this->get_custom_login_slug();
        if (!$slug || false === strpos($url, 'wp-login.php')) {
            return $url;
        }

        $query = '';
        $parsed = wp_parse_url($url);
        if (!empty($parsed['query'])) {
            $query = '?' . $parsed['query'];
        }

        return home_url(trailingslashit($slug)) . $query;
    }

    public function filter_login_header_url($url) {
        $admin_slug = $this->get_custom_admin_slug();
        if ($admin_slug) {
            return home_url(trailingslashit($admin_slug));
        }
        return home_url('/');
    }

    public function filter_login_header_text($text) {
        return get_bloginfo('name');
    }

    public function brand_login_screen() {
        $options = get_option($this->options_key, array());
        $bg = isset($options['login_background_color']) ? $this->sanitize_color($options['login_background_color'], '#0f172a') : '#0f172a';
        $form_bg = isset($options['login_form_color']) ? $this->sanitize_color($options['login_form_color'], '#ffffff') : '#ffffff';
        $button_bg = isset($options['login_button_color']) ? $this->sanitize_color($options['login_button_color'], '#2563eb') : '#2563eb';
        $button_text = isset($options['login_button_text_color']) ? $this->sanitize_color($options['login_button_text_color'], '#ffffff') : '#ffffff';
        $custom_css = !empty($options['login_custom_css']) ? $options['login_custom_css'] : '';

        $logo_id = get_theme_mod('custom_logo');
        $logo_url = '';
        if ($logo_id) {
            $logo = wp_get_attachment_image_src($logo_id, 'full');
            if ($logo) {
                $logo_url = $logo[0];
            }
        }
        if (!$logo_url) {
            $logo_url = get_site_icon_url(256);
        }

        ?>
        <style>
            body.login {
                background: <?php echo esc_html($bg); ?>;
                background-image: radial-gradient(circle at top left, rgba(255,255,255,0.08), transparent 45%);
                color: #fff;
            }
            body.login #login {
                padding-top: 5vh;
            }
            body.login #login h1 a {
                <?php if ($logo_url) : ?>
                background-image: url('<?php echo esc_url($logo_url); ?>');
                <?php endif; ?>
                background-size: contain;
                width: 220px;
                height: 90px;
            }
            body.login .message, body.login #login_form, body.login .login form {
                border-radius: 12px;
                box-shadow: 0 20px 60px rgba(15,23,42,0.4);
                background: <?php echo esc_html($form_bg); ?>;
            }
            body.login .login form .input, body.login .login form input[type="text"],
            body.login .login form input[type="password"] {
                border-radius: 8px;
                border: 1px solid #d4d7e0;
                padding: 10px 14px;
            }
            body.login .wp-core-ui .button-primary {
                background: <?php echo esc_html($button_bg); ?>;
                border-color: <?php echo esc_html($button_bg); ?>;
                color: <?php echo esc_html($button_text); ?>;
                border-radius: 999px;
                text-transform: uppercase;
                letter-spacing: .05em;
                box-shadow: 0 10px 18px rgba(37,99,235,0.3);
            }
            body.login .wp-core-ui .button-primary:hover,
            body.login .wp-core-ui .button-primary:focus {
                opacity: 0.9;
            }
            <?php if (!empty($custom_css)) : ?>
            <?php echo wp_strip_all_tags($custom_css); ?>
            <?php endif; ?>
        </style>
        <?php
    }

    public function maybe_handle_admin_alias_request() {
        if (intval(get_query_var($this->admin_alias_var)) !== 1) {
            return;
        }

        if (!is_user_logged_in()) {
            auth_redirect();
        }

        wp_safe_redirect(admin_url());
        exit;
    }
    
    /**
     * Enqueue assets
     */
    public function enqueue_assets($hook) {
        $is_admin_screen = strpos($hook, 'onyx-essentials') !== false || strpos($hook, 'oc-blocked-accounts') !== false;
        $is_editor_screen = in_array($hook, array('post.php', 'post-new.php'), true);
        $is_media_library = $hook === 'upload.php';
        $module_url = plugin_dir_url(__FILE__);

        if ($is_admin_screen) {
            wp_enqueue_style('oc-essentials-style', $module_url . 'assets/css/essentials.css', array(), OC_VERSION);
            wp_enqueue_script('oc-essentials-script', $module_url . 'assets/js/essentials.js', array('jquery'), OC_VERSION, true);

            $has_ai_module = class_exists('AI_Alt_Tag_Manager');
            
            wp_localize_script('oc-essentials-script', 'ocEssentials', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('oc_essentials_nonce'),
                'ai_nonce' => $has_ai_module ? wp_create_nonce('ai_alt_tag_nonce') : '',
                'site_url' => home_url(),
                'admin_url' => admin_url(),
                'has_ai_module' => $has_ai_module,
                'google_nonce' => wp_create_nonce('oc_submit_nonindexed'),
                'blocked_nonce' => wp_create_nonce('oc_blocked_record_action'),
                'strings' => array(
                    'confirm_change_admin_username' => __('Changing the default admin username will immediately rename the selected account. Continue?', 'oc-essentials'),
                    'confirm_disable_file_editing' => __('Disabling file editing prevents non-admins from editing plugin files. Proceed?', 'oc-essentials'),
                    'confirm_disable_theme_editing' => __('This hides the built-in theme editor for non-admins. Continue?', 'oc-essentials'),
                    'confirm_delete_unattached' => __('This permanently deletes all unattached media files. This cannot be undone. Continue?', 'oc-essentials'),
                    'confirm_backup' => __('This process prepares a downloadable backup. It may take a few minutes. Continue?', 'oc-essentials'),
                    'confirm_cleanup_thumbs' => __('Remove orphaned thumbnail files? This cannot be undone.', 'oc-essentials'),
                    'confirm_regen_thumbs' => __('Regenerate all thumbnails? This may take several minutes.', 'oc-essentials'),
                    'confirm_delete_thumbs' => __('Delete all generated thumbnails? Original images will be preserved.', 'oc-essentials'),
                    'processing' => __('Processing...', 'oc-essentials'),
                    'completed' => __('Completed successfully.', 'oc-essentials'),
                    'error_generic' => __('Something went wrong. Please try again.', 'oc-essentials'),
                    'cleaning' => __('Cleaning...', 'oc-essentials'),
                    'regenerating' => __('Regenerating...', 'oc-essentials'),
                    'deleting' => __('Deleting...', 'oc-essentials'),
                    'clearing_cache_content' => __('Clearing cache for this content...', 'oc-essentials'),
                    'clearing_cache_site' => __('Clearing entire site cache...', 'oc-essentials'),
                    'cache_cleared_content' => __('Content cache cleared successfully!', 'oc-essentials'),
                    'cache_cleared_site' => __('Site cache cleared successfully!', 'oc-essentials')
                )
            ));
        }

        if ($is_editor_screen) {
            $post_id = 0;
            if (!empty($_GET['post'])) {
                $post_id = intval($_GET['post']);
            } else {
                global $post;
                if ($post && isset($post->ID)) {
                    $post_id = (int) $post->ID;
                }
            }

            wp_enqueue_script('oc-editor-tools', $module_url . 'assets/js/editor-tools.js', array('jquery'), '1.0.0', true);
            wp_localize_script('oc-editor-tools', 'ocEditorTools', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('oc_essentials_nonce'),
                'post_id' => $post_id,
                'strings' => array(
                    'scan_single' => __('Scan This Content', 'oc-essentials'),
                    'scanning' => __('Scanning...', 'oc-essentials'),
                    'no_broken' => __('No broken URLs found in this content.', 'oc-essentials'),
                    'error_generic' => __('Error: %s', 'oc-essentials'),
                    'network_error' => __('Network error. Please try again.', 'oc-essentials'),
                    'results_header' => __('Broken URLs detected in this content:', 'oc-essentials')
                ),
                'cache_strings' => array(
                    'clear_label' => __('Clear Cache for This Page', 'oc-essentials'),
                    'clearing' => __('Clearing cache...', 'oc-essentials'),
                    'success' => __('Content cache cleared successfully.', 'oc-essentials'),
                    'error' => __('Failed to clear cache for this content.', 'oc-essentials'),
                    'network_error' => __('Network error while clearing cache.', 'oc-essentials')
                )
            ));

            wp_localize_script('oc-editor-tools', 'ocAIRewriter', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('oc_essentials_nonce'),
                'post_id' => $post_id,
                'strings' => array(
                    'button_label' => __('Rewrite Highlighted Text', 'oc-essentials'),
                    'button_working' => __('Rewriting...', 'oc-essentials'),
                    'rewriting' => __('Contacting AI...', 'oc-essentials'),
                    'rewrite_done' => __('Rewritten text inserted.', 'oc-essentials'),
                    'replace_failed' => __('Unable to replace selected text. Please try again.', 'oc-essentials'),
                    'no_selection' => __('Highlight the sentence or paragraph you want to rewrite first.', 'oc-essentials'),
                    'error_generic' => __('Unable to rewrite this content right now.', 'oc-essentials'),
                    'network_error' => __('Network error. Please try again.', 'oc-essentials')
                )
            ));
        }

        if ($is_media_library) {
            wp_enqueue_style('oc-media-tools-style', $module_url . 'assets/css/media-library.css', array(), OC_VERSION);
            wp_enqueue_script('oc-media-tools-script', $module_url . 'assets/js/media-library.js', array('jquery'), OC_VERSION, true);
            wp_localize_script('oc-media-tools-script', 'ocMediaTools', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('oc_essentials_nonce'),
                'strings' => array(
                    'confirm_delete' => __('Delete all generated thumbnails? Originals will remain untouched.', 'oc-essentials'),
                    'confirm_regen' => __('Regenerate all thumbnails now? This may take several minutes.', 'oc-essentials'),
                    'processing' => __('Working...', 'oc-essentials'),
                    'complete' => __('Finished!', 'oc-essentials'),
                    'error' => __('Unable to complete the requested action.', 'oc-essentials'),
                    'network_error' => __('Network error. Please try again.', 'oc-essentials')
                )
            ));
        }
    }

    public function render_media_library_toolbar() {
        if (!current_user_can('upload_files')) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->id !== 'upload') {
            return;
        }

        static $rendered = false;
        if ($rendered) {
            return;
        }
        $rendered = true;
        ?>
        <div id="oc-media-tools-bar" class="oc-media-tools-bar">
            <button type="button" class="button button-secondary" data-action="oc_delete_all_thumbnails">
                <?php esc_html_e('Delete All Thumbnails', 'oc-essentials'); ?>
            </button>
            <button type="button" class="button button-primary" data-action="oc_regenerate_thumbnails">
                <?php esc_html_e('Regenerate All Thumbnails', 'oc-essentials'); ?>
            </button>
            <div id="oc-media-progress" class="oc-media-progress" aria-hidden="true">
                <div class="oc-media-progress-bar">
                    <span class="oc-media-progress-fill"></span>
                </div>
                <div class="oc-media-progress-text">
                    <?php esc_html_e('Ready', 'oc-essentials'); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Save settings
        $current_options = get_option($this->options_key, array());

        if (isset($_POST['oc_essentials_save']) && check_admin_referer('oc_essentials_settings')) {
            $options = array();
            $force_flush_rewrite = false;
            
            // Save each option
            $features = array(
                'ssl_check', 'disable_file_editing', 'disable_theme_file_editing', 'disable_xmlrpc',
                'disable_wpconfig_edit', 'hide_wp_version', 'keep_admin_logged_in',
                'change_admin_username', 'disable_comments', 'enforce_strong_passwords',
                'limit_login_attempts', 'block_invalid_usernames', 'disable_emojis',
                'under_construction', 'hotlink_protection', 'disable_right_click',
                'disable_gutenberg', 'disable_classic_editor', 'draft_preview_share',
                'featured_image_column'
            );
            
            foreach ($features as $feature) {
                $options[$feature] = isset($_POST[$feature]) ? 1 : 0;
            }
            
            // Save login attempt limit
            if (isset($_POST['login_attempt_limit'])) {
                $options['login_attempt_limit'] = intval($_POST['login_attempt_limit']);
            }
            
            // Handle admin username change
            if (!empty($_POST['new_admin_username']) && !empty($options['change_admin_username'])) {
                $result = $this->change_admin_username(sanitize_user($_POST['new_admin_username']));
                if ($result) {
                    echo '<div class="notice notice-success"><p>Admin username changed successfully!</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Failed to change admin username. Username may already exist.</p></div>';
                }
            }

            $bars_input = isset($_POST['announcement_bars']) && is_array($_POST['announcement_bars'])
                ? array_values($_POST['announcement_bars'])
                : array();

            $options['announcement_bars'] = $this->sanitize_announcement_bars_from_post($bars_input);

            if (!empty($options['announcement_bars'])) {
                $primary_bar = $options['announcement_bars'][0];
                $options['announcement_bar_enabled'] = $primary_bar['enabled'];
                $options['announcement_text'] = $primary_bar['text'];
                $options['announcement_bg'] = $primary_bar['bg'];
                $options['announcement_text_color'] = $primary_bar['color'];
                $options['announcement_font_family'] = $primary_bar['font_family'];
                $options['announcement_font_size'] = $primary_bar['font_size'];
                $options['announcement_marquee'] = $primary_bar['marquee'];
                $options['announcement_button_text'] = $primary_bar['button_text'];
                $options['announcement_button_url'] = $primary_bar['button_url'];
                $options['announcement_button_bg'] = $primary_bar['button_bg'];
                $options['announcement_button_color'] = $primary_bar['button_color'];
                $options['announcement_start_at'] = $primary_bar['start_at'];
                $options['announcement_disable_on'] = $primary_bar['disable_on'];
                $options['announcement_hide_desktop'] = $primary_bar['hide_desktop'];
                $options['announcement_hide_tablet'] = $primary_bar['hide_tablet'];
                $options['announcement_hide_mobile'] = $primary_bar['hide_mobile'];
            } else {
                $options['announcement_bar_enabled'] = 0;
                $options['announcement_text'] = '';
                $options['announcement_bg'] = '#111111';
                $options['announcement_text_color'] = '#ffffff';
                $options['announcement_font_family'] = 'inherit';
                $options['announcement_font_size'] = 16;
                $options['announcement_marquee'] = 0;
                $options['announcement_button_text'] = '';
                $options['announcement_button_url'] = '';
                $options['announcement_button_bg'] = '#ffffff';
                $options['announcement_button_color'] = '#111111';
                $options['announcement_start_at'] = '';
                $options['announcement_disable_on'] = '';
                $options['announcement_hide_desktop'] = 0;
                $options['announcement_hide_tablet'] = 0;
                $options['announcement_hide_mobile'] = 0;
            }

            $previous_login_slug = isset($current_options['custom_login_slug']) ? $current_options['custom_login_slug'] : '';
            $previous_admin_slug = isset($current_options['custom_admin_slug']) ? $current_options['custom_admin_slug'] : '';
            $options['custom_login_slug'] = !empty($_POST['custom_login_slug']) ? sanitize_title_with_dashes(wp_unslash($_POST['custom_login_slug'])) : '';
            $options['custom_admin_slug'] = !empty($_POST['custom_admin_slug']) ? sanitize_title_with_dashes(wp_unslash($_POST['custom_admin_slug'])) : '';

            if ($options['custom_login_slug'] !== $previous_login_slug || $options['custom_admin_slug'] !== $previous_admin_slug) {
                $force_flush_rewrite = true;
            }

            $options['login_background_color'] = isset($_POST['login_background_color']) ? $this->sanitize_color($_POST['login_background_color'], '#0f172a') : '#0f172a';
            $options['login_form_color'] = isset($_POST['login_form_color']) ? $this->sanitize_color($_POST['login_form_color'], '#ffffff') : '#ffffff';
            $options['login_button_color'] = isset($_POST['login_button_color']) ? $this->sanitize_color($_POST['login_button_color'], '#2563eb') : '#2563eb';
            $options['login_button_text_color'] = isset($_POST['login_button_text_color']) ? $this->sanitize_color($_POST['login_button_text_color'], '#ffffff') : '#ffffff';
            $options['login_custom_css'] = isset($_POST['login_custom_css']) ? sanitize_textarea_field(wp_unslash($_POST['login_custom_css'])) : '';

            $builder_default = isset($_POST['builder_default']) ? sanitize_text_field(wp_unslash($_POST['builder_default'])) : 'block';
            $options['builder_default'] = in_array($builder_default, array('block', 'classic'), true) ? $builder_default : 'block';

            
            update_option($this->options_key, $options);

            if ($force_flush_rewrite) {
                flush_rewrite_rules();
            }

            echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
        }
        
        $options = get_option($this->options_key, array());
        if (empty($options['builder_default'])) {
            $options['builder_default'] = 'block';
        }
        $announcement_bars = $this->get_announcement_bars($options);
        if (empty($announcement_bars)) {
            $announcement_bars[] = $this->get_default_announcement_bar();
        }
        $ssl_info = $this->get_ssl_info();
        $locked_ips = get_option($this->locked_ips_key, array());
        $locked_users = get_option($this->locked_users_key, array());
        
        include(dirname(__FILE__) . '/templates/settings.php');
    }

    /**
     * Render Blocked Accounts management page
     */
    public function render_blocked_accounts_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $records = $this->get_blocked_records();
        ?>
        <div class="wrap oc-essentials-wrap oc-blocked-wrap">
            <h1><?php esc_html_e('Blocked Accounts', 'oc-essentials'); ?></h1>
            <p class="description"><?php esc_html_e('Review locked accounts and permanently blocked IPs. Unlock entries to immediately restore access or mark them as permanently blocked.', 'oc-essentials'); ?></p>

            <?php if (empty($records)) : ?>
                <div class="notice notice-success">
                    <p><?php esc_html_e('Great news! There are currently no blocked accounts or IP addresses.', 'oc-essentials'); ?></p>
                </div>
            <?php else : ?>
                <table class="oc-table oc-blocked-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Type', 'oc-essentials'); ?></th>
                            <th><?php esc_html_e('Username / Email', 'oc-essentials'); ?></th>
                            <th><?php esc_html_e('Password Used', 'oc-essentials'); ?></th>
                            <th><?php esc_html_e('IP / Country', 'oc-essentials'); ?></th>
                            <th><?php esc_html_e('URLs', 'oc-essentials'); ?></th>
                            <th><?php esc_html_e('Reason', 'oc-essentials'); ?></th>
                            <th><?php esc_html_e('Status', 'oc-essentials'); ?></th>
                            <th><?php esc_html_e('Actions', 'oc-essentials'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $record_id => $record) : 
                            $type_label = $record['type'] === 'ip' ? __('IP Address', 'oc-essentials') : __('User', 'oc-essentials');
                            $passwords = !empty($record['passwords']) ? implode(', ', array_map('esc_html', array_slice($record['passwords'], -3))) : __('n/a', 'oc-essentials');
                            $status_badge = !empty($record['permanent']) ? '<span class="oc-badge oc-badge-danger">' . esc_html__('Permanent', 'oc-essentials') . '</span>' : '<span class="oc-badge oc-badge-warning">' . esc_html__('Temporary', 'oc-essentials') . '</span>';
                            $urls = array();
                            if (!empty($record['urls']['request'])) {
                                $urls[] = sprintf('<a href="%1$s" target="_blank">%2$s</a>', esc_url($record['urls']['request']), esc_html__('Request', 'oc-essentials'));
                            }
                            if (!empty($record['urls']['referer'])) {
                                $urls[] = sprintf('<a href="%1$s" target="_blank">%2$s</a>', esc_url($record['urls']['referer']), esc_html__('Referer', 'oc-essentials'));
                            }
                            if (!empty($record['urls']['clicked'])) {
                                $urls[] = sprintf('<a href="%1$s" target="_blank">%2$s</a>', esc_url($record['urls']['clicked']), esc_html__('Clicked URL', 'oc-essentials'));
                            }
                            $url_output = !empty($urls) ? implode('<br>', $urls) : __('n/a', 'oc-essentials');
                            $reason = isset($record['reason']) ? esc_html(ucwords(str_replace('_', ' ', $record['reason']))) : __('Unknown', 'oc-essentials');
                            ?>
                            <tr data-record-id="<?php echo esc_attr($record_id); ?>">
                                <td><?php echo esc_html($type_label); ?></td>
                                <td>
                                    <?php if (!empty($record['username'])) : ?>
                                        <strong><?php echo esc_html($record['username']); ?></strong><br>
                                    <?php endif; ?>
                                    <?php if (!empty($record['email'])) : ?>
                                        <small><?php echo esc_html($record['email']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $passwords; ?></td>
                                <td>
                                    <?php if (!empty($record['ip'])) : ?>
                                        <strong><?php echo esc_html($record['ip']); ?></strong><br>
                                    <?php endif; ?>
                                    <?php if (!empty($record['country'])) : ?>
                                        <small><?php echo esc_html($record['country']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $url_output; ?></td>
                                <td>
                                    <?php echo $reason; ?><br>
                                    <small><?php printf(esc_html__('Locked %s', 'oc-essentials'), esc_html($record['locked_at'])); ?></small>
                                </td>
                                <td><?php echo wp_kses_post($status_badge); ?></td>
                                <td>
                                    <button type="button"
                                            class="button button-secondary oc-blocked-action"
                                            data-record-id="<?php echo esc_attr($record_id); ?>"
                                            data-action="unblock">
                                        <?php esc_html_e('Unblock', 'oc-essentials'); ?>
                                    </button>
                                    <button type="button"
                                            class="button button-link-delete oc-blocked-action"
                                            data-record-id="<?php echo esc_attr($record_id); ?>"
                                            data-action="toggle_permanent">
                                        <?php echo !empty($record['permanent']) ? esc_html__('Remove Permanent Block', 'oc-essentials') : esc_html__('Permanently Block', 'oc-essentials'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Retrieve blocked account records
     */
    private function get_blocked_records() {
        $records = get_option($this->blocked_records_key, array());
        return is_array($records) ? $records : array();
    }

    /**
     * Persist blocked account records
     */
    private function save_blocked_records($records) {
        update_option($this->blocked_records_key, $records);
    }

    /**
     * Locate a record id by its logical key
     */
    private function find_record_id_by_key($key) {
        $records = $this->get_blocked_records();
        foreach ($records as $record_id => $record) {
            if (!empty($record['key']) && $record['key'] === $key) {
                return $record_id;
            }
        }
        return null;
    }

    /**
     * Remove blocked record by key
     */
    private function remove_blocked_record($key) {
        $records = $this->get_blocked_records();
        $record_id = $this->find_record_id_by_key($key);
        if ($record_id !== null) {
            unset($records[$record_id]);
            $this->save_blocked_records($records);
        }
    }

    /**
     * Add or update a blocked record
     */
    private function log_blocked_record($payload) {
        $records = $this->get_blocked_records();
        $key = isset($payload['key']) ? $payload['key'] : uniqid('oc_blk_', true);
        $record_id = $this->find_record_id_by_key($key);

        $entry = array(
            'type' => isset($payload['type']) ? $payload['type'] : 'user',
            'key' => $key,
            'user_id' => isset($payload['user_id']) ? intval($payload['user_id']) : 0,
            'username' => isset($payload['username']) ? sanitize_text_field($payload['username']) : '',
            'email' => isset($payload['email']) ? sanitize_email($payload['email']) : '',
            'ip' => isset($payload['ip']) ? sanitize_text_field($payload['ip']) : '',
            'country' => isset($payload['country']) ? sanitize_text_field($payload['country']) : 'Unknown',
            'reason' => isset($payload['reason']) ? sanitize_text_field($payload['reason']) : 'unknown',
            'locked_at' => isset($payload['locked_at']) ? $payload['locked_at'] : current_time('mysql'),
            'attempts' => isset($payload['attempts']) ? intval($payload['attempts']) : 1,
            'permanent' => !empty($payload['permanent']),
            'urls' => isset($payload['urls']) && is_array($payload['urls']) ? array_map('esc_url_raw', $payload['urls']) : array(),
        );

        $password_attempt = isset($payload['password']) ? sanitize_text_field($payload['password']) : '';
        if (!empty($password_attempt)) {
            $entry['passwords'] = array($password_attempt);
        } else {
            $entry['passwords'] = array();
        }

        if ($record_id !== null) {
            $existing = $records[$record_id];
            $entry['attempts'] = isset($existing['attempts']) ? intval($existing['attempts']) + 1 : $entry['attempts'];
            $entry['passwords'] = array_merge(
                isset($existing['passwords']) && is_array($existing['passwords']) ? $existing['passwords'] : array(),
                $entry['passwords']
            );
            $entry['passwords'] = array_slice(array_unique($entry['passwords']), -5);
            $entry['locked_at'] = $entry['locked_at'];
            $entry['permanent'] = !empty($existing['permanent']) || $entry['permanent'];
            $records[$record_id] = array_merge($existing, $entry);
        } else {
            $record_id = uniqid('oc_blk_', true);
            $records[$record_id] = $entry;
        }

        $this->save_blocked_records($records);
    }

    public function add_clone_link($actions, $post) {
        if (!current_user_can('edit_post', $post->ID)) {
            return $actions;
        }

        $url = wp_nonce_url(
            admin_url('admin.php?action=oc_clone_post&post=' . $post->ID),
            'oc_clone_post_' . $post->ID
        );

        $actions['oc_clone_post'] = '<a href="' . esc_url($url) . '">' . esc_html__('Clone', 'oc-essentials') . '</a>';

        return $actions;
    }

    public function handle_clone_action() {
        if (!isset($_GET['post'])) {
            wp_die(__('No post ID supplied.', 'oc-essentials'));
        }

        $post_id = intval($_GET['post']);
        check_admin_referer('oc_clone_post_' . $post_id);

        if (!current_user_can('edit_post', $post_id)) {
            wp_die(__('You do not have permission to clone this content.', 'oc-essentials'));
        }

        $post = get_post($post_id);

        if (!$post) {
            wp_die(__('Post not found.', 'oc-essentials'));
        }

        $new_post = array(
            'post_title' => $post->post_title . ' (Clone)',
            'post_content' => $post->post_content,
            'post_excerpt' => $post->post_excerpt,
            'post_type' => $post->post_type,
            'post_status' => 'draft',
            'post_author' => get_current_user_id()
        );

        $new_post_id = wp_insert_post($new_post, true);

        if (is_wp_error($new_post_id)) {
            wp_die($new_post_id->get_error_message());
        }

        $meta = get_post_meta($post_id);
        foreach ($meta as $meta_key => $values) {
            if (in_array($meta_key, array('_edit_lock', '_edit_last'), true)) {
                continue;
            }
            foreach ($values as $value) {
                add_post_meta($new_post_id, $meta_key, maybe_unserialize($value));
            }
        }

        $thumbnail_id = get_post_thumbnail_id($post_id);
        if ($thumbnail_id) {
            set_post_thumbnail($new_post_id, $thumbnail_id);
        }

        $taxonomies = get_object_taxonomies($post->post_type, 'names');
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'ids'));
            if (!is_wp_error($terms) && !empty($terms)) {
                wp_set_object_terms($new_post_id, $terms, $taxonomy);
            }
        }

        update_post_meta($new_post_id, '_oc_static_author_name', get_bloginfo('name'));

        $redirect = add_query_arg(
            array(
                'post_type' => $post->post_type,
                'oc_clone_created' => $new_post_id
            ),
            admin_url('edit.php')
        );

        wp_safe_redirect($redirect);
        exit;
    }

    public function maybe_override_author_display($display_name, $user_id = 0) {
        global $post;

        if ($post instanceof WP_Post) {
            $custom = get_post_meta($post->ID, '_oc_static_author_name', true);
            if (!empty($custom)) {
                return $custom;
            }
        }

        return $display_name;
    }

    private function get_permanent_blocks() {
        $blocks = get_option($this->permanent_blocks_key, array('ips' => array(), 'users' => array()));
        if (!isset($blocks['ips']) || !is_array($blocks['ips'])) {
            $blocks['ips'] = array();
        }
        if (!isset($blocks['users']) || !is_array($blocks['users'])) {
            $blocks['users'] = array();
        }
        return $blocks;
    }

    private function save_permanent_blocks($blocks) {
        update_option($this->permanent_blocks_key, $blocks);
    }

    private function update_permanent_flag($record, $enabled) {
        $blocks = $this->get_permanent_blocks();

        if ($record['type'] === 'ip' && !empty($record['ip'])) {
            $locked_ips = get_option($this->locked_ips_key, array());
            if (isset($locked_ips[$record['ip']])) {
                $locked_ips[$record['ip']]['permanent'] = $enabled ? 1 : 0;
                update_option($this->locked_ips_key, $locked_ips);
            }
            if ($enabled) {
                $blocks['ips'][$record['ip']] = current_time('mysql');
            } else {
                unset($blocks['ips'][$record['ip']]);
            }
        }

        if ($record['type'] === 'user' && !empty($record['user_id'])) {
            $locked_users = get_option($this->locked_users_key, array());
            if (isset($locked_users[$record['user_id']])) {
                $locked_users[$record['user_id']]['permanent'] = $enabled ? 1 : 0;
                update_option($this->locked_users_key, $locked_users);
            }
            if ($enabled) {
                $blocks['users'][$record['user_id']] = current_time('mysql');
            } else {
                unset($blocks['users'][$record['user_id']]);
            }
        }

        $this->save_permanent_blocks($blocks);
    }

    private function unlock_record_entry($record) {
        if ($record['type'] === 'ip' && !empty($record['ip'])) {
            $this->unlock_ip_address($record['ip']);
        }

        if ($record['type'] === 'user' && !empty($record['user_id'])) {
            $this->unlock_specific_user($record['user_id']);
        }

        if (!empty($record['key'])) {
            $this->remove_blocked_record($record['key']);
        }
    }
    
    /**
     * Feature 1: Get SSL Information
     */
    private function get_ssl_info() {
        $info = array(
            'is_ssl' => is_ssl(),
            'status' => 'Unknown',
            'expiry_date' => null,
            'days_remaining' => null,
            'issuer' => null
        );
        
        if (!is_ssl()) {
            $info['status'] = 'Not Active';
            return $info;
        }
        
        $domain = parse_url(home_url(), PHP_URL_HOST);
        
        $context = stream_context_create(array(
            'ssl' => array(
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false
            )
        ));
        
        $stream = @stream_socket_client(
            'ssl://' . $domain . ':443',
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $context
        );
        
        if ($stream) {
            $params = stream_context_get_params($stream);
            $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
            
            if ($cert) {
                $expiry_timestamp = $cert['validTo_time_t'];
                $info['expiry_date'] = date('Y-m-d H:i:s', $expiry_timestamp);
                $info['days_remaining'] = floor(($expiry_timestamp - time()) / 86400);
                $info['issuer'] = isset($cert['issuer']['O']) ? $cert['issuer']['O'] : 'Unknown';
                
                if ($info['days_remaining'] > 0) {
                    $info['status'] = 'Active';
                } else {
                    $info['status'] = 'Expired';
                }
            }
            
            fclose($stream);
        }
        
        return $info;
    }
    
    /**
     * Feature 1: Upload SSL Certificate
     */
    public function upload_ssl_certificate() {
        check_ajax_referer('oc_essentials_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        if (!isset($_FILES['ssl_cert']) || !isset($_FILES['ssl_key'])) {
            wp_send_json_error('Certificate and key files are required');
        }
        
        // This is a placeholder - actual SSL installation requires server-level access
        // In a shared hosting environment, this would need to use hosting provider APIs
        wp_send_json_success(array(
            'message' => 'SSL certificate upload requires server-level access. Please install manually via your hosting control panel or contact your hosting provider.'
        ));
    }
    
    /**
     * Feature 3: Disable file editing
     */
    public function disable_file_editing() {
        if (!current_user_can('administrator') && !is_super_admin()) {
            if (!defined('DISALLOW_FILE_EDIT')) {
                define('DISALLOW_FILE_EDIT', true);
            }
        }
    }

    public function disable_theme_file_editing() {
        if (current_user_can('administrator') || is_super_admin()) {
            return;
        }

        remove_submenu_page('themes.php', 'theme-editor.php');

        global $pagenow;
        if ($pagenow === 'theme-editor.php') {
            wp_die(__('Theme file editing has been disabled for security reasons.', 'oc-essentials'));
        }
    }
    
    /**
     * Feature 4: Remove XML-RPC headers
     */
    public function remove_xmlrpc_headers($headers) {
        unset($headers['X-Pingback']);
        return $headers;
    }
    
    /**
     * Feature 5: Disable wp-config editing
     */
    public function disable_wpconfig_editing() {
        if (!current_user_can('administrator') && !is_super_admin()) {
            if (!defined('DISALLOW_FILE_MODS')) {
                define('DISALLOW_FILE_MODS', true);
            }
        }
    }
    
    /**
     * Feature 6: Remove version strings
     */
    public function remove_version_strings($src) {
        if (strpos($src, 'ver=')) {
            $src = remove_query_arg('ver', $src);
        }
        return $src;
    }

    public function hide_footer_upgrade_div() {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var footerUpgrade = document.getElementById('footer-upgrade');
            if (footerUpgrade) {
                footerUpgrade.style.display = 'none';
            }
        });
        </script>
        <?php
    }
    
    /**
     * Feature 7: Extend admin login
     */
    public function extend_admin_login($expiration, $user_id, $remember) {
        $user = get_userdata($user_id);
        
        if ($user && in_array('administrator', $user->roles)) {
            return YEAR_IN_SECONDS * 10; // 10 years
        }
        
        return $expiration;
    }
    
    /**
     * Feature 8 & 11: Change admin username
     */
    private function change_admin_username($new_username) {
        global $wpdb;
        
        if (username_exists($new_username)) {
            return false;
        }
        
        $admin_user = get_user_by('login', 'admin');
        
        if (!$admin_user) {
            // Try to find first administrator
            $admins = get_users(array('role' => 'administrator', 'number' => 1));
            if (empty($admins)) {
                return false;
            }
            $admin_user = $admins[0];
        }
        
        $wpdb->update(
            $wpdb->users,
            array('user_login' => $new_username, 'user_nicename' => $new_username),
            array('ID' => $admin_user->ID),
            array('%s', '%s'),
            array('%d')
        );
        
        clean_user_cache($admin_user->ID);
        
        return true;
    }
    
    /**
     * Feature 9: Comment controls
     */
    public function disable_comments_admin() {
        global $pagenow;
        
        if ($pagenow === 'edit-comments.php' || $pagenow === 'options-discussion.php') {
            wp_redirect(admin_url());
            exit;
        }
    }
    
    public function filter_comments_status($open, $post_id) {
        $allow_comments = get_post_meta($post_id, '_oc_allow_comments', true);
        return $allow_comments ? true : false;
    }
    
    public function remove_comment_menu() {
        remove_menu_page('edit-comments.php');
    }
    
    public function remove_comment_support() {
        $post_types = get_post_types();
        foreach ($post_types as $post_type) {
            if (post_type_supports($post_type, 'comments')) {
                remove_post_type_support($post_type, 'comments');
                remove_post_type_support($post_type, 'trackbacks');
            }
        }
    }
    
    public function add_comment_meta_box() {
        $options = get_option($this->options_key, array());
        
        if (empty($options['disable_comments'])) {
            return;
        }
        
        add_meta_box(
            'oc_comment_control',
            'Comment Control',
            array($this, 'render_comment_meta_box'),
            array('post', 'page'),
            'side',
            'high'
        );
    }
    
    public function render_comment_meta_box($post) {
        wp_nonce_field('oc_comment_meta', 'oc_comment_nonce');
        $allow = get_post_meta($post->ID, '_oc_allow_comments', true);
        ?>
        <label>
            <input type="checkbox" name="oc_allow_comments" value="1" <?php checked($allow, '1'); ?>>
            Allow comments on this <?php echo $post->post_type; ?>
        </label>
        <?php
    }

    public function add_broken_url_metabox($post_type) {
        if (!in_array($post_type, array('post', 'page'), true)) {
            return;
        }

        add_meta_box(
            'oc_broken_url_scanner',
            __('Broken URL Scanner', 'oc-essentials'),
            array($this, 'render_broken_url_metabox'),
            $post_type,
            'side',
            'high'
        );
    }

    public function render_broken_url_metabox($post) {
        $post_id = isset($post->ID) ? (int) $post->ID : 0;
        ?>
        <div id="ocBrokenUrlMetabox">
            <?php if ($post_id) : ?>
                <p>
                    <button type="button" class="button button-secondary oc-scan-broken-single">
                        <?php esc_html_e('Scan This Content', 'oc-essentials'); ?>
                    </button>
                </p>
                <p>
                    <button type="button" class="button oc-clear-cache-post" data-post-id="<?php echo esc_attr($post_id); ?>">
                        <?php esc_html_e('Clear Cache for This Page', 'oc-essentials'); ?>
                    </button>
                </p>
                <div class="oc-results" style="font-size:12px;color:#646970;">
                    <?php esc_html_e('Scans this post or page for broken links when you click the button.', 'oc-essentials'); ?>
                </div>
                <div class="oc-cache-results" style="font-size:12px;color:#2271b1;margin-top:8px;"></div>
            <?php else : ?>
                <p style="font-size:12px;color:#646970;">
                    <?php esc_html_e('Save the draft first to enable broken URL scanning for this content.', 'oc-essentials'); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function save_comment_meta($post_id) {
        if (!isset($_POST['oc_comment_nonce']) || !wp_verify_nonce($_POST['oc_comment_nonce'], 'oc_comment_meta')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        $allow = isset($_POST['oc_allow_comments']) ? '1' : '0';
        update_post_meta($post_id, '_oc_allow_comments', $allow);
        
        // Update comment status
        if ($allow) {
            wp_update_post(array(
                'ID' => $post_id,
                'comment_status' => 'open'
            ));
        } else {
            wp_update_post(array(
                'ID' => $post_id,
                'comment_status' => 'closed'
            ));
        }
    }
    
    /**
     * Feature 12: Validate strong passwords
     */
    public function validate_strong_password($errors, $update, $user) {
        if (empty($_POST['pass1']) || empty($_POST['pass2'])) {
            return;
        }
        
        // Exempt administrators and super admins
        if (isset($user->ID)) {
            if (user_can($user->ID, 'administrator') || is_super_admin($user->ID)) {
                return;
            }
        }
        
        $password = $_POST['pass1'];
        
        if (!$this->is_strong_password($password)) {
            $errors->add('weak_password', '<strong>ERROR</strong>: Password must be at least 8 characters long and contain uppercase letter, lowercase letter, number, and special character.');
        }
    }
    
    public function validate_strong_password_reset($errors, $user) {
        // Exempt administrators and super admins
        if ($user && (user_can($user, 'administrator') || is_super_admin($user->ID))) {
            return;
        }
        
        if (!empty($_POST['pass1']) && !$this->is_strong_password($_POST['pass1'])) {
            $errors->add('weak_password', '<strong>ERROR</strong>: Password must be at least 8 characters long and contain uppercase letter, lowercase letter, number, and special character.');
        }
    }
    
    public function validate_registration_password($errors, $sanitized_user_login, $user_email) {
        if (!empty($_POST['password']) && !$this->is_strong_password($_POST['password'])) {
            $errors->add('weak_password', '<strong>ERROR</strong>: Password must be at least 8 characters long and contain uppercase letter, lowercase letter, number, and special character.');
        }
        return $errors;
    }
    
    private function is_strong_password($password) {
        if (strlen($password) < 8) {
            return false;
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            return false;
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            return false;
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            return false;
        }
        
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            return false;
        }
        
        return true;
    }

    public function enforce_password_on_login($user, $password) {
        if (is_wp_error($user)) {
            return $user;
        }

        if (user_can($user, 'administrator') || is_super_admin($user->ID)) {
            return $user;
        }

        if ($this->is_strong_password($password)) {
            delete_user_meta($user->ID, 'oc_require_password_reset');
            return $user;
        }

        update_user_meta($user->ID, 'oc_require_password_reset', 1);

        return new WP_Error(
            'weak_password',
            '<strong>ERROR</strong>: ' . __('Your password does not meet the minimum security requirements. Please reset your password before logging in.', 'oc-essentials')
        );
    }

    public function clear_password_reset_flag($user, $new_password) {
        if ($this->is_strong_password($new_password)) {
            delete_user_meta($user->ID, 'oc_require_password_reset');
        }
    }
    
    /**
     * Feature 13 & 14: Login attempt limiting
     */
    public function handle_login_failure($username) {
        $options = get_option($this->options_key, array());
        $attempts = get_option($this->login_attempts_key, array());
        $user_ip = $this->get_user_ip();
        $password_attempt = isset($_POST['pwd']) ? sanitize_text_field(wp_unslash($_POST['pwd'])) : '';
        $password_attempt = mb_substr($password_attempt, 0, 200);
        $request_context = $this->collect_request_context();
        
        // Check if username exists
        if (!empty($options['block_invalid_usernames']) && !username_exists($username) && !is_email($username)) {
            $locked_ips = get_option($this->locked_ips_key, array());
            $existing_lock = isset($locked_ips[$user_ip]) ? $locked_ips[$user_ip] : array();
            $locked_ips[$user_ip] = array(
                'username' => $username,
                'country' => $this->get_country_from_ip($user_ip),
                'locked_at' => current_time('mysql'),
                'attempts' => isset($existing_lock['attempts']) ? $existing_lock['attempts'] + 1 : 1,
                'permanent' => !empty($existing_lock['permanent']),
                'reason' => 'invalid_username'
            );
            update_option($this->locked_ips_key, $locked_ips);
            $this->log_blocked_record(array(
                'type' => 'ip',
                'key' => 'ip:' . $user_ip,
                'username' => $username,
                'ip' => $user_ip,
                'country' => $locked_ips[$user_ip]['country'],
                'reason' => 'invalid_username',
                'locked_at' => $locked_ips[$user_ip]['locked_at'],
                'attempts' => $locked_ips[$user_ip]['attempts'],
                'password' => $password_attempt,
                'urls' => $request_context,
                'permanent' => !empty($locked_ips[$user_ip]['permanent'])
            ));
            return;
        }
        
        // Track login attempts
        if (!empty($options['limit_login_attempts'])) {
            $limit = isset($options['login_attempt_limit']) ? $options['login_attempt_limit'] : 5;
            $key = $username . '|' . $user_ip;
            
            if (!isset($attempts[$key])) {
                $attempts[$key] = array('count' => 0, 'first_attempt' => time());
            }
            
            $attempts[$key]['count']++;
            $attempts[$key]['last_attempt'] = time();
            
            if ($attempts[$key]['count'] >= $limit) {
                $user = get_user_by('login', $username);
                if (!$user) {
                    $user = get_user_by('email', $username);
                }
                
                if ($user) {
                    $locked_users = get_option($this->locked_users_key, array());
                    $locked_users[$user->ID] = array(
                        'username' => $user->user_login,
                        'email' => $user->user_email,
                        'ip' => $user_ip,
                        'country' => $this->get_country_from_ip($user_ip),
                        'attempts' => $attempts[$key]['count'],
                        'locked_at' => current_time('mysql'),
                        'permanent' => 0,
                        'reason' => 'too_many_attempts'
                    );
                    update_option($this->locked_users_key, $locked_users);
                    $this->log_blocked_record(array(
                        'type' => 'user',
                        'key' => 'user:' . $user->ID,
                        'user_id' => $user->ID,
                        'username' => $user->user_login,
                        'email' => $user->user_email,
                        'ip' => $user_ip,
                        'country' => $locked_users[$user->ID]['country'],
                        'reason' => 'too_many_attempts',
                        'locked_at' => $locked_users[$user->ID]['locked_at'],
                        'attempts' => $locked_users[$user->ID]['attempts'],
                        'password' => $password_attempt,
                        'urls' => $request_context,
                        'permanent' => 0
                    ));
                }
            }
            
            update_option($this->login_attempts_key, $attempts);
        }
    }
    
    public function check_login_attempts($user, $username, $password) {
        if (empty($username) || empty($password)) {
            return $user;
        }

        $user_ip = $this->get_user_ip();
        $permanent_blocks = $this->get_permanent_blocks();

        if (!empty($permanent_blocks['ips'][$user_ip])) {
            return new WP_Error('ip_locked', '<strong>ERROR</strong>: ' . __('This IP address is permanently blocked. Please contact an administrator.', 'oc-essentials'));
        }
        
        $locked_ips = get_option($this->locked_ips_key, array());
        if (isset($locked_ips[$user_ip])) {
            if (!empty($locked_ips[$user_ip]['permanent'])) {
                return new WP_Error('ip_locked', '<strong>ERROR</strong>: ' . __('This IP address is permanently blocked. Please contact an administrator.', 'oc-essentials'));
            }
            return new WP_Error('ip_locked', '<strong>ERROR</strong>: ' . __('This IP address has been locked for security reasons. Please wait up to 72 hours or contact an administrator.', 'oc-essentials'));
        }

        $user_obj = null;
        if ($user instanceof WP_User) {
            $user_obj = $user;
        } else {
            $user_obj = get_user_by('login', $username);
            if (!$user_obj) {
                $user_obj = get_user_by('email', $username);
            }
        }
        
        if ($user_obj) {
            if (!empty($permanent_blocks['users'][$user_obj->ID])) {
                return new WP_Error('account_locked', '<strong>ERROR</strong>: ' . __('This account is permanently locked. Please contact an administrator.', 'oc-essentials'));
            }

            $locked_users = get_option($this->locked_users_key, array());
            if (isset($locked_users[$user_obj->ID])) {
                if (!empty($locked_users[$user_obj->ID]['permanent'])) {
                    return new WP_Error('account_locked', '<strong>ERROR</strong>: ' . __('This account is permanently locked. Please contact an administrator.', 'oc-essentials'));
                }

                $unlock_password = isset($_POST['oc_unlock_password']) ? sanitize_text_field(wp_unslash($_POST['oc_unlock_password'])) : '';
                if ($unlock_password && $this->validate_unlock_password($user_obj->ID, $unlock_password)) {
                    $this->unlock_specific_user($user_obj->ID);
                    return $user;
                }

                return new WP_Error('account_locked', '<strong>ERROR</strong>: ' . __('This account has been temporarily locked. Enter your unlock password or wait up to 72 hours.', 'oc-essentials'));
            }
        }
        
        return $user;
    }
    
    /**
     * Feature 20: Clear post/page cache
     */
    public function clear_post_cache($post_id, $post_after, $post_before) {
        // Clear object cache for this post
        wp_cache_delete($post_id, 'posts');
        wp_cache_delete($post_id, 'post_meta');
        
        // Clear common caching plugins for this specific post
        if (function_exists('wp_cache_post_change')) {
            wp_cache_post_change($post_id);
        }
        
        if (function_exists('w3tc_flush_post')) {
            w3tc_flush_post($post_id);
        }
        
        if (function_exists('wp_rocket_clean_post')) {
            wp_rocket_clean_post($post_id);
        }
    }
    
    /**
     * Feature 24: Disable emojis
     */
    public function disable_emojis() {
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
        
        add_filter('tiny_mce_plugins', function($plugins) {
            return is_array($plugins) ? array_diff($plugins, array('wpemoji')) : array();
        });
        
        add_filter('wp_resource_hints', function($urls, $relation_type) {
            if ('dns-prefetch' === $relation_type) {
                $emoji_svg_url = apply_filters('emoji_svg_url', 'https://s.w.org/images/core/emoji/2/svg/');
                $urls = array_diff($urls, array($emoji_svg_url));
            }
            return $urls;
        }, 10, 2);
    }

    public function maybe_show_under_construction() {
        $options = get_option($this->options_key, array());
        if (empty($options['under_construction'])) {
            return;
        }

        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        if (!empty($GLOBALS['pagenow']) && $GLOBALS['pagenow'] === 'wp-login.php') {
            return;
        }

        if (is_user_logged_in() && current_user_can('administrator')) {
            return;
        }

        status_header(503);
        nocache_headers();
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php esc_html_e('Under Construction', 'oc-essentials'); ?></title>
            <style>
                body {
                    background: #000;
                    color: #fff;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    height: 100vh;
                    margin: 0;
                    font-family: Arial, sans-serif;
                }
                h1 {
                    font-size: 3rem;
                    letter-spacing: 4px;
                    text-transform: uppercase;
                }
            </style>
        </head>
        <body>
            <h1><?php esc_html_e('Under Construction', 'oc-essentials'); ?></h1>
        </body>
        </html>
        <?php
        exit;
    }

    public function render_announcement_bar() {
        $bars = $this->get_announcement_bars();

        if (empty($bars)) {
            return;
        }

        $now_gmt = current_time('timestamp', true);
        $rendered = 0;

        foreach ($bars as $bar) {
            if (!$bar['enabled']) {
                continue;
            }

            if (!$this->announcement_bar_is_within_schedule($bar, $now_gmt)) {
                continue;
            }

            $this->output_announcement_bar_markup($bar);
            $rendered++;
        }

        return $rendered > 0;
    }

    private function output_announcement_bar_markup($bar) {
        $classes = array('oc-announcement-bar');
        if (!empty($bar['hide_desktop'])) {
            $classes[] = 'oc-hide-desktop';
        }
        if (!empty($bar['hide_tablet'])) {
            $classes[] = 'oc-hide-tablet';
        }
        if (!empty($bar['hide_mobile'])) {
            $classes[] = 'oc-hide-mobile';
        }

        $font_family = $bar['font_family'] ? $bar['font_family'] : 'inherit';
        $font_size = $bar['font_size'] ?: 16;
        $button_text = trim($bar['button_text']);
        $button_url = $bar['button_url'];
        ?>
        <div class="<?php echo esc_attr(implode(' ', $classes)); ?>" style="background: <?php echo esc_attr($bar['bg']); ?>; color: <?php echo esc_attr($bar['color']); ?>; font-family: <?php echo esc_attr($font_family); ?>; font-size: <?php echo esc_attr($font_size); ?>px;">
            <div class="oc-announcement-inner <?php echo !empty($bar['marquee']) ? 'oc-announcement-marquee' : ''; ?>">
                <span class="oc-announcement-message"><?php echo wp_kses_post($bar['text']); ?></span>
                <?php if ($button_text && $button_url) : ?>
                    <a href="<?php echo esc_url($button_url); ?>" class="oc-announcement-button" style="background: <?php echo esc_attr($bar['button_bg']); ?>; color: <?php echo esc_attr($bar['button_color']); ?>;">
                        <?php echo esc_html($button_text); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public function enforce_hotlink_protection() {
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        if (empty($_SERVER['HTTP_REFERER']) || empty($_SERVER['REQUEST_URI'])) {
            return;
        }

        $site_host = parse_url(home_url(), PHP_URL_HOST);
        $referer_host = parse_url(wp_unslash($_SERVER['HTTP_REFERER']), PHP_URL_HOST);

        if (!$referer_host || !$site_host || stripos($referer_host, $site_host) !== false) {
            return;
        }

        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $blocked_extensions = array('jpg','jpeg','png','gif','svg','webp','bmp','pdf','doc','docx','xls','xlsx','ppt','pptx','mp4','mov','avi','mp3','wav','zip');

        if (!in_array($extension, $blocked_extensions, true)) {
            return;
        }

        status_header(403);
        nocache_headers();
        exit(__('Hotlinking is not permitted.', 'oc-essentials'));
    }

    public function enqueue_frontend_scripts() {
        if (is_user_logged_in() && (current_user_can('administrator') || is_super_admin())) {
            return;
        }

        add_action('wp_footer', array($this, 'print_disable_right_click_script'), 100);
    }

    public function print_disable_right_click_script() {
        ?>
        <script>
        document.addEventListener('contextmenu', function(event) {
            if (!event.target.closest('.oc-context-allowed')) {
                event.preventDefault();
            }
        }, { capture: true });
        </script>
        <?php
    }
    
    /**
     * AJAX: Download database
     */
    public function download_database() {
        check_ajax_referer('oc_essentials_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        set_time_limit(0);
        global $wpdb;

        $temp_dir = trailingslashit(get_temp_dir());
        $sql_path = tempnam($temp_dir, 'oc_db_');
        $sql_handle = fopen($sql_path, 'w');

        if (!$sql_handle) {
            wp_die(__('Unable to create temporary file for database export.', 'oc-essentials'));
        }

        fwrite($sql_handle, "-- WordPress Database Backup\n");
        fwrite($sql_handle, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
        fwrite($sql_handle, "-- Site: " . get_bloginfo('name') . "\n");
        fwrite($sql_handle, "-- URL: " . home_url() . "\n\n");

        $tables = $wpdb->get_results('SHOW TABLES', ARRAY_N);

        foreach ($tables as $table) {
            $table_name = $table[0];
            $create_table = $wpdb->get_row("SHOW CREATE TABLE `$table_name`", ARRAY_N);
            fwrite($sql_handle, "\n\n-- Table structure for $table_name\n");
            fwrite($sql_handle, "DROP TABLE IF EXISTS `$table_name`;\n");
            fwrite($sql_handle, $create_table[1] . ";\n\n");

            fwrite($sql_handle, "-- Data for $table_name\n");
            $rows = $wpdb->get_results("SELECT * FROM `$table_name`", ARRAY_A);
            if ($rows) {
                foreach ($rows as $row) {
                    $values = array();
                    foreach ($row as $value) {
                        if (is_null($value)) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = "'" . $wpdb->_real_escape($value) . "'";
                        }
                    }
                    fwrite($sql_handle, "INSERT INTO `$table_name` VALUES(" . implode(', ', $values) . ");\n");
                }
            }
        }

        fclose($sql_handle);

        $zip_filename = 'database-backup-' . sanitize_file_name(get_bloginfo('name')) . '-' . date('Y-m-d-His') . '.zip';
        $zip_path = $temp_dir . $zip_filename;

        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($sql_path);
            wp_die(__('Unable to create database archive.', 'oc-essentials'));
        }

        $zip->addFile($sql_path, 'database.sql');
        $zip->close();
        @unlink($sql_path);

        $this->track_backup_file($zip_path);
        $this->deliver_backup_archive($zip_path, $zip_filename);
    }
    
    /**
     * AJAX: Download entire site
     */
    public function download_site() {
        check_ajax_referer('oc_essentials_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Set time limit
        set_time_limit(0);
        ini_set('memory_limit', '512M');
        
        $filename = 'site-backup-' . sanitize_file_name(get_bloginfo('name')) . '-' . date('Y-m-d-His') . '.zip';
        $temp_dir = get_temp_dir();
        $zip_path = $temp_dir . $filename;
        
        // Create zip archive
        $zip = new ZipArchive();
        
        if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            wp_send_json_error('Cannot create zip file');
        }
        
        // Add WordPress files
        $root_path = ABSPATH;
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root_path),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($files as $file) {
            if (!$file->isDir()) {
                $file_path = $file->getRealPath();
                $relative_path = substr($file_path, strlen($root_path));
                $zip->addFile($file_path, $relative_path);
            }
        }
        
        // Add database backup
        global $wpdb;
        $db_backup = '';
        $tables = $wpdb->get_results('SHOW TABLES', ARRAY_N);
        
        foreach ($tables as $table) {
            $table_name = $table[0];
            $create_table = $wpdb->get_row("SHOW CREATE TABLE `$table_name`", ARRAY_N);
            $db_backup .= "\n\n" . $create_table[1] . ";\n\n";
            
            $rows = $wpdb->get_results("SELECT * FROM `$table_name`", ARRAY_A);
            if ($rows) {
                foreach ($rows as $row) {
                    $values = array();
                    foreach ($row as $value) {
                        $values[] = is_null($value) ? 'NULL' : "'" . $wpdb->_real_escape($value) . "'";
                    }
                    $db_backup .= "INSERT INTO `$table_name` VALUES(" . implode(', ', $values) . ");\n";
                }
            }
        }
        
        $zip->addFromString('database-backup.sql', $db_backup);
        $zip->close();
        
        $this->track_backup_file($zip_path);
        $this->deliver_backup_archive($zip_path, $filename);
    }
    
    /**
     * AJAX: Download files only (no media)
     */
    public function download_files_only() {
        check_ajax_referer('oc_essentials_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        set_time_limit(0);
        ini_set('memory_limit', '512M');
        
        $filename = 'files-backup-' . sanitize_file_name(get_bloginfo('name')) . '-' . date('Y-m-d-His') . '.zip';
        $temp_dir = get_temp_dir();
        $zip_path = $temp_dir . $filename;
        
        $zip = new ZipArchive();
        
        if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            wp_send_json_error('Cannot create zip file');
        }
        
        $root_path = ABSPATH;
        $upload_dir = wp_upload_dir();
        $uploads_path = $upload_dir['basedir'];
        
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root_path),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($files as $file) {
            if (!$file->isDir()) {
                $file_path = $file->getRealPath();
                
                // Skip uploads directory
                if (strpos($file_path, $uploads_path) === 0) {
                    continue;
                }
                
                $relative_path = substr($file_path, strlen($root_path));
                $zip->addFile($file_path, $relative_path);
            }
        }
        
        $zip->close();
        
        $this->track_backup_file($zip_path);
        $this->deliver_backup_archive($zip_path, $filename);
    }
    
    /**
     * AJAX: Regenerate thumbnails
     */
    public function regenerate_thumbnails() {
        check_ajax_referer('oc_essentials_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        set_time_limit(0);
        if (function_exists('wp_raise_memory_limit')) {
            wp_raise_memory_limit('image');
        }
        
        $attachments = get_posts(array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
        ));
        
        $count = 0;
        $errors = 0;
        $removed_files = 0;

        $action = isset($_POST['regen_action']) ? sanitize_text_field($_POST['regen_action']) : 'regenerate';
        $remove_orphans = !empty($_POST['remove_orphans']);
        $sizes = isset($_POST['sizes']) ? (array) $_POST['sizes'] : array();
        $sizes = array_map('sanitize_text_field', $sizes);

        if ($action === 'cleanup') {
            $result = $this->cleanup_orphaned_thumbnails();
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }
            wp_send_json_success(array(
                'message' => sprintf(__('Removed %d orphaned thumbnail files.', 'oc-essentials'), $result['removed']),
                'removed' => $result['removed']
            ));
        }

        if ($remove_orphans) {
            $this->cleanup_orphaned_thumbnails();
        }

        $core_sizes = array('thumbnail','medium','medium_large','large');
        $selected_sizes = array_values(array_intersect($sizes, array_merge($core_sizes, array('custom'))));
        
        foreach ($attachments as $attachment_id) {
            $removed_files += $this->delete_attachment_thumbnails($attachment_id, $selected_sizes);
            $filepath = get_attached_file($attachment_id);
            
            if ($filepath && file_exists($filepath)) {
                $metadata = wp_generate_attachment_metadata($attachment_id, $filepath);
                if (is_wp_error($metadata) || empty($metadata)) {
                    $errors++;
                    continue;
                }

                if (!empty($selected_sizes) && !empty($metadata['sizes'])) {
                    $filtered = array();
                    foreach ($metadata['sizes'] as $size => $data) {
                        if (in_array('custom', $selected_sizes, true) && !in_array($size, $core_sizes, true)) {
                            $filtered[$size] = $data;
                        }
                        if (in_array($size, $selected_sizes, true)) {
                            $filtered[$size] = $data;
                        }
                    }
                    $metadata['sizes'] = $filtered;
                }
                
                if (wp_update_attachment_metadata($attachment_id, $metadata)) {
                    $count++;
                } else {
                    $errors++;
                }
            } else {
                $errors++;
            }
        }
        
        wp_send_json_success(array(
            'message' => sprintf(
                __('Regenerated thumbnails for %1$d images. %2$d errors encountered.', 'oc-essentials'),
                $count,
                $errors
            ),
            'count' => $count,
            'errors' => $errors,
            'removed' => $removed_files
        ));
    }

    public function delete_all_thumbnails() {
        check_ajax_referer('oc_essentials_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'oc-essentials'));
        }

        $attachments = get_posts(array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
        ));

        set_time_limit(0);

        $removed = 0;
        foreach ($attachments as $attachment_id) {
            $removed += $this->delete_attachment_thumbnails($attachment_id);
        }

        $message = $removed
            ? sprintf(_n('Removed %d thumbnail file.', 'Removed %d thumbnail files.', $removed, 'oc-essentials'), $removed)
            : __('No thumbnail files were removed.', 'oc-essentials');

        wp_send_json_success(array(
            'message' => $message,
            'removed' => $removed
        ));
    }

    private function cleanup_orphaned_thumbnails() {
        $uploads = wp_upload_dir();
        $base_dir = trailingslashit($uploads['basedir']);

        if (!is_dir($base_dir)) {
            return new WP_Error('missing_uploads', __('Uploads directory not found.', 'oc-essentials'));
        }

        $removed = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base_dir, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $filename = $file->getFilename();
            if (strpos($filename, '-') !== false && preg_match('/-\d+x\d+\.(jpg|jpeg|png|gif|webp)$/i', $filename)) {
                $original = preg_replace('/-\d+x\d+(\.[^\.]+)$/', '$1', $filename);
                $original_path = str_replace($filename, $original, $file->getPathname());

                if (!file_exists($original_path)) {
                    @unlink($file->getPathname());
                    $removed++;
                }
            }
        }

        return array('removed' => $removed);
    }

    private function delete_attachment_thumbnails($attachment_id, $limit_sizes = array()) {
        $file = get_attached_file($attachment_id);
        $metadata = wp_get_attachment_metadata($attachment_id);

        if (!$file || empty($metadata) || empty($metadata['sizes'])) {
            return 0;
        }

        $removed = 0;
        $dir = trailingslashit(dirname($file));
        $core_sizes = array('thumbnail','medium','medium_large','large');
        $remove_all = empty($limit_sizes);
        $limit_sizes = array_values($limit_sizes);

        foreach ($metadata['sizes'] as $size => $data) {
            $should_remove = $remove_all;

            if (!$remove_all) {
                if (in_array($size, $limit_sizes, true)) {
                    $should_remove = true;
                } elseif (in_array('custom', $limit_sizes, true) && !in_array($size, $core_sizes, true)) {
                    $should_remove = true;
                }
            }

            if (!$should_remove) {
                continue;
            }

            if (!empty($data['file'])) {
                $thumb_path = $dir . $data['file'];
                if (file_exists($thumb_path) && is_file($thumb_path)) {
                    @unlink($thumb_path);
                    $removed++;
                }
            }

            unset($metadata['sizes'][$size]);
        }

        if (!isset($metadata['sizes']) || !is_array($metadata['sizes'])) {
            $metadata['sizes'] = array();
        }

        wp_update_attachment_metadata($attachment_id, $metadata);

        return $removed;
    }
    
    /**
     * AJAX: Delete unattached media
     */
    public function delete_unattached_media() {
        check_ajax_referer('oc_essentials_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $attachments = get_posts(array(
            'post_type' => 'attachment',
            'post_status' => 'any',
            'numberposts' => -1,
            'post_parent' => 0
        ));
        
        $keep_original = !empty($_POST['keep_original']);
        
        $count = 0;
        foreach ($attachments as $attachment) {
            if ($keep_original) {
                $file = get_attached_file($attachment->ID);
                $metadata = wp_get_attachment_metadata($attachment->ID);
                if (!empty($metadata['sizes'])) {
                    foreach ($metadata['sizes'] as $size => $data) {
                        $path = path_join(dirname($file), $data['file']);
                        if (file_exists($path)) {
                            @unlink($path);
                        }
                    }
                }
                $count++;
            } else {
                if (wp_delete_attachment($attachment->ID, true)) {
                    $count++;
                }
            }
        }
        
        $message = $keep_original
            ? sprintf(__('Deleted thumbnail sizes for %d unattached images.', 'oc-essentials'), $count)
            : sprintf(__('Deleted %d unattached files and their thumbnails.', 'oc-essentials'), $count);
        
        wp_send_json_success(array(
            'message' => $message,
            'count' => $count
        ));
    }
    
    /**
     * AJAX: Clear site cache
     */
    public function clear_site_cache() {
        check_ajax_referer('oc_essentials_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $message = $this->perform_cache_clear($post_id);
        wp_send_json_success(array('message' => $message));
    }

    private function perform_cache_clear($post_id = 0) {
        if ($post_id > 0) {
            wp_cache_delete($post_id, 'posts');
            wp_cache_delete($post_id, 'post_meta');

            if (function_exists('wp_cache_post_change')) {
                wp_cache_post_change($post_id);
            }

            return __('Post cache cleared successfully.', 'oc-essentials');
        }

        wp_cache_flush();

        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }

        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }

        if (function_exists('wp_rocket_clean_domain')) {
            wp_rocket_clean_domain();
        }

        if (class_exists('WpFastestCache')) {
            $cache = new WpFastestCache();
            $cache->deleteCache();
        }

        if (function_exists('litespeed_purge_all')) {
            litespeed_purge_all();
        }

        return __('Entire site cache cleared successfully.', 'oc-essentials');
    }

    public function auto_clear_cache($post_id = 0, $post = null) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        $this->perform_cache_clear(intval($post_id));
    }

    public function auto_clear_cache_after_upgrade($upgrader, $hook_extra) {
        $this->perform_cache_clear();
    }
    
    /**
     * AJAX: Scan orphaned content
     */
    public function scan_orphaned_content() {
        check_ajax_referer('oc_essentials_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        global $wpdb;
        
        $posts = get_posts(array(
            'post_type' => array('post', 'page'),
            'post_status' => 'publish',
            'numberposts' => -1
        ));
        
        $orphaned = array();
        
        foreach ($posts as $post) {
            $permalink = get_permalink($post->ID);
            $post_url_slug = basename($permalink);
            
            // Check if linked from any other post
            $links = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} 
                WHERE (post_content LIKE %s OR post_content LIKE %s) 
                AND ID != %d 
                AND post_status = 'publish'",
                '%' . $wpdb->esc_like($permalink) . '%',
                '%' . $wpdb->esc_like($post_url_slug) . '%',
                $post->ID
            ));
            
            // Check if in menu
            $in_menu = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} 
                WHERE meta_key = '_menu_item_object_id' 
                AND meta_value = %d",
                $post->ID
            ));
            
            if ($links == 0 && $in_menu == 0) {
                // Find suggested posts
                $suggestions = array();
                
                // By category
                $categories = wp_get_post_categories($post->ID);
                if ($categories) {
                    $related = get_posts(array(
                        'category__in' => $categories,
                        'post__not_in' => array($post->ID),
                        'numberposts' => 3,
                        'post_status' => 'publish'
                    ));
                    
                    foreach ($related as $rel) {
                        $suggestions[] = array(
                            'title' => $rel->post_title,
                            'edit_url' => get_edit_post_link($rel->ID)
                        );
                    }
                }
                
                // By tags
                if (empty($suggestions)) {
                    $tags = wp_get_post_tags($post->ID, array('fields' => 'ids'));
                    if ($tags) {
                        $related = get_posts(array(
                            'tag__in' => $tags,
                            'post__not_in' => array($post->ID),
                            'numberposts' => 3,
                            'post_status' => 'publish'
                        ));
                        
                        foreach ($related as $rel) {
                            $suggestions[] = array(
                                'title' => $rel->post_title,
                                'edit_url' => get_edit_post_link($rel->ID),
                                'view_url' => get_permalink($rel->ID)
                            );
                        }
                    }
                }

                if (count($suggestions) < 3) {
                    $keywords = array_filter(preg_split('/\s+/', wp_strip_all_tags($post->post_title)), function($word) {
                        return strlen($word) > 3;
                    });

                    $meta_desc = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true);
                    if (empty($meta_desc)) {
                        $meta_desc = get_post_meta($post->ID, '_aioseo_description', true);
                    }

                    if (!empty($meta_desc)) {
                        $meta_words = array_filter(preg_split('/\s+/', wp_strip_all_tags($meta_desc)), function($word) {
                            return strlen($word) > 5;
                        });
                        $keywords = array_merge($keywords, $meta_words);
                    }

                    $keywords = array_unique($keywords);
                    $search_phrase = implode(' ', array_slice($keywords, 0, 5));

                    if (!empty($search_phrase)) {
                        $related = get_posts(array(
                            's' => $search_phrase,
                            'post_type' => array('post', 'page'),
                            'post_status' => 'publish',
                            'numberposts' => 3 - count($suggestions),
                            'post__not_in' => array($post->ID)
                        ));

                        foreach ($related as $rel) {
                            $suggestions[] = array(
                                'title' => $rel->post_title,
                                'edit_url' => get_edit_post_link($rel->ID),
                                'view_url' => get_permalink($rel->ID)
                            );
                        }
                    }
                }
                
                $orphaned[] = array(
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'url' => $permalink,
                    'edit_url' => get_edit_post_link($post->ID),
                    'type' => $post->post_type,
                    'date' => get_the_date('M j, Y', $post->ID),
                    'suggestions' => $suggestions
                );
            }
        }
        
        wp_send_json_success(array(
            'orphaned' => $orphaned,
            'count' => count($orphaned)
        ));
    }
    
    /**
     * AJAX: Scan missing meta
     */
    public function scan_missing_meta() {
        check_ajax_referer('oc_essentials_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $posts = get_posts(array(
            'post_type' => array('post', 'page'),
            'post_status' => 'publish',
            'numberposts' => -1
        ));
        
        $missing_meta = array();
        $seo_plugin = 'none';
        
        // Detect SEO plugin
        if (defined('WPSEO_VERSION')) {
            $seo_plugin = 'yoast';
        } elseif (class_exists('RankMath')) {
            $seo_plugin = 'rankmath';
        } elseif (defined('AIOSEO_VERSION')) {
            $seo_plugin = 'aioseo';
        }
        
        foreach ($posts as $post) {
            $missing = array();
            
            if ($seo_plugin === 'yoast') {
                $focus_keyword = get_post_meta($post->ID, '_yoast_wpseo_focuskw', true);
                $meta_title = get_post_meta($post->ID, '_yoast_wpseo_title', true);
                $meta_desc = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true);
                
                if (empty($focus_keyword)) $missing[] = 'Focus Keyphrase';
                if (empty($meta_title)) $missing[] = 'SEO Title';
                if (empty($meta_desc)) $missing[] = 'Meta Description';
                
            } elseif ($seo_plugin === 'rankmath') {
                $focus_keyword = get_post_meta($post->ID, 'rank_math_focus_keyword', true);
                $meta_title = get_post_meta($post->ID, 'rank_math_title', true);
                $meta_desc = get_post_meta($post->ID, 'rank_math_description', true);
                
                if (empty($focus_keyword)) $missing[] = 'Focus Keyword';
                if (empty($meta_title)) $missing[] = 'SEO Title';
                if (empty($meta_desc)) $missing[] = 'SEO Description';
                
            } elseif ($seo_plugin === 'aioseo') {
                $aioseo_data = get_post_meta($post->ID, '_aioseo_title', true);
                $meta_title = get_post_meta($post->ID, '_aioseo_title', true);
                $meta_desc = get_post_meta($post->ID, '_aioseo_description', true);
                
                if (empty($meta_title)) $missing[] = 'SEO Title';
                if (empty($meta_desc)) $missing[] = 'SEO Description';
            }
            
            if (!empty($missing)) {
                $missing_meta[] = array(
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'type' => $post->post_type,
                    'edit_url' => get_edit_post_link($post->ID),
                    'missing' => $missing
                );
            }
        }
        
        wp_send_json_success(array(
            'missing_meta' => $missing_meta,
            'count' => count($missing_meta),
            'seo_plugin' => $seo_plugin
        ));
    }
    
    /**
     * AJAX: Scan missing alt text
     */
    public function scan_missing_alt_text() {
        check_ajax_referer('oc_essentials_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $attachments = get_posts(array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'numberposts' => -1,
            'post_status' => 'any'
        ));
        
        $missing = array();
        
        foreach ($attachments as $attachment) {
            $alt_text = get_post_meta($attachment->ID, '_wp_attachment_image_alt', true);
            
            if (empty($alt_text)) {
                $missing[] = array(
                    'id' => $attachment->ID,
                    'title' => $attachment->post_title,
                    'url' => wp_get_attachment_url($attachment->ID),
                    'thumb' => wp_get_attachment_image_url($attachment->ID, 'thumbnail'),
                    'edit_url' => get_edit_post_link($attachment->ID)
                );
            }
        }
        
        // Check if AI Alt Tag Manager is installed
        $has_ai_alt_tag = class_exists('AI_Alt_Tag_Manager');
        
        wp_send_json_success(array(
            'missing' => $missing,
            'count' => count($missing),
            'has_ai_alt_tag' => $has_ai_alt_tag,
            'ai_alt_tag_url' => admin_url('upload.php?page=missing-alt-tags')
        ));
    }

    public function generate_alt_via_ai() {
        check_ajax_referer('oc_essentials_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(__('Insufficient permissions.', 'oc-essentials'));
        }

        // Accept both attachment_id and image_id for flexibility
        $attachment_id = 0;
        if (!empty($_POST['attachment_id'])) {
            $attachment_id = intval($_POST['attachment_id']);
        } elseif (!empty($_POST['image_id'])) {
            $attachment_id = intval($_POST['image_id']);
        }

        if (!$attachment_id || !wp_attachment_is_image($attachment_id)) {
            wp_send_json_error(__('Invalid attachment.', 'oc-essentials'));
        }

        $result = $this->generate_alt_text_for_attachment($attachment_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        update_post_meta($attachment_id, '_wp_attachment_image_alt', $result);

        wp_send_json_success(array(
            'message' => __('Alt text generated successfully.', 'oc-essentials'),
            'alt_text' => $result
        ));
    }

    public function submit_nonindexed_content() {
        check_ajax_referer('oc_submit_nonindexed', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'oc-essentials'));
        }

        $posts = get_posts(array(
            'post_type' => array('post', 'page'),
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_oc_google_submitted',
                    'compare' => 'NOT EXISTS'
                )
            ),
            'numberposts' => 20,
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        if (empty($posts)) {
            wp_send_json_success(array(
                'submitted' => 0,
                'message' => __('All posts and pages have already been submitted.', 'oc-essentials')
            ));
        }

        $submitted = 0;
        $errors = 0;

        foreach ($posts as $post) {
            $url = 'https://www.google.com/ping?sitemap=' . rawurlencode(get_permalink($post->ID));
            $response = wp_remote_get($url, array('timeout' => 8));

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                update_post_meta($post->ID, '_oc_google_submitted', current_time('mysql'));
                $submitted++;
            } else {
                $errors++;
            }
        }

        wp_send_json_success(array(
            'submitted' => $submitted,
            'errors' => $errors
        ));
    }

    /**
     * AJAX: Scan for broken URLs in site content
     */
    public function scan_broken_urls() {
        check_ajax_referer('oc_essentials_nonce', 'nonce');
        
        set_time_limit(300); // 5 minutes max
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if ($post_id) {
            if (!current_user_can('edit_post', $post_id)) {
                wp_send_json_error('Insufficient permissions');
            }
        } else {
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
            }
        }
        
        // Get posts to scan
        $args = array(
            'post_type' => array('post', 'page'),
            'post_status' => $post_id ? get_post_status($post_id) : 'publish',
            'numberposts' => $post_id ? 1 : 100, // Limit to 100 for full site scan
            'orderby' => 'modified',
            'order' => 'DESC'
        );
        
        if ($post_id) {
            $args['include'] = array($post_id);
            if (empty($args['post_status'])) {
                $args['post_status'] = 'any';
            }
        }
        
        $posts = get_posts($args);
        $broken_urls = array();
        $checked_urls = array(); // Cache to avoid rechecking same URL
        
        foreach ($posts as $post) {
            $content = $post->post_content;
            
            // Find all URLs in content
            $urls = $this->extract_urls_from_content($content);
            
            foreach ($urls as $url_data) {
                $url = $url_data['url'];
                
                // Skip if already checked
                if (isset($checked_urls[$url])) {
                    if ($checked_urls[$url]['broken']) {
                        $broken_urls[] = array_merge($checked_urls[$url], array(
                            'post_id' => $post->ID,
                            'post_title' => $post->post_title,
                            'post_date' => get_the_date('M j, Y', $post->ID),
                            'post_edit_url' => get_edit_post_link($post->ID),
                            'context' => $url_data['context']
                        ));
                    }
                    continue;
                }
                
                // Determine URL type
                $url_type = $this->get_url_type($url);
                
                // Check if URL is broken
                $is_broken = $this->check_url_broken($url);
                
                $checked_urls[$url] = array(
                    'url' => $url,
                    'type' => $url_type,
                    'broken' => $is_broken
                );
                
                if ($is_broken) {
                    $broken_urls[] = array(
                        'url' => $url,
                        'type' => $url_type,
                        'post_id' => $post->ID,
                        'post_title' => $post->post_title,
                        'post_date' => get_the_date('M j, Y', $post->ID),
                        'post_edit_url' => get_edit_post_link($post->ID),
                        'post_view_url' => get_permalink($post->ID),
                        'context' => $url_data['context']
                    );
                }
            }
        }
        
        wp_send_json_success(array(
            'broken_urls' => $broken_urls,
            'count' => count($broken_urls),
            'posts_scanned' => count($posts)
        ));
    }
    
    /**
     * Extract URLs from post content
     */
    private function extract_urls_from_content($content) {
        $urls = array();
        
        // Match href and src attributes
        preg_match_all('/(?:href|src)=["\']([^"\']+)["\']/i', $content, $matches);
        
        if (!empty($matches[1])) {
            foreach ($matches[1] as $url) {
                // Skip anchors, javascript, and mailto
                if (strpos($url, '#') === 0 || strpos($url, 'javascript:') === 0 || strpos($url, 'mailto:') === 0) {
                    continue;
                }
                
                // Get context (surrounding text)
                $context = '';
                $pos = strpos($content, $url);
                if ($pos !== false) {
                    $start = max(0, $pos - 50);
                    $context = substr($content, $start, 100);
                    $context = wp_strip_all_tags($context);
                    $context = trim(preg_replace('/\s+/', ' ', $context));
                }
                
                $urls[] = array(
                    'url' => $url,
                    'context' => $context
                );
            }
        }
        
        return $urls;
    }
    
    /**
     * Determine URL type
     */
    private function get_url_type($url) {
        $site_url = home_url();
        $is_internal = strpos($url, $site_url) === 0 || strpos($url, '/') === 0;
        
        // Check file extension
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        
        $image_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico');
        $document_extensions = array('pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv');
        $media_extensions = array('mp4', 'mp3', 'avi', 'mov', 'wmv', 'flv', 'webm', 'ogg', 'wav');
        
        if (in_array($extension, $image_extensions)) {
            return $is_internal ? 'internal_image' : 'external_image';
        }
        
        if (in_array($extension, $document_extensions)) {
            return $is_internal ? 'internal_document' : 'external_document';
        }
        
        if (in_array($extension, $media_extensions)) {
            return $is_internal ? 'internal_media' : 'external_media';
        }
        
        return $is_internal ? 'internal_link' : 'external_link';
    }
    
    /**
     * Check if URL is broken
     */
    private function check_url_broken($url) {
        // Handle relative URLs
        if (strpos($url, '/') === 0) {
            $url = home_url($url);
        }
        
        // Validate URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return true; // Invalid URL format
        }
        
        // Check with HEAD request first (faster)
        $response = wp_remote_head($url, array(
            'timeout' => 10,
            'redirection' => 5,
            'sslverify' => false,
            'user-agent' => 'Mozilla/5.0 (compatible; OnyxCommand/1.0; URL checker)'
        ));
        
        if (is_wp_error($response)) {
            return true; // Connection failed
        }
        
        $code = wp_remote_retrieve_response_code($response);
        
        // 2xx = success, 3xx = redirect (usually ok)
        if ($code >= 200 && $code < 400) {
            return false;
        }
        
        // 4xx or 5xx = broken
        return true;
    }

    private function generate_alt_text_for_attachment($attachment_id) {
        if (!class_exists('OC_Settings')) {
            return new WP_Error('missing_dependency', __('Onyx Command settings are unavailable.', 'oc-essentials'));
        }

        $api_key = OC_Settings::get_api_key('claude');
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', __('Claude API key is not configured. Configure it inside Onyx Command settings.', 'oc-essentials'));
        }

        if (!wp_attachment_is_image($attachment_id)) {
            return new WP_Error('invalid_attachment', __('Attachment is not an image.', 'oc-essentials'));
        }

        $image_path = get_attached_file($attachment_id);
        if (!$image_path || !file_exists($image_path)) {
            return new WP_Error('missing_file', __('Unable to locate the image file on disk.', 'oc-essentials'));
        }

        $image_data = file_get_contents($image_path);
        $base64_image = base64_encode($image_data);
        $mime_type = get_post_mime_type($attachment_id);

        $response = wp_remote_post('https://api.anthropic.com/v1/messages', array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01'
            ),
            'timeout' => 30,
            'body' => wp_json_encode(array(
                'model' => 'claude-sonnet-4-20250514',
                'max_tokens' => 150,
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => array(
                            array(
                                'type' => 'image',
                                'source' => array(
                                    'type' => 'base64',
                                    'media_type' => $mime_type,
                                    'data' => $base64_image
                                )
                            ),
                            array(
                                'type' => 'text',
                                'text' => 'Generate a concise, SEO-friendly alt tag for this image. Respond with only the alt text.'
                            )
                        )
                    )
                )
            ))
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (200 !== $code || empty($body['content'][0]['text'])) {
            $message = isset($body['error']['message']) ? $body['error']['message'] : __('Unknown API error.', 'oc-essentials');
            return new WP_Error('api_error', $message);
        }

        return sanitize_text_field(trim($body['content'][0]['text']));
    }

    public function rewrite_highlighted_content() {
        check_ajax_referer('oc_essentials_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if ($post_id) {
            if (!current_user_can('edit_post', $post_id)) {
                wp_send_json_error(__('You do not have permission to edit this content.', 'oc-essentials'));
            }
        } else {
            if (!current_user_can('edit_posts')) {
                wp_send_json_error(__('Insufficient permissions.', 'oc-essentials'));
            }
        }

        $content = isset($_POST['content']) ? trim(wp_unslash($_POST['content'])) : '';

        if (empty($content)) {
            wp_send_json_error(__('Please highlight some content to rewrite.', 'oc-essentials'));
        }

        if (strlen($content) > 1000) {
            $content = substr($content, 0, 1000);
        }

        $result = $this->generate_rewritten_content($content);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('text' => $result));
    }

    private function generate_rewritten_content($text) {
        if (!class_exists('OC_Settings')) {
            return new WP_Error('missing_dependency', __('Onyx Command settings are unavailable.', 'oc-essentials'));
        }

        $api_key = OC_Settings::get_api_key('claude');
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', __('Claude API key is not configured. Configure it inside Onyx Command settings.', 'oc-essentials'));
        }

        $prompt = "Rewrite the following sentence or paragraph to improve clarity and tone while keeping the original meaning. Respond with only the rewritten text.\n\n" . $text;

        $response = wp_remote_post('https://api.anthropic.com/v1/messages', array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01'
            ),
            'timeout' => 30,
            'body' => wp_json_encode(array(
                'model' => 'claude-sonnet-4-20250514',
                'max_tokens' => 250,
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => array(
                            array(
                                'type' => 'text',
                                'text' => $prompt
                            )
                        )
                    )
                )
            ))
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (200 !== $code || empty($body['content'][0]['text'])) {
            $message = isset($body['error']['message']) ? $body['error']['message'] : __('Unable to rewrite content at this time.', 'oc-essentials');
            return new WP_Error('api_error', $message);
        }

        return sanitize_text_field(trim($body['content'][0]['text']));
    }
    
    /**
     * AJAX: Unlock IP
     */
    public function unlock_ip() {
        check_ajax_referer('oc_essentials_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $ip = sanitize_text_field($_POST['ip']);

        if ($this->unlock_ip_address($ip)) {
            wp_send_json_success(array('message' => 'IP unlocked successfully.'));
        }

        wp_send_json_error('IP not found.');
    }
    
    /**
     * AJAX: Unlock user
     */
    public function unlock_user() {
        check_ajax_referer('oc_essentials_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $user_id = intval($_POST['user_id']);
        
        if ($this->unlock_specific_user($user_id)) {
            wp_send_json_success(array('message' => 'User unlocked successfully.'));
        }

        wp_send_json_error('User not found.');
    }

    public function handle_blocked_record_action() {
        check_ajax_referer('oc_blocked_record_action', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $record_id = sanitize_text_field($_POST['record_id']);
        $sub_action = sanitize_text_field($_POST['sub_action']);
        $records = $this->get_blocked_records();

        if (empty($records[$record_id])) {
            wp_send_json_error('Record not found.');
        }

        $record = $records[$record_id];

        switch ($sub_action) {
            case 'unblock':
                $this->unlock_record_entry($record);
                unset($records[$record_id]);
                $this->save_blocked_records($records);
                wp_send_json_success(array('message' => __('Record unlocked successfully.', 'oc-essentials')));
                break;
            case 'toggle_permanent':
                $new_state = empty($record['permanent']);
                $records[$record_id]['permanent'] = $new_state;
                $this->save_blocked_records($records);
                $this->update_permanent_flag($record, $new_state);
                $label = $new_state ? __('Marked as permanently blocked.', 'oc-essentials') : __('Permanent block removed.', 'oc-essentials');
                wp_send_json_success(array('message' => $label, 'permanent' => $new_state));
                break;
            default:
                wp_send_json_error('Invalid action.');
        }
    }

    private function unlock_specific_user($user_id) {
        $locked_users = get_option($this->locked_users_key, array());
        if (!isset($locked_users[$user_id])) {
            return false;
        }

        unset($locked_users[$user_id]);
        update_option($this->locked_users_key, $locked_users);

        $attempts = get_option($this->login_attempts_key, array());
        foreach ($attempts as $key => $data) {
            if (strpos($key, '|') !== false) {
                list($username) = explode('|', $key);
                $user = get_user_by('login', $username);
                if ($user && intval($user->ID) === intval($user_id)) {
                    unset($attempts[$key]);
                }
            }
        }
        update_option($this->login_attempts_key, $attempts);

        $blocks = $this->get_permanent_blocks();
        unset($blocks['users'][$user_id]);
        $this->save_permanent_blocks($blocks);

        $this->remove_blocked_record('user:' . $user_id);

        return true;
    }

    private function validate_unlock_password($user_id, $input) {
        if (empty($input)) {
            return false;
        }

        $hash = get_user_meta($user_id, 'oc_unlock_password_hash', true);
        if (empty($hash)) {
            return false;
        }

        return wp_check_password($input, $hash);
    }

    private function unlock_ip_address($ip) {
        $locked_ips = get_option($this->locked_ips_key, array());
        if (!isset($locked_ips[$ip])) {
            return false;
        }

        unset($locked_ips[$ip]);
        update_option($this->locked_ips_key, $locked_ips);

        $blocks = $this->get_permanent_blocks();
        unset($blocks['ips'][$ip]);
        $this->save_permanent_blocks($blocks);

        $this->remove_blocked_record('ip:' . $ip);

        return true;
    }

    public function prune_expired_locks() {
        $locked_ips = get_option($this->locked_ips_key, array());
        $locked_users = get_option($this->locked_users_key, array());
        $records = $this->get_blocked_records();
        $changed = false;

        foreach ($locked_ips as $ip => $data) {
            if (!empty($data['permanent'])) {
                continue;
            }
            if (isset($data['locked_at']) && $this->has_lock_expired($data['locked_at'])) {
                unset($locked_ips[$ip]);
                $this->remove_blocked_record('ip:' . $ip);
                $changed = true;
            }
        }

        if ($changed) {
            update_option($this->locked_ips_key, $locked_ips);
        }

        $changed = false;

        foreach ($locked_users as $user_id => $data) {
            if (!empty($data['permanent'])) {
                continue;
            }
            if (isset($data['locked_at']) && $this->has_lock_expired($data['locked_at'])) {
                unset($locked_users[$user_id]);
                $this->remove_blocked_record('user:' . $user_id);
                $changed = true;
            }
        }

        if ($changed) {
            update_option($this->locked_users_key, $locked_users);
        }
    }

    private function has_lock_expired($locked_at) {
        $timestamp = strtotime($locked_at);
        if (!$timestamp) {
            return false;
        }
        return (time() - $timestamp) >= $this->lock_duration;
    }

    private function collect_request_context() {
        $context = array();

        if (!empty($_SERVER['REQUEST_URI'])) {
            $context['request'] = esc_url_raw(home_url($_SERVER['REQUEST_URI']));
        }

        if (!empty($_SERVER['HTTP_REFERER'])) {
            $context['referer'] = esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER']));
        }

        if (!empty($_REQUEST['redirect_to'])) {
            $context['clicked'] = esc_url_raw(wp_unslash($_REQUEST['redirect_to']));
        }

        return $context;
    }

    public function schedule_backup_cleanup() {
        if (!wp_next_scheduled($this->backup_cleanup_hook)) {
            wp_schedule_event(time(), 'twicedaily', $this->backup_cleanup_hook);
        }
    }

    private function track_backup_file($path) {
        $queue = get_option($this->backup_queue_key, array());
        $queue[$path] = current_time('timestamp');
        update_option($this->backup_queue_key, $queue);
    }

    public function cleanup_backup_queue() {
        $queue = get_option($this->backup_queue_key, array());
        if (empty($queue)) {
            return;
        }

        $now = time();
        foreach ($queue as $path => $timestamp) {
            if (($now - $timestamp) >= DAY_IN_SECONDS) {
                if (file_exists($path)) {
                    @unlink($path);
                }
                unset($queue[$path]);
            }
        }

        update_option($this->backup_queue_key, $queue);
    }

    private function remove_tracked_backup($path) {
        $queue = get_option($this->backup_queue_key, array());
        if (isset($queue[$path])) {
            unset($queue[$path]);
            update_option($this->backup_queue_key, $queue);
        }
    }

    private function deliver_backup_archive($zip_path, $download_name) {
        if (!file_exists($zip_path)) {
            wp_die(__('Unable to locate the backup archive.', 'oc-essentials'));
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($download_name) . '"');
        header('Content-Length: ' . filesize($zip_path));
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($zip_path);

        $this->remove_tracked_backup($zip_path);
        @unlink($zip_path);
        exit;
    }

    private function sanitize_color($color, $fallback = '#111111') {
        $color = sanitize_hex_color($color);
        return $color ? $color : $fallback;
    }

    public function grant_preview_access($caps, $cap, $user_id, $args) {
        $options = get_option($this->options_key, array());
        if (empty($options['draft_preview_share'])) {
            return $caps;
        }

        if (!in_array($cap, array('edit_post', 'read_post'), true)) {
            return $caps;
        }

        if (empty($_GET['preview']) || $_GET['preview'] !== 'true') {
            return $caps;
        }

        $post_id = 0;
        if (!empty($args[0])) {
            $post_id = intval($args[0]);
        } elseif (!empty($_GET['preview_id'])) {
            $post_id = intval($_GET['preview_id']);
        }

        if (!$post_id) {
            return $caps;
        }

        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'draft') {
            return $caps;
        }

        $nonce = '';
        if (!empty($_GET['preview_nonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_GET['preview_nonce']));
        } elseif (!empty($_GET['_wpnonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce']));
        }

        if (!$nonce || !wp_verify_nonce($nonce, 'post_preview_' . $post_id)) {
            return $caps;
        }

        return array('exist');
    }

    public function maybe_noindex_drafts($robots, $context) {
        if (is_singular()) {
            $post = get_post();
            if ($post && $post->post_status === 'draft') {
                $robots['noindex'] = true;
                $robots['nofollow'] = true;
            }
        }

        return $robots;
    }
    
    /**
     * Helper: Get user IP
     */
    private function get_user_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR']);
        } else {
            return sanitize_text_field($_SERVER['REMOTE_ADDR']);
        }
    }
    
    /**
     * Helper: Get country from IP
     */
    private function get_country_from_ip($ip) {
        // Use a free IP geolocation service
        $response = wp_remote_get("http://ip-api.com/json/{$ip}?fields=country", array('timeout' => 5));
        
        if (is_wp_error($response)) {
            return 'Unknown';
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return isset($data['country']) ? $data['country'] : 'Unknown';
    }
    
    /**
     * General AJAX handler for various actions
     */
    public function handle_ajax_actions() {
        check_ajax_referer('oc_essentials_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $action = sanitize_text_field($_POST['sub_action']);
        
        switch ($action) {
            case 'check_ssl':
                $ssl_info = $this->get_ssl_info();
                wp_send_json_success($ssl_info);
                break;
                
            default:
                wp_send_json_error('Invalid action');
        }
    }
    
    /**
     * Uninstall Method
     * Called when the module is deleted from Onyx Command
     * Reverts all settings and removes all data created by this module
     */
    public function uninstall() {
        global $wpdb;
        
        // Log the uninstall start
        if (class_exists('OC_Error_Logger')) {
            OC_Error_Logger::log('info', 'Onyx Essentials uninstall started', 'Beginning cleanup process');
        }
        
        // =========================================
        // 1. DELETE MODULE OPTIONS
        // =========================================
        delete_option($this->options_key);           // oc_essentials_options
        delete_option($this->locked_ips_key);        // oc_locked_ips
        delete_option($this->locked_users_key);      // oc_locked_users
        delete_option($this->login_attempts_key);    // oc_login_attempts
        
        // =========================================
        // 2. REMOVE POST META DATA
        // =========================================
        // Remove comment control meta from all posts/pages
        $wpdb->query(
            "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_oc_allow_comments'"
        );
        
        // =========================================
        // 3. REVERT WORDPRESS SETTINGS
        // =========================================
        
        // Re-enable comments on all posts that were disabled
        $wpdb->query(
            "UPDATE {$wpdb->posts} SET comment_status = 'open' WHERE comment_status = 'closed'"
        );
        
        // =========================================
        // 4. CLEAN UP TRANSIENTS
        // =========================================
        // Delete any transients this module may have created
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_oc_essentials_%'"
        );
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_timeout_oc_essentials_%'"
        );
        
        // =========================================
        // 5. REMOVE SCHEDULED EVENTS
        // =========================================
        // Clear any scheduled cron events (future-proofing)
        $cron_hooks = array(
            'oc_essentials_daily_cleanup',
            'oc_essentials_security_scan',
            'oc_essentials_cache_clear',
            'oc_essentials_ssl_check'
        );
        
        foreach ($cron_hooks as $hook) {
            $timestamp = wp_next_scheduled($hook);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
            }
            // Also clear all instances
            wp_clear_scheduled_hook($hook);
        }
        
        // =========================================
        // 6. CLEAN UP USER META
        // =========================================
        // Remove any user meta added by this module
        $wpdb->query(
            "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'oc_essentials_%'"
        );
        
        // =========================================
        // 7. REMOVE TEMPORARY FILES
        // =========================================
        $this->cleanup_temp_files();
        
        // =========================================
        // 8. CLEAN UP UPLOAD DIRECTORY
        // =========================================
        $this->cleanup_upload_directory();
        
        // =========================================
        // 9. REMOVE CAPABILITY MODIFICATIONS
        // =========================================
        // Restore any capabilities that were modified
        // (This module doesn't modify caps, but included for completeness)
        
        // =========================================
        // 10. FLUSH REWRITE RULES
        // =========================================
        // In case any custom rewrite rules were added
        flush_rewrite_rules();
        
        // =========================================
        // 11. CLEAR ALL CACHES
        // =========================================
        $this->flush_all_caches();
        
        // Log completion
        if (class_exists('OC_Error_Logger')) {
            OC_Error_Logger::log('info', 'Onyx Essentials uninstall completed', 'All module data has been removed');
        }
        
        return true;
    }
    
    /**
     * Clean up temporary files created by the module
     */
    private function cleanup_temp_files() {
        $temp_dir = get_temp_dir();
        
        // Find and delete any backup files created by this module
        $patterns = array(
            'database-backup-*.sql',
            'site-backup-*.zip',
            'files-backup-*.zip'
        );
        
        foreach ($patterns as $pattern) {
            $files = glob($temp_dir . $pattern);
            if ($files) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
        }
    }
    
    /**
     * Clean up any files in the uploads directory created by this module
     */
    private function cleanup_upload_directory() {
        $upload_dir = wp_upload_dir();
        $essentials_dir = $upload_dir['basedir'] . '/onyx-essentials/';
        
        if (is_dir($essentials_dir)) {
            $this->recursive_delete($essentials_dir);
        }
        
        // Also check for any backup directories
        $backup_dir = $upload_dir['basedir'] . '/oc-backups/';
        if (is_dir($backup_dir)) {
            $this->recursive_delete($backup_dir);
        }
    }
    
    /**
     * Recursively delete a directory and its contents
     */
    private function recursive_delete($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->recursive_delete($path);
            } else {
                @unlink($path);
            }
        }
        
        @rmdir($dir);
    }
    
    /**
     * Flush all possible caches after uninstall
     */
    private function flush_all_caches() {
        // WordPress object cache
        wp_cache_flush();
        
        // Common caching plugins
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }
        
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }
        
        if (function_exists('wp_rocket_clean_domain')) {
            wp_rocket_clean_domain();
        }
        
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
        
        if (class_exists('WpFastestCache')) {
            $cache = new WpFastestCache();
            if (method_exists($cache, 'deleteCache')) {
                $cache->deleteCache();
            }
        }
        
        if (function_exists('litespeed_purge_all')) {
            litespeed_purge_all();
        }
        
        // Clear opcache if available
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }
    
    /**
     * Get summary of what will be removed (for confirmation dialogs)
     * Can be called statically or through instance
     */
    public static function get_uninstall_summary() {
        global $wpdb;
        
        $summary = array(
            'options' => array(
                'oc_essentials_options' => get_option('oc_essentials_options') ? 'Yes' : 'No',
                'oc_locked_ips' => get_option('oc_locked_ips') ? 'Yes' : 'No',
                'oc_locked_users' => get_option('oc_locked_users') ? 'Yes' : 'No',
                'oc_login_attempts' => get_option('oc_login_attempts') ? 'Yes' : 'No',
            ),
            'post_meta_count' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_oc_allow_comments'"
            ),
            'user_meta_count' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key LIKE 'oc_essentials_%'"
            ),
            'transients_count' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '%_transient_oc_essentials_%'"
            ),
        );
        
        // Check for upload directories
        $upload_dir = wp_upload_dir();
        $summary['has_essentials_dir'] = is_dir($upload_dir['basedir'] . '/onyx-essentials/');
        $summary['has_backup_dir'] = is_dir($upload_dir['basedir'] . '/oc-backups/');
        
        return $summary;
    }
}

// Initialize the module
OC_Onyx_Essentials::get_instance();
