<?php
get_header();

$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
$month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('n'));

if ($year < 2020 || $year > 2030) {
    $year = intval(date('Y'));
}
if ($month < 1 || $month > 12) {
    $month = intval(date('n'));
}
?>

<div class="vqh-calendar-page">
    <div class="vqh-calendar-layout">
        <main class="vqh-calendar-main">
            <header class="vqh-calendar-hero">
                <h1>Calendario de eventos</h1>
                <p>Descubre conciertos, teatro, exposiciones y actividades para cada día del mes.</p>
            </header>

            <div class="vqh-calendar-widget-wrap">
                <?php echo vqh_render_global_calendar($year, $month); ?>
            </div>

            <section class="vqh-calendar-events">
                <h2 class="vqh-section-title">Próximos eventos</h2>
                <?php
                $today_date = date('Y-m-d');
                $args = array(
                    'post_type' => 'listado',
                    'post_status' => 'publish',
                    'posts_per_page' => 12,
                    'orderby' => 'date',
                    'order' => 'ASC',
                    'meta_query' => array(
                        'relation' => 'OR',
                        array('key' => 'st_date', 'value' => $today_date, 'compare' => '>=', 'type' => 'DATE'),
                        array('key' => 'event_start_date', 'value' => $today_date, 'compare' => '>=', 'type' => 'DATE'),
                    ),
                    'ignore_sticky_posts' => true,
                );

                $events_query = new WP_Query($args);
                if ($events_query->have_posts()) :
                    echo '<div class="vqh-featured-grid">';
                    while ($events_query->have_posts()) :
                        $events_query->the_post();
                        get_template_part('template-parts/event', 'card');
                    endwhile;
                    echo '</div>';
                    wp_reset_postdata();
                else :
                    echo '<p class="vqh-no-events">No hay eventos programados para este periodo.</p>';
                endif;
                ?>
            </section>
        </main>

        <aside class="vqh-calendar-ad">
            <div class="ad-box">Publicidad</div>
        </aside>
    </div>
</div>

<?php get_footer(); ?>