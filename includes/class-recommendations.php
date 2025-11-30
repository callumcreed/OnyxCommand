<?php
/**
 * Plugin recommendations and site signal detection.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OC_Recommendations {

    private static $instance = null;
    private $active_plugins = array();

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->active_plugins = $this->get_active_plugin_slugs();
    }

    private function get_active_plugin_slugs() {
        $active = (array) get_option('active_plugins', array());

        if (is_multisite()) {
            $network = (array) get_site_option('active_sitewide_plugins', array());
            $active = array_merge($active, array_keys($network));
        }

        return array_map(function($plugin_file) {
            $parts = explode('/', $plugin_file);
            return $parts[0];
        }, $active);
    }

    private function plugin_is_active($slug) {
        return in_array($slug, $this->active_plugins, true);
    }

    public function get_site_signals() {
        $post_counts = wp_count_posts();
        $page_counts = wp_count_posts('page');
        $media_counts = wp_count_attachments();
        $media_total  = 0;
        if (is_object($media_counts)) {
            foreach ((array) $media_counts as $count) {
                $media_total += (int) $count;
            }
        }

        $signals = array(
            'is_store'             => class_exists('WooCommerce') || post_type_exists('product'),
            'has_membership'       => class_exists('MemberPress') || class_exists('Paid_Memberships_Pro'),
            'has_learning'         => class_exists('LearnPress') || class_exists('Sensei_Main'),
            'is_multilingual'      => defined('POLYLANG_BASENAME') || class_exists('SitePress'),
            'large_media_library'  => $media_total > 400,
            'large_content_site'   => ($post_counts->publish + $page_counts->publish) > 80,
            'high_comment_volume'  => (int) get_comments(array('count' => true)) > 200,
            'needs_forms'          => !$this->any_plugin_active(array('wpforms-lite', 'gravityforms', 'ninja-forms', 'contact-form-7')),
            'needs_seo'            => !$this->any_plugin_active(array('wordpress-seo', 'seo-by-rank-math', 'all-in-one-seo-pack', 'squirrly-seo')),
            'needs_caching'        => !$this->any_plugin_active(array('wp-rocket', 'w3-total-cache', 'litespeed-cache', 'wp-super-cache', 'fvm')),
            'needs_security'       => !$this->any_plugin_active(array('wordfence', 'sucuri-scanner', 'ithemes-security-pro', 'security-malware-firewall')),
            'needs_backups'        => !$this->any_plugin_active(array('updraftplus', 'vaultpress', 'backupbuddy', 'jetpack-backup')),
        );

        return $signals;
    }

    private function any_plugin_active($slugs) {
        foreach ($slugs as $slug) {
            if ($this->plugin_is_active($slug)) {
                return true;
            }
        }
        return false;
    }

    private function build_install_url($slug) {
        return admin_url('plugin-install.php?tab=plugin-information&plugin=' . urlencode($slug) . '&TB_iframe=true&width=640&height=550');
    }

    private function add_recommendation(&$list, $category, $plugin, $condition = true) {
        if (!$condition || $this->plugin_is_active($plugin['slug'])) {
            return;
        }

        $plugin['category']   = $category;
        $plugin['installUrl'] = $this->build_install_url($plugin['slug']);
        $list[]               = $plugin;
    }

    public function get_recommendations() {
        $signals = $this->get_site_signals();
        $recommendations = array();

        // Performance / caching.
        $this->add_recommendation($recommendations, 'Performance', array(
            'slug'        => 'litespeed-cache',
            'name'        => 'LiteSpeed Cache',
            'summary'     => __('Server-level full-page caching, image optimization, and critical CSS generation.', 'onyx-command'),
            'why'         => __('No caching solution detected. Caching drastically improves load time and Core Web Vitals.', 'onyx-command')
        ), $signals['needs_caching']);

        $this->add_recommendation($recommendations, 'Performance', array(
            'slug'        => 'autoptimize',
            'name'        => 'Autoptimize',
            'summary'     => __('Aggregates, minifies, and optimizes CSS, JS, and Google Fonts.', 'onyx-command'),
            'why'         => __('Great companion to any cache plugin for script optimization.', 'onyx-command')
        ), $signals['needs_caching']);

        if ($signals['large_media_library']) {
            $this->add_recommendation($recommendations, 'Performance', array(
                'slug'    => 'smush',
                'name'    => 'Smush Image Compression & Optimization',
                'summary' => __('Bulk compresses media library images, adds lazy loading, and preserves originals.', 'onyx-command'),
                'why'     => __('Large media libraries benefit from automated image compression to keep pages lightweight.', 'onyx-command')
            ), true);
        }

        // SEO.
        $this->add_recommendation($recommendations, 'SEO', array(
            'slug'    => 'seo-by-rank-math',
            'name'    => 'Rank Math SEO',
            'summary' => __('Modern SEO suite with schema markup, content analysis, and Google Search Console insights.', 'onyx-command'),
            'why'     => __('No active SEO plugin detected. Rank Math adds structured data and on-page guidance.', 'onyx-command')
        ), $signals['needs_seo']);

        // Security.
        $this->add_recommendation($recommendations, 'Security', array(
            'slug'    => 'wordfence',
            'name'    => 'Wordfence Security',
            'summary' => __('Firewall, malware scanner, and login hardening in one package.', 'onyx-command'),
            'why'     => __('No security suite detected. Wordfence protects against brute force and exploits.', 'onyx-command')
        ), $signals['needs_security']);

        $this->add_recommendation($recommendations, 'Security', array(
            'slug'    => 'loginpress',
            'name'    => 'LoginPress',
            'summary' => __('Customize the WP login page with brand assets, reCAPTCHA, and brute-force protection.', 'onyx-command'),
            'why'     => __('Pairs nicely with the custom login slug feature for added brand polish.', 'onyx-command')
        ), true);

        // Backups.
        $this->add_recommendation($recommendations, 'Reliability', array(
            'slug'    => 'updraftplus',
            'name'    => 'UpdraftPlus WordPress Backup',
            'summary' => __('Automated backups to S3, Google Drive, Dropbox, and more.', 'onyx-command'),
            'why'     => __('No dedicated backup plugin detected. Scheduled backups are essential before heavy maintenance.', 'onyx-command')
        ), $signals['needs_backups']);

        // Commerce specific.
        if ($signals['is_store']) {
            $this->add_recommendation($recommendations, 'Commerce', array(
                'slug'    => 'woo-stripe-payment',
                'name'    => 'WooCommerce Stripe Gateway',
                'summary' => __('Accept Apple Pay, Google Pay, and credit cards with Stripe inside WooCommerce.', 'onyx-command'),
                'why'     => __('Recommended for stores to diversify payment options and reduce cart abandonment.', 'onyx-command')
            ), true);

            $this->add_recommendation($recommendations, 'Commerce', array(
                'slug'    => 'woo-refund-and-exchange-lite',
                'name'    => 'WooCommerce Refund And Exchange',
                'summary' => __('Self-service RMA dashboard and automated refund workflows for WooCommerce.', 'onyx-command'),
                'why'     => __('Helps support teams and increases buyer trust by simplifying returns.', 'onyx-command')
            ), true);
        }

        // Forms / lead gen.
        $this->add_recommendation($recommendations, 'Lead Generation', array(
            'slug'    => 'wpforms-lite',
            'name'    => 'WPForms Lite',
            'summary' => __('Drag-and-drop form builder with templates for contact, surveys, and payments.', 'onyx-command'),
            'why'     => __('No major form plugin detected. WPForms is lightweight and beginner friendly.', 'onyx-command')
        ), $signals['needs_forms']);

        // Multilingual / translation.
        $this->add_recommendation($recommendations, 'Localization', array(
            'slug'    => 'translatepress-multilingual',
            'name'    => 'TranslatePress',
            'summary' => __('Visual translation editor for pages, WooCommerce, and automatic translation.', 'onyx-command'),
            'why'     => __('Adds multilingual support without complex configuration.', 'onyx-command')
        ), !$signals['is_multilingual'] && $signals['large_content_site']);

        // Productivity / content.
        $this->add_recommendation($recommendations, 'Content', array(
            'slug'    => 'duplicate-post',
            'name'    => 'Yoast Duplicate Post',
            'summary' => __('Clone posts and pages with a single click while preserving SEO settings.', 'onyx-command'),
            'why'     => __('Pairs with the Onyx clone improvements to accelerate content workflows.', 'onyx-command')
        ), true);

        if ($signals['high_comment_volume']) {
            $this->add_recommendation($recommendations, 'Content', array(
                'slug'    => 'akismet',
                'name'    => 'Akismet Anti-Spam',
                'summary' => __('Stops comment and form spam using cloud-based heuristics.', 'onyx-command'),
                'why'     => __('High comment volume detected. Akismet keeps the moderation queue clean.', 'onyx-command')
            ), true);
        }

        return $this->group_by_category($recommendations);
    }

    private function group_by_category($recommendations) {
        $grouped = array();
        foreach ($recommendations as $plugin) {
            if (!isset($grouped[$plugin['category']])) {
                $grouped[$plugin['category']] = array();
            }
            $grouped[$plugin['category']][] = $plugin;
        }
        ksort($grouped);
        return $grouped;
    }
}

