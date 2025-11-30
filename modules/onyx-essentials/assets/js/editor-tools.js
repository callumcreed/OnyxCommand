(function($) {
    'use strict';

    function escapeHtml(text) {
        return String(text || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function initBrokenUrlScanner() {
        if (typeof ocEditorTools === 'undefined') {
            return;
        }

        var settings = ocEditorTools;
        var $box = $('#ocBrokenUrlMetabox');

        if (!$box.length) {
            return;
        }

        var postId = parseInt(settings.post_id, 10) || 0;
        var $button = $box.find('.oc-scan-broken-single');
        var $results = $box.find('.oc-results');

        if (!postId && $button.length) {
            $button.prop('disabled', true);
            return;
        }

        $box.on('click', '.oc-scan-broken-single', function(event) {
            event.preventDefault();

            var $btn = $(this);
            $btn.prop('disabled', true).text(settings.strings.scanning);
            $results.removeClass('notice notice-error notice-success').html('<em>' + settings.strings.scanning + '</em>');

            $.post(settings.ajax_url, {
                action: 'oc_scan_broken_urls',
                nonce: settings.nonce,
                post_id: postId
            }).done(function(response) {
                $btn.prop('disabled', false).text(settings.strings.scan_single);

                if (!response || !response.success) {
                    var message = response && response.data ? response.data : settings.strings.error_generic.replace('%s', '');
                    $results.addClass('notice notice-error').text(message);
                    return;
                }

                var data = response.data || {};
                var broken = data.broken_urls || [];

                if (!broken.length) {
                    $results.removeClass().addClass('notice notice-success').text(settings.strings.no_broken);
                    return;
                }

                var html = '<div class="oc-broken-editor-results"><strong>' + escapeHtml(settings.strings.results_header) + '</strong><ul>';
                broken.forEach(function(item) {
                    html += '<li style="margin:6px 0;">';
                    html += '<a href="' + escapeHtml(item.url) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(item.url) + '</a>';
                    if (item.context) {
                        html += '<br><small style="color:#646970;">' + escapeHtml(item.context) + '</small>';
                    }
                    html += '</li>';
                });
                html += '</ul></div>';

                $results.removeClass().html(html);
            }).fail(function() {
                $btn.prop('disabled', false).text(settings.strings.scan_single);
                $results.addClass('notice notice-error').text(settings.strings.network_error);
            });
        });
    }

    function initCacheButton() {
        if (typeof ocEditorTools === 'undefined') {
            return;
        }

        var settings = ocEditorTools;
        var $box = $('#ocBrokenUrlMetabox');
        if (!$box.length) {
            return;
        }

        $box.on('click', '.oc-clear-cache-post', function(event) {
            event.preventDefault();

            var $btn = $(this);
            var $status = $box.find('.oc-cache-results');
            var postId = parseInt($btn.data('post-id'), 10) || 0;

            if (!postId) {
                return;
            }

            $btn.prop('disabled', true).text(settings.cache_strings.clearing);
            $status.text(settings.cache_strings.clearing);

            $.post(settings.ajax_url, {
                action: 'oc_clear_cache',
                nonce: settings.nonce,
                post_id: postId
            }).done(function(response) {
                $btn.prop('disabled', false).text(settings.cache_strings.clear_label);

                if (response && response.success) {
                    $status.text(settings.cache_strings.success).css('color', '#2271b1');
                } else {
                    var message = (response && response.data && response.data.message) ? response.data.message : settings.cache_strings.error;
                    $status.text(message).css('color', '#d63638');
                }
            }).fail(function() {
                $btn.prop('disabled', false).text(settings.cache_strings.clear_label);
                $status.text(settings.cache_strings.network_error).css('color', '#d63638');
            });
        });
    }

    function createRewriteButton(settings) {
        var $wrapper = $('<div class="oc-ai-rewriter-box" style="margin:15px 0; padding:12px; border:1px solid #dcd7ca; border-radius:6px; background:#fff;"></div>');
        var $button = $('<button type="button" class="button button-secondary" style="margin-right:10px;"></button>').text(settings.strings.button_label);
        var $status = $('<span class="oc-ai-status" style="font-size:12px;color:#666;"></span>');

        $wrapper.append($button).append($status);

        var inserted = false;
        var targets = [
            '#postdivrich',
            '.interface-complementary-area__header',
            '#submitdiv .misc-pub-section',
            '#editor'
        ];

        targets.some(function(selector) {
            var $target = $(selector).first();
            if ($target.length) {
                $target.before($wrapper);
                inserted = true;
                return true;
            }
            return false;
        });

        if (!inserted) {
            $('#poststuff').prepend($wrapper);
        }

        var savedRange = null;

        function captureSelection() {
            var selection = window.getSelection();

            if (!selection || selection.rangeCount === 0 || selection.isCollapsed) {
                return null;
            }

            savedRange = selection.getRangeAt(0).cloneRange();
            return selection.toString();
        }

        function replaceSelection(text) {
            if (!savedRange) {
                return false;
            }

            savedRange.deleteContents();
            var textNode = document.createTextNode(text);
            savedRange.insertNode(textNode);

            var selection = window.getSelection();
            selection.removeAllRanges();
            var range = document.createRange();
            range.setStartAfter(textNode);
            range.collapse(true);
            selection.addRange(range);

            savedRange = null;
            return true;
        }

        $button.on('click', function(event) {
            event.preventDefault();

            var highlighted = captureSelection();

            if (!highlighted || highlighted.trim().length === 0) {
                $status.text(settings.strings.no_selection).css('color', '#d63638');
                return;
            }

            if (highlighted.length > 1000) {
                highlighted = highlighted.substring(0, 1000);
            }

            $button.prop('disabled', true).text(settings.strings.button_working);
            $status.text(settings.strings.rewriting).css('color', '#3858e9');

            $.post(settings.ajax_url, {
                action: 'oc_rewrite_content',
                nonce: settings.nonce,
                post_id: settings.post_id,
                content: highlighted
            }).done(function(response) {
                $button.prop('disabled', false).text(settings.strings.button_label);

                if (!response || !response.success || !response.data || !response.data.text) {
                    var message = (response && response.data) ? response.data : settings.strings.error_generic;
                    $status.text(message).css('color', '#d63638');
                    return;
                }

                if (replaceSelection(response.data.text)) {
                    $status.text(settings.strings.rewrite_done).css('color', '#007cba');
                } else {
                    $status.text(settings.strings.replace_failed).css('color', '#d63638');
                }
            }).fail(function() {
                $button.prop('disabled', false).text(settings.strings.button_label);
                $status.text(settings.strings.network_error).css('color', '#d63638');
            });
        });
    }

    function initAIRewriter() {
        if (typeof ocAIRewriter === 'undefined') {
            return;
        }

        createRewriteButton(ocAIRewriter);
    }

    $(function() {
        initBrokenUrlScanner();
        initCacheButton();
        initAIRewriter();
    });

})(jQuery);

