<?php
if (!defined('ABSPATH')) {
    exit;
}

$recommendations = isset($recommendations) ? $recommendations : array();
$signals         = isset($signals) ? $signals : array();
$context         = isset($context) ? $context : array();
?>

<div class="wrap oc-recommended-wrap">
    <h1><?php esc_html_e('✨ Recommended Plugins', 'onyx-command'); ?></h1>
    <p class="description">
        <?php esc_html_e('Curated suggestions based on your current content footprint, active plugins, and detected gaps.', 'onyx-command'); ?>
    </p>

    <div class="oc-recommended-context notice notice-info" style="padding:15px; margin:20px 0;">
        <strong><?php esc_html_e('Site snapshot:', 'onyx-command'); ?></strong>
        <ul style="margin:8px 0 0 18px; list-style:disc;">
            <li><?php printf(esc_html__('%d published posts, %d published pages', 'onyx-command'), intval($context['posts']), intval($context['pages'])); ?></li>
            <li><?php printf(esc_html__('%d media attachments, %d total comments', 'onyx-command'), intval($context['media']), intval($context['comments'])); ?></li>
            <?php if (!empty($signals['is_store'])): ?>
                <li><?php esc_html_e('WooCommerce detected – storefront optimizations recommended.', 'onyx-command'); ?></li>
            <?php endif; ?>
            <?php if (!empty($signals['large_media_library'])): ?>
                <li><?php esc_html_e('Large media library detected – image compression recommended.', 'onyx-command'); ?></li>
            <?php endif; ?>
            <?php if (!empty($signals['needs_caching'])): ?>
                <li><?php esc_html_e('No caching layer detected – consider enabling full-page caching.', 'onyx-command'); ?></li>
            <?php endif; ?>
        </ul>
    </div>

    <?php if (empty($recommendations)) : ?>
        <div class="notice notice-success">
            <p><?php esc_html_e('Great news! We could not find any obvious gaps. Keep monitoring performance and security regularly.', 'onyx-command'); ?></p>
        </div>
    <?php else : ?>
        <?php foreach ($recommendations as $category => $plugins) : ?>
            <div class="oc-recommended-category" style="margin-bottom:30px;">
                <h2>
                    <?php echo esc_html($category); ?>
                    <span class="oc-pill" style="display:inline-block;margin-left:10px;padding:2px 10px;border-radius:999px;background:#f0f0f1;font-size:12px;">
                        <?php printf(esc_html__('%d suggestions', 'onyx-command'), count($plugins)); ?>
                    </span>
                </h2>
                <div class="oc-recommended-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;">
                    <?php foreach ($plugins as $plugin) : ?>
                        <div class="oc-recommended-card" style="border:1px solid #dcdcdc;border-radius:8px;padding:16px;background:#fff;display:flex;flex-direction:column;">
                            <div>
                                <h3 style="margin-top:0;"><?php echo esc_html($plugin['name']); ?></h3>
                                <p style="margin:8px 0;color:#444;"><?php echo esc_html($plugin['summary']); ?></p>
                                <?php if (!empty($plugin['why'])) : ?>
                                    <p style="font-size:13px;color:#666;"><strong><?php esc_html_e('Why we like it:', 'onyx-command'); ?></strong> <?php echo esc_html($plugin['why']); ?></p>
                                <?php endif; ?>
                            </div>
                            <div style="margin-top:auto;">
                                <a class="button button-primary thickbox" href="<?php echo esc_url($plugin['installUrl']); ?>">
                                    <?php esc_html_e('Preview & Install', 'onyx-command'); ?>
                                </a>
                                <a class="button button-secondary" href="<?php echo esc_url('https://wordpress.org/plugins/' . $plugin['slug'] . '/'); ?>" target="_blank" rel="noopener noreferrer" style="margin-left:8px;">
                                    <?php esc_html_e('View on WordPress.org', 'onyx-command'); ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

