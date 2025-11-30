(function($) {
    'use strict';

    $(function() {
        if (typeof ocMediaTools === 'undefined') {
            return;
        }

        var $bar = $('#oc-media-tools-bar');
        if (!$bar.length) {
            return;
        }

        var $progress = $('#oc-media-progress');
        var $fill = $progress.find('.oc-media-progress-fill');
        var $text = $progress.find('.oc-media-progress-text');
        var interval = null;

        function startProgress(message) {
            clearInterval(interval);
            $progress.attr('aria-hidden', 'false');
            $fill.css('width', '0%');
            $text.text(message || ocMediaTools.strings.processing);
            var value = 0;

            interval = setInterval(function() {
                value = Math.min(value + Math.random() * 12, 95);
                $fill.css('width', value + '%');
            }, 450);
        }

        function stopProgress(message) {
            clearInterval(interval);
            $fill.css('width', '100%');
            $text.text(message || ocMediaTools.strings.complete);
            setTimeout(function() {
                $progress.attr('aria-hidden', 'true');
                $fill.css('width', '0%');
                $text.text(ocMediaTools.strings.processing);
            }, 2000);
        }

        function handleAjax(action, confirmMessage) {
            if (!window.confirm(confirmMessage)) {
                return;
            }

            var $button = $bar.find('[data-action="' + action + '"]');
            if ($button.length) {
                $button.prop('disabled', true);
            }

            startProgress(ocMediaTools.strings.processing);

            $.post(ocMediaTools.ajax_url, {
                action: action,
                nonce: ocMediaTools.nonce
            }).done(function(response) {
                if (response && response.success) {
                    var message = (response.data && response.data.message) ? response.data.message : ocMediaTools.strings.complete;
                    stopProgress(message);
                } else {
                    var errorMsg = (response && response.data && response.data.message) ? response.data.message : ocMediaTools.strings.error;
                    stopProgress(errorMsg);
                    window.alert(errorMsg);
                }
            }).fail(function() {
                stopProgress(ocMediaTools.strings.network_error);
                window.alert(ocMediaTools.strings.network_error);
            }).always(function() {
                if ($button.length) {
                    $button.prop('disabled', false);
                }
            });
        }

        $bar.on('click', '[data-action="oc_delete_all_thumbnails"]', function(event) {
            event.preventDefault();
            handleAjax('oc_delete_all_thumbnails', ocMediaTools.strings.confirm_delete);
        });

        $bar.on('click', '[data-action="oc_regenerate_thumbnails"]', function(event) {
            event.preventDefault();
            handleAjax('oc_regenerate_thumbnails', ocMediaTools.strings.confirm_regen);
        });
    });
})(jQuery);

