/**
 * Onyx Essentials JavaScript
 * Version: 2.0.0 - Complete rewrite to fix duplicate handlers and broken code
 */
(function($) {
    'use strict';

    // Ensure we only initialize once
    if (window.ocEssentialsInitialized) {
        return;
    }
    window.ocEssentialsInitialized = true;

    $(function() {
        var $wrap = $('.oc-essentials-wrap');
        
        // Exit early if not on essentials page
        if (!$wrap.length) {
            return;
        }

        // ============================================
        // NOTICE SYSTEM
        // ============================================
        var Notices = {
            show: function(type, message, duration) {
                duration = duration || 5000;
                var $notice = $('<div class="notice notice-' + type + ' is-dismissible oc-temp-notice"><p>' + message + '</p></div>');
                $wrap.find('h1').first().after($notice);
                
                if (duration > 0) {
                    setTimeout(function() {
                        $notice.fadeOut(200, function() {
                            $(this).remove();
                        });
                    }, duration);
                }
                
                return $notice;
            },
            success: function(msg) { return this.show('success', msg); },
            error: function(msg) { return this.show('error', msg); },
            warning: function(msg) { return this.show('warning', msg); },
            info: function(msg) { return this.show('info', msg); }
        };

        // ============================================
        // CONFIRMATION MODAL
        // ============================================
        var Modal = (function() {
            var $overlay = null;
            var confirmCallback = null;
            var cancelCallback = null;

            function init() {
                if ($overlay) return;
                
                $overlay = $('<div class="oc-modal-overlay" aria-hidden="true">' +
                    '<div class="oc-modal">' +
                        '<h3 class="oc-modal-title">Confirm Action</h3>' +
                        '<div class="oc-modal-body">' +
                            '<div class="oc-modal-content" aria-live="polite"></div>' +
                            '<p class="oc-modal-message"></p>' +
                        '</div>' +
                        '<div class="oc-modal-actions">' +
                            '<button type="button" class="button button-secondary oc-modal-cancel">Cancel</button>' +
                            '<button type="button" class="button button-primary oc-modal-confirm">Confirm</button>' +
                        '</div>' +
                    '</div>' +
                '</div>');
                
                $('body').append($overlay);
                $overlay.find('.oc-modal-cancel').data('label', 'Cancel');
                $overlay.find('.oc-modal-confirm').data('label', 'Confirm');
                
                // Click overlay to cancel
                $overlay.on('click', function(e) {
                    if ($(e.target).is('.oc-modal-overlay')) {
                        close(false);
                    }
                });
                
                // Cancel button
                $overlay.find('.oc-modal-cancel').on('click', function() {
                    close(false);
                });
                
                // Confirm button
                $overlay.find('.oc-modal-confirm').on('click', function() {
                    close(true);
                });
            }

            function resetModal() {
                if (!$overlay) {
                    return;
                }
                var $message = $overlay.find('.oc-modal-message');
                var $content = $overlay.find('.oc-modal-content');
                var $cancel = $overlay.find('.oc-modal-cancel');
                var $confirm = $overlay.find('.oc-modal-confirm');

                $message.show().text('');
                $content.hide().empty();
                $confirm.show().text($confirm.data('label') || 'Confirm');
                $cancel.text($cancel.data('label') || 'Cancel');
            }

            function close(confirmed) {
                if (!$overlay) return;
                
                $overlay.hide().attr('aria-hidden', 'true');
                
                if (confirmed && confirmCallback) {
                    confirmCallback();
                } else if (!confirmed && cancelCallback) {
                    cancelCallback();
                }
                
                confirmCallback = null;
                cancelCallback = null;
                resetModal();
            }

            return {
                confirm: function(message, onConfirm, onCancel, title) {
                    init();
                    resetModal();
                    
                    if (title) {
                        $overlay.find('.oc-modal-title').text(title);
                    } else {
                        $overlay.find('.oc-modal-title').text('Confirm Action');
                    }
                    
                    $overlay.find('.oc-modal-message').text(message);
                    confirmCallback = onConfirm || null;
                    cancelCallback = onCancel || null;
                    $overlay.show().attr('aria-hidden', 'false');
                },
                content: function(html, title) {
                    init();
                    resetModal();

                    $overlay.find('.oc-modal-title').text(title || 'Details');
                    $overlay.find('.oc-modal-message').hide();
                    $overlay.find('.oc-modal-content').html(html).show();
                    $overlay.find('.oc-modal-confirm').hide();
                    $overlay.find('.oc-modal-cancel').text('Close');
                    confirmCallback = null;
                    cancelCallback = null;
                    $overlay.show().attr('aria-hidden', 'false');
                }
            };
        })();

        $wrap.on('click', '[data-oc-lightbox]', function(e) {
            e.preventDefault();
            var target = $(this).data('oc-lightbox');
            if (!target) {
                return;
            }

            var $template = $(target);
            if (!$template.length) {
                Notices.error('Lightbox content missing.');
                return;
            }

            var title = $(this).data('ocTitle') || $(this).data('oc-title') || $template.data('title') || 'Details';
            Modal.content($template.html(), title);
        });

        // ============================================
        // PROGRESS BAR SYSTEM
        // ============================================
        var Progress = {
            bars: {},
            
            start: function(key) {
                    var $bar = $('[data-progress="' + key + '"]');
                    if (!$bar.length) return;
                
                this.bars[key] = { interval: null, value: 0 };
                    $bar.show();
                    var $fill = $bar.find('.oc-progress-bar');
                    var $text = $bar.find('.oc-progress-label');
                    var startText = (window.ocEssentials && ocEssentials.strings && ocEssentials.strings.processing) ? ocEssentials.strings.processing : 'Starting...';
                    $fill.css('width', '0%');
                    $text.text(startText);
                
                var self = this;
                this.bars[key].interval = setInterval(function() {
                    self.bars[key].value = Math.min(self.bars[key].value + Math.random() * 15, 90);
                        $fill.css('width', self.bars[key].value + '%');
                        $text.text(Math.round(self.bars[key].value) + '%');
                }, 500);
            },
            
            stop: function(key, message) {
                    var $bar = $('[data-progress="' + key + '"]');
                if (!$bar.length) return;
                
                if (this.bars[key] && this.bars[key].interval) {
                    clearInterval(this.bars[key].interval);
                }
                
                    var $fill = $bar.find('.oc-progress-bar');
                    var $text = $bar.find('.oc-progress-label');
                    var doneText = message || (ocEssentials.strings && ocEssentials.strings.completed) || 'Complete!';
                    $fill.css('width', '100%');
                    $text.text(doneText);
                
                setTimeout(function() {
                        $bar.fadeOut();
                }, 2000);
            }
        };

        // ============================================
        // UTILITY FUNCTIONS
        // ============================================
        function escapeHtml(text) {
            if (!text) return '';
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        function lockButton($btn, text) {
            if (!$btn.length) return;
            if (!$btn.data('original-text')) {
                $btn.data('original-text', $btn.text());
            }
            $btn.prop('disabled', true);
            if (text) $btn.text(text);
        }

        function unlockButton($btn, restoreText) {
            if (!$btn.length) return;
            $btn.prop('disabled', false);
            if (restoreText && $btn.data('original-text')) {
                $btn.text($btn.data('original-text'));
            }
        }

        function runAjax(action, data, options) {
            options = options || {};
            data = data || {};
            data.action = action;
            data.nonce = ocEssentials.nonce;

            if (options.button) {
                lockButton(options.button, options.loadingText || 'Processing...');
            }

            return $.post(ocEssentials.ajax_url, data)
                .done(function(response) {
                    if (options.button) {
                        unlockButton(options.button, true);
                    }
                    
                    if (response.success) {
                        if (options.successMessage) {
                            Notices.success(options.successMessage);
                        } else if (response.data && response.data.message) {
                            Notices.success(response.data.message);
                        }
                        if (options.onSuccess) {
                            options.onSuccess(response.data);
                        }
                    } else {
                        var errorMsg = (response.data && response.data.message) || response.data || 'An error occurred';
                        Notices.error(errorMsg);
                        if (options.onError) {
                            options.onError(response.data);
                        }
                    }
                })
                .fail(function(xhr, status, error) {
                    if (options.button) {
                        unlockButton(options.button, true);
                    }
                    Notices.error('Network error: ' + (error || 'Please try again'));
                    if (options.onError) {
                        options.onError({ message: 'Network error' });
                    }
                });
        }

        // ============================================
        // CONFIRMATION MESSAGES
        // ============================================
        var confirmMessages = {
            'change-admin': 'Are you sure you want to change the admin username? This action cannot be undone.',
            'disable-file-editing': 'Disable plugin file editing? (Administrators/Super Admins are exempt)',
            'disable-theme-editing': 'Disable theme file editing? (Administrators/Super Admins are exempt)',
            'delete-unattached': 'Delete all unattached media? This cannot be undone.',
            'backup': 'Create a backup? This may take several minutes for large sites.',
            'clear-cache': 'Clear the entire site cache?'
        };

        // ============================================
        // CONFIRMATION TOGGLE CHECKBOXES
        // ============================================
        $wrap.on('change', '.oc-confirm-toggle', function() {
            var $checkbox = $(this);
            if (!$checkbox.is(':checked')) {
                return;
            }
            
            var confirmKey = $checkbox.data('confirm');
            var message = confirmMessages[confirmKey] || 'Are you sure you want to enable this option?';
            
            Modal.confirm(message, function() {
                // Confirmed - checkbox stays checked
            }, function() {
                // Cancelled - uncheck
                $checkbox.prop('checked', false);
            });
        });

        // ============================================
        // BACKUP & DOWNLOAD BUTTONS
        // ============================================
        function handleBackup($button, action, progressKey) {
            // Prevent double-clicks
            if ($button.data('processing')) {
                return;
            }
            $button.data('processing', true);
            
            Modal.confirm(confirmMessages.backup, function() {
                lockButton($button, 'Creating backup, please wait...');
                Progress.start(progressKey);
                
                // Use iframe for file download to avoid navigation issues
                var downloadUrl = ocEssentials.ajax_url + '?action=' + action + '&nonce=' + ocEssentials.nonce;
                
                // Create hidden iframe for download
                var $iframe = $('<iframe>', {
                    style: 'display:none',
                    src: downloadUrl
                }).appendTo('body');
                
                // Clean up after download starts
                setTimeout(function() {
                    Progress.stop(progressKey, 'Download started!');
                    unlockButton($button, true);
                    $button.data('processing', false);
                    
                    // Remove iframe after a delay
                    setTimeout(function() {
                        $iframe.remove();
                    }, 5000);
                }, 3000);
                
            }, function() {
                $button.data('processing', false);
            });
        }

        $wrap.on('click', '.oc-download-db', function(e) {
            e.preventDefault();
            handleBackup($(this), 'oc_download_database', 'db');
        });

        $wrap.on('click', '.oc-download-site', function(e) {
            e.preventDefault();
            handleBackup($(this), 'oc_download_site', 'site');
        });

        $wrap.on('click', '.oc-download-files', function(e) {
            e.preventDefault();
            handleBackup($(this), 'oc_download_files_only', 'files');
        });

        // ============================================
        // UNLOCK USER/IP BUTTONS
        // ============================================
        $wrap.on('click', '.oc-unlock-user', function(e) {
            e.preventDefault();
            var $button = $(this);
            var userId = $button.data('user-id');
            
            Modal.confirm('Unlock this user account?', function() {
                runAjax('oc_unlock_user', { user_id: userId }, {
                    button: $button,
                    onSuccess: function() {
                        $button.closest('tr').fadeOut(function() {
                            $(this).remove();
                        });
                    }
                });
            });
        });

        $wrap.on('click', '.oc-unlock-ip', function(e) {
            e.preventDefault();
            var $button = $(this);
            var ip = $button.data('ip');
            
            Modal.confirm('Unlock this IP address?', function() {
                runAjax('oc_unlock_ip', { ip: ip }, {
                    button: $button,
                    onSuccess: function() {
                        $button.closest('tr').fadeOut(function() {
                            $(this).remove();
                        });
                    }
                });
            });
        });

        // ============================================
        // MAINTENANCE BUTTONS
        // ============================================
        $wrap.on('click', '.oc-regenerate-thumbs', function(e) {
            e.preventDefault();
            var $button = $(this);
            var action = $button.data('action') || 'regenerate';
            var removeOrphans = $('input[name="regen_remove_orphans"]').is(':checked');
            var sizes = $('.oc-regenerate-sizes').val() || [];

            var confirmMessage = action === 'cleanup'
                ? ocEssentials.strings.confirm_cleanup_thumbs
                : ocEssentials.strings.confirm_regen_thumbs;

            Modal.confirm(confirmMessage, function() {
                Progress.start('thumbs');
                runAjax('oc_regenerate_thumbnails', {
                    regen_action: action,
                    remove_orphans: removeOrphans ? 1 : 0,
                    sizes: sizes
                }, {
                    button: $button,
                    loadingText: (action === 'cleanup') ? ocEssentials.strings.cleaning : ocEssentials.strings.regenerating,
                    onSuccess: function(data) {
                        var message = (data && data.message) ? data.message : ocEssentials.strings.completed;
                        Progress.stop('thumbs', message);
                    },
                    onError: function(data) {
                        var message = (data && data.message) ? data.message : ocEssentials.strings.error_generic;
                        Progress.stop('thumbs', message);
                    }
                });
            });
        });

        $wrap.on('click', '.oc-delete-all-thumbnails', function(e) {
            e.preventDefault();
            var $button = $(this);

            Modal.confirm(ocEssentials.strings.confirm_delete_thumbs, function() {
                Progress.start('thumbs');
                runAjax('oc_delete_all_thumbnails', {}, {
                    button: $button,
                    loadingText: ocEssentials.strings.deleting,
                    onSuccess: function(data) {
                        var message = (data && data.message) ? data.message : ocEssentials.strings.completed;
                        Progress.stop('thumbs', message);
                    },
                    onError: function(data) {
                        var message = (data && data.message) ? data.message : ocEssentials.strings.error_generic;
                        Progress.stop('thumbs', message);
                    }
                });
            });
        });

        $wrap.on('click', '.oc-delete-unattached', function(e) {
            e.preventDefault();
            var $button = $(this);
            var keepOriginal = $('input[name="delete_keep_original"]').is(':checked');

            Modal.confirm(
                ocEssentials.strings.confirm_delete_unattached,
                function() {
                    runAjax('oc_delete_unattached', {
                        keep_original: keepOriginal ? 1 : 0
                    }, {
                        button: $button,
                        loadingText: ocEssentials.strings.deleting
                    });
                },
                function() {
                    window.location.href = ocEssentials.admin_url + 'upload.php?attachment-filter=detached';
                }
            );
        });

        $wrap.on('click', '.oc-clear-cache', function(e) {
            e.preventDefault();
            var $button = $(this);
            
            // No confirmation per requirements
            var payload = {};
            if ($button.data('postId')) {
                payload.post_id = $button.data('postId');
            }

            runAjax('oc_clear_cache', payload, {
                button: $button,
                loadingText: $button.data('postId') ? ocEssentials.strings.clearing_cache_content : ocEssentials.strings.clearing_cache_site,
                successMessage: $button.data('postId') ? ocEssentials.strings.cache_cleared_content : ocEssentials.strings.cache_cleared_site
            });
        });

        // ============================================
        // SCAN FUNCTIONS
        // ============================================
        function handleScan(action, $button, $results, renderCallback) {
            if ($button.data('scanning')) {
                return;
            }
            $button.data('scanning', true);
            
            lockButton($button, 'Scanning...');
            $results.html('<p class="oc-loading">🔍 Scanning...</p>');
            
            $.post(ocEssentials.ajax_url, {
                action: action,
                nonce: ocEssentials.nonce
            })
            .done(function(response) {
                unlockButton($button, true);
                $button.data('scanning', false);
                
                if (response.success) {
                    renderCallback(response.data, $results);
                } else {
                    var errorMsg = (response.data && response.data.message) || response.data || 'Unknown error';
                    $results.html('<p class="oc-error">❌ Error: ' + escapeHtml(errorMsg) + '</p>');
                }
            })
            .fail(function() {
                unlockButton($button, true);
                $button.data('scanning', false);
                $results.html('<p class="oc-error">❌ Network error occurred. Please try again.</p>');
            });
        }

        // Scan for Orphaned Content
        $wrap.on('click', '.oc-scan-orphans', function(e) {
            e.preventDefault();
            var $button = $(this);
            var $results = $('#oc-orphan-results');
            
            handleScan('oc_scan_orphaned', $button, $results, function(data) {
                var orphans = data.orphaned || [];
                
                if (orphans.length === 0) {
                    $results.html('<p class="oc-success">✓ Great! No orphaned content found.</p>');
                    return;
                }
                
                var html = '<h4>Found ' + orphans.length + ' orphaned item' + (orphans.length > 1 ? 's' : '') + ':</h4>';
                html += '<div class="oc-orphan-list">';
                
                orphans.forEach(function(item) {
                    html += '<div class="oc-orphan-item">';
                    html += '<h5><a href="' + escapeHtml(item.edit_url) + '" target="_blank">' + escapeHtml(item.title) + '</a></h5>';
                    html += '<p><small>' + escapeHtml(item.type) + ' | Published: ' + escapeHtml(item.date) + '</small></p>';
                    
                    // Show suggestions if available
                    if (item.suggestions && item.suggestions.length > 0) {
                        html += '<p><strong>Suggested pages to link from:</strong></p>';
                        html += '<ul class="oc-suggestions">';
                        item.suggestions.forEach(function(suggestion) {
                            html += '<li><a href="' + escapeHtml(suggestion.edit_url) + '" target="_blank">' + escapeHtml(suggestion.title) + '</a></li>';
                        });
                        html += '</ul>';
                    }
                    
                    html += '</div>';
                });
                
                html += '</div>';
                $results.html(html);
            });
        });

        // Scan for Missing Meta Data
        $wrap.on('click', '.oc-scan-meta', function(e) {
            e.preventDefault();
            var $button = $(this);
            var $results = $('#oc-meta-results');
            
            handleScan('oc_scan_meta', $button, $results, function(data) {
                var missing = data.missing_meta || [];
                var seoPlugin = data.seo_plugin || 'none';
                
                if (seoPlugin === 'none') {
                    $results.html('<p class="oc-warning">⚠️ No SEO plugin detected. Please install Yoast SEO, Rank Math, or All in One SEO.</p>');
                    return;
                }
                
                if (missing.length === 0) {
                    $results.html('<p class="oc-success">✓ Excellent! All posts and pages have complete meta data.</p>');
                    return;
                }
                
                var html = '<h4>Found ' + missing.length + ' item' + (missing.length > 1 ? 's' : '') + ' with missing meta:</h4>';
                html += '<div class="oc-meta-list">';
                
                missing.forEach(function(item) {
                    html += '<div class="oc-meta-item">';
                    html += '<h5><a href="' + escapeHtml(item.edit_url) + '" target="_blank">' + escapeHtml(item.title) + '</a></h5>';
                    html += '<p><small>' + escapeHtml(item.type) + '</small></p>';
                    html += '<p><strong>⚠️ Missing:</strong> ' + escapeHtml(item.missing.join(', ')) + '</p>';
                    html += '</div>';
                });
                
                html += '</div>';
                $results.html(html);
            });
        });

        // Scan for Missing Alt Text
        $wrap.on('click', '.oc-scan-alt', function(e) {
            e.preventDefault();
            var $button = $(this);
            var $results = $('#oc-alt-results');
            
            handleScan('oc_scan_alt_text', $button, $results, function(data) {
                var missing = data.missing || [];
                var hasAiAltTag = data.has_ai_alt_tag || false;
                var aiUrl = data.ai_alt_tag_url || '';
                
                if (missing.length === 0) {
                    $results.html('<p class="oc-success">✓ Perfect! All images have alt text.</p>');
                    return;
                }
                
                var html = '<h4>Found ' + missing.length + ' image' + (missing.length > 1 ? 's' : '') + ' without alt text</h4>';
                
                if (hasAiAltTag) {
                    html += '<p><a href="' + escapeHtml(aiUrl) + '" class="button button-primary" target="_blank">🤖 Fix with AI Alt Tag Manager</a></p>';
                    html += '<div class="oc-ai-generate-section" style="margin: 15px 0; padding: 15px; background: #f0f7ff; border-radius: 4px;">';
                    html += '<p><strong>Or generate alt text now:</strong></p>';
                    html += '<button type="button" class="button oc-generate-all-alt" data-count="' + missing.length + '">🤖 Generate Alt Text for All ' + missing.length + ' Images</button>';
                    html += '<div id="oc-alt-generate-progress" style="display:none; margin-top: 10px;"></div>';
                    html += '</div>';
                } else {
                    html += '<p class="oc-note">💡 Install "AI Alt Tag Manager" module to automatically generate alt text.</p>';
                }
                
                html += '<div class="oc-alt-list">';
                
                var displayCount = Math.min(missing.length, 10);
                for (var i = 0; i < displayCount; i++) {
                    var item = missing[i];
                    html += '<div class="oc-alt-item" data-id="' + item.id + '">';
                    html += '<img src="' + escapeHtml(item.thumb) + '" alt="" style="max-width: 80px; max-height: 80px; object-fit: cover;">';
                    html += '<div class="oc-alt-item-info">';
                    html += '<h5>' + escapeHtml(item.title) + '</h5>';
                    html += '<p><a href="' + escapeHtml(item.edit_url) + '" target="_blank">Edit</a> | <a href="' + escapeHtml(item.url) + '" target="_blank">View</a>';
                    if (hasAiAltTag) {
                        html += ' | <button type="button" class="button button-small oc-generate-single-alt" data-id="' + item.id + '">Generate</button>';
                    }
                    html += '</p>';
                    html += '</div>';
                    html += '</div>';
                }
                
                html += '</div>';
                
                if (missing.length > 10) {
                    html += '<p class="oc-note">...and ' + (missing.length - 10) + ' more</p>';
                }
                
                $results.html(html);
            });
        });

        // Generate single alt text
        $wrap.on('click', '.oc-generate-single-alt', function(e) {
            e.preventDefault();
            var $button = $(this);
            var imageId = $button.data('id');
            var $item = $button.closest('.oc-alt-item');
            
            lockButton($button, '...');
            
            $.post(ocEssentials.ajax_url, {
                action: 'oc_generate_alt_from_scan',
                nonce: ocEssentials.nonce,
                image_id: imageId
            })
            .done(function(response) {
                if (response.success) {
                    $item.fadeOut(function() {
                        $(this).remove();
                    });
                    Notices.success('Alt text generated: "' + response.data.alt_text + '"');
                } else {
                    unlockButton($button, true);
                    Notices.error(response.data || 'Failed to generate alt text');
                }
            })
            .fail(function() {
                unlockButton($button, true);
                Notices.error('Network error');
            });
        });

        // Generate all alt text
        $wrap.on('click', '.oc-generate-all-alt', function(e) {
            e.preventDefault();
            var $button = $(this);
            var count = $button.data('count');
            var $progress = $('#oc-alt-generate-progress');
            
            Modal.confirm('Generate alt text for ' + count + ' images? This will use AI credits.', function() {
                lockButton($button, 'Generating...');
                $progress.show().html('<p>Processing images... <span class="oc-alt-progress-count">0</span>/' + count + '</p>');
                
                // Process images one at a time
                var $items = $('.oc-alt-item');
                var processed = 0;
                
                function processNext() {
                    var $item = $items.eq(processed);
                    if (!$item.length) {
                        $progress.html('<p class="oc-success">✓ Completed! Generated alt text for ' + processed + ' images.</p>');
                        unlockButton($button, true);
                        return;
                    }
                    
                    var imageId = $item.data('id');
                    
                    $.post(ocEssentials.ajax_url, {
                        action: 'oc_generate_alt_from_scan',
                        nonce: ocEssentials.nonce,
                        image_id: imageId
                    })
                    .always(function() {
                        processed++;
                        $('.oc-alt-progress-count').text(processed);
                        $item.fadeOut();
                        
                        // Small delay between requests
                        setTimeout(processNext, 500);
                    });
                }
                
                processNext();
            });
        });

        // ============================================
        // SUBMIT TO GOOGLE
        // ============================================
        $wrap.on('click', '.oc-submit-google', function(e) {
            e.preventDefault();
            var $button = $(this);
            
            runAjax('oc_submit_nonindexed', {}, {
                button: $button,
                loadingText: 'Submitting...'
            });
        });

        // ============================================
        // BROKEN URL SCANNER
        // ============================================
        $wrap.on('click', '.oc-scan-broken-urls', function(e) {
            e.preventDefault();
            var $button = $(this);
            var $results = $('#oc-broken-url-results');
            var postId = $button.data('post-id') || 0;
            
            if ($button.data('scanning')) {
                return;
            }
            $button.data('scanning', true);
            
            lockButton($button, 'Scanning URLs...');
            $results.html('<p class="oc-loading">🔍 Scanning for broken URLs... This may take a few minutes.</p>');
            
            $.post(ocEssentials.ajax_url, {
                action: 'oc_scan_broken_urls',
                nonce: ocEssentials.nonce,
                post_id: postId
            })
            .done(function(response) {
                unlockButton($button, true);
                $button.data('scanning', false);
                
                if (response.success) {
                    var data = response.data;
                    var broken = data.broken_urls || [];
                    
                    if (broken.length === 0) {
                        $results.html('<p class="oc-success">✓ No broken URLs found! Scanned ' + data.posts_scanned + ' posts/pages.</p>');
                        return;
                    }
                    
                    var html = '<h4>Found ' + broken.length + ' broken URL' + (broken.length > 1 ? 's' : '') + '</h4>';
                    html += '<p class="oc-note">Scanned ' + data.posts_scanned + ' posts/pages</p>';
                    html += '<div class="oc-broken-url-list">';
                    html += '<table class="oc-table widefat striped">';
                    html += '<thead><tr><th>URL</th><th>Type</th><th>Found In</th><th>Published</th><th>Action</th></tr></thead>';
                    html += '<tbody>';
                    
                    broken.forEach(function(item) {
                        var typeLabel = item.type.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
                        var typeClass = item.type.indexOf('internal') === 0 ? 'oc-type-internal' : 'oc-type-external';
                        
                        html += '<tr>';
                        html += '<td class="oc-broken-url-cell">';
                        html += '<a href="' + escapeHtml(item.url) + '" target="_blank" title="' + escapeHtml(item.url) + '">';
                        html += escapeHtml(item.url.length > 50 ? item.url.substring(0, 50) + '...' : item.url);
                        html += '</a>';
                        if (item.context) {
                            html += '<br><small class="oc-context">"...' + escapeHtml(item.context) + '..."</small>';
                        }
                        html += '</td>';
                        html += '<td><span class="oc-url-type ' + typeClass + '">' + escapeHtml(typeLabel) + '</span></td>';
                        html += '<td><a href="' + escapeHtml(item.post_edit_url) + '" target="_blank">' + escapeHtml(item.post_title) + '</a></td>';
                        html += '<td>' + escapeHtml(item.post_date) + '</td>';
                        html += '<td>';
                        html += '<a href="' + escapeHtml(item.post_edit_url) + '&highlight_url=' + encodeURIComponent(item.url) + '" class="button button-small" target="_blank">Edit Post</a>';
                        html += '</td>';
                        html += '</tr>';
                    });
                    
                    html += '</tbody></table></div>';
                    $results.html(html);
                } else {
                    $results.html('<p class="oc-error">❌ Error: ' + (response.data || 'Unknown error') + '</p>');
                }
            })
            .fail(function() {
                unlockButton($button, true);
                $button.data('scanning', false);
                $results.html('<p class="oc-error">❌ Network error occurred. Please try again.</p>');
            });
        });

        // ============================================
        // PAGESPEED TEST
        // ============================================
        $wrap.on('click', '.oc-pagespeed-test', function(e) {
            e.preventDefault();
            var url = 'https://pagespeed.web.dev/analysis?url=' + encodeURIComponent(ocEssentials.site_url);
            window.open(url, '_blank');
            Notices.info('Opening PageSpeed Insights in a new tab...');
        });

        // ============================================
        // BLOCKED ACCOUNTS MANAGEMENT
        // ============================================
        $wrap.on('click', '.oc-block-permanent', function(e) {
            e.preventDefault();
            var $button = $(this);
            var recordId = $button.data('record-id');
            
            Modal.confirm('Permanently block this IP? The user will need admin help to unlock.', function() {
                runAjax('oc_block_record_action', { 
                    record_id: recordId,
                    block_action: 'permanent'
                }, {
                    button: $button,
                    onSuccess: function() {
                        $button.closest('tr').addClass('oc-permanently-blocked');
                        $button.replaceWith('<span class="oc-badge oc-badge-danger">Permanently Blocked</span>');
                    }
                });
            });
        });

        $wrap.on('click', '.oc-unblock-record', function(e) {
            e.preventDefault();
            var $button = $(this);
            var recordId = $button.data('record-id');
            
            Modal.confirm('Unblock this account and delete the recorded data?', function() {
                runAjax('oc_block_record_action', {
                    record_id: recordId,
                    block_action: 'unblock'
                }, {
                    button: $button,
                    onSuccess: function() {
                        $button.closest('tr').fadeOut(function() {
                            $(this).remove();
                        });
                    }
                });
            });
        });

        // ============================================
        // SSL CERTIFICATE UPLOAD
        // ============================================
        $wrap.on('click', '.oc-upload-ssl', function(e) {
            e.preventDefault();
            var $button = $(this);
            var $form = $button.closest('.oc-ssl-upload-form');
            var certFile = $form.find('input[name="ssl_cert"]')[0];
            var keyFile = $form.find('input[name="ssl_key"]')[0];
            
            if (!certFile.files.length || !keyFile.files.length) {
                Notices.error('Please select both certificate and key files');
                return;
            }
            
            var formData = new FormData();
            formData.append('action', 'oc_upload_ssl');
            formData.append('nonce', ocEssentials.nonce);
            formData.append('ssl_cert', certFile.files[0]);
            formData.append('ssl_key', keyFile.files[0]);
            
            lockButton($button, 'Uploading...');
            
            $.ajax({
                url: ocEssentials.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false
            })
            .done(function(response) {
                unlockButton($button, true);
                if (response.success) {
                    Notices.success('SSL certificate uploaded successfully');
                    $form[0].reset();
                } else {
                    Notices.error(response.data || 'Upload failed');
                }
            })
            .fail(function() {
                unlockButton($button, true);
                Notices.error('Network error during upload');
            });
        });

        // ============================================
        // FORM SUBMISSION
        // ============================================
        $wrap.find('form').on('submit', function() {
            // Allow form to submit normally
            return true;
        });

        console.log('Onyx Essentials: JavaScript initialized successfully');
    });

})(jQuery);

