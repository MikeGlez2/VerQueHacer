<?php
get_header();

$map_cities = vqh_get_map_events();
$total_events = 0;
foreach ($map_cities as $map_city) {
    $total_events += count($map_city['items']);
}
?>

<div class="vqh-map-page">
    <main class="vqh-map-main">
        <header class="vqh-map-hero">
            <div>
                <span class="vqh-map-kicker">Agenda nacional</span>
                <h1>Mapa de eventos en España</h1>
                <p>Explora los eventos que se celebran desde hoy en distintas ciudades de España.</p>
            </div>
            <div class="vqh-map-stats" aria-label="Resumen de eventos">
                <strong><?php echo esc_html($total_events); ?></strong>
                <span>eventos próximos</span>
            </div>
        </header>

        <?php if (!empty($map_cities)) : ?>
            <section class="vqh-map-panel" aria-label="Mapa interactivo de eventos">
                <div id="vqh-events-map" class="vqh-events-map"></div>
                <p class="vqh-map-attribution">Mapa: <a href="https://www.openstreetmap.org/copyright" rel="noopener">OpenStreetMap</a> contributors</p>
            </section>

            <section class="vqh-map-directory" aria-labelledby="vqh-map-directory-title">
                <div class="vqh-map-section-heading">
                    <div>
                        <span class="vqh-map-kicker">Por ciudades</span>
                        <h2 id="vqh-map-directory-title">Eventos próximos por ciudad</h2>
                    </div>
                    <span><?php echo esc_html(count($map_cities)); ?> ciudades</span>
                </div>

                <div class="vqh-map-city-grid">
                    <?php foreach ($map_cities as $map_city) : ?>
                        <article class="vqh-map-city-card">
                            <div class="vqh-map-city-card-heading">
                                <h3>
                                    <a href="<?php echo esc_url(home_url('/' . trim($map_city['city_slug'], '/') . '/')); ?>">
                                        <?php echo esc_html($map_city['city_name']); ?>
                                    </a>
                                </h3>
                                <span><?php echo esc_html(count($map_city['items'])); ?></span>
                            </div>
                            <ul>
                                <?php foreach ($map_city['items'] as $map_event) : ?>
                                    <li>
                                        <a href="<?php echo esc_url($map_event['url']); ?>">
                                            <?php echo esc_html($map_event['title']); ?>
                                        </a>
                                        <time datetime="<?php echo esc_attr($map_event['date']); ?>">
                                            <?php echo esc_html(date_i18n('d M Y', strtotime($map_event['date']))); ?>
                                            <?php if (!empty($map_event['time'])) : ?>
                                                · <?php echo esc_html($map_event['time']); ?>
                                            <?php endif; ?>
                                        </time>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php else : ?>
            <p class="vqh-map-empty">No hay eventos próximos con ciudad disponible.</p>
        <?php endif; ?>
    </main>
</div>

<?php get_footer(); ?>