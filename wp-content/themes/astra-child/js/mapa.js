(function () {
    'use strict';

    function formatDate(dateValue) {
        if (!dateValue) {
            return '';
        }

        var parts = dateValue.split('-');
        if (parts.length !== 3) {
            return dateValue;
        }

        return parts[2] + '/' + parts[1] + '/' + parts[0];
    }

    function escapeHtml(value) {
        var element = document.createElement('div');
        element.textContent = value || '';
        return element.innerHTML;
    }

    function initMap() {
        var mapElement = document.getElementById('vqh-events-map');
        if (!mapElement || !window.L || !window.vqhMapData || !window.vqhMapData.cities.length) {
            return;
        }

        var map = L.map(mapElement, {
            scrollWheelZoom: false,
            minZoom: 5,
            maxZoom: 13
        }).setView([40.2, -3.7], 5.5);

        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(map);

        var bounds = [];
        window.vqhMapData.cities.forEach(function (city) {
            var position = [Number(city.latitude), Number(city.longitude)];
            bounds.push(position);

            var eventLinks = city.items.map(function (event) {
                var approximateLabel = event.approximate ? '<small>Ubicación aproximada en la ciudad</small>' : '';
                return '<li><a href="' + event.url + '">' + escapeHtml(event.title) + '</a>' +
                    '<span>' + formatDate(event.date) + (event.time ? ' · ' + escapeHtml(event.time) : '') + '</span>' +
                    approximateLabel + '</li>';
            }).join('');

            var popup = '<div class="vqh-map-popup"><strong>' + escapeHtml(city.city_name) + '</strong>' +
                '<span>' + city.items.length + (city.items.length === 1 ? ' evento próximo' : ' eventos próximos') + '</span>' +
                '<ul>' + eventLinks + '</ul></div>';

            L.marker(position).addTo(map).bindPopup(popup);
        });

        if (bounds.length > 1) {
            map.fitBounds(bounds, { padding: [32, 32], maxZoom: 8 });
        } else {
            map.setView(bounds[0], 10);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMap);
    } else {
        initMap();
    }
}());
