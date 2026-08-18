/**
 * Geolocalización de Eventos - VerQueHacer
 */
(function() {
    'use strict';

    if (window.vqhGeoInitialized) {
        return;
    }
    window.vqhGeoInitialized = true;

    const CONFIG = {
        debug: false,
        cookieName: 'vqh_city_selected',
        dismissCookieName: 'vqh_city_dismissed',
        cookieDays: 30,
        apiUrl: window.vqhGeolocationData && window.vqhGeolocationData.ajaxUrl ? window.vqhGeolocationData.ajaxUrl : '/wp-admin/admin-ajax.php',
        homeUrl: window.vqhGeolocationData && window.vqhGeolocationData.homeUrl ? window.vqhGeolocationData.homeUrl : '/',
        bannerId: 'vqh-geo-banner',
        loadingId: 'vqh-geo-loading',
        ipFallbackUrl: 'https://ipapi.co/json/',
        logoUrl: '/wp-content/themes/astra-child/assets/logo-vqh.svg'
    };

    function log(msg) {
        if (CONFIG.debug) {
            console.log('[VQH Geo]', msg);
        }
    }

    function setCookie(name, value, days) {
        const expires = new Date(Date.now() + days * 864e5).toUTCString();
        document.cookie = name + '=' + encodeURIComponent(value) + '; expires=' + expires + '; path=/';
    }

    function deleteCookie(name) {
        document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
    }

    function getCookie(name) {
        const value = '; ' + document.cookie;
        const parts = value.split('; ' + name + '=');
        if (parts.length === 2) {
            return decodeURIComponent(parts.pop().split(';').shift());
        }
        return null;
    }

    function hasCitySelected() {
        const city = getCookie(CONFIG.cookieName);
        return city !== null && city !== 'home';
    }

    function hasDismissedBanner() {
        return getCookie(CONFIG.dismissCookieName) !== null;
    }

    function normalizeBaseUrl() {
        return CONFIG.homeUrl.replace(/\/+$/, '');
    }

    function degreesToRadians(degrees) {
        return degrees * Math.PI / 180;
    }

    function distanceKm(lat1, lng1, lat2, lng2) {
        const earthRadiusKm = 6371;
        const dLat = degreesToRadians(lat2 - lat1);
        const dLon = degreesToRadians(lng2 - lng1);
        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(degreesToRadians(lat1)) * Math.cos(degreesToRadians(lat2)) *
            Math.sin(dLon / 2) * Math.sin(dLon / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return earthRadiusKm * c;
    }

    function getNearestCity(lat, lng, cities) {
        let bestCity = null;
        let bestDistance = Infinity;

        cities.forEach(function(city) {
            if (typeof city.lat === 'undefined' || typeof city.lng === 'undefined') {
                return;
            }
            const cityLat = parseFloat(city.lat);
            const cityLng = parseFloat(city.lng);
            if (isNaN(cityLat) || isNaN(cityLng)) {
                return;
            }
            const distance = distanceKm(lat, lng, cityLat, cityLng);
            if (distance < bestDistance) {
                bestDistance = distance;
                bestCity = city;
            }
        });

        return bestCity;
    }

    function redirectToCity(citySlug, searchTerm) {
        if (!citySlug) {
            return;
        }
        setCookie(CONFIG.cookieName, citySlug, CONFIG.cookieDays);
        const normalizedBaseUrl = normalizeBaseUrl();
        const targetUrl = new URL(normalizedBaseUrl + '/' + encodeURIComponent(citySlug) + '/', window.location.origin);
        const trimmedSearch = (searchTerm || '').trim();

        targetUrl.searchParams.set('city', citySlug);
        targetUrl.searchParams.set('city_slug', citySlug);

        if (trimmedSearch) {
            targetUrl.searchParams.set('s', trimmedSearch);
        }

        window.location.replace(targetUrl.toString());
    }

    function performSearch(term) {
        const trimmed = (term || '').trim();
        if (!trimmed) {
            alert('Introduce una palabra o frase para buscar eventos');
            return;
        }

        const url = new URL(window.location.href);
        url.searchParams.set('s', trimmed);
        window.location.assign(url.toString());
    }

    function showGeoLoading(message) {
        hideGeoLoading();
        const loading = document.createElement('div');
        loading.id = CONFIG.loadingId;
        loading.className = 'vqh-geo-loading';
        loading.innerHTML = '<div class="vqh-geo-loading-content"><span class="vqh-geo-loading-spinner"></span><p>' + message + '</p></div>';
        (document.body || document.documentElement).appendChild(loading);
    }

    function hideGeoLoading() {
        const existing = document.getElementById(CONFIG.loadingId);
        if (existing) {
            existing.remove();
        }
    }

    function lookupBrowserLocation() {
        return new Promise(function(resolve) {
            if (!navigator.geolocation) {
                log('Browser geolocation API no disponible');
                resolve(null);
                return;
            }

            navigator.geolocation.getCurrentPosition(
                function(position) {
                    log('Ubicación obtenida por navegador');
                    resolve({
                        lat: position.coords.latitude,
                        lng: position.coords.longitude
                    });
                },
                function(error) {
                    log('Geolocation navegador fallo: ' + (error.message || error.code));
                    resolve(null);
                },
                {
                    maximumAge: 300000,
                    timeout: 10000,
                    enableHighAccuracy: true
                }
            );
        });
    }

    function fetchIpLocation() {
        return fetch(CONFIG.ipFallbackUrl, { cache: 'no-cache' })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('No se pudo obtener la ubicación por IP');
                }
                return response.json();
            })
            .then(function(data) {
                const latitude = data.latitude || data.lat || null;
                const longitude = data.longitude || data.lon || data.lng || null;
                if (!latitude || !longitude) {
                    throw new Error('Respuesta IP inválida');
                }
                log('Ubicación obtenida por IP: ' + latitude + ',' + longitude);
                return {
                    lat: parseFloat(latitude),
                    lng: parseFloat(longitude)
                };
            })
            .catch(function(error) {
                log('Fallback IP fallido: ' + (error.message || error));
                return null;
            });
    }

    function detectLocation() {
        return lookupBrowserLocation().then(function(location) {
            if (location) {
                return location;
            }
            return fetchIpLocation();
        });
    }

    function isHomePath() {
        const currentPath = window.location.pathname.replace(/\/+$/, '');
        const homePath = new URL(CONFIG.homeUrl, window.location.origin).pathname.replace(/\/+$/, '');
        return currentPath === '' || currentPath === '/' || currentPath === homePath || currentPath === '/index.php';
    }

    function showCityBanner(cities) {
        hideGeoLoading();
        log('Mostrando banner: ' + cities.length + ' ciudades');

        const existing = document.getElementById(CONFIG.bannerId);
        if (existing) {
            existing.remove();
        }

        const banner = document.createElement('div');
        banner.id = CONFIG.bannerId;
        banner.className = 'vqh-geo-banner';
        banner.setAttribute('style', 'position: relative !important; top: auto !important; left: auto !important; right: auto !important; z-index: 999999 !important; display: flex !important; flex-direction: column !important; align-items: center !important; justify-content: center !important; gap: 12px !important; width: 100% !important; max-width: 100% !important; margin: 0 0 24px 0 !important; padding: 16px 32px !important; background: linear-gradient(135deg, #0f4c81 0%, #1e88e5 100%) !important; color: #fff !important; box-shadow: 0 8px 24px rgba(0,0,0,0.25) !important; border-radius: 0 0 12px 12px !important;');
        banner.innerHTML = '<div style="display: flex; gap: 20px; align-items: center; justify-content: center; flex-wrap: wrap;"><div class="vqh-geo-search-wrapper"><input type="text" placeholder="Buscar eventos..." class="vqh-geo-search-input"><button type="button" class="vqh-geo-search-btn" aria-label="Buscar"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="6"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></button></div><select id="vqh-geo-city-select" class="vqh-geo-select" style="width: 180px; padding: 8px 8px; height: 38px; box-sizing: border-box;"><option value="">-- Selecciona una ciudad --</option>' +
            cities.map(function(c) { return '<option value="' + c.slug + '">' + c.name + '</option>'; }).join('') +
            '</select></div>' +
            '<div style="display: flex; gap: 12px; justify-content: center; margin-top: 12px;"><button id="vqh-geo-confirm" class="vqh-geo-btn vqh-geo-btn-primary" style="white-space: nowrap; padding: 10px 16px;">Ver eventos</button></div>';

        const siteHeader = document.getElementById('masthead') || document.querySelector('.site-header');
        const mainContent = document.getElementById('primary') || document.body;

        if (siteHeader && siteHeader.parentNode) {
            siteHeader.insertAdjacentElement('afterend', banner);
        } else {
            mainContent.insertBefore(banner, mainContent.firstChild);
        }
        document.body.classList.add('show-banner');
        document.body.classList.add('vqh-geo-active');
        document.body.style.overflow = 'visible';
        document.body.style.overflowX = 'visible';
        document.body.style.overflowY = 'visible';
        banner.style.display = 'block';

        const searchInput = banner.querySelector('.vqh-geo-search-input');
        const searchButton = banner.querySelector('.vqh-geo-search-btn');

        searchButton.addEventListener('click', function() {
            performSearch(searchInput.value);
        });

        searchInput.addEventListener('keydown', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                performSearch(searchInput.value);
            }
        });

        document.getElementById('vqh-geo-confirm').addEventListener('click', function() {
            const select = document.getElementById('vqh-geo-city-select');
            const citySlug = select.options[select.selectedIndex].value;

            if (citySlug) {
                redirectToCity(citySlug, '');
            } else {
                alert('Por favor, selecciona una ciudad');
            }
        });
    }

    function fetchCities() {
        return new Promise(function(resolve) {
            const formData = new FormData();
            formData.append('action', 'vqh_get_all_cities');
            formData.append('_ts', String(Date.now()));
            fetch(CONFIG.apiUrl, {
                method: 'POST',
                body: formData,
                cache: 'no-store',
                headers: {
                    'Cache-Control': 'no-cache, no-store, must-revalidate',
                    'Pragma': 'no-cache'
                }
            })
                .then(function(r) { return r.json(); })
                .then(function(d) { resolve(d.success ? d.data : []); })
                .catch(function() { resolve([]); });
        });
    }

    function autoSelectNearestCity(cities) {
        showGeoLoading('Detectando tu ciudad más cercana...');

        return detectLocation().then(function(location) {
            if (!location) {
                hideGeoLoading();
                showCityBanner(cities);
                return;
            }

            const nearestCity = getNearestCity(location.lat, location.lng, cities);
            if (nearestCity && nearestCity.slug) {
                log('Ciudad más cercana: ' + nearestCity.name + ' (' + nearestCity.slug + ')');
                redirectToCity(nearestCity.slug);
                return;
            }

            hideGeoLoading();
            showCityBanner(cities);
        });
    }

    function initGeolocation() {
        log('=== INICIANDO ===');
        const urlParams = new URLSearchParams(window.location.search);
        const wantsToChange = urlParams.has('vqh_change_city') || document.body.classList.contains('show-banner');
        const isCiudadesPage = /(^|\/)ciudades-con-eventos(\/|$)/.test(window.location.pathname);
        const isHome = isHomePath();
        const isCityArchivePage = document.body && (
            document.body.classList.contains('archive') ||
            document.body.classList.contains('post-type-archive') ||
            document.body.classList.contains('post-type-archive-listado')
        );
        const forceBanner = true;

        if (!forceBanner && hasDismissedBanner()) {
            log('Banner descartado anteriormente. Saltando.');
            return;
        }

        if (!forceBanner && hasCitySelected()) {
            log('Ciudad ya seleccionada y no hay petición de cambio. Saltando.');
            return;
        }

        if (wantsToChange || isCiudadesPage || isHome || isCityArchivePage) {
            log('Forzando banner en la página actual.');
            if (urlParams.has('vqh_change_city') && window.history.replaceState) {
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        }

        fetchCities().then(function(cities) {
            if (cities.length === 0) {
                log('Sin ciudades disponibles.');
                return;
            }

            showCityBanner(cities);
        });
    }

    function boot() {
        if (document.body) {
            initGeolocation();
            return;
        }
        document.addEventListener('DOMContentLoaded', initGeolocation);
    }

    boot();
})();
