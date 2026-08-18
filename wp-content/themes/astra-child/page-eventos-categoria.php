<?php
get_header();

$category_slug = sanitize_title((string) get_query_var('vqh_category_slug'));
$categories = vqh_get_footer_event_categories();
$category = isset($categories[$category_slug]) ? $categories[$category_slug] : null;
$events = $category ? vqh_get_category_directory_events($category_slug) : array();
?>

<div class="vqh-map-page vqh-category-page">
    <main class="vqh-map-main">
        <?php if ($category) : ?>
            <header class="vqh-map-hero">
                <div>
                    <span class="vqh-map-kicker">Agenda por categoría</span>
                    <h1>Eventos de <?php echo esc_html($category['label']); ?></h1>
                    <p>Descubre los próximos eventos de <?php echo esc_html(strtolower($category['label'])); ?> seleccionados para nuestra agenda.</p>
                </div>
                <div class="vqh-map-stats" aria-label="Resumen de eventos">
                    <strong><?php echo esc_html(count($events)); ?></strong>
                    <span>eventos próximos</span>
                </div>
            </header>

            <section class="vqh-map-directory" aria-labelledby="vqh-category-events-title">
                <div class="vqh-map-section-heading">
                    <div>
                        <span class="vqh-map-kicker"><?php echo esc_html($category['label']); ?></span>
                        <h2 id="vqh-category-events-title">Próximos eventos</h2>
                    </div>
                    <span><?php echo esc_html(count($events)); ?> eventos</span>
                </div>

                <?php if (!empty($events)) : ?>
                    <div class="vqh-map-city-grid">
                        <article class="vqh-map-city-card">
                            <div class="vqh-map-city-card-heading">
                                <h3><?php echo esc_html($category['label']); ?></h3>
                                <span><?php echo esc_html(count($events)); ?></span>
                            </div>
                            <ul>
                                <?php foreach ($events as $event) : ?>
                                    <li>
                                        <a href="<?php echo esc_url($event['url']); ?>">
                                            <?php echo esc_html($event['title']); ?>
                                        </a>
                                        <?php if (!empty($event['date'])) : ?>
                                            <time datetime="<?php echo esc_attr($event['date']); ?>">
                                                <?php echo esc_html(date_i18n('d M Y', strtotime($event['date']))); ?>
                                                <?php if (!empty($event['time'])) : ?>
                                                    · <?php echo esc_html($event['time']); ?>
                                                <?php endif; ?>
                                            </time>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </article>
                    </div>
                <?php else : ?>
                    <p class="vqh-map-empty">No hay eventos próximos en esta categoría.</p>
                <?php endif; ?>
            </section>
        <?php else : ?>
            <p class="vqh-map-empty">La categoría solicitada no está disponible.</p>
        <?php endif; ?>
    </main>
</div>

<?php get_footer(); ?>