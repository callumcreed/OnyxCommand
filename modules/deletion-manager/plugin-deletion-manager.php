<?php
/**
 * Module ID: plugin-deletion-manager
 * Module Name: Deletion Manager
 * Description: Enhanced deletion workflow for WordPress with 30-day archive, backup capability, and detailed logging for plugins, themes, posts, pages, and media
 * Version: 2.0.0
 * Author: Callum Creed
 * 
 * Features:
 * - Intercepts delete actions for ALL plugins
 * - 30-day deletion archive for plugins, themes, posts, pages, media
 * - Automatic cleanup after 30 days
 * - Detailed deletion logging
 * - One-click restore from archive
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access not allowed');
}

// Ensure this is loaded as a module
if (!defined('OC_PLUGIN_DIR')) {
    if (is_admin()) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>Deletion Manager Error:</strong> This is a module for Onyx Command plugin.</p></div>';
        });
    }
    return;
}

/**
 * Deletion Manager Module
 */
class Plugin_Deletion_Manager {
    
    private static $instance = null;
    private $mu_plugin_file = 'onyx-plugin-deletion-manager.php';
    private $mu_plugins_dir;
    private $archive_dir;
    private $table_name;
    private $archive_days = 30;
    private $retention_choices = array(3, 5, 14, 30);
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        
        $stored_retention = get_option('pdm_retention_days', false);
        if ($stored_retention === false) {
            $stored_retention = 30;
            add_option('pdm_retention_days', $stored_retention);
        }
        $stored_retention = intval($stored_retention);
        if (!in_array($stored_retention, $this->retention_choices, true)) {
            $stored_retention = 30;
            update_option('pdm_retention_days', $stored_retention);
        }
        $this->archive_days = $stored_retention;
        
        // Set directories and table
        $this->mu_plugins_dir = defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : (WP_CONTENT_DIR . '/mu-plugins');
        $this->archive_dir = WP_CONTENT_DIR . '/oc-deletion-archive';
        $this->table_name = $wpdb->prefix . 'oc_deletion_archive';
        
        // Create database table
        $this->create_table();
        
        // Install MU plugin
        $this->install_mu_plugin();
        
        // Ensure archive directory exists
        $this->ensure_archive_dir();
        
        // Hook into WordPress deletion actions
        $this->register_deletion_hooks();
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'), 20);
        
        // AJAX handlers
        add_action('wp_ajax_pdm_get_plugin_info', array($this, 'ajax_get_plugin_info'));
        add_action('wp_ajax_pdm_delete_plugin', array($this, 'ajax_delete_plugin'));
        add_action('wp_ajax_pdm_restore_item', array($this, 'ajax_restore_item'));
        add_action('wp_ajax_pdm_permanent_delete', array($this, 'ajax_permanent_delete'));
        add_action('wp_ajax_pdm_empty_archive', array($this, 'ajax_empty_archive'));
        
        // Schedule cleanup cron
        add_action('pdm_daily_cleanup', array($this, 'cleanup_expired_archives'));
        if (!wp_next_scheduled('pdm_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'pdm_daily_cleanup');
        }
        
        // Admin notices
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // Handle module actions
        add_action('admin_init', array($this, 'handle_module_actions'));
    }
    
    /**
     * Create the archive database table
     */
    private function create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            item_type varchar(50) NOT NULL,
            item_id varchar(255) NOT NULL,
            item_name varchar(255) NOT NULL,
            item_slug varchar(255),
            item_version varchar(50),
            item_author varchar(255),
            item_description text,
            archive_path varchar(500),
            original_path varchar(500),
            file_size bigint(20) DEFAULT 0,
            file_count int(11) DEFAULT 0,
            deleted_data longtext,
            delete_type varchar(50) DEFAULT 'files_only',
            deleted_by bigint(20),
            deleted_at datetime NOT NULL,
            expires_at datetime NOT NULL,
            restored_at datetime DEFAULT NULL,
            status varchar(20) DEFAULT 'archived',
            metadata longtext,
            PRIMARY KEY (id),
            KEY item_type (item_type),
            KEY status (status),
            KEY expires_at (expires_at),
            KEY deleted_at (deleted_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Ensure archive directory exists
     */
    private function ensure_archive_dir() {
        if (!file_exists($this->archive_dir)) {
            wp_mkdir_p($this->archive_dir);
        }
        
        // Protect directory
        $htaccess = $this->archive_dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "deny from all\n");
        }
        
        $index = $this->archive_dir . '/index.php';
        if (!file_exists($index)) {
            file_put_contents($index, '<?php // Silence is golden');
        }
        
        // Create subdirectories
        $subdirs = array('plugins', 'themes', 'posts', 'pages', 'media', 'modules', 'other');
        foreach ($subdirs as $subdir) {
            $path = $this->archive_dir . '/' . $subdir;
            if (!file_exists($path)) {
                wp_mkdir_p($path);
            }
        }
    }
    
    /**
     * Register hooks for various WordPress deletion actions
     */
    private function register_deletion_hooks() {
        // Posts and Pages - hook before deletion
        add_action('before_delete_post', array($this, 'archive_post'), 10, 2);
        
        // Media/Attachments
        add_action('delete_attachment', array($this, 'archive_attachment'), 10, 2);
        
        // Themes - hook into switch/delete
        add_action('switch_theme', array($this, 'on_theme_switch'), 10, 3);
        add_filter('pre_delete_theme', array($this, 'archive_theme'), 10, 2);
        
        // Comments
        add_action('delete_comment', array($this, 'archive_comment'), 10, 2);
        
        // Users
        add_action('delete_user', array($this, 'archive_user'), 10, 3);
    }
    
    /**
     * Archive a post before deletion
     */
    public function archive_post($post_id, $post = null) {
        if (!$post) {
            $post = get_post($post_id);
        }
        
        if (!$post) return;
        
        // Skip revisions and auto-drafts
        if ($post->post_type === 'revision' || $post->post_status === 'auto-draft') {
            return;
        }
        
        // Determine item type
        $item_type = $post->post_type;
        if ($item_type === 'attachment') {
            return; // Handled separately
        }
        
        // Get post meta
        $meta = get_post_meta($post_id);
        
        // Get taxonomies
        $taxonomies = array();
        $tax_names = get_object_taxonomies($post->post_type);
        foreach ($tax_names as $tax_name) {
            $terms = wp_get_object_terms($post_id, $tax_name);
            if (!is_wp_error($terms)) {
                $taxonomies[$tax_name] = $terms;
            }
        }
        
        // Get featured image
        $thumbnail_id = get_post_thumbnail_id($post_id);
        $thumbnail_url = $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : '';
        
        // Prepare data
        $data = array(
            'post' => (array) $post,
            'meta' => $meta,
            'taxonomies' => $taxonomies,
            'thumbnail_id' => $thumbnail_id,
            'thumbnail_url' => $thumbnail_url
        );
        
        // Create archive record
        $this->create_archive_record(
            $item_type,
            $post_id,
            $post->post_title,
            $post->post_name,
            null,
            get_the_author_meta('display_name', $post->post_author),
            wp_trim_words($post->post_content, 30),
            null,
            null,
            0,
            0,
            $data,
            'complete'
        );
    }
    
    /**
     * Archive an attachment before deletion
     */
    public function archive_attachment($attachment_id, $attachment = null) {
        if (!$attachment) {
            $attachment = get_post($attachment_id);
        }
        
        if (!$attachment) return;
        
        // Get attachment metadata
        $meta = wp_get_attachment_metadata($attachment_id);
        $file_path = get_attached_file($attachment_id);
        $file_url = wp_get_attachment_url($attachment_id);
        
        // Archive the actual file
        $archive_path = null;
        $file_size = 0;
        $file_count = 1;
        
        if ($file_path && file_exists($file_path)) {
            $archive_subdir = $this->archive_dir . '/media/' . date('Y/m');
            wp_mkdir_p($archive_subdir);
            
            $archive_filename = $attachment_id . '_' . basename($file_path);
            $archive_path = $archive_subdir . '/' . $archive_filename;
            
            // Copy main file
            copy($file_path, $archive_path);
            $file_size = filesize($file_path);
            
            // Copy thumbnails/sizes
            if (!empty($meta['sizes'])) {
                $upload_dir = dirname($file_path);
                foreach ($meta['sizes'] as $size => $size_data) {
                    $size_file = $upload_dir . '/' . $size_data['file'];
                    if (file_exists($size_file)) {
                        $size_archive = $archive_subdir . '/' . $attachment_id . '_' . $size_data['file'];
                        copy($size_file, $size_archive);
                        $file_size += filesize($size_file);
                        $file_count++;
                    }
                }
            }
        }
        
        // Prepare data
        $data = array(
            'attachment' => (array) $attachment,
            'meta' => $meta,
            'file_path' => $file_path,
            'file_url' => $file_url,
            'post_meta' => get_post_meta($attachment_id)
        );
        
        $this->create_archive_record(
            'media',
            $attachment_id,
            $attachment->post_title ?: basename($file_path),
            $attachment->post_name,
            null,
            get_the_author_meta('display_name', $attachment->post_author),
            $attachment->post_mime_type,
            $archive_path,
            $file_path,
            $file_size,
            $file_count,
            $data,
            'complete'
        );
    }
    
    /**
     * Archive a theme before deletion
     */
    public function archive_theme($delete, $stylesheet) {
        $theme = wp_get_theme($stylesheet);
        
        if (!$theme->exists()) {
            return $delete;
        }
        
        $theme_dir = $theme->get_stylesheet_directory();
        $archive_path = $this->archive_dir . '/themes/' . $stylesheet . '_' . time();
        
        // Copy theme directory
        $this->recursive_copy($theme_dir, $archive_path);
        
        // Calculate size
        $file_size = $this->get_directory_size($theme_dir);
        $file_count = count($this->get_all_files($theme_dir));
        
        // Prepare data
        $data = array(
            'name' => $theme->get('Name'),
            'version' => $theme->get('Version'),
            'author' => $theme->get('Author'),
            'description' => $theme->get('Description'),
            'template' => $theme->get_template(),
            'stylesheet' => $stylesheet,
            'theme_root' => $theme->get_theme_root()
        );
        
        $this->create_archive_record(
            'theme',
            $stylesheet,
            $theme->get('Name'),
            $stylesheet,
            $theme->get('Version'),
            $theme->get('Author'),
            $theme->get('Description'),
            $archive_path,
            $theme_dir,
            $file_size,
            $file_count,
            $data,
            'complete'
        );
        
        return $delete; // Continue with deletion
    }
    
    /**
     * Archive a comment before deletion
     */
    public function archive_comment($comment_id, $comment = null) {
        if (!$comment) {
            $comment = get_comment($comment_id);
        }
        
        if (!$comment) return;
        
        $meta = get_comment_meta($comment_id);
        
        $data = array(
            'comment' => (array) $comment,
            'meta' => $meta
        );
        
        $this->create_archive_record(
            'comment',
            $comment_id,
            wp_trim_words($comment->comment_content, 10),
            null,
            null,
            $comment->comment_author,
            $comment->comment_content,
            null,
            null,
            0,
            0,
            $data,
            'complete'
        );
    }
    
    /**
     * Archive a user before deletion
     */
    public function archive_user($user_id, $reassign, $user = null) {
        if (!$user) {
            $user = get_userdata($user_id);
        }
        
        if (!$user) return;
        
        $meta = get_user_meta($user_id);
        
        $data = array(
            'user' => array(
                'ID' => $user->ID,
                'user_login' => $user->user_login,
                'user_email' => $user->user_email,
                'user_nicename' => $user->user_nicename,
                'display_name' => $user->display_name,
                'user_registered' => $user->user_registered,
                'roles' => $user->roles
            ),
            'meta' => $meta,
            'reassign_to' => $reassign
        );
        
        $this->create_archive_record(
            'user',
            $user_id,
            $user->display_name,
            $user->user_login,
            null,
            $user->user_email,
            'Roles: ' . implode(', ', $user->roles),
            null,
            null,
            0,
            0,
            $data,
            'complete'
        );
    }
    
    /**
     * Create archive record in database
     */
    private function create_archive_record($item_type, $item_id, $item_name, $item_slug, $item_version, $item_author, $item_description, $archive_path, $original_path, $file_size, $file_count, $deleted_data, $delete_type, $metadata = array()) {
        global $wpdb;
        
        $current_user = wp_get_current_user();
        $deleted_at = current_time('mysql');
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$this->archive_days} days"));
        
        $wpdb->insert(
            $this->table_name,
            array(
                'item_type' => $item_type,
                'item_id' => $item_id,
                'item_name' => $item_name,
                'item_slug' => $item_slug,
                'item_version' => $item_version,
                'item_author' => $item_author,
                'item_description' => $item_description,
                'archive_path' => $archive_path,
                'original_path' => $original_path,
                'file_size' => $file_size,
                'file_count' => $file_count,
                'deleted_data' => json_encode($deleted_data),
                'delete_type' => $delete_type,
                'deleted_by' => $current_user->ID,
                'deleted_at' => $deleted_at,
                'expires_at' => $expires_at,
                'status' => 'archived',
                'metadata' => json_encode($metadata)
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Archive a plugin (called from AJAX)
     */
    public function archive_plugin($plugin_file, $delete_data = false) {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
        
        if (!file_exists($plugin_path)) {
            return new WP_Error('not_found', 'Plugin file not found');
        }
        
        $plugin_data = get_plugin_data($plugin_path);
        $plugin_dir = dirname($plugin_path);
        $plugin_slug = dirname($plugin_file);
        
        // Create archive directory
        $archive_subdir = $this->archive_dir . '/plugins/' . $plugin_slug . '_' . time();
        wp_mkdir_p($archive_subdir);
        
        // Copy plugin files
        if (is_dir($plugin_dir) && $plugin_slug !== '.') {
            $this->recursive_copy($plugin_dir, $archive_subdir);
        } else {
            // Single file plugin
            copy($plugin_path, $archive_subdir . '/' . basename($plugin_file));
        }
        
        // Calculate size and file count
        $file_size = $this->get_directory_size($plugin_dir);
        $files = $this->get_all_files($plugin_dir);
        $file_count = count($files);
        
        // Collect database data if delete_data is true
        $db_data = array();
        if ($delete_data) {
            $db_data = $this->collect_plugin_db_data($plugin_file);
        }
        
        // Prepare deleted data
        $deleted_data = array(
            'plugin_data' => $plugin_data,
            'plugin_file' => $plugin_file,
            'was_active' => is_plugin_active($plugin_file),
            'files' => array_map(function($f) use ($plugin_dir) {
                return str_replace($plugin_dir, '', $f);
            }, $files),
            'db_data' => $db_data
        );
        
        // Create archive record
        $archive_id = $this->create_archive_record(
            'plugin',
            $plugin_file,
            $plugin_data['Name'],
            $plugin_slug,
            $plugin_data['Version'],
            $plugin_data['Author'],
            $plugin_data['Description'],
            $archive_subdir,
            $plugin_dir,
            $file_size,
            $file_count,
            $deleted_data,
            $delete_data ? 'complete' : 'files_only'
        );
        
        // Deactivate if active
        if (is_plugin_active($plugin_file)) {
            deactivate_plugins($plugin_file);
        }
        
        // Delete database data if requested
        $tables_deleted = array();
        $options_deleted = array();
        $transients_deleted = 0;
        $usermeta_deleted = 0;
        $postmeta_deleted = 0;
        
        if ($delete_data && !empty($db_data)) {
            global $wpdb;
            
            // Delete tables
            foreach ($db_data['tables'] as $table) {
                $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
                $tables_deleted[] = $table;
            }
            
            // Delete options
            foreach ($db_data['options'] as $option) {
                delete_option($option);
                $options_deleted[] = $option;
            }
            
            // Delete transients
            $plugin_slug_underscore = str_replace('-', '_', $plugin_slug);
            $transients_deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_%' . $wpdb->esc_like($plugin_slug) . '%',
                '_transient_timeout_%' . $wpdb->esc_like($plugin_slug) . '%'
            ));
            
            // Delete usermeta
            $usermeta_deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
                '%' . $wpdb->esc_like($plugin_slug) . '%',
                '%' . $wpdb->esc_like($plugin_slug_underscore) . '%'
            ));
            
            // Delete postmeta
            $postmeta_deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
                '%' . $wpdb->esc_like($plugin_slug) . '%',
                '%' . $wpdb->esc_like($plugin_slug_underscore) . '%'
            ));
        }
        
        // Delete the actual plugin files
        if (is_dir($plugin_dir) && $plugin_slug !== '.') {
            $this->recursive_delete($plugin_dir);
        } else {
            unlink($plugin_path);
        }
        
        // Log the deletion
        $this->log_info("Plugin archived and deleted: {$plugin_data['Name']}");
        
        return array(
            'archive_id' => $archive_id,
            'plugin_name' => $plugin_data['Name'],
            'plugin_file' => $plugin_file,
            'plugin_version' => $plugin_data['Version'],
            'delete_type' => $delete_data ? 'complete' : 'files_only',
            'file_size' => $file_size,
            'file_count' => $file_count,
            'tables_deleted' => $tables_deleted,
            'options_deleted' => $options_deleted,
            'transients_deleted' => $transients_deleted ?: 0,
            'usermeta_deleted' => $usermeta_deleted ?: 0,
            'postmeta_deleted' => $postmeta_deleted ?: 0,
            'expires_at' => date('Y-m-d H:i:s', strtotime("+{$this->archive_days} days")),
            'archived_files' => array_slice($files, 0, 50)
        );
    }

    /**
     * Archive an Onyx Command module before deletion
     *
     * @param array $module Module row from database
     * @return int|WP_Error
     */
    public function archive_onyx_module($module) {
        if (empty($module['module_id'])) {
            return new WP_Error('pdm_module_invalid', __('Missing module ID.', 'onyx-command'));
        }

        $module_id = $module['module_id'];
        $module_dir = trailingslashit(OC_MODULES_DIR) . $module_id;

        if (!is_dir($module_dir) && !empty($module['file_path'])) {
            $relative_dir = trim(dirname($module['file_path']), '/\\');
            if (!empty($relative_dir) && $relative_dir !== '.') {
                $module_dir = trailingslashit(OC_MODULES_DIR) . $relative_dir;
            }
        }

        if (!is_dir($module_dir)) {
            return new WP_Error('pdm_module_missing', sprintf(__('Module directory not found: %s', 'onyx-command'), $module_dir));
        }

        $archive_subdir = $this->archive_dir . '/modules/' . sanitize_title($module_id) . '_' . time();
        wp_mkdir_p($archive_subdir);
        $this->recursive_copy($module_dir, $archive_subdir);

        $files = $this->get_all_files($module_dir);
        $file_size = $this->get_directory_size($module_dir);
        $file_count = count($files);

        $deleted_data = array(
            'module' => $module,
            'files' => array_map(function($file) use ($module_dir) {
                return ltrim(str_replace($module_dir, '', $file), '/\\');
            }, $files),
        );

        $archive_id = $this->create_archive_record(
            'onyx_module',
            $module_id,
            $module['name'],
            $module_id,
            isset($module['version']) ? $module['version'] : '',
            isset($module['author']) ? $module['author'] : '',
            isset($module['description']) ? $module['description'] : '',
            $archive_subdir,
            $module_dir,
            $file_size,
            $file_count,
            $deleted_data,
            'files_only'
        );

        $this->log_info(sprintf('Onyx module archived: %s (record #%d)', $module_id, $archive_id));

        return $archive_id;
    }
    
    /**
     * Collect plugin database data before deletion
     */
    private function collect_plugin_db_data($plugin_file) {
        global $wpdb;
        
        $plugin_slug = sanitize_title(dirname($plugin_file));
        $plugin_slug_underscore = str_replace('-', '_', $plugin_slug);
        
        $data = array(
            'tables' => array(),
            'options' => array(),
            'options_data' => array()
        );
        
        // Find tables
        $all_tables = $wpdb->get_col("SHOW TABLES");
        $core_tables = array(
            $wpdb->prefix . 'commentmeta', $wpdb->prefix . 'comments',
            $wpdb->prefix . 'links', $wpdb->prefix . 'options',
            $wpdb->prefix . 'postmeta', $wpdb->prefix . 'posts',
            $wpdb->prefix . 'termmeta', $wpdb->prefix . 'terms',
            $wpdb->prefix . 'term_relationships', $wpdb->prefix . 'term_taxonomy',
            $wpdb->prefix . 'usermeta', $wpdb->prefix . 'users'
        );
        
        foreach ($all_tables as $table) {
            if (in_array($table, $core_tables)) continue;
            
            $table_without_prefix = str_replace($wpdb->prefix, '', $table);
            if (stripos($table_without_prefix, $plugin_slug) !== false || 
                stripos($table_without_prefix, $plugin_slug_underscore) !== false) {
                $data['tables'][] = $table;
            }
        }
        
        // Find options
        $options = $wpdb->get_results($wpdb->prepare(
            "SELECT option_name, option_value FROM {$wpdb->options} 
             WHERE option_name LIKE %s OR option_name LIKE %s LIMIT 500",
            '%' . $wpdb->esc_like($plugin_slug) . '%',
            '%' . $wpdb->esc_like($plugin_slug_underscore) . '%'
        ));
        
        foreach ($options as $option) {
            $data['options'][] = $option->option_name;
            $data['options_data'][$option->option_name] = $option->option_value;
        }
        
        return $data;
    }
    
    /**
     * Restore an item from archive
     */
    public function restore_item($archive_id) {
        global $wpdb;
        
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d AND status = 'archived'",
            $archive_id
        ));
        
        if (!$record) {
            return new WP_Error('not_found', 'Archive record not found');
        }
        
        $deleted_data = json_decode($record->deleted_data, true);
        $result = array('success' => false, 'message' => '');
        
        switch ($record->item_type) {
            case 'plugin':
                $result = $this->restore_plugin($record, $deleted_data);
                break;
            case 'theme':
                $result = $this->restore_theme($record, $deleted_data);
                break;
            case 'post':
            case 'page':
                $result = $this->restore_post($record, $deleted_data);
                break;
            case 'media':
                $result = $this->restore_media($record, $deleted_data);
                break;
            case 'comment':
                $result = $this->restore_comment($record, $deleted_data);
                break;
            case 'user':
                $result = $this->restore_user($record, $deleted_data);
                break;
            default:
                return new WP_Error('unknown_type', 'Unknown item type: ' . $record->item_type);
        }
        
        if ($result['success']) {
            // Update record status
            $wpdb->update(
                $this->table_name,
                array('status' => 'restored', 'restored_at' => current_time('mysql')),
                array('id' => $archive_id),
                array('%s', '%s'),
                array('%d')
            );
            
            // Clean up archive files
            if ($record->archive_path && file_exists($record->archive_path)) {
                $this->recursive_delete($record->archive_path);
            }
            
            $this->log_info("Item restored from archive: {$record->item_name}");
        }
        
        return $result;
    }
    
    /**
     * Restore a plugin from archive
     */
    private function restore_plugin($record, $deleted_data) {
        $archive_path = $record->archive_path;
        $original_path = $record->original_path;
        
        if (!file_exists($archive_path)) {
            return array('success' => false, 'message' => 'Archive files not found');
        }
        
        // Restore plugin files
        wp_mkdir_p(dirname($original_path));
        $this->recursive_copy($archive_path, $original_path);
        
        // Restore database data if it was a complete deletion
        if ($record->delete_type === 'complete' && !empty($deleted_data['db_data'])) {
            global $wpdb;
            
            // Restore options
            if (!empty($deleted_data['db_data']['options_data'])) {
                foreach ($deleted_data['db_data']['options_data'] as $option_name => $option_value) {
                    update_option($option_name, maybe_unserialize($option_value));
                }
            }
        }
        
        // Reactivate if it was active
        if (!empty($deleted_data['was_active'])) {
            activate_plugin($deleted_data['plugin_file']);
        }
        
        return array('success' => true, 'message' => 'Plugin restored successfully');
    }
    
    /**
     * Restore a theme from archive
     */
    private function restore_theme($record, $deleted_data) {
        $archive_path = $record->archive_path;
        $theme_dir = get_theme_root() . '/' . $record->item_slug;
        
        if (!file_exists($archive_path)) {
            return array('success' => false, 'message' => 'Archive files not found');
        }
        
        $this->recursive_copy($archive_path, $theme_dir);
        
        return array('success' => true, 'message' => 'Theme restored successfully');
    }
    
    /**
     * Restore a post from archive
     */
    private function restore_post($record, $deleted_data) {
        if (empty($deleted_data['post'])) {
            return array('success' => false, 'message' => 'Post data not found in archive');
        }
        
        $post_data = $deleted_data['post'];
        unset($post_data['ID']); // Let WordPress assign new ID
        
        $new_post_id = wp_insert_post($post_data);
        
        if (is_wp_error($new_post_id)) {
            return array('success' => false, 'message' => $new_post_id->get_error_message());
        }
        
        // Restore meta
        if (!empty($deleted_data['meta'])) {
            foreach ($deleted_data['meta'] as $key => $values) {
                foreach ($values as $value) {
                    add_post_meta($new_post_id, $key, maybe_unserialize($value));
                }
            }
        }
        
        // Restore taxonomies
        if (!empty($deleted_data['taxonomies'])) {
            foreach ($deleted_data['taxonomies'] as $taxonomy => $terms) {
                $term_ids = array();
                foreach ($terms as $term) {
                    $term_ids[] = $term->term_id;
                }
                wp_set_object_terms($new_post_id, $term_ids, $taxonomy);
            }
        }
        
        return array('success' => true, 'message' => 'Post restored successfully', 'new_id' => $new_post_id);
    }
    
    /**
     * Restore media from archive
     */
    private function restore_media($record, $deleted_data) {
        if (empty($deleted_data['attachment']) || !$record->archive_path) {
            return array('success' => false, 'message' => 'Media data not found in archive');
        }
        
        // Find the main archived file
        $archive_dir = dirname($record->archive_path);
        $original_filename = basename($deleted_data['file_path']);
        $archived_file = $record->archive_path;
        
        if (!file_exists($archived_file)) {
            return array('success' => false, 'message' => 'Archived file not found');
        }
        
        // Restore to uploads directory
        $upload_dir = wp_upload_dir();
        $dest_path = $upload_dir['path'] . '/' . $original_filename;
        
        // Ensure unique filename
        $dest_path = wp_unique_filename($upload_dir['path'], $original_filename);
        $dest_path = $upload_dir['path'] . '/' . $dest_path;
        
        copy($archived_file, $dest_path);
        
        // Create attachment
        $attachment = array(
            'post_mime_type' => $deleted_data['attachment']['post_mime_type'],
            'post_title' => $deleted_data['attachment']['post_title'],
            'post_content' => '',
            'post_status' => 'inherit'
        );
        
        $attach_id = wp_insert_attachment($attachment, $dest_path);
        
        if (is_wp_error($attach_id)) {
            return array('success' => false, 'message' => $attach_id->get_error_message());
        }
        
        // Generate metadata
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $dest_path);
        wp_update_attachment_metadata($attach_id, $attach_data);
        
        return array('success' => true, 'message' => 'Media restored successfully', 'new_id' => $attach_id);
    }
    
    /**
     * Restore a comment from archive
     */
    private function restore_comment($record, $deleted_data) {
        if (empty($deleted_data['comment'])) {
            return array('success' => false, 'message' => 'Comment data not found');
        }
        
        $comment_data = $deleted_data['comment'];
        unset($comment_data['comment_ID']);
        
        $new_comment_id = wp_insert_comment($comment_data);
        
        if (!$new_comment_id) {
            return array('success' => false, 'message' => 'Failed to restore comment');
        }
        
        // Restore meta
        if (!empty($deleted_data['meta'])) {
            foreach ($deleted_data['meta'] as $key => $values) {
                foreach ($values as $value) {
                    add_comment_meta($new_comment_id, $key, maybe_unserialize($value));
                }
            }
        }
        
        return array('success' => true, 'message' => 'Comment restored successfully', 'new_id' => $new_comment_id);
    }
    
    /**
     * Restore a user from archive
     */
    private function restore_user($record, $deleted_data) {
        if (empty($deleted_data['user'])) {
            return array('success' => false, 'message' => 'User data not found');
        }
        
        $user_data = $deleted_data['user'];
        
        // Check if username/email already exists
        if (username_exists($user_data['user_login'])) {
            return array('success' => false, 'message' => 'Username already exists');
        }
        
        if (email_exists($user_data['user_email'])) {
            return array('success' => false, 'message' => 'Email already exists');
        }
        
        $new_user_id = wp_insert_user(array(
            'user_login' => $user_data['user_login'],
            'user_email' => $user_data['user_email'],
            'user_nicename' => $user_data['user_nicename'],
            'display_name' => $user_data['display_name'],
            'role' => !empty($user_data['roles']) ? $user_data['roles'][0] : 'subscriber'
        ));
        
        if (is_wp_error($new_user_id)) {
            return array('success' => false, 'message' => $new_user_id->get_error_message());
        }
        
        return array('success' => true, 'message' => 'User restored (password reset required)', 'new_id' => $new_user_id);
    }
    
    /**
     * Cleanup expired archives (30+ days old)
     */
    public function cleanup_expired_archives() {
        global $wpdb;
        
        // Get expired archives
        $expired = $wpdb->get_results(
            "SELECT * FROM {$this->table_name} 
             WHERE status = 'archived' AND expires_at < NOW()"
        );
        
        foreach ($expired as $record) {
            // Delete archive files
            if ($record->archive_path && file_exists($record->archive_path)) {
                if (is_dir($record->archive_path)) {
                    $this->recursive_delete($record->archive_path);
                } else {
                    unlink($record->archive_path);
                }
            }
            
            // Update status to expired
            $wpdb->update(
                $this->table_name,
                array('status' => 'expired'),
                array('id' => $record->id),
                array('%s'),
                array('%d')
            );
        }
        
        // Permanently delete records older than 60 days
        $wpdb->query(
            "DELETE FROM {$this->table_name} 
             WHERE status IN ('expired', 'restored') 
             AND deleted_at < DATE_SUB(NOW(), INTERVAL 60 DAY)"
        );
        
        $this->log_info("Cleanup completed. Processed " . count($expired) . " expired archives.");
    }
    
    /**
     * Get archive statistics
     */
    public function get_archive_stats() {
        global $wpdb;
        
        $stats = array(
            'total' => 0,
            'by_type' => array(),
            'total_size' => 0,
            'expiring_soon' => 0
        );
        
        // Total archived
        $stats['total'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'archived'"
        );
        
        // By type
        $by_type = $wpdb->get_results(
            "SELECT item_type, COUNT(*) as count, SUM(file_size) as size 
             FROM {$this->table_name} WHERE status = 'archived' 
             GROUP BY item_type"
        );
        foreach ($by_type as $row) {
            $stats['by_type'][$row->item_type] = array(
                'count' => $row->count,
                'size' => $row->size
            );
        }
        
        // Total size
        $stats['total_size'] = $wpdb->get_var(
            "SELECT SUM(file_size) FROM {$this->table_name} WHERE status = 'archived'"
        ) ?: 0;
        
        // Expiring within 7 days
        $stats['expiring_soon'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE status = 'archived' AND expires_at < DATE_ADD(NOW(), INTERVAL 7 DAY)"
        );
        
        return $stats;
    }
    
    /**
     * Get archived items
     */
    public function get_archived_items($type = 'all', $page = 1, $per_page = 20) {
        global $wpdb;
        
        $offset = ($page - 1) * $per_page;
        $where = "WHERE status = 'archived'";
        
        if ($type !== 'all') {
            $where .= $wpdb->prepare(" AND item_type = %s", $type);
        }
        
        $items = $wpdb->get_results(
            "SELECT * FROM {$this->table_name} {$where} ORDER BY deleted_at DESC LIMIT {$offset}, {$per_page}"
        );
        
        $total = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} {$where}"
        );
        
        return array(
            'items' => $items,
            'total' => $total,
            'pages' => ceil($total / $per_page)
        );
    }
    
    /**
     * AJAX: Get plugin info
     */
    public function ajax_get_plugin_info() {
        check_ajax_referer('pdm_action', 'nonce');
        
        if (!current_user_can('delete_plugins')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $plugin_file = isset($_POST['plugin']) ? sanitize_text_field($_POST['plugin']) : '';
        
        if (empty($plugin_file)) {
            wp_send_json_error('No plugin specified');
        }
        
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
        
        if (!file_exists($plugin_path)) {
            wp_send_json_error('Plugin file not found');
        }
        
        $plugin_data = get_plugin_data($plugin_path);
        $plugin_dir = dirname($plugin_path);
        
        $size = $this->get_directory_size($plugin_dir);
        $tables = $this->find_plugin_tables($plugin_file);
        $options = $this->find_plugin_options($plugin_file);
        
        wp_send_json_success(array(
            'name' => $plugin_data['Name'],
            'version' => $plugin_data['Version'],
            'author' => wp_strip_all_tags($plugin_data['Author']),
            'description' => $plugin_data['Description'],
            'plugin_file' => $plugin_file,
            'size' => size_format($size),
            'size_bytes' => $size,
            'tables' => $tables,
            'tables_count' => count($tables),
            'options_count' => count($options),
            'is_active' => is_plugin_active($plugin_file)
        ));
    }
    
    /**
     * AJAX: Delete plugin
     */
    public function ajax_delete_plugin() {
        check_ajax_referer('pdm_action', 'nonce');
        
        if (!current_user_can('delete_plugins')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $plugin_file = isset($_POST['plugin']) ? sanitize_text_field($_POST['plugin']) : '';
        $delete_data = isset($_POST['delete_data']) && $_POST['delete_data'] === 'true';
        
        if (empty($plugin_file)) {
            wp_send_json_error('No plugin specified');
        }
        
        $result = $this->archive_plugin($plugin_file, $delete_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        // Store result for results page
        $transient_key = 'pdm_result_' . md5($plugin_file . time());
        set_transient($transient_key, $result, 3600);
        
        wp_send_json_success(array(
            'redirect' => admin_url('admin.php?page=pdm-deletion-results&key=' . $transient_key),
            'result' => $result
        ));
    }
    
    /**
     * AJAX: Restore item
     */
    public function ajax_restore_item() {
        check_ajax_referer('pdm_action', 'nonce');
        
        if (!current_user_can('install_plugins')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $archive_id = isset($_POST['archive_id']) ? intval($_POST['archive_id']) : 0;
        
        if (!$archive_id) {
            wp_send_json_error('No archive ID specified');
        }
        
        $result = $this->restore_item($archive_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Permanent delete
     */
    public function ajax_permanent_delete() {
        check_ajax_referer('pdm_action', 'nonce');
        
        if (!current_user_can('delete_plugins')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        global $wpdb;
        
        $archive_id = isset($_POST['archive_id']) ? intval($_POST['archive_id']) : 0;
        
        if (!$archive_id) {
            wp_send_json_error('No archive ID specified');
        }
        
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $archive_id
        ));
        
        if (!$record) {
            wp_send_json_error('Archive not found');
        }
        
        // Delete files
        if ($record->archive_path && file_exists($record->archive_path)) {
            if (is_dir($record->archive_path)) {
                $this->recursive_delete($record->archive_path);
            } else {
                unlink($record->archive_path);
            }
        }
        
        // Delete record
        $wpdb->delete($this->table_name, array('id' => $archive_id), array('%d'));
        
        wp_send_json_success(array('message' => 'Item permanently deleted'));
    }
    
    /**
     * AJAX: Empty archive
     */
    public function ajax_empty_archive() {
        check_ajax_referer('pdm_action', 'nonce');
        
        if (!current_user_can('delete_plugins')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        global $wpdb;
        
        // Get all archived items
        $items = $wpdb->get_results(
            "SELECT * FROM {$this->table_name} WHERE status = 'archived'"
        );
        
        foreach ($items as $item) {
            if ($item->archive_path && file_exists($item->archive_path)) {
                if (is_dir($item->archive_path)) {
                    $this->recursive_delete($item->archive_path);
                } else {
                    unlink($item->archive_path);
                }
            }
        }
        
        // Delete all archived records
        $wpdb->query("DELETE FROM {$this->table_name} WHERE status = 'archived'");
        
        wp_send_json_success(array('message' => 'Archive emptied'));
    }
    
    /**
     * Find plugin tables
     */
    private function find_plugin_tables($plugin_file) {
        global $wpdb;
        
        $tables = array();
        $plugin_slug = sanitize_title(dirname($plugin_file));
        $plugin_slug_underscore = str_replace('-', '_', $plugin_slug);
        
        $all_tables = $wpdb->get_col("SHOW TABLES");
        $core_tables = array(
            $wpdb->prefix . 'commentmeta', $wpdb->prefix . 'comments',
            $wpdb->prefix . 'links', $wpdb->prefix . 'options',
            $wpdb->prefix . 'postmeta', $wpdb->prefix . 'posts',
            $wpdb->prefix . 'termmeta', $wpdb->prefix . 'terms',
            $wpdb->prefix . 'term_relationships', $wpdb->prefix . 'term_taxonomy',
            $wpdb->prefix . 'usermeta', $wpdb->prefix . 'users'
        );
        
        foreach ($all_tables as $table) {
            if (in_array($table, $core_tables)) continue;
            $table_without_prefix = str_replace($wpdb->prefix, '', $table);
            if (stripos($table_without_prefix, $plugin_slug) !== false || 
                stripos($table_without_prefix, $plugin_slug_underscore) !== false) {
                $tables[] = $table;
            }
        }
        
        return $tables;
    }
    
    /**
     * Find plugin options
     */
    private function find_plugin_options($plugin_file) {
        global $wpdb;
        
        $plugin_slug = sanitize_title(dirname($plugin_file));
        $plugin_slug_underscore = str_replace('-', '_', $plugin_slug);
        
        return $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} 
             WHERE option_name LIKE %s OR option_name LIKE %s LIMIT 100",
            '%' . $wpdb->esc_like($plugin_slug) . '%',
            '%' . $wpdb->esc_like($plugin_slug_underscore) . '%'
        ));
    }
    
    /**
     * Utility: Get directory size
     */
    private function get_directory_size($dir) {
        $size = 0;
        if (!is_dir($dir)) return file_exists($dir) ? filesize($dir) : 0;
        
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
            $size += $file->getSize();
        }
        return $size;
    }
    
    /**
     * Utility: Get all files in directory
     */
    private function get_all_files($dir) {
        $files = array();
        if (!is_dir($dir)) return array($dir);
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) $files[] = $file->getPathname();
        }
        return $files;
    }
    
    /**
     * Utility: Recursive copy
     */
    private function recursive_copy($src, $dst) {
        if (!file_exists($dst)) wp_mkdir_p($dst);
        $dir = opendir($src);
        while (($file = readdir($dir)) !== false) {
            if ($file != '.' && $file != '..') {
                $src_path = $src . '/' . $file;
                $dst_path = $dst . '/' . $file;
                if (is_dir($src_path)) {
                    $this->recursive_copy($src_path, $dst_path);
                } else {
                    copy($src_path, $dst_path);
                }
            }
        }
        closedir($dir);
    }
    
    /**
     * Utility: Recursive delete
     */
    private function recursive_delete($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        $this->recursive_delete($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            return rmdir($dir);
        }
        return true;
    }
    
    /**
     * Install MU plugin
     */
    public function install_mu_plugin() {
        if (!file_exists($this->mu_plugins_dir)) {
            if (!wp_mkdir_p($this->mu_plugins_dir)) {
                return false;
            }
        }
        
        $mu_plugin_path = $this->mu_plugins_dir . '/' . $this->mu_plugin_file;
        
        if (file_exists($mu_plugin_path)) {
            $installed_version = $this->get_mu_plugin_version($mu_plugin_path);
            if (version_compare($installed_version, '2.0.0', '>=')) {
                return true;
            }
        }
        
        return file_put_contents($mu_plugin_path, $this->get_mu_plugin_content()) !== false;
    }
    
    /**
     * Uninstall MU plugin
     */
    public function uninstall_mu_plugin() {
        $mu_plugin_path = $this->mu_plugins_dir . '/' . $this->mu_plugin_file;
        if (file_exists($mu_plugin_path)) {
            return unlink($mu_plugin_path);
        }
        return true;
    }
    
    /**
     * Check if MU plugin is installed
     */
    public function is_mu_plugin_installed() {
        return file_exists($this->mu_plugins_dir . '/' . $this->mu_plugin_file);
    }
    
    /**
     * Get MU plugin version
     */
    private function get_mu_plugin_version($file_path) {
        $content = file_get_contents($file_path);
        if (preg_match('/\* Version:\s*(.+)/i', $content, $matches)) {
            return trim($matches[1]);
        }
        return '0.0.0';
    }
    
    /**
     * Admin notices
     */
    public function admin_notices() {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'onyx') === false) return;
        
        if (!$this->is_mu_plugin_installed()) {
            echo '<div class="notice notice-warning"><p><strong>Deletion Manager:</strong> MU plugin not installed. <a href="' . admin_url('admin.php?page=pdm-archive') . '">Fix now</a></p></div>';
        }
    }
    
    /**
     * Handle module actions
     */
    public function handle_module_actions() {
        if (!isset($_GET['page'])) return;
        if (!current_user_can('manage_options')) return;
        
        if ($_GET['page'] === 'pdm-archive' && isset($_GET['action']) && $_GET['action'] === 'reinstall') {
            if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'pdm_reinstall')) {
                $this->uninstall_mu_plugin();
                $this->install_mu_plugin();
                wp_redirect(admin_url('admin.php?page=pdm-archive&message=reinstalled'));
                exit;
            }
        }
    }
    
    /**
     * Add admin menus
     */
    public function add_admin_menu() {
        add_submenu_page(
            'onyx-command',
            'Deletion Archive',
            '🗑️ Deletion Archive',
            'manage_options',
            'pdm-archive',
            array($this, 'render_archive_page')
        );
        
        add_submenu_page(
            null,
            'Deletion Results',
            'Deletion Results',
            'activate_plugins',
            'pdm-deletion-results',
            array($this, 'render_results_page')
        );
    }
    
    /**
     * Render archive page
     */
    public function render_archive_page() {
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'archive';
        $stats = $this->get_archive_stats();
        $type_filter = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'all';
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $items_data = $this->get_archived_items($type_filter, $paged, 20);
        $nonce = wp_create_nonce('pdm_action');
        $message = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';

        if ($tab === 'settings' && 'POST' === $_SERVER['REQUEST_METHOD'] && isset($_POST['pdm_retention_nonce'])) {
            check_admin_referer('pdm_retention_update', 'pdm_retention_nonce');
            $new_retention = isset($_POST['pdm_retention_days']) ? intval($_POST['pdm_retention_days']) : $this->archive_days;
            if (!in_array($new_retention, $this->retention_choices, true)) {
                $new_retention = 30;
            }
            update_option('pdm_retention_days', $new_retention);
            $this->archive_days = $new_retention;
            wp_safe_redirect(admin_url('admin.php?page=pdm-archive&tab=settings&message=retention_saved'));
            exit;
        }
        ?>
        <style>
            .pdm-wrap { max-width: 1200px; }
            .pdm-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }
            .pdm-stat-card { background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 20px; text-align: center; }
            .pdm-stat-value { font-size: 32px; font-weight: bold; color: #1d2327; }
            .pdm-stat-label { font-size: 14px; color: #666; margin-top: 5px; }
            .pdm-stat-card.warning { border-color: #f59e0b; background: #fffbeb; }
            .pdm-tabs { display: flex; gap: 0; margin: 20px 0; border-bottom: 1px solid #ccd0d4; }
            .pdm-tab { padding: 12px 24px; background: #f0f0f1; border: 1px solid #ccd0d4; border-bottom: none; margin-bottom: -1px; text-decoration: none; color: #1d2327; border-radius: 4px 4px 0 0; }
            .pdm-tab.active { background: #fff; border-bottom-color: #fff; font-weight: 600; }
            .pdm-tab:hover { background: #fff; }
            .pdm-card { background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 20px; margin: 20px 0; }
            .pdm-table { width: 100%; border-collapse: collapse; }
            .pdm-table th, .pdm-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
            .pdm-table th { background: #f8f9fa; font-weight: 600; }
            .pdm-table tr:hover { background: #f8f9fa; }
            .pdm-type-badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 500; }
            .pdm-type-plugin { background: #dbeafe; color: #1e40af; }
            .pdm-type-theme { background: #fce7f3; color: #be185d; }
            .pdm-type-post, .pdm-type-page { background: #dcfce7; color: #166534; }
            .pdm-type-media { background: #fef3c7; color: #92400e; }
            .pdm-type-comment { background: #e0e7ff; color: #3730a3; }
            .pdm-type-user { background: #f3e8ff; color: #7c3aed; }
            .pdm-type-onyx_module { background: #fee2e2; color: #b91c1c; }
            .pdm-expires { font-size: 12px; color: #666; }
            .pdm-expires.soon { color: #dc2626; font-weight: 600; }
            .pdm-actions { display: flex; gap: 8px; }
            .pdm-btn { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; text-decoration: none; display: inline-block; }
            .pdm-btn-restore { background: #22c55e; color: #fff; }
            .pdm-btn-restore:hover { background: #16a34a; color: #fff; }
            .pdm-btn-delete { background: #dc2626; color: #fff; }
            .pdm-btn-delete:hover { background: #b91c1c; color: #fff; }
            .pdm-btn-secondary { background: #6b7280; color: #fff; }
            .pdm-btn-secondary:hover { background: #4b5563; color: #fff; }
            .pdm-filter { display: flex; gap: 10px; margin: 15px 0; flex-wrap: wrap; }
            .pdm-filter a { padding: 6px 14px; background: #f0f0f1; border-radius: 4px; text-decoration: none; color: #1d2327; font-size: 13px; }
            .pdm-filter a.active { background: #2271b1; color: #fff; }
            .pdm-empty { text-align: center; padding: 60px 20px; color: #666; }
            .pdm-empty-icon { font-size: 64px; margin-bottom: 20px; }
            .pdm-pagination { display: flex; justify-content: center; gap: 5px; margin: 20px 0; }
            .pdm-pagination a, .pdm-pagination span { padding: 8px 14px; background: #f0f0f1; border-radius: 4px; text-decoration: none; color: #1d2327; }
            .pdm-pagination a:hover { background: #ddd; }
            .pdm-pagination .current { background: #2271b1; color: #fff; }
            .pdm-info-row { display: flex; gap: 40px; margin: 15px 0; }
            .pdm-info-item { flex: 1; }
            .pdm-info-label { font-size: 12px; color: #666; margin-bottom: 4px; }
            .pdm-info-value { font-weight: 500; }
        </style>
        
        <div class="wrap pdm-wrap">
            <h1>🗑️ Deletion Archive</h1>
            <p>Items are automatically archived before deletion and kept for <?php echo $this->archive_days; ?> days.</p>
            
            <?php if ($message === 'reinstalled'): ?>
                <div class="notice notice-success is-dismissible"><p>MU plugin reinstalled successfully!</p></div>
            <?php endif; ?>
            <?php if ($message === 'retention_saved'): ?>
                <div class="notice notice-success is-dismissible"><p>Retention period updated successfully.</p></div>
            <?php endif; ?>
            
            <div class="pdm-stats">
                <div class="pdm-stat-card">
                    <div class="pdm-stat-value"><?php echo number_format($stats['total']); ?></div>
                    <div class="pdm-stat-label">Items in Archive</div>
                </div>
                <div class="pdm-stat-card">
                    <div class="pdm-stat-value"><?php echo size_format($stats['total_size']); ?></div>
                    <div class="pdm-stat-label">Archive Size</div>
                </div>
                <div class="pdm-stat-card <?php echo $stats['expiring_soon'] > 0 ? 'warning' : ''; ?>">
                    <div class="pdm-stat-value"><?php echo number_format($stats['expiring_soon']); ?></div>
                    <div class="pdm-stat-label">Expiring in 7 Days</div>
                </div>
                <div class="pdm-stat-card">
                    <div class="pdm-stat-value"><?php echo $this->is_mu_plugin_installed() ? '✅' : '❌'; ?></div>
                    <div class="pdm-stat-label">MU Plugin Status</div>
                </div>
            </div>
            
            <div class="pdm-tabs">
                <a href="<?php echo admin_url('admin.php?page=pdm-archive&tab=archive'); ?>" class="pdm-tab <?php echo $tab === 'archive' ? 'active' : ''; ?>">📦 Archive</a>
                <a href="<?php echo admin_url('admin.php?page=pdm-archive&tab=settings'); ?>" class="pdm-tab <?php echo $tab === 'settings' ? 'active' : ''; ?>">⚙️ Settings</a>
            </div>
            
            <?php if ($tab === 'archive'): ?>
            
            <div class="pdm-filter">
                <a href="<?php echo admin_url('admin.php?page=pdm-archive&type=all'); ?>" class="<?php echo $type_filter === 'all' ? 'active' : ''; ?>">All</a>
                <a href="<?php echo admin_url('admin.php?page=pdm-archive&type=plugin'); ?>" class="<?php echo $type_filter === 'plugin' ? 'active' : ''; ?>">🔌 Plugins</a>
                <a href="<?php echo admin_url('admin.php?page=pdm-archive&type=theme'); ?>" class="<?php echo $type_filter === 'theme' ? 'active' : ''; ?>">🎨 Themes</a>
                <a href="<?php echo admin_url('admin.php?page=pdm-archive&type=post'); ?>" class="<?php echo $type_filter === 'post' ? 'active' : ''; ?>">📝 Posts</a>
                <a href="<?php echo admin_url('admin.php?page=pdm-archive&type=page'); ?>" class="<?php echo $type_filter === 'page' ? 'active' : ''; ?>">📄 Pages</a>
                <a href="<?php echo admin_url('admin.php?page=pdm-archive&type=media'); ?>" class="<?php echo $type_filter === 'media' ? 'active' : ''; ?>">🖼️ Media</a>
                <a href="<?php echo admin_url('admin.php?page=pdm-archive&type=comment'); ?>" class="<?php echo $type_filter === 'comment' ? 'active' : ''; ?>">💬 Comments</a>
                <a href="<?php echo admin_url('admin.php?page=pdm-archive&type=user'); ?>" class="<?php echo $type_filter === 'user' ? 'active' : ''; ?>">👤 Users</a>
                <a href="<?php echo admin_url('admin.php?page=pdm-archive&type=onyx_module'); ?>" class="<?php echo $type_filter === 'onyx_module' ? 'active' : ''; ?>">🧩 Modules</a>
            </div>
            
            <?php if (empty($items_data['items'])): ?>
                <div class="pdm-card pdm-empty">
                    <div class="pdm-empty-icon">📭</div>
                    <h3>Archive is Empty</h3>
                    <p>Deleted items will appear here for <?php echo $this->archive_days; ?> days before being permanently removed.</p>
                </div>
            <?php else: ?>
                <div class="pdm-card" style="padding: 0; overflow: hidden;">
                    <table class="pdm-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Name</th>
                                <th>Size</th>
                                <th>Deleted</th>
                                <th>Expires</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items_data['items'] as $item): 
                                $expires_soon = strtotime($item->expires_at) < strtotime('+7 days');
                                $days_left = max(0, ceil((strtotime($item->expires_at) - time()) / DAY_IN_SECONDS));
                            ?>
                            <tr data-id="<?php echo esc_attr($item->id); ?>">
                                <?php $type_label = ucwords(str_replace('_', ' ', $item->item_type)); ?>
                                <td><span class="pdm-type-badge pdm-type-<?php echo esc_attr($item->item_type); ?>"><?php echo esc_html($type_label); ?></span></td>
                                <td>
                                    <strong><?php echo esc_html($item->item_name); ?></strong>
                                    <?php if ($item->item_version): ?>
                                        <span style="color:#666;font-size:12px;">v<?php echo esc_html($item->item_version); ?></span>
                                    <?php endif; ?>
                                    <br><small style="color:#666;"><?php echo esc_html($item->delete_type === 'complete' ? 'Complete deletion' : 'Files only'); ?></small>
                                </td>
                                <td><?php echo $item->file_size ? size_format($item->file_size) : '-'; ?></td>
                                <td>
                                    <?php echo human_time_diff(strtotime($item->deleted_at), current_time('timestamp')); ?> ago
                                    <br><small style="color:#666;"><?php echo date('M j, Y', strtotime($item->deleted_at)); ?></small>
                                </td>
                                <td>
                                    <span class="pdm-expires <?php echo $expires_soon ? 'soon' : ''; ?>">
                                        <?php echo $days_left; ?> days left
                                    </span>
                                </td>
                                <td class="pdm-actions">
                                    <button type="button" class="pdm-btn pdm-btn-restore" data-action="restore" data-id="<?php echo esc_attr($item->id); ?>">↩️ Restore</button>
                                    <button type="button" class="pdm-btn pdm-btn-delete" data-action="delete" data-id="<?php echo esc_attr($item->id); ?>">🗑️ Delete</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($items_data['pages'] > 1): ?>
                <div class="pdm-pagination">
                    <?php for ($i = 1; $i <= $items_data['pages']; $i++): ?>
                        <?php if ($i === $paged): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="<?php echo admin_url('admin.php?page=pdm-archive&type=' . $type_filter . '&paged=' . $i); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
                
                <p style="text-align: center;">
                    <button type="button" class="pdm-btn pdm-btn-delete" id="pdmEmptyArchive">🗑️ Empty Entire Archive</button>
                </p>
            <?php endif; ?>
            
            <?php elseif ($tab === 'settings'): ?>
            
            <div class="pdm-card">
                <h2 style="margin-top:0;">⚙️ Settings</h2>
                
                <div class="pdm-info-row">
                    <div class="pdm-info-item">
                        <div class="pdm-info-label">MU Plugin Status</div>
                        <div class="pdm-info-value"><?php echo $this->is_mu_plugin_installed() ? '✅ Installed' : '❌ Not Installed'; ?></div>
                    </div>
                    <div class="pdm-info-item">
                        <div class="pdm-info-label">MU Plugin Path</div>
                        <div class="pdm-info-value"><code style="font-size:12px;"><?php echo esc_html($this->mu_plugins_dir . '/' . $this->mu_plugin_file); ?></code></div>
                    </div>
                </div>
                
                <div class="pdm-info-row">
                    <div class="pdm-info-item">
                        <div class="pdm-info-label">Archive Directory</div>
                        <div class="pdm-info-value"><code style="font-size:12px;"><?php echo esc_html($this->archive_dir); ?></code></div>
                    </div>
                    <div class="pdm-info-item">
                        <div class="pdm-info-label">Retention Period</div>
                        <div class="pdm-info-value"><?php echo $this->archive_days; ?> days</div>
                    </div>
                </div>

                    <form method="post" class="pdm-retention-form" style="margin:20px 0;">
                        <?php wp_nonce_field('pdm_retention_update', 'pdm_retention_nonce'); ?>
                        <label for="pdmRetentionSelect" style="display:block;font-weight:600;margin-bottom:8px;">
                            <?php esc_html_e('Retention period', 'onyx-command'); ?>
                        </label>
                        <select id="pdmRetentionSelect" name="pdm_retention_days" style="min-width:200px;">
                            <?php foreach ($this->retention_choices as $days): ?>
                                <option value="<?php echo esc_attr($days); ?>" <?php selected($this->archive_days, $days); ?>>
                                    <?php echo esc_html(sprintf(_n('%d Day', '%d Days', $days, 'onyx-command'), $days)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description" style="margin-top:8px;"><?php esc_html_e('Select how long archived items remain before automatic cleanup.', 'onyx-command'); ?></p>
                        <p style="margin-top:12px;">
                            <button type="submit" class="button button-primary"><?php esc_html_e('Save Retention', 'onyx-command'); ?></button>
                        </p>
                    </form>
                
                <hr style="margin: 20px 0;">
                
                <h3>Actions</h3>
                <p>
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=pdm-archive&tab=settings&action=reinstall'), 'pdm_reinstall'); ?>" class="button button-primary">🔄 Reinstall MU Plugin</a>
                </p>
                
                <hr style="margin: 20px 0;">
                
                <h3>What Gets Archived</h3>
                <ul>
                    <li>✅ <strong>Plugins</strong> - Files and optionally database tables/options</li>
                    <li>✅ <strong>Themes</strong> - Complete theme directories</li>
                    <li>✅ <strong>Posts & Pages</strong> - Content, meta, and taxonomies</li>
                    <li>✅ <strong>Media</strong> - Files and all generated sizes</li>
                    <li>✅ <strong>Comments</strong> - Comment data and meta</li>
                    <li>✅ <strong>Users</strong> - User data and meta (password excluded)</li>
                    <li>✅ <strong>Onyx Modules</strong> - Custom modules installed via Onyx Command</li>
                </ul>
            </div>
            
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var nonce = '<?php echo $nonce; ?>';
            
            // Restore item
            $('.pdm-btn-restore').on('click', function() {
                var btn = $(this);
                var id = btn.data('id');
                
                if (!confirm('Restore this item?')) return;
                
                btn.prop('disabled', true).text('Restoring...');
                
                $.post(ajaxurl, {
                    action: 'pdm_restore_item',
                    nonce: nonce,
                    archive_id: id
                }, function(response) {
                    if (response.success) {
                        btn.closest('tr').fadeOut(function() { $(this).remove(); });
                        alert('Item restored successfully!');
                    } else {
                        alert('Error: ' + response.data);
                        btn.prop('disabled', false).text('↩️ Restore');
                    }
                });
            });
            
            // Permanent delete
            $('.pdm-btn-delete[data-action="delete"]').on('click', function() {
                var btn = $(this);
                var id = btn.data('id');
                
                if (!confirm('PERMANENTLY delete this item? This cannot be undone!')) return;
                
                btn.prop('disabled', true).text('Deleting...');
                
                $.post(ajaxurl, {
                    action: 'pdm_permanent_delete',
                    nonce: nonce,
                    archive_id: id
                }, function(response) {
                    if (response.success) {
                        btn.closest('tr').fadeOut(function() { $(this).remove(); });
                    } else {
                        alert('Error: ' + response.data);
                        btn.prop('disabled', false).text('🗑️ Delete');
                    }
                });
            });
            
            // Empty archive
            $('#pdmEmptyArchive').on('click', function() {
                if (!confirm('PERMANENTLY delete ALL archived items? This cannot be undone!')) return;
                if (!confirm('Are you ABSOLUTELY sure? All backups will be lost!')) return;
                
                var btn = $(this);
                btn.prop('disabled', true).text('Emptying...');
                
                $.post(ajaxurl, {
                    action: 'pdm_empty_archive',
                    nonce: nonce
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                        btn.prop('disabled', false).text('🗑️ Empty Entire Archive');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render deletion results page
     */
    public function render_results_page() {
        $key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';
        $result = get_transient($key);
        
        if (!$result) {
            echo '<div class="wrap"><h1>Results Expired</h1><p>The deletion results have expired. <a href="' . admin_url('plugins.php') . '">Return to plugins</a></p></div>';
            return;
        }
        
        $nonce = wp_create_nonce('pdm_action');
        ?>
        <style>
            .pdm-results { max-width: 900px; }
            .pdm-card { background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
            .pdm-header { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; }
            .pdm-header-icon { font-size: 48px; }
            .pdm-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px; margin: 20px 0; }
            .pdm-stat { background: #f8f9fa; padding: 15px; border-radius: 6px; text-align: center; }
            .pdm-stat-value { font-size: 24px; font-weight: bold; }
            .pdm-stat-label { font-size: 12px; color: #666; margin-top: 5px; }
            .pdm-section { margin-top: 20px; }
            .pdm-section h3 { margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #eee; }
            .pdm-list { max-height: 200px; overflow-y: auto; background: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px; }
            .pdm-list-item { padding: 3px 0; border-bottom: 1px solid #eee; }
            .pdm-archive-notice { background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); border: 2px solid #22c55e; padding: 20px; border-radius: 8px; margin-top: 20px; }
            .pdm-archive-notice h3 { margin-top: 0; color: #166534; }
        </style>
        
        <div class="wrap pdm-results">
            <div class="pdm-card">
                <div class="pdm-header">
                    <span class="pdm-header-icon"><?php echo $result['delete_type'] === 'complete' ? '🗑️' : '💾'; ?></span>
                    <div>
                        <h1 style="margin:0;">Deletion Complete</h1>
                        <p style="margin:5px 0 0;color:#666;"><strong><?php echo esc_html($result['plugin_name']); ?></strong> v<?php echo esc_html($result['plugin_version']); ?></p>
                    </div>
                </div>
                
                <div class="pdm-stats">
                    <div class="pdm-stat">
                        <div class="pdm-stat-value"><?php echo number_format($result['file_count']); ?></div>
                        <div class="pdm-stat-label">Files Archived</div>
                    </div>
                    <div class="pdm-stat">
                        <div class="pdm-stat-value"><?php echo size_format($result['file_size']); ?></div>
                        <div class="pdm-stat-label">Size</div>
                    </div>
                    <?php if ($result['delete_type'] === 'complete'): ?>
                    <div class="pdm-stat">
                        <div class="pdm-stat-value"><?php echo count($result['tables_deleted']); ?></div>
                        <div class="pdm-stat-label">Tables Dropped</div>
                    </div>
                    <div class="pdm-stat">
                        <div class="pdm-stat-value"><?php echo count($result['options_deleted']); ?></div>
                        <div class="pdm-stat-label">Options Deleted</div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <p><strong>Deletion Type:</strong> <?php echo $result['delete_type'] === 'complete' ? 'Complete (files + data)' : 'Files Only (data preserved)'; ?></p>
            </div>
            
            <?php if (!empty($result['archived_files'])): ?>
            <div class="pdm-card pdm-section">
                <h3>📁 Archived Files (<?php echo $result['file_count']; ?>)</h3>
                <div class="pdm-list">
                    <?php foreach ($result['archived_files'] as $file): ?>
                        <div class="pdm-list-item"><?php echo esc_html($file); ?></div>
                    <?php endforeach; ?>
                    <?php if ($result['file_count'] > 50): ?>
                        <div class="pdm-list-item"><em>... and <?php echo $result['file_count'] - 50; ?> more files</em></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($result['delete_type'] === 'complete'): ?>
                <?php if (!empty($result['tables_deleted'])): ?>
                <div class="pdm-card pdm-section">
                    <h3>🗄️ Database Tables Dropped</h3>
                    <div class="pdm-list">
                        <?php foreach ($result['tables_deleted'] as $table): ?>
                            <div class="pdm-list-item"><?php echo esc_html($table); ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($result['options_deleted'])): ?>
                <div class="pdm-card pdm-section">
                    <h3>⚙️ Options Deleted</h3>
                    <div class="pdm-list">
                        <?php foreach (array_slice($result['options_deleted'], 0, 30) as $option): ?>
                            <div class="pdm-list-item"><?php echo esc_html($option); ?></div>
                        <?php endforeach; ?>
                        <?php if (count($result['options_deleted']) > 30): ?>
                            <div class="pdm-list-item"><em>... and <?php echo count($result['options_deleted']) - 30; ?> more</em></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <div class="pdm-archive-notice">
                <h3>📦 Archived for <?php echo $this->archive_days; ?> Days</h3>
                <p>This plugin has been moved to the <strong>Deletion Archive</strong>. You can restore it anytime before <strong><?php echo date('F j, Y', strtotime($result['expires_at'])); ?></strong>.</p>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=pdm-archive'); ?>" class="button button-primary">View Archive</a>
                    <a href="<?php echo admin_url('plugins.php'); ?>" class="button">Return to Plugins</a>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Log info
     */
    private function log_info($message) {
        if (class_exists('OC_Error_Logger')) {
            OC_Error_Logger::log('info', 'Deletion Manager', $message);
        }
    }
    
    /**
     * Get MU plugin content
     */
    private function get_mu_plugin_content() {
        $nonce = wp_create_nonce('pdm_action');
        
        return '<?php
/**
* Plugin Name: Onyx Deletion Manager
* Description: Enhanced deletion workflow with archive and restore capability
 * Version: 2.0.0
 * Author: Callum Creed
 */

if (!defined("ABSPATH")) exit;
if (!is_admin()) return;

global $wpdb;
$pdm_table = isset($wpdb) ? $wpdb->prefix . "mm_modules" : "";
$pdm_active = false;
if ($pdm_table) {
    $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $pdm_table));
    if ($table_exists === $pdm_table) {
        $status = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$pdm_table} WHERE module_id = %s LIMIT 1", "plugin-deletion-manager"));
        $pdm_active = ($status === "active");
    }
}
if (!$pdm_active) {
    return;
}

$pdm_retention = intval(get_option("pdm_retention_days", 30));
if ($pdm_retention <= 0) {
    $pdm_retention = 30;
}

add_action("admin_footer-plugins.php", "pdm_mu_render_modal");

function pdm_mu_render_modal() {
    $nonce = wp_create_nonce("pdm_action");
    $pdm_retention = intval(get_option("pdm_retention_days", 30));
    if ($pdm_retention <= 0) {
        $pdm_retention = 30;
    }
    ?>
    <style>
        .pdm-overlay{display:none;position:fixed;z-index:999999;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.8);backdrop-filter:blur(4px)}
        .pdm-modal{background:#fff;margin:5% auto;border-radius:12px;width:90%;max-width:700px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);overflow:hidden;animation:pdmSlide 0.3s ease}
        @keyframes pdmSlide{from{opacity:0;transform:translateY(-30px)}to{opacity:1;transform:translateY(0)}}
        .pdm-header{padding:25px 30px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff}
        .pdm-header h2{margin:0;font-size:22px;color:#fff}
        .pdm-header p{margin:8px 0 0;opacity:0.9}
        .pdm-body{padding:30px}
        .pdm-plugin-info{background:#f8f9fa;padding:15px;border-radius:8px;margin-bottom:20px}
        .pdm-plugin-name{font-size:18px;font-weight:600;margin:0 0 5px}
        .pdm-plugin-meta{color:#666;font-size:13px}
        .pdm-options{display:flex;gap:20px;margin-top:20px}
        .pdm-option{flex:1;padding:25px 20px;border:2px solid #e5e7eb;border-radius:12px;cursor:pointer;text-align:center;transition:all 0.25s}
        .pdm-option:hover{transform:translateY(-3px);box-shadow:0 10px 25px -5px rgba(0,0,0,0.1)}
        .pdm-option.keep:hover{border-color:#667eea}
        .pdm-option.delete:hover{border-color:#dc2626}
        .pdm-option-icon{font-size:48px;margin-bottom:15px;display:block}
        .pdm-option h3{margin:0 0 10px;font-size:18px}
        .pdm-option p{margin:0;font-size:13px;color:#666}
        .pdm-option ul{margin:15px 0 0;padding:15px 0 0;border-top:1px solid #e5e7eb;text-align:left;list-style:none}
        .pdm-option li{font-size:12px;color:#666;margin-bottom:6px;padding-left:18px;position:relative}
        .pdm-option li::before{content:"";position:absolute;left:0;top:6px;width:8px;height:8px;border-radius:50%}
        .pdm-option.keep li::before{background:#22c55e}
        .pdm-option.delete li::before{background:#dc2626}
        .pdm-footer{padding:20px 30px;background:#f9fafb;border-top:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center}
        .pdm-btn-cancel{padding:10px 24px;border:none;border-radius:6px;cursor:pointer;font-size:14px;background:#6b7280;color:#fff}
        .pdm-btn-cancel:hover{background:#4b5563}
        .pdm-loading{display:none;text-align:center;padding:40px}
        .pdm-loading.active{display:block}
        .pdm-spinner{width:40px;height:40px;border:4px solid #e5e7eb;border-top-color:#667eea;border-radius:50%;animation:pdmSpin 0.8s linear infinite;margin:0 auto 20px}
        @keyframes pdmSpin{to{transform:rotate(360deg)}}
        .pdm-active-warning{background:#fef3c7;border:1px solid #f59e0b;padding:12px;border-radius:6px;margin-bottom:15px;color:#92400e}
        .pdm-archive-info{background:#dcfce7;border:1px solid #22c55e;padding:12px;border-radius:6px;margin-top:15px;color:#166534;font-size:13px}
        @media(max-width:600px){.pdm-options{flex-direction:column}}
    </style>
    
    <div id="pdmOverlay" class="pdm-overlay">
        <div class="pdm-modal">
            <div class="pdm-header">
                <h2>⚠️ Delete Plugin</h2>
                <p>Choose how to handle plugin data</p>
            </div>
            <div class="pdm-body" id="pdmContent">
                <div class="pdm-plugin-info">
                    <p class="pdm-plugin-name" id="pdmPluginName">Loading...</p>
                    <p class="pdm-plugin-meta"><span id="pdmPluginVersion"></span> • <span id="pdmPluginSize"></span> • <span id="pdmPluginTables"></span></p>
                </div>
                
                <div id="pdmActiveWarning" class="pdm-active-warning" style="display:none">
                    ⚠️ This plugin is currently <strong>active</strong>. It will be deactivated before deletion.
                </div>
                
                <p><strong>How would you like to proceed?</strong></p>
                
                <div class="pdm-options">
                    <div class="pdm-option keep" id="pdmKeepFiles">
                        <span class="pdm-option-icon">💾</span>
                        <h3>Keep Data</h3>
                        <p>Remove files only. Database preserved.</p>
                        <ul>
                            <li>Plugin files archived</li>
                            <li>Database tables kept</li>
                            <li>Options preserved</li>
                            <li>Easy reinstall</li>
                        </ul>
                    </div>
                    <div class="pdm-option delete" id="pdmDeleteAll">
                        <span class="pdm-option-icon">🗑️</span>
                        <h3>Delete Everything</h3>
                        <p>Complete removal of all data.</p>
                        <ul>
                            <li>Files archived</li>
                            <li>Tables dropped</li>
                            <li>Options deleted</li>
                            <li>Clean uninstall</li>
                        </ul>
                    </div>
                </div>
                
                <div class="pdm-archive-info">
                    📦 <strong><?php echo intval($pdm_retention); ?>-Day Archive:</strong> Plugin will be archived and can be restored for <?php echo intval($pdm_retention); ?> days.
                </div>
            </div>
            <div class="pdm-loading" id="pdmLoading">
                <div class="pdm-spinner"></div>
                <h3 id="pdmLoadingTitle">Archiving & Deleting...</h3>
                <p>Creating backup and removing files...</p>
            </div>
            <div class="pdm-footer">
                <span style="font-size:12px;color:#9ca3af">Onyx Command Deletion Manager</span>
                <button type="button" class="pdm-btn-cancel" id="pdmCancel">Cancel</button>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        var overlay = $("#pdmOverlay");
        var currentPlugin = "";
        var nonce = "<?php echo esc_js($nonce); ?>";
        
        $(document).on("click", ".plugins .delete a, .plugins .row-actions .delete a, a[href*=\\"action=delete-selected\\"]", function(e) {
            var href = $(this).attr("href");
            var plugin = "";
            var $row = $(this).closest("tr");
            
            if ($row.length && $row.attr("data-plugin")) {
                plugin = $row.attr("data-plugin");
            } else if (href) {
                var match = href.match(/checked%5B0%5D=([^&]+)/);
                if (match) plugin = decodeURIComponent(match[1]);
            }
            
            if (!plugin) return true;
            
            e.preventDefault();
            e.stopPropagation();
            showModal(plugin);
            return false;
        });
        
        $(document).on("submit", "form", function(e) {
            var action = $(this).find("select[name=action], select[name=action2]").val();
            if (action === "delete-selected") {
                var checked = $(this).find("input[name=\\"checked[]\\"]:checked");
                if (checked.length === 1) {
                    e.preventDefault();
                    showModal(checked.val());
                    return false;
                }
            }
        });
        
        function showModal(plugin) {
            currentPlugin = plugin;
            $("#pdmContent").show();
            $("#pdmLoading").removeClass("active");
            $("#pdmActiveWarning").hide();
            overlay.fadeIn(200);
            
            $("#pdmPluginName").text("Loading...");
            $("#pdmPluginVersion, #pdmPluginSize, #pdmPluginTables").text("");
            
            $.post(ajaxurl, {action: "pdm_get_plugin_info", nonce: nonce, plugin: plugin}, function(r) {
                if (r.success) {
                    var d = r.data;
                    $("#pdmPluginName").text(d.name);
                    $("#pdmPluginVersion").text("v" + d.version);
                    $("#pdmPluginSize").text(d.size);
                    $("#pdmPluginTables").text(d.tables_count + " tables, " + d.options_count + " options");
                    if (d.is_active) $("#pdmActiveWarning").show();
                } else {
                    $("#pdmPluginName").text(plugin);
                }
            });
        }
        
        function doDelete(deleteData) {
            $("#pdmContent").hide();
            $("#pdmLoading").addClass("active");
            
            $.post(ajaxurl, {action: "pdm_delete_plugin", nonce: nonce, plugin: currentPlugin, delete_data: deleteData ? "true" : "false"}, function(r) {
                if (r.success) {
                    window.location.href = r.data.redirect;
                } else {
                    alert("Error: " + r.data);
                    overlay.fadeOut(200);
                }
            }).fail(function() {
                alert("Request failed");
                overlay.fadeOut(200);
            });
        }
        
        $("#pdmKeepFiles").on("click", function() { doDelete(false); });
        
        $("#pdmDeleteAll").on("click", function() {
            if (confirm("Delete ALL data including database tables and options?")) {
                doDelete(true);
            }
        });
        
        $("#pdmCancel").on("click", function() { overlay.fadeOut(200); });
        overlay.on("click", function(e) { if (e.target.id === "pdmOverlay") overlay.fadeOut(200); });
        $(document).on("keydown", function(e) { if (e.key === "Escape" && overlay.is(":visible")) overlay.fadeOut(200); });
    });
    </script>
    <?php
}
';
    }
}

// Initialize
Plugin_Deletion_Manager::get_instance();
