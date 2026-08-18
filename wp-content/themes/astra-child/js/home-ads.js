(function () {
    'use strict';

    var resizeTimer = null;

    function createHorizontalAdSlot() {
        var wrapper = document.createElement('div');
        wrapper.className = 'vqh-ad-slot vqh-ad-slot-horizontal';

        wrapper.innerHTML = '' +
            '<span class="vqh-ad-label">Publicidad</span>' +
            '<a rel="sponsored noopener" target="_blank" href="https://www.awin1.com/cread.php?s=3868502&v=113286&q=512799&r=338721">' +
            '<img src="https://www.awin1.com/cshow.php?s=3868502&v=113286&q=512799&r=338721" alt="Publicidad MANAWA ES" />' +
            '</a>';

        return wrapper;
    }

    function createVerticalAdSlot() {
        var wrapper = document.createElement('aside');
        wrapper.className = 'vqh-ad-slot vqh-ad-slot-vertical';

        wrapper.innerHTML = '' +
            '<span class="vqh-ad-label">Publicidad</span>' +
            '<a rel="sponsored noopener" target="_blank" href="https://www.awin1.com/cread.php?s=4747775&v=52555&q=473126&r=338721">' +
            '<img src="https://www.awin1.com/cshow.php?s=4747775&v=52555&q=473126&r=338721" alt="Publicidad VIAJES CARREFOUR" />' +
            '</a>';

        return wrapper;
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function createCategoriesSlot(categories) {
        if (!Array.isArray(categories) || !categories.length) {
            return null;
        }

        var wrapper = document.createElement('aside');
        wrapper.className = 'vqh-home-categories';

        var html = '<h3>Categorias</h3><ul class="vqh-home-categories-list">';

        categories.forEach(function (parent) {
            var hasChildren = Array.isArray(parent.children) && parent.children.length > 0;
            html += '<li' + (hasChildren ? ' class="vqh-home-categories-parent"' : '') + '>';
            html += '<a href="' + escapeHtml(parent.url) + '">' +
                escapeHtml(parent.name) +
                ' <span class="vqh-cat-count">(' + Number(parent.count || 0) + ')</span></a>';

            if (hasChildren) {
                html += '<ul>';
                parent.children.forEach(function (child) {
                    html += '<li><a href="' + escapeHtml(child.url) + '">' +
                        escapeHtml(child.name) +
                        ' <span class="vqh-cat-count">(' + Number(child.count || 0) + ')</span></a></li>';
                });
                html += '</ul>';
            }

            html += '</li>';
        });

        html += '</ul>';
        wrapper.innerHTML = html;

        return wrapper;
    }

    function clearInjectedAds(feed) {
        if (!feed) {
            return;
        }

        var injected = feed.querySelectorAll('.vqh-ad-slot-horizontal, .vqh-ad-slot-vertical, .vqh-home-categories');
        Array.prototype.forEach.call(injected, function (node) {
            node.remove();
        });
    }

    function buildRowsFromCards(cards) {
        var rows = [];
        var tolerance = 6;

        cards.forEach(function (card) {
            var top = Math.round(card.getBoundingClientRect().top);
            var row = null;

            for (var i = 0; i < rows.length; i++) {
                if (Math.abs(rows[i].top - top) <= tolerance) {
                    row = rows[i];
                    break;
                }
            }

            if (!row) {
                row = { top: top, items: [] };
                rows.push(row);
            }

            row.items.push(card);
        });

        rows.sort(function (a, b) {
            return a.top - b.top;
        });

        return rows;
    }

    function ensureFourColumnLayout(feed, grid) {
        var layout = feed.querySelector('.vqh-home-four-col-layout');
        var eventsCol = null;
        var adCol = null;

        if (!layout) {
            layout = document.createElement('div');
            layout.className = 'vqh-home-four-col-layout';

            eventsCol = document.createElement('div');
            eventsCol.className = 'vqh-home-events-col';

            adCol = document.createElement('div');
            adCol.className = 'vqh-home-ad-col';

            grid.parentNode.insertBefore(layout, grid);
            eventsCol.appendChild(grid);
            layout.appendChild(eventsCol);
            layout.appendChild(adCol);
        } else {
            eventsCol = layout.querySelector('.vqh-home-events-col');
            adCol = layout.querySelector('.vqh-home-ad-col');

            if (!eventsCol) {
                eventsCol = document.createElement('div');
                eventsCol.className = 'vqh-home-events-col';
                layout.appendChild(eventsCol);
            }

            if (!adCol) {
                adCol = document.createElement('div');
                adCol.className = 'vqh-home-ad-col';
                layout.appendChild(adCol);
            }

            if (grid.parentElement !== eventsCol) {
                eventsCol.appendChild(grid);
            }
        }

        return {
            layout: layout,
            eventsCol: eventsCol,
            adCol: adCol
        };
    }

    function syncSidebarStickyOffsets(adCol, verticalAdSlot, categoriesSlot) {
        if (adCol) {
            adCol.style.removeProperty('--vqh-categories-top');
        }

        if (verticalAdSlot) {
            verticalAdSlot.style.position = 'static';
            verticalAdSlot.style.top = 'auto';
        }

        if (categoriesSlot) {
            categoriesSlot.style.position = 'static';
            categoriesSlot.style.top = 'auto';
        }
    }

    function mountHomeAds() {
        if (!document.body.classList.contains('home')) {
            return;
        }

        var feed = document.querySelector('#proximos-eventos');
        var grid = feed ? feed.querySelector('.vqh-featured-grid') : null;

        if (!feed || !grid) {
            return;
        }

        clearInjectedAds(feed);

        var cards = Array.prototype.slice.call(grid.querySelectorAll('.vqh-event-card'));
        if (!cards.length) {
            return;
        }

        var columns = ensureFourColumnLayout(feed, grid);
        var rows = buildRowsFromCards(cards);
        for (var rowIndex = 1; rowIndex < rows.length; rowIndex += 2) {
            var rowItems = rows[rowIndex].items;
            var lastInRow = rowItems[rowItems.length - 1];
            lastInRow.insertAdjacentElement('afterend', createHorizontalAdSlot());
        }

        var verticalAdSlot = createVerticalAdSlot();
        columns.adCol.appendChild(verticalAdSlot);

        var categoriesData = window.vqhHomeAdsData && Array.isArray(window.vqhHomeAdsData.categories)
            ? window.vqhHomeAdsData.categories
            : [];

        var categoriesSlot = createCategoriesSlot(categoriesData);
        if (categoriesSlot) {
            columns.adCol.appendChild(categoriesSlot);
        }

        syncSidebarStickyOffsets(columns.adCol, verticalAdSlot, categoriesSlot);

        var adImage = verticalAdSlot ? verticalAdSlot.querySelector('img') : null;
        if (adImage) {
            adImage.addEventListener('load', function () {
                syncSidebarStickyOffsets(columns.adCol, verticalAdSlot, categoriesSlot);
            }, { once: true });
        }
    }

    function handleResize() {
        if (resizeTimer) {
            window.clearTimeout(resizeTimer);
        }

        resizeTimer = window.setTimeout(function () {
            mountHomeAds();
        }, 180);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mountHomeAds);
    } else {
        mountHomeAds();
    }

    window.addEventListener('load', mountHomeAds);
    window.addEventListener('resize', handleResize);
})();
