(function () {
    'use strict';

    var EVENT_NAME = 'vqh_share_click';
    var PIXEL_EVENT_NAME = 'VQHShareClick';

    function pushToDataLayer(payload) {
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push(payload);
    }

    function trackInGtag(payload) {
        if (typeof window.gtag !== 'function') {
            return;
        }

        window.gtag('event', 'share', {
            method: payload.share_channel,
            content_type: payload.content_type,
            item_id: payload.item_id,
            event_label: payload.share_position,
            page_location: payload.page_location,
        });

        window.gtag('event', EVENT_NAME, {
            share_channel: payload.share_channel,
            share_position: payload.share_position,
            content_type: payload.content_type,
            item_id: payload.item_id,
            item_title: payload.item_title,
            page_location: payload.page_location,
            page_path: payload.page_path,
            city_slug: payload.city_slug,
            primary_category: payload.primary_category,
            share_target_url: payload.share_target_url,
        });
    }

    function trackInMetaPixel(payload) {
        if (typeof window.fbq !== 'function') {
            return;
        }

        window.fbq('trackCustom', PIXEL_EVENT_NAME, {
            share_channel: payload.share_channel,
            share_position: payload.share_position,
            item_id: payload.item_id,
            item_title: payload.item_title,
            page_location: payload.page_location,
            city_slug: payload.city_slug,
            primary_category: payload.primary_category,
        });
    }

    function getNetworkFromButton(button) {
        if (button.dataset && button.dataset.shareNetwork) {
            return button.dataset.shareNetwork;
        }

        if (button.classList.contains('vqh-share-whatsapp')) {
            return 'whatsapp';
        }
        if (button.classList.contains('vqh-share-facebook')) {
            return 'facebook';
        }
        if (button.classList.contains('vqh-share-twitter')) {
            return 'twitter';
        }
        if (button.classList.contains('vqh-share-email')) {
            return 'email';
        }

        return 'unknown';
    }

    function getPositionFromButton(button) {
        if (button.dataset && button.dataset.sharePosition) {
            return button.dataset.sharePosition;
        }

        var widget = button.closest('.vqh-event-share-widget');
        if (!widget) {
            return 'unknown';
        }

        if (widget.classList.contains('vqh-event-share-widget--top')) {
            return 'top';
        }

        if (widget.classList.contains('vqh-event-share-widget--bottom')) {
            return 'bottom';
        }

        return 'unknown';
    }

    function onShareClick(event) {
        var button = event.target.closest('.vqh-share-btn');
        if (!button) {
            return;
        }

        var trackingData = window.vqhShareTrackingData || {};
        var shareChannel = getNetworkFromButton(button);
        var sharePosition = getPositionFromButton(button);
        var payload = {
            event: EVENT_NAME,
            share_channel: shareChannel,
            share_position: sharePosition,
            item_id: String(trackingData.postId || ''),
            item_title: trackingData.title || document.title || '',
            page_location: trackingData.url || window.location.href,
            page_path: window.location.pathname || '',
            content_type: trackingData.postType || 'listado',
            city_slug: trackingData.citySlug || '',
            primary_category: trackingData.primaryCategory || '',
            share_target_url: button.getAttribute('href') || '',
            timestamp: new Date().toISOString(),
        };

        pushToDataLayer(payload);
        trackInGtag(payload);
        trackInMetaPixel(payload);
    }

    document.addEventListener('click', onShareClick);
})();
