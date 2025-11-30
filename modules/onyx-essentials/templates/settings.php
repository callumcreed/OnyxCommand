<?php
/**
 * Onyx Essentials Settings Template
 * Place in: modules/onyx-essentials/templates/settings.php
 */

if (!defined('ABSPATH')) exit;

$announcement_bars = isset($announcement_bars) ? $announcement_bars : array();
?>

<div class="wrap oc-essentials-wrap">
    <h1>⚡ Onyx Essentials</h1>
    <p class="description">Comprehensive security, optimization, and maintenance tools for your WordPress site.</p>
    <div class="oc-essentials-toolbar">
        <button type="button" class="button button-secondary" data-oc-lightbox="#ocEssentialsLightbox" data-oc-title="<?php esc_attr_e('Onyx Essentials Quickstart', 'oc-essentials'); ?>">
            📘 <?php esc_html_e('Quickstart Guide', 'oc-essentials'); ?>
        </button>
        <a class="button button-link" href="https://onyxcommand.com/docs" target="_blank" rel="noopener">
            <?php esc_html_e('Open Documentation ↗', 'oc-essentials'); ?>
        </a>
    </div>
    
    <form method="post" action="">
        <?php wp_nonce_field('oc_essentials_settings'); ?>
        
        <div class="oc-essentials-grid">
            
            <!-- Security Features -->
            <div class="oc-essentials-section">
                <h2>🔒 Security Features</h2>
                
                <!-- Feature 1: SSL Status -->
                <div class="oc-feature-card">
                    <div class="oc-feature-header">
                        <label>
                            <input type="checkbox" name="ssl_check" value="1" <?php checked(!empty($options['ssl_check'])); ?>>
                            <strong>SSL Certificate Monitor</strong>
                        </label>
                    </div>
                    <div class="oc-feature-body">
                        <p>Monitor SSL certificate status and expiration</p>
                        <?php if (!empty($options['ssl_check'])): ?>
                            <div class="oc-ssl-status">
                                <?php if ($ssl_info['status'] === 'Active'): ?>
                                    <span class="oc-status-badge oc-status-success">✓ Active</span>
                                    <p><strong>Expires:</strong> <?php echo esc_html($ssl_info['expiry_date']); ?></p>
                                    <p><strong>Days Remaining:</strong> <?php echo esc_html($ssl_info['days_remaining']); ?> days</p>
                                    <?php if ($ssl_info['days_remaining'] < 30): ?>
                                        <p class="oc-warning">⚠️ Your SSL certificate will expire soon!</p>
                                    <?php endif; ?>
                                <?php elseif ($ssl_info['status'] === 'Expired'): ?>
                                    <span class="oc-status-badge oc-status-error">✗ Expired</span>
                                    <p><strong>Expired on:</strong> <?php echo esc_html($ssl_info['expiry_date']); ?></p>
                                    <div class="oc-ssl-help">
                                        <p><strong>Get a new SSL certificate from:</strong></p>
                                        <ul>
                                            <li><a href="https://letsencrypt.org/" target="_blank">Let's Encrypt</a> - Free SSL certificates</li>
                                            <li><a href="https://www.cloudflare.com/ssl/" target="_blank">Cloudflare</a> - Free SSL with CDN</li>
                                        </ul>
                                        <p><strong>Manual Installation Instructions:</strong></p>
                                        <ol>
                                            <li>Generate or obtain SSL certificate and private key files</li>
                                            <li>Access your hosting control panel (cPanel, Plesk, etc.)</li>
                                            <li>Navigate to SSL/TLS section</li>
                                            <li>Upload certificate (.crt) and private key (.key) files</li>
                                            <li>Save and apply changes</li>
                                        </ol>
                                    </div>
                                <?php else: ?>
                                    <span class="oc-status-badge oc-status-warning">! Not Active</span>
                                    <p>SSL is not currently active on this site</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Feature 3: Disable File Editing -->
                <div class="oc-feature-card">
                    <div class="oc-feature-header">
                        <label>
                            <input type="checkbox" class="oc-confirm-toggle" data-confirm="disable-file-editing" name="disable_file_editing" value="1" <?php checked(!empty($options['disable_file_editing'])); ?>>
                            <strong>Disable File Editing</strong>
                        </label>
                    </div>
                    <div class="oc-feature-body">
                        <p>Disable plugin file editing for non-administrators</p>
                        <label class="oc-inline-checkbox">
                            <input type="checkbox" class="oc-confirm-toggle" data-confirm="disable-theme-editing" name="disable_theme_file_editing" value="1" <?php checked(!empty($options['disable_theme_file_editing'])); ?>>
                            <span><?php esc_html_e('Also disable theme file editing (ignored for Administrators/Super Administrators)', 'oc-essentials'); ?></span>
                        </label>
                    </div>
                </div>
                
                <!-- Feature 4: Disable XML-RPC -->
                <div class="oc-feature-card">
                    <div class="oc-feature-header">
                        <label>
                            <input type="checkbox" name="disable_xmlrpc" value="1" <?php checked(!empty($options['disable_xmlrpc'])); ?>>
                            <strong>Disable XML-RPC</strong>
                        </label>
                    </div>
                    <div class="oc-feature-body">
                        <p>Disable XML-RPC to prevent brute force attacks</p>
                    </div>
                </div>
                
                <!-- Feature 5: Disable wp-config Editing -->
                <div class="oc-feature-card">
                    <div class="oc-feature-header">
                        <label>
                            <input type="checkbox" name="disable_wpconfig_edit" value="1" <?php checked(!empty($options['disable_wpconfig_edit'])); ?>>
                            <strong>Protect wp-config.php</strong>
                        </label>
                    </div>
                    <div class="oc-feature-body">
                        <p>Prevent wp-config.php editing except by administrators</p>
                    </div>
                </div>
                
                <!-- Feature 6: Hide WordPress Version -->
                <div class="oc-feature-card">
                    <div class="oc-feature-header">
                        <label>
                            <input type="checkbox" name="hide_wp_version" value="1" <?php checked(!empty($options['hide_wp_version'])); ?>>
                            <strong>Hide WordPress Version</strong>
                        </label>
                    </div>
                    <div class="oc-feature-body">
                        <p>Remove WordPress version from public HTML source</p>
                    </div>
                </div>

                <div class="oc-feature-card">
                    <div class="oc-feature-header">
                        <label>
                            <input type="checkbox" name="under_construction" value="1" <?php checked(!empty($options['under_construction'])); ?>>
                            <strong><?php esc_html_e('Under Construction Mode', 'oc-essentials'); ?></strong>
                        </label>
                    </div>
                    <div class="oc-feature-body">
                        <p><?php esc_html_e('Show a site-wide under construction notice to visitors who are not administrators.', 'oc-essentials'); ?></p>
                    </div>
                </div>

                <div class="oc-feature-card">
                    <div class="oc-feature-header">
                        <label>
                            <input type="checkbox" name="hotlink_protection" value="1" <?php checked(!empty($options['hotlink_protection'])); ?>>
                            <strong><?php esc_html_e('Enforce Hotlink Protection', 'oc-essentials'); ?></strong>
                        </label>
                    </div>
                    <div class="oc-feature-body">
                        <p><?php esc_html_e('Block other domains from embedding your images, documents, or videos.', 'oc-essentials'); ?></p>
                    </div>
                </div>

                <div class="oc-feature-card">
                    <div class="oc-feature-header">
                        <label>
                            <input type="checkbox" name="disable_right_click" value="1" <?php checked(!empty($options['disable_right_click'])); ?>>
                            <strong><?php esc_html_e('Disable Right Click', 'oc-essentials'); ?></strong>
                        </label>
                    </div>
                    <div class="oc-feature-body">
                        <p><?php esc_html_e('Prevent visitors from opening the context menu. Administrators are exempt.', 'oc-essentials'); ?></p>
                    </div>
                </div>
                
                <!-- Feature 12: Strong Passwords -->
                <div class="oc-feature-card">
                    <div class="oc-feature-header">
                        <label>
                            <input type="checkbox" name="enforce_strong_passwords" value="1" <?php checked(!empty($options['enforce_strong_passwords'])); ?>>
                            <strong>Enforce Strong Passwords</strong>
                        </label>
                    </div>
                    <div class="oc-feature-body">
                        <p>Require passwords with 8+ characters, uppercase, lowercase, number, and special character</p>
                    </div>
                </div>
                
                <!-- Feature 13: Limit Login Attempts -->
                <div class="oc-feature-card">
                    <div class="oc-feature-header">
                        <label>
                            <input type="checkbox" name="limit_login_attempts" value="1" <?php checked(!empty($options['limit_login_attempts'])); ?>>
                            <strong>Limit Login Attempts</strong>
                        </label>
                    </div>
                    <div class="oc-feature-body">
                        <p>Lock accounts after failed login attempts</p>
                        <label>
                            Maximum attempts:
                            <select name="login_attempt_limit">
                                <option value="3" <?php selected(!empty($options['login_attempt_limit']) ? $options['login_attempt_limit'] : 5, 3); ?>>3</option>
                                <option value="5" <?php selected(!empty($options['login_attempt_limit']) ? $options['login_attempt_limit'] : 5, 5); ?>>5</option>
                                <option value="10" <?php selected(!empty($options['login_attempt_limit']) ? $options['login_attempt_limit'] : 5, 10); ?>>10</option>
                            </select>
                        </label>
                    </div>
                </div>
                
                <!-- Feature 14: Block Invalid Usernames -->
                <div class="oc-feature-card">
                    <div class="oc-feature-header">
                        <label>
                            <input type="checkbox" name="block_invalid_usernames" value="1" <?php checked(!empty($options['block_invalid_usernames'])); ?>>
                            <strong>Block Invalid Username Attempts</strong>
                        </label>
                    </div>
                    <div class="oc-feature-body">
                        <p>Automatically block IPs attempting to login with non-existent usernames</p>
                    </div>
                </div>

                <div class="oc-feature-card">
                    <h3>🔐 Custom Login & Admin URLs</h3>
                    <p>Replace the default <code>wp-login.php</code> and <code>wp-admin</code> entry points with branded slugs.</p>
                    <label>
                        <?php esc_html_e('Login slug', 'oc-essentials'); ?>
                        <input type="text" name="custom_login_slug" value="<?php echo esc_attr(isset($options['custom_login_slug']) ? $options['custom_login_slug'] : ''); ?>" placeholder="<?php esc_attr_e('secure-login', 'oc-essentials'); ?>">
                    </label>
                    <p class="description"><?php esc_html_e('Visitors will log in at https://yoursite.com/slug/. Only lowercase letters, numbers, and dashes are allowed.', 'oc-essentials'); ?></p>
                    <label style="margin-top:10px; display:block;">
                        <?php esc_html_e('Admin dashboard alias', 'oc-essentials'); ?>
                        <input type="text" name="custom_admin_slug" value="<?php echo esc_attr(isset($options['custom_admin_slug']) ? $options['custom_admin_slug'] : ''); ?>" placeholder="<?php esc_attr_e('control-center', 'oc-essentials'); ?>">
                    </label>
                    <p class="description"><?php esc_html_e('Create a friendlier URL that forwards directly to wp-admin for logged-in users.', 'oc-essentials'); ?></p>
                </div>

                <div class="oc-feature-card">
                    <h3>🎨 Login Branding</h3>
                    <p><?php esc_html_e('Automatically apply your site logo and colors to the WordPress login screen.', 'oc-essentials'); ?></p>
                    <div class="oc-login-colors">
                        <label>
                            <?php esc_html_e('Background color', 'oc-essentials'); ?>
                            <input type="color" name="login_background_color" value="<?php echo esc_attr(isset($options['login_background_color']) ? $options['login_background_color'] : '#0f172a'); ?>">
                        </label>
                        <label>
                            <?php esc_html_e('Form color', 'oc-essentials'); ?>
                            <input type="color" name="login_form_color" value="<?php echo esc_attr(isset($options['login_form_color']) ? $options['login_form_color'] : '#ffffff'); ?>">
                        </label>
                        <label>
                            <?php esc_html_e('Button color', 'oc-essentials'); ?>
                            <input type="color" name="login_button_color" value="<?php echo esc_attr(isset($options['login_button_color']) ? $options['login_button_color'] : '#2563eb'); ?>">
                        </label>
                        <label>
                            <?php esc_html_e('Button text', 'oc-essentials'); ?>
                            <input type="color" name="login_button_text_color" value="<?php echo esc_attr(isset($options['login_button_text_color']) ? $options['login_button_text_color'] : '#ffffff'); ?>">
                        </label>
                    </div>
                    <label style="display:block; margin-top:10px;">
                        <?php esc_html_e('Additional CSS (optional)', 'oc-essentials'); ?>
                        <textarea name="login_custom_css" rows="4" class="widefat" placeholder=".login form { border: 2px solid #000; }"><?php echo esc_textarea(isset($options['login_custom_css']) ? $options['login_custom_css'] : ''); ?></textarea>
                    </label>
                </div>
            </div>
            
            <!-- User Management -->
            <div class="oc-essentials-section">
                <h2>👥 User Management</h2>
                
                <!-- Feature 7: Keep Admin Logged In -->
                <div class="oc-feature-card">
                    <div class="oc-feature-header">
                        <label>
                            <input type="checkbox" name="keep_admin_logged_in" value="1" <?php checked(!empty($options['keep_admin_logged_in'])); ?>>
                            <strong>Keep Administrators Logged In</strong>
                        </label>
                    </div>
                    <div class="oc-feature-body">
                        <p>Extend admin session to 10 years (until manual logout)</p>
                    </div>
                </div>
                
                <!-- Feature 8 & 11: Change Admin Username -->
                <div class="oc-feature-card">
                    <div class="oc-feature-header">
                        <label>
                            <input type="checkbox" class="oc-confirm-toggle" data-confirm="change-admin" name="change_admin_username" value="1" <?php checked(!empty($options['change_admin_username'])); ?>>
                            <strong>Change Default Admin Username</strong>
                        </label>
                    </div>
                    <div class="oc-feature-body">
                        <p>Change the default 'admin' username for security</p>
                        <label>
                            New username:
                            <input type="text" name="new_admin_username" value="<?php echo esc_attr(!empty($options['new_admin_username']) ? $options['new_admin_username'] : ''); ?>" placeholder="Enter new admin username">
                        </label>
                    </div>
                </div>
                
                <!-- Locked Users Display -->
                <?php if (!empty($locked_users)): ?>
                <div class="oc-feature-card oc-locked-section">
                    <h3>🔒 Locked User Accounts</h3>
                    <table class="oc-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Email</th>
                                <th>IP Address</th>
                                <th>Country</th>
                                <th>Attempts</th>
                                <th>Locked At</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($locked_users as $user_id => $data): ?>
                            <tr>
                                <td><?php echo esc_html($data['username']); ?></td>
                                <td><?php echo esc_html($data['email']); ?></td>
                                <td><?php echo esc_html($data['ip']); ?></td>
                                <td><?php echo esc_html($data['country']); ?></td>
                                <td><?php echo esc_html($data['attempts']); ?></td>
                                <td><?php echo esc_html($data['locked_at']); ?></td>
                                <td>
                                    <button type="button" class="button oc-unlock-user" data-user-id="<?php echo esc_attr($user_id); ?>">Unlock</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                
                <!-- Locked IPs Display -->
                <?php if (!empty($locked_ips)): ?>
                <div class="oc-feature-card oc-locked-section">
                    <h3>🚫 Locked IP Addresses</h3>
                    <table class="oc-table">
                        <thead>
                            <tr>
                                <th>IP Address</th>
                                <th>Attempted Username</th>
                                <th>Country</th>
                                <th>Locked At</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($locked_ips as $ip => $data): ?>
                            <tr>
                                <td><?php echo esc_html($ip); ?></td>
                                <td><?php echo esc_html($data['username']); ?></td>
                                <td><?php echo esc_html($data['country']); ?></td>
                                <td><?php echo esc_html($data['locked_at']); ?></td>
                                <td>
                                    <button type="button" class="button oc-unlock-ip" data-ip="<?php echo esc_attr($ip); ?>">Unlock</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <div class="oc-feature-card">
                    <h3><?php esc_html_e('Blocked Accounts Dashboard', 'oc-essentials'); ?></h3>
                    <p><?php esc_html_e('Review locked accounts, see captured login details, permanently block IPs, and manually unlock users.', 'oc-essentials'); ?></p>
                    <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=oc-blocked-accounts')); ?>">
                        <?php esc_html_e('Open Blocked Accounts', 'oc-essentials'); ?>
                    </a>
                </div>
            </div>
            
            <!-- Content Management -->
            <div class="oc-essentials-section">
                <h2>📝 Content Management</h2>
                
                <!-- Feature 9: Disable Comments -->
                <div class="oc-feature-card">
                    <div class="oc-feature-header">
                        <label>
                            <input type="checkbox" name="disable_comments" value="1" <?php checked(!empty($options['disable_comments'])); ?>>
                            <strong>Disable Comments Sitewide</strong>
                        </label>
                    </div>
                    <div class="oc-feature-body">
                        <p>Disable comments globally with per-post override option</p>
                    </div>
                </div>
                
                <div class="oc-feature-card">
                    <h3>🧰 Builder Controls</h3>
                    <p><?php esc_html_e('Enable or disable specific editors and choose the default editing experience for authors.', 'oc-essentials'); ?></p>
                    <label class="oc-inline-checkbox">
                        <input type="checkbox" name="disable_gutenberg" value="1" <?php checked(!empty($options['disable_gutenberg'])); ?>>
                        <span><?php esc_html_e('Disable Gutenberg (Block Editor)', 'oc-essentials'); ?></span>
                    </label>
                    <label class="oc-inline-checkbox">
                        <input type="checkbox" name="disable_classic_editor" value="1" <?php checked(!empty($options['disable_classic_editor'])); ?>>
                        <span><?php esc_html_e('Disable Classic Editor UI', 'oc-essentials'); ?></span>
                    </label>
                    <label style="display:block;margin-top:12px;">
                        <?php esc_html_e('Default Editor', 'oc-essentials'); ?>
                        <select name="builder_default">
                            <option value="block" <?php selected(isset($options['builder_default']) ? $options['builder_default'] : 'block', 'block'); ?>>
                                <?php esc_html_e('Block Editor (Gutenberg)', 'oc-essentials'); ?>
                            </option>
                            <option value="classic" <?php selected(isset($options['builder_default']) ? $options['builder_default'] : 'block', 'classic'); ?>>
                                <?php esc_html_e('Classic Editor', 'oc-essentials'); ?>
                            </option>
                        </select>
                    </label>
                    <p class="description"><?php esc_html_e('If both editors are enabled, this sets the default view when opening the editor.', 'oc-essentials'); ?></p>
                </div>

                <div class="oc-feature-card">
                    <div class="oc-feature-header">
                        <label>
                            <input type="checkbox" name="featured_image_column" value="1" <?php checked(!empty($options['featured_image_column'])); ?>>
                            <strong><?php esc_html_e('Featured Image Column (Posts & Pages)', 'oc-essentials'); ?></strong>
                        </label>
                    </div>
                    <div class="oc-feature-body">
                        <p><?php esc_html_e('Adds a thumbnail column to the Posts and Pages list screens for quick visual identification. Thumbnails are displayed at a glance without opening each item.', 'oc-essentials'); ?></p>
                    </div>
                </div>

                <div class="oc-feature-card">
                    <h3>🔗 Share Drafts Securely</h3>
                    <label class="oc-inline-checkbox">
                        <input type="checkbox" name="draft_preview_share" value="1" <?php checked(!empty($options['draft_preview_share'])); ?>>
                        <span><?php esc_html_e('Allow non-logged-in visitors to view draft previews (requires preview nonce).', 'oc-essentials'); ?></span>
                    </label>
                    <p class="description">
                        <?php esc_html_e('When enabled, copy the default WordPress preview link (Preview → Copy Link) and send it to a stakeholder. Anyone with the full URL, including the preview nonce, can view the draft without logging in.', 'oc-essentials'); ?>
                    </p>
                    <p class="description">
                        <?php esc_html_e('Disable this option to require authentication for all draft previews.', 'oc-essentials'); ?>
                    </p>
                </div>
                
                <!-- Feature 21: Scan Orphaned Content -->
                <div class="oc-feature-card">
                    <h3>🔍 Scan for Orphaned Content</h3>
                    <p>Find posts and pages with no incoming links</p>
                    <button type="button" class="button button-secondary oc-scan-orphans">Scan Now</button>
                    <div id="oc-orphan-results" class="oc-results"></div>
                </div>
                
                <!-- Feature 22: Scan Missing Meta -->
                <div class="oc-feature-card">
                    <h3>🏷️ Scan for Missing Meta Data</h3>
                    <p>Find posts missing SEO meta information</p>
                    <button type="button" class="button button-secondary oc-scan-meta">Scan Now</button>
                    <div id="oc-meta-results" class="oc-results"></div>
                </div>
                
                <!-- Feature 23: Scan Missing Alt Text -->
                <div class="oc-feature-card">
                    <h3>🖼️ Scan for Missing Alt Text</h3>
                    <p>Find images without alt text</p>
                    <button type="button" class="button button-secondary oc-scan-alt">Scan Now</button>
                    <div id="oc-alt-results" class="oc-results"></div>
                </div>

                <!-- Broken URL Scanner -->
                <div class="oc-feature-card">
                    <h3>🔗 Scan for Broken URLs</h3>
                    <p>Find broken links, images, and media files across your entire site</p>
                    <button type="button" class="button button-secondary oc-scan-broken-urls">Scan Entire Site</button>
                    <div id="oc-broken-url-results" class="oc-results"></div>
                </div>

                <div class="oc-feature-card">
                    <h3>📬 Submit Non-Indexed URLs</h3>
                    <p><?php esc_html_e('Ping Google to crawl up to 20 recently published posts or pages that have not been submitted before.', 'oc-essentials'); ?></p>
                    <button type="button" class="button button-secondary oc-submit-google"><?php esc_html_e('Submit to Google', 'oc-essentials'); ?></button>
                    <div id="oc-google-status" class="oc-status-message"></div>
                </div>
            </div>
            
            <!-- Media Management -->
            <div class="oc-essentials-section">
                <h2>🖼️ Media Management</h2>
                
                <!-- Feature 18: Regenerate Thumbnails -->
                <div class="oc-feature-card">
                    <h3>🔄 Regenerate Thumbnails</h3>
                    <p><?php esc_html_e('Regenerate thumbnails or clean out orphaned files without leaving the dashboard.', 'oc-essentials'); ?></p>
                    <div class="oc-regenerate-controls">
                        <label>
                            <input type="checkbox" class="oc-regenerate-option" name="regen_remove_orphans" value="1">
                            <?php esc_html_e('Delete orphaned thumbnails before regenerating', 'oc-essentials'); ?>
                        </label>
                        <label>
                            <?php esc_html_e('Regenerate only these sizes', 'oc-essentials'); ?><br>
                            <select name="regen_sizes[]" class="oc-regenerate-sizes" multiple size="5">
                                <option value="thumbnail"><?php esc_html_e('Thumbnail', 'oc-essentials'); ?></option>
                                <option value="medium"><?php esc_html_e('Medium', 'oc-essentials'); ?></option>
                                <option value="medium_large"><?php esc_html_e('Medium Large', 'oc-essentials'); ?></option>
                                <option value="large"><?php esc_html_e('Large', 'oc-essentials'); ?></option>
                                <option value="custom"><?php esc_html_e('All custom sizes', 'oc-essentials'); ?></option>
                            </select>
                        </label>
                    </div>
                    <button type="button" class="button button-secondary oc-regenerate-thumbs" data-action="regenerate"><?php esc_html_e('Regenerate Selected Thumbnails', 'oc-essentials'); ?></button>
                    <button type="button" class="button button-secondary oc-regenerate-thumbs" data-action="cleanup"><?php esc_html_e('Clean Up Orphaned Thumbnails', 'oc-essentials'); ?></button>
                    <button type="button" class="button button-secondary oc-delete-all-thumbnails"><?php esc_html_e('Delete All Generated Thumbnails', 'oc-essentials'); ?></button>
                    <p class="description"><?php esc_html_e('Removes every generated thumbnail while keeping original images intact. Use this before regenerating to ensure clean media outputs.', 'oc-essentials'); ?></p>
                    <div class="oc-progress" data-progress="thumbs">
                        <div class="oc-progress-bar"></div>
                        <span class="oc-progress-label"></span>
                    </div>
                    <div class="oc-status-message" data-status="thumbs"></div>
                </div>
                
                <!-- Feature 19: Delete Unattached Media -->
                <div class="oc-feature-card">
                    <h3>🗑️ Delete Unattached Media</h3>
                    <p><?php esc_html_e('Remove unused files or strip leftover thumbnails while keeping originals intact.', 'oc-essentials'); ?></p>
                    <label>
                        <input type="checkbox" class="oc-delete-option" name="delete_keep_original" value="1">
                        <?php esc_html_e('Delete derivative thumbnails but keep the original image', 'oc-essentials'); ?>
                    </label>
                    <button type="button" class="button button-secondary oc-delete-unattached" data-confirm="delete-unattached"><?php esc_html_e('Delete Unattached Files', 'oc-essentials'); ?></button>
                    <div class="oc-progress" data-progress="unattached">
                        <div class="oc-progress-bar"></div>
                        <span class="oc-progress-label"></span>
                    </div>
                    <div class="oc-status-message" data-status="unattached"></div>
                </div>
            </div>

            <!-- Announcement & Experience -->
            <div class="oc-essentials-section">
                <h2>📣 Announcement & Experience</h2>

                <div class="oc-feature-card">
                    <h3><?php esc_html_e('Announcement Bars', 'oc-essentials'); ?></h3>
                    <p><?php esc_html_e('Create multiple announcement bars, schedule them, and control their visibility per device.', 'oc-essentials'); ?></p>

                    <style>
                        .oc-announcement-item {
                            border: 1px solid #dcd7ca;
                            border-radius: 8px;
                            padding: 15px;
                            margin-bottom: 15px;
                            background: #fff;
                        }
                        .oc-announcement-item-header {
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                            margin-bottom: 12px;
                        }
                        .oc-announcement-item-actions {
                            display: flex;
                            gap: 12px;
                            align-items: center;
                        }
                    </style>

                    <div id="ocAnnouncementList" data-next-index="<?php echo esc_attr(count($announcement_bars)); ?>">
                        <?php foreach ($announcement_bars as $index => $bar):
                            $start_value = !empty($bar['start_at']) ? esc_attr(get_date_from_gmt($bar['start_at'], 'Y-m-d\TH:i')) : '';
                            $disable_value = !empty($bar['disable_on']) ? esc_attr(get_date_from_gmt($bar['disable_on'], 'Y-m-d\TH:i')) : '';
                        ?>
                        <div class="oc-announcement-item" data-index="<?php echo esc_attr($index); ?>">
                            <div class="oc-announcement-item-header">
                                <strong><?php printf(esc_html__('Announcement #%d', 'oc-essentials'), $index + 1); ?></strong>
                                <div class="oc-announcement-item-actions">
                                    <label class="oc-inline-checkbox" style="margin:0;">
                                        <input type="checkbox" name="announcement_bars[<?php echo esc_attr($index); ?>][enabled]" value="1" <?php checked(!empty($bar['enabled'])); ?>>
                                        <span><?php esc_html_e('Enabled', 'oc-essentials'); ?></span>
                                    </label>
                                    <button type="button" class="button-link-delete oc-announcement-remove"><?php esc_html_e('Remove', 'oc-essentials'); ?></button>
                                </div>
                            </div>

                            <label>
                                <?php esc_html_e('Message', 'oc-essentials'); ?>
                                <textarea name="announcement_bars[<?php echo esc_attr($index); ?>][text]" rows="3"><?php echo esc_textarea($bar['text']); ?></textarea>
                            </label>

                            <div class="oc-grid-two">
                                <label>
                                    <?php esc_html_e('Background Color', 'oc-essentials'); ?>
                                    <input type="color" name="announcement_bars[<?php echo esc_attr($index); ?>][bg]" value="<?php echo esc_attr($bar['bg']); ?>">
                                </label>
                                <label>
                                    <?php esc_html_e('Text Color', 'oc-essentials'); ?>
                                    <input type="color" name="announcement_bars[<?php echo esc_attr($index); ?>][color]" value="<?php echo esc_attr($bar['color']); ?>">
                                </label>
                            </div>

                            <div class="oc-grid-two">
                                <label>
                                    <?php esc_html_e('Font Family', 'oc-essentials'); ?>
                                    <input type="text" name="announcement_bars[<?php echo esc_attr($index); ?>][font_family]" value="<?php echo esc_attr($bar['font_family']); ?>">
                                </label>
                                <label>
                                    <?php esc_html_e('Font Size (px)', 'oc-essentials'); ?>
                                    <input type="number" min="10" max="72" name="announcement_bars[<?php echo esc_attr($index); ?>][font_size]" value="<?php echo esc_attr($bar['font_size']); ?>">
                                </label>
                            </div>

                            <label class="oc-inline-checkbox">
                                <input type="checkbox" name="announcement_bars[<?php echo esc_attr($index); ?>][marquee]" value="1" <?php checked(!empty($bar['marquee'])); ?>>
                                <span><?php esc_html_e('Enable scrolling marquee animation', 'oc-essentials'); ?></span>
                            </label>

                            <div class="oc-grid-two">
                                <label>
                                    <?php esc_html_e('Start Display', 'oc-essentials'); ?>
                                    <input type="datetime-local" name="announcement_bars[<?php echo esc_attr($index); ?>][start_at]" value="<?php echo $start_value; ?>">
                                </label>
                                <label>
                                    <?php esc_html_e('Disable On', 'oc-essentials'); ?>
                                    <input type="datetime-local" name="announcement_bars[<?php echo esc_attr($index); ?>][disable_on]" value="<?php echo $disable_value; ?>">
                                </label>
                            </div>

                            <div class="oc-grid-two">
                                <label>
                                    <?php esc_html_e('Button Text', 'oc-essentials'); ?>
                                    <input type="text" name="announcement_bars[<?php echo esc_attr($index); ?>][button_text]" value="<?php echo esc_attr($bar['button_text']); ?>">
                                </label>
                                <label>
                                    <?php esc_html_e('Button URL', 'oc-essentials'); ?>
                                    <input type="url" name="announcement_bars[<?php echo esc_attr($index); ?>][button_url]" value="<?php echo esc_attr($bar['button_url']); ?>">
                                </label>
                            </div>

                            <div class="oc-grid-two">
                                <label>
                                    <?php esc_html_e('Button Background', 'oc-essentials'); ?>
                                    <input type="color" name="announcement_bars[<?php echo esc_attr($index); ?>][button_bg]" value="<?php echo esc_attr($bar['button_bg']); ?>">
                                </label>
                                <label>
                                    <?php esc_html_e('Button Text Color', 'oc-essentials'); ?>
                                    <input type="color" name="announcement_bars[<?php echo esc_attr($index); ?>][button_color]" value="<?php echo esc_attr($bar['button_color']); ?>">
                                </label>
                            </div>

                            <div class="oc-grid-three">
                                <label class="oc-inline-checkbox">
                                    <input type="checkbox" name="announcement_bars[<?php echo esc_attr($index); ?>][hide_desktop]" value="1" <?php checked(!empty($bar['hide_desktop'])); ?>>
                                    <span><?php esc_html_e('Hide on desktop', 'oc-essentials'); ?></span>
                                </label>
                                <label class="oc-inline-checkbox">
                                    <input type="checkbox" name="announcement_bars[<?php echo esc_attr($index); ?>][hide_tablet]" value="1" <?php checked(!empty($bar['hide_tablet'])); ?>>
                                    <span><?php esc_html_e('Hide on tablet', 'oc-essentials'); ?></span>
                                </label>
                                <label class="oc-inline-checkbox">
                                    <input type="checkbox" name="announcement_bars[<?php echo esc_attr($index); ?>][hide_mobile]" value="1" <?php checked(!empty($bar['hide_mobile'])); ?>>
                                    <span><?php esc_html_e('Hide on mobile', 'oc-essentials'); ?></span>
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <p>
                        <button type="button" class="button button-secondary" id="ocAddAnnouncement"><?php esc_html_e('Add Announcement Bar', 'oc-essentials'); ?></button>
                    </p>
                </div>
            </div>
            
            <!-- Backup & Maintenance -->
            <div class="oc-essentials-section">
                <h2>💾 Backup & Maintenance</h2>
                
                <!-- Feature 10 & 17: Download Database -->
                <div class="oc-feature-card">
                    <h3>📦 Download Database Backup</h3>
                    <p>Export complete SQL database backup</p>
                    <button type="button" class="button button-primary oc-download-db">Download Database Only</button>
                    <div class="oc-progress" data-progress="db">
                        <div class="oc-progress-bar"></div>
                        <span class="oc-progress-label"></span>
                    </div>
                    <div class="oc-status-message" data-status="db"></div>
                </div>
                
                <!-- Feature 15: Download Entire Site -->
                <div class="oc-feature-card">
                    <h3>📦 Download Entire Site</h3>
                    <p>Download complete site including files and database</p>
                    <button type="button" class="button button-primary oc-download-site">Download Entire Site</button>
                    <div class="oc-progress" data-progress="site">
                        <div class="oc-progress-bar"></div>
                        <span class="oc-progress-label"></span>
                    </div>
                    <div class="oc-status-message" data-status="site"></div>
                </div>
                
                <!-- Feature 16: Download Files Only -->
                <div class="oc-feature-card">
                    <h3>📦 Download Files Only</h3>
                    <p>Download site files without media library content</p>
                    <button type="button" class="button button-primary oc-download-files">Download Files Only</button>
                    <div class="oc-progress" data-progress="files">
                        <div class="oc-progress-bar"></div>
                        <span class="oc-progress-label"></span>
                    </div>
                    <div class="oc-status-message" data-status="files"></div>
                </div>
                
                <!-- Feature 20: Clear Cache -->
                <div class="oc-feature-card">
                    <h3>🧹 Clear Cache</h3>
                    <p>Clear entire site cache</p>
                    <button type="button" class="button button-secondary oc-clear-cache">Clear Entire Site Cache</button>
                </div>
            </div>
            
            <!-- Performance & Optimization -->
            <div class="oc-essentials-section">
                <h2>⚡ Performance & Optimization</h2>
                
                <!-- Feature 24: Disable Emojis -->
                <div class="oc-feature-card">
                    <div class="oc-feature-header">
                        <label>
                            <input type="checkbox" name="disable_emojis" value="1" <?php checked(!empty($options['disable_emojis'])); ?>>
                            <strong>Disable WordPress Emojis</strong>
                        </label>
                    </div>
                    <div class="oc-feature-body">
                        <p>Remove emoji scripts and styles to improve performance</p>
                    </div>
                </div>
                
                <!-- Feature 25: PageSpeed Test -->
                <div class="oc-feature-card">
                    <h3>🚀 PageSpeed Insights</h3>
                    <p>Test your site's performance with Google PageSpeed Insights</p>
                    <button type="button" class="button button-secondary oc-pagespeed-test">Perform PageSpeed Test</button>
                </div>
            </div>
            
        </div>
        
        <div class="oc-save-section">
            <button type="submit" name="oc_essentials_save" class="button button-primary button-large">Save All Settings</button>
        </div>
    </form>
</div>

<script type="text/template" id="ocAnnouncementTemplate">
    <div class="oc-announcement-item" data-index="__index__">
        <div class="oc-announcement-item-header">
            <strong><?php esc_html_e('Announcement', 'oc-essentials'); ?> #__display_index__</strong>
            <div class="oc-announcement-item-actions">
                <label class="oc-inline-checkbox" style="margin:0;">
                    <input type="checkbox" name="announcement_bars[__index__][enabled]" value="1" checked>
                    <span><?php esc_html_e('Enabled', 'oc-essentials'); ?></span>
                </label>
                <button type="button" class="button-link-delete oc-announcement-remove"><?php esc_html_e('Remove', 'oc-essentials'); ?></button>
            </div>
        </div>
        <label>
            <?php esc_html_e('Message', 'oc-essentials'); ?>
            <textarea name="announcement_bars[__index__][text]" rows="3"></textarea>
        </label>
        <div class="oc-grid-two">
            <label>
                <?php esc_html_e('Background Color', 'oc-essentials'); ?>
                <input type="color" name="announcement_bars[__index__][bg]" value="#111111">
            </label>
            <label>
                <?php esc_html_e('Text Color', 'oc-essentials'); ?>
                <input type="color" name="announcement_bars[__index__][color]" value="#ffffff">
            </label>
        </div>
        <div class="oc-grid-two">
            <label>
                <?php esc_html_e('Font Family', 'oc-essentials'); ?>
                <input type="text" name="announcement_bars[__index__][font_family]" value="inherit">
            </label>
            <label>
                <?php esc_html_e('Font Size (px)', 'oc-essentials'); ?>
                <input type="number" min="10" max="72" name="announcement_bars[__index__][font_size]" value="16">
            </label>
        </div>
        <label class="oc-inline-checkbox">
            <input type="checkbox" name="announcement_bars[__index__][marquee]" value="1">
            <span><?php esc_html_e('Enable scrolling marquee animation', 'oc-essentials'); ?></span>
        </label>
        <div class="oc-grid-two">
            <label>
                <?php esc_html_e('Start Display', 'oc-essentials'); ?>
                <input type="datetime-local" name="announcement_bars[__index__][start_at]" value="">
            </label>
            <label>
                <?php esc_html_e('Disable On', 'oc-essentials'); ?>
                <input type="datetime-local" name="announcement_bars[__index__][disable_on]" value="">
            </label>
        </div>
        <div class="oc-grid-two">
            <label>
                <?php esc_html_e('Button Text', 'oc-essentials'); ?>
                <input type="text" name="announcement_bars[__index__][button_text]" value="">
            </label>
            <label>
                <?php esc_html_e('Button URL', 'oc-essentials'); ?>
                <input type="url" name="announcement_bars[__index__][button_url]" value="">
            </label>
        </div>
        <div class="oc-grid-two">
            <label>
                <?php esc_html_e('Button Background', 'oc-essentials'); ?>
                <input type="color" name="announcement_bars[__index__][button_bg]" value="#ffffff">
            </label>
            <label>
                <?php esc_html_e('Button Text Color', 'oc-essentials'); ?>
                <input type="color" name="announcement_bars[__index__][button_color]" value="#111111">
            </label>
        </div>
        <div class="oc-grid-three">
            <label class="oc-inline-checkbox">
                <input type="checkbox" name="announcement_bars[__index__][hide_desktop]" value="1">
                <span><?php esc_html_e('Hide on desktop', 'oc-essentials'); ?></span>
            </label>
            <label class="oc-inline-checkbox">
                <input type="checkbox" name="announcement_bars[__index__][hide_tablet]" value="1">
                <span><?php esc_html_e('Hide on tablet', 'oc-essentials'); ?></span>
            </label>
            <label class="oc-inline-checkbox">
                <input type="checkbox" name="announcement_bars[__index__][hide_mobile]" value="1">
                <span><?php esc_html_e('Hide on mobile', 'oc-essentials'); ?></span>
            </label>
        </div>
    </div>
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var container = document.getElementById('ocAnnouncementList');
    if (!container) {
        return;
    }

    var template = document.getElementById('ocAnnouncementTemplate');
    var addButton = document.getElementById('ocAddAnnouncement');
    var nextIndex = parseInt(container.getAttribute('data-next-index'), 10);
    if (isNaN(nextIndex)) {
        nextIndex = container.querySelectorAll('.oc-announcement-item').length;
    }

    function addAnnouncement() {
        if (!template) {
            return;
        }

        var html = template.innerHTML
            .replace(/__index__/g, nextIndex)
            .replace(/__display_index__/g, nextIndex + 1);

        container.insertAdjacentHTML('beforeend', html);
        nextIndex++;
    }

    if (addButton) {
        addButton.addEventListener('click', function(event) {
            event.preventDefault();
            addAnnouncement();
        });
    }

    container.addEventListener('click', function(event) {
        var removeButton = event.target.closest('.oc-announcement-remove');
        if (!removeButton) {
            return;
        }

        event.preventDefault();
        var item = removeButton.closest('.oc-announcement-item');
        if (item) {
            item.remove();
        }

        if (!container.querySelector('.oc-announcement-item')) {
            addAnnouncement();
        }
    });
});
</script>

<script type="text/template" id="ocEssentialsLightbox" data-title="<?php esc_attr_e('Onyx Essentials Quickstart', 'oc-essentials'); ?>">
    <div class="oc-lightbox-content">
        <p><?php esc_html_e('Use this checklist to move quickly through hardening, optimization, and cleanup tasks without leaving the dashboard.', 'oc-essentials'); ?></p>
        <div class="oc-lightbox-grid">
            <div class="oc-lightbox-card">
                <h4><?php esc_html_e('Security Pulse', 'oc-essentials'); ?></h4>
                <ul>
                    <li><?php esc_html_e('Enable the SSL monitor and login locking before going live.', 'oc-essentials'); ?></li>
                    <li><?php esc_html_e('Rotate the unlock password after each hand-off.', 'oc-essentials'); ?></li>
                    <li><?php esc_html_e('Use custom login/admin slugs to keep bots away from wp-admin.', 'oc-essentials'); ?></li>
                </ul>
            </div>
            <div class="oc-lightbox-card">
                <h4><?php esc_html_e('Content Workflow', 'oc-essentials'); ?></h4>
                <ul>
                    <li><?php esc_html_e('Run the Orphaned Content and Missing Meta scans every publishing cycle.', 'oc-essentials'); ?></li>
                    <li><?php esc_html_e('Share draft previews securely with the Preview toggle instead of temporary accounts.', 'oc-essentials'); ?></li>
                    <li><?php esc_html_e('Pin important promos with multiple announcement bars and device targeting.', 'oc-essentials'); ?></li>
                </ul>
            </div>
            <div class="oc-lightbox-card">
                <h4><?php esc_html_e('Media & Performance', 'oc-essentials'); ?></h4>
                <ul>
                    <li><?php esc_html_e('Clean orphaned thumbnails before regenerating to avoid bloat.', 'oc-essentials'); ?></li>
                    <li><?php esc_html_e('Use the featured image column toggle to audit pages visually.', 'oc-essentials'); ?></li>
                    <li><?php esc_html_e('Clear caches from the dashboard or per-post buttons—no popups required.', 'oc-essentials'); ?></li>
                </ul>
            </div>
        </div>
        <p style="margin-top:15px;"><?php esc_html_e('Need more help? Open the documentation link for full walkthroughs, video guides, and troubleshooting tips.', 'oc-essentials'); ?></p>
    </div>
</script>
