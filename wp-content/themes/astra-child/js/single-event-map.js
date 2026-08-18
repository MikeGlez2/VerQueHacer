(function () {
    'use strict';

    var MAP_EVENT_NAME = 'vqh_map_interaction';
    var MAP_PIXEL_EVENT_NAME = 'VQHMapInteraction';

    function pushToDataLayer(payload) {
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push(payload);
    }

    function trackInGtag(payload) {
        if (typeof window.gtag !== 'function') {
            return;
        }

        window.gtag('event', MAP_EVENT_NAME, {
            map_action: payload.map_action,
            map_provider: payload.map_provider,
            content_type: payload.content_type,
            item_id: payload.item_id,
            item_title: payload.item_title,
            page_location: payload.page_location,
            page_path: payload.page_path,
            city_slug: payload.city_slug,
            primary_category: payload.primary_category,
            map_target_url: payload.map_target_url,
        });
    }

    function trackInMetaPixel(payload) {
        if (typeof window.fbq !== 'function') {
            return;
        }

        window.fbq('trackCustom', MAP_PIXEL_EVENT_NAME, {
            map_action: payload.map_action,
            map_provider: payload.map_provider,
            item_id: payload.item_id,
            item_title: payload.item_title,
            city_slug: payload.city_slug,
            primary_category: payload.primary_category,
            map_target_url: payload.map_target_url,
            page_location: payload.page_location,
        });
    }

    function trackMapInteraction(action, targetUrl) {
        var trackingData = window.vqhMapTrackingData || {};
        var payload = {
            event: MAP_EVENT_NAME,
            map_action: action || 'unknown',
            map_provider: 'google_maps',
            item_id: String(trackingData.postId || ''),
            item_title: trackingData.title || document.title || '',
            page_location: trackingData.url || window.location.href,
            page_path: window.location.pathname || '',
            content_type: trackingData.postType || 'listado',
            city_slug: trackingData.citySlug || '',
            primary_category: trackingData.primaryCategory || '',
            map_target_url: targetUrl || '',
            timestamp: new Date().toISOString(),
        };

        pushToDataLayer(payload);
        trackInGtag(payload);
        trackInMetaPixel(payload);
    }

    function loadMap(wrap) {
        if (!wrap || wrap.classList.contains('is-map-loaded')) {
            return false;
        }

        var iframe = wrap.querySelector('.vqh-event-map-embed');
        if (!iframe) {
            return false;
        }

        var mapSrc = iframe.getAttribute('data-map-src');
        if (!mapSrc) {
            return false;
        }

        iframe.setAttribute('src', mapSrc);
        wrap.classList.add('is-map-loaded');
        return true;
    }

    function initMapWrap(wrap) {
        if (!wrap) {
            return;
        }

        var button = wrap.querySelector('[data-map-load]');
        if (!button) {
            return;
        }

        button.addEventListener('click', function () {
            var loaded = loadMap(wrap);
            if (loaded) {
                var iframe = wrap.querySelector('.vqh-event-map-embed');
                trackMapInteraction('load_map', iframe ? iframe.getAttribute('src') || iframe.getAttribute('data-map-src') || '' : '');
            }
        });
    }

    function initMapActionLinks() {
        var links = document.querySelectorAll('[data-map-action]');
        Array.prototype.forEach.call(links, function (link) {
            if (link.tagName !== 'A') {
                return;
            }

            link.addEventListener('click', function () {
                trackMapInteraction(link.getAttribute('data-map-action') || 'unknown', link.getAttribute('href') || '');
            });
        });
    }

    function init() {
        var wraps = document.querySelectorAll('[data-map-lazy]');
        Array.prototype.forEach.call(wraps, initMapWrap);
        initMapActionLinks();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
