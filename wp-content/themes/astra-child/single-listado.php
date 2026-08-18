<?php get_header(); ?>
<?php
// 🔒 SOLUCIÓN DE SEGURIDAD: Forzar carga del post correcto según la URL
global $post;
$current_url = $_SERVER['REQUEST_URI'];
// Intentar obtener el post por su slug si la URL lo contiene
if (isset($post) && $post->post_type === 'listado') {
    // Verificar que el ID coincida con lo que WordPress cree que es
    $correct_post = get_page_by_path(basename(trim($current_url, '/')), OBJECT, 'listado');
    if ($correct_post && $correct_post->ID !== $post->ID) {
        setup_postdata($correct_post);
        $post = $correct_post; // Forzar la global
    }
}
?>
<?php if (current_user_can('manage_options')): ?>
    <div style="background:#0f0; color:#000; padding:10px; margin:10px; border:2px solid #000; font-weight:bold;">
        ✅ USANDO single-listado.php - DOS COLUMNAS
    </div>
<?php endif; ?>

<div class="ast-container vqh-single-event">
    <main class="vqh-single-event-main">
        <?php
        $city_slug = get_query_var('city_slug');
        $city_name = '';
        if (!empty($city_slug)) {
            global $wpdb;
            $city_name = $wpdb->get_var($wpdb->prepare(
                "SELECT cityname FROM {$wpdb->prefix}multicity WHERE city_slug = %s LIMIT 1",
                $city_slug
            ));
        }

        $post_id = get_the_ID();
        $event_schedule_type = function_exists('vqh_get_event_schedule_type') ? vqh_get_event_schedule_type($post_id) : get_post_meta($post_id, 'event_schedule_type', true);
        $event_schedule_type = $event_schedule_type ? $event_schedule_type : 'unique';
        $recurrence_weekdays_raw = get_post_meta($post_id, 'recurrence_weekdays', true);
        $recurrence_weekdays = function_exists('vqh_parse_event_weekdays') ? vqh_parse_event_weekdays($recurrence_weekdays_raw) : array();
        $recurrence_weekday_labels = function_exists('vqh_get_event_weekday_labels') ? vqh_get_event_weekday_labels($recurrence_weekdays) : array();

        $start_date = function_exists('vqh_get_event_start_date') ? vqh_get_event_start_date($post_id) : get_post_meta($post_id, 'st_date', true);
        $end_date = function_exists('vqh_get_event_end_date') ? vqh_get_event_end_date($post_id) : get_post_meta($post_id, 'end_date', true);
        $start_time = function_exists('vqh_get_event_start_time') ? vqh_get_event_start_time($post_id) : get_post_meta($post_id, 'st_time', true);
        $end_time = function_exists('vqh_get_event_end_time') ? vqh_get_event_end_time($post_id) : get_post_meta($post_id, 'end_time', true);

        $fecha_param = isset($_GET['fecha']) ? sanitize_text_field(wp_unslash($_GET['fecha'])) : '';
        $event_occurrences = array();
        $selected_occurrence = '';
        $selected_occurrence_invalid = false;

        if ($event_schedule_type === 'recurring' && !empty($recurrence_weekdays) && !empty($start_date) && !empty($end_date)) {
            $event_occurrences = function_exists('vqh_get_event_occurrences') ? vqh_get_event_occurrences($start_date, $end_date, $recurrence_weekdays, 16) : array();
            if (!empty($fecha_param) && function_exists('vqh_get_event_selected_date_from_param')) {
                $selected_occurrence = vqh_get_event_selected_date_from_param($fecha_param, $event_occurrences);
                if (empty($selected_occurrence)) {
                    $selected_occurrence_invalid = true;
                }
            }

            if (empty($selected_occurrence) && function_exists('vqh_get_event_next_occurrence')) {
                $selected_occurrence = vqh_get_event_next_occurrence($event_occurrences);
            }
        }

        $share_permalink = get_permalink($post_id);
        $share_title = get_the_title($post_id);
        $share_text = $share_title . ' - ' . $share_permalink;
        $share_links = array(
            'whatsapp' => 'https://wa.me/?text=' . rawurlencode($share_text),
            'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($share_permalink),
            'twitter' => 'https://twitter.com/intent/tweet?url=' . rawurlencode($share_permalink) . '&text=' . rawurlencode($share_title),
            'email' => 'mailto:?subject=' . rawurlencode($share_title) . '&body=' . rawurlencode($share_text),
        );

        $vqh_render_share_block = function ($position = 'bottom') use ($share_links) {
            $wrapper_class = $position === 'top' ? 'vqh-event-share-widget vqh-event-share-widget--top' : 'vqh-event-share-widget vqh-event-share-widget--bottom';
            $title_text = $position === 'top' ? '🔗 Comparte este evento' : '🔗 Compartir';
        ?>
            <div class="<?php echo esc_attr($wrapper_class); ?>">
                <h2 class="vqh-widget-title"><?php echo esc_html($title_text); ?></h2>
                <div class="vqh-share-buttons" aria-label="Botonera para compartir evento">
                    <a href="<?php echo esc_url($share_links['whatsapp']); ?>" target="_blank" rel="noopener" class="vqh-share-btn vqh-share-whatsapp" data-share-network="whatsapp" data-share-position="<?php echo esc_attr($position); ?>" aria-label="Compartir en WhatsApp" title="Compartir en WhatsApp">
                        <span class="dashicons dashicons-whatsapp" aria-hidden="true"></span>
                    </a>
                    <a href="<?php echo esc_url($share_links['facebook']); ?>" target="_blank" rel="noopener" class="vqh-share-btn vqh-share-facebook" data-share-network="facebook" data-share-position="<?php echo esc_attr($position); ?>" aria-label="Compartir en Facebook" title="Compartir en Facebook">
                        <span class="dashicons dashicons-facebook" aria-hidden="true"></span>
                    </a>
                    <a href="<?php echo esc_url($share_links['twitter']); ?>" target="_blank" rel="noopener" class="vqh-share-btn vqh-share-twitter" data-share-network="twitter" data-share-position="<?php echo esc_attr($position); ?>" aria-label="Compartir en X" title="Compartir en X">
                        <span class="dashicons dashicons-twitter" aria-hidden="true"></span>
                    </a>
                    <a href="<?php echo esc_url($share_links['email']); ?>" class="vqh-share-btn vqh-share-email" data-share-network="email" data-share-position="<?php echo esc_attr($position); ?>" aria-label="Compartir por email" title="Compartir por email">
                        <span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
                    </a>
                </div>
            </div>
        <?php
        };
        ?>

        <!-- 1. BREADCRUMB (USAR FUNCIÓN CENTRALIZADA) -->
        <?php
        if (function_exists('vqh_render_breadcrumb')) {
            vqh_render_breadcrumb();
        }
        ?>

        <!-- 2. TÍTULO Y CATEGORÍAS -->
        <header class="vqh-event-single-header">
            <h1 class="vqh-event-single-title"><?php the_title(); ?></h1>
            <?php
            $categories = get_the_terms(get_the_ID(), 'ecategory');
            if ($categories && !is_wp_error($categories)):
            ?>
                <div class="vqh-event-categories">
                    <?php foreach ($categories as $category): ?>
                        <span class="vqh-category-tag">
                            <a href="<?php echo esc_url(get_term_link($category)); ?>">
                                <?php echo esc_html($category->name); ?>
                            </a>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </header>

        <?php $vqh_render_share_block('top'); ?>

        <!-- 3. IMAGEN DESTACADA (FULL WIDTH) -->
        <?php if (has_post_thumbnail()): ?>
            <div class="vqh-event-featured-image-full">
                <?php the_post_thumbnail('large'); ?>
            </div>
        <?php endif; ?>

        <!-- 4. FECHA Y HORA (2 COLUMNAS / RECURRENCIAS) -->
        <div class="vqh-event-datetime-widget">
            <h2 class="vqh-widget-title">📅 Fecha y Hora</h2>
            <?php if ($event_schedule_type === 'recurring' && !empty($event_occurrences)): ?>
                <div class="vqh-event-recurring-summary">
                    <p>Este evento se repite <strong><?php echo esc_html(implode(', ', $recurrence_weekday_labels)); ?></strong> desde <strong><?php echo esc_html(date_i18n('d/m/Y', strtotime($start_date))); ?></strong> hasta <strong><?php echo esc_html(date_i18n('d/m/Y', strtotime($end_date))); ?></strong>.</p>
                    <?php if (!empty($selected_occurrence)): ?>
                        <p>Función seleccionada: <strong><?php echo esc_html(date_i18n('d/m/Y', strtotime($selected_occurrence))); ?></strong></p>
                    <?php endif; ?>
                    <?php if ($selected_occurrence_invalid): ?>
                        <p class="vqh-recurring-warning">La fecha indicada no está disponible para este horario. Se muestra la próxima función disponible.</p>
                    <?php endif; ?>
                    <?php if ($start_time): ?>
                        <p>Hora: <strong><?php echo esc_html($start_time); ?></strong><?php if ($end_time) echo ' – <strong>' . esc_html($end_time) . '</strong>'; ?></p>
                    <?php endif; ?>
                </div>
                <div class="vqh-event-occurrences-grid">
                    <?php foreach ($event_occurrences as $occurrence): ?>
                        <a class="vqh-event-occurrence-link <?php echo $occurrence === $selected_occurrence ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('fecha', $occurrence, get_permalink())); ?>">
                            <?php echo esc_html(date_i18n('D, d/m/Y', strtotime($occurrence))); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="vqh-datetime-grid">
                    <!-- Columna 1: Inicio -->
                    <div class="vqh-datetime-column">
                        <?php if ($start_date): ?>
                            <div class="vqh-datetime-row">
                                <span class="dashicons dashicons-calendar-alt"></span>
                                <div class="vqh-datetime-info">
                                    <span class="vqh-datetime-label">Fecha de inicio</span>
                                    <span class="vqh-datetime-value"><?php echo esc_html(date_i18n('d/m/Y', strtotime($start_date))); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($start_time): ?>
                            <div class="vqh-datetime-row">
                                <span class="dashicons dashicons-clock"></span>
                                <div class="vqh-datetime-info">
                                    <span class="vqh-datetime-label">Hora de inicio</span>
                                    <span class="vqh-datetime-value"><?php echo esc_html($start_time); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Columna 2: Fin -->
                    <div class="vqh-datetime-column">
                        <?php if ($end_date): ?>
                            <div class="vqh-datetime-row">
                                <span class="dashicons dashicons-calendar-alt"></span>
                                <div class="vqh-datetime-info">
                                    <span class="vqh-datetime-label">Fecha de fin</span>
                                    <span class="vqh-datetime-value"><?php echo esc_html(date_i18n('d/m/Y', strtotime($end_date))); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($end_time): ?>
                            <div class="vqh-datetime-row">
                                <span class="dashicons dashicons-clock"></span>
                                <div class="vqh-datetime-info">
                                    <span class="vqh-datetime-label">Hora de fin</span>
                                    <span class="vqh-datetime-value"><?php echo esc_html($end_time); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- 5. DESCRIPCIÓN -->
        <div class="vqh-event-description-widget">
            <h2 class="vqh-widget-title">📝 Descripción</h2>
            <div class="vqh-event-content-body">
                <?php the_content(); ?>
            </div>
        </div>

        <!-- 6. UBICACIÓN -->
        <?php
        $address = get_post_meta(get_the_ID(), 'address', true);
        $latitude = get_post_meta(get_the_ID(), 'geo_latitude', true);
        $longitude = get_post_meta(get_the_ID(), 'geo_longitude', true);
        $map_query = '';

        if (!empty($latitude) && !empty($longitude)) {
            $map_query = trim($latitude) . ',' . trim($longitude);
        } elseif (!empty($address)) {
            $map_query = preg_replace('/\s+/', ' ', wp_strip_all_tags($address));
        }

        $map_embed_url = !empty($map_query) ? 'https://www.google.com/maps?q=' . rawurlencode($map_query) . '&z=15&output=embed' : '';
        $map_open_url = !empty($map_query) ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($map_query) : '';

        if ($address || ($latitude && $longitude)):
        ?>
            <div class="vqh-event-location-widget">
                <h2 class="vqh-widget-title">📍 Ubicación</h2>
                <?php if ($address): ?>
                    <p class="vqh-location-address">
                        <span class="dashicons dashicons-location"></span>
                        <?php echo nl2br(esc_html($address)); ?>
                    </p>
                <?php endif; ?>

                <?php if (!empty($map_embed_url)): ?>
                    <div class="vqh-event-map-wrap" data-map-lazy>
                        <div class="vqh-event-map-placeholder">
                            <p>Pulsa para cargar el mapa interactivo de este evento.</p>
                            <button type="button" class="vqh-map-load-btn" data-map-load data-map-action="load_map">
                                Mostrar mapa
                            </button>
                        </div>
                        <iframe
                            class="vqh-event-map-embed"
                            data-map-src="<?php echo esc_url($map_embed_url); ?>"
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            allowfullscreen
                            title="Mapa del evento"></iframe>
                        <noscript>
                            <iframe
                                class="vqh-event-map-embed is-noscript"
                                src="<?php echo esc_url($map_embed_url); ?>"
                                loading="lazy"
                                referrerpolicy="no-referrer-when-downgrade"
                                allowfullscreen
                                title="Mapa del evento"></iframe>
                        </noscript>
                    </div>
                <?php endif; ?>

                <?php if (!empty($map_open_url)): ?>
                    <div class="vqh-map-actions">
                        <p class="vqh-map-link">
                            <a href="<?php echo esc_url($map_open_url); ?>" target="_blank" rel="noopener" data-map-action="open_google_maps">
                                Ver en Google Maps →
                            </a>
                        </p>
                        <p class="vqh-map-link">
                            <a href="<?php echo esc_url($map_open_url); ?>&travelmode=driving" target="_blank" rel="noopener" data-map-action="get_directions">
                                Cómo llegar →
                            </a>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- 7. COMPARTIR -->
        <?php $vqh_render_share_block('bottom'); ?>
    </main>

    <aside class="vqh-single-event-sidebar" aria-label="Publicidad y categorias">
        <div class="vqh-ad-slot vqh-ad-slot-vertical">
            <span class="vqh-ad-label">Publicidad</span>
            <a rel="sponsored noopener" target="_blank" href="https://www.awin1.com/cread.php?s=4747775&v=52555&q=473126&r=338721">
                <img src="https://www.awin1.com/cshow.php?s=4747775&v=52555&q=473126&r=338721" alt="Publicidad VIAJES CARREFOUR" />
            </a>
        </div>

        <?php $single_categories = function_exists('vqh_get_home_ads_categories_data') ? vqh_get_home_ads_categories_data() : array(); ?>
        <?php if (!empty($single_categories)): ?>
            <aside class="vqh-home-categories" aria-label="Categorias de eventos">
                <h3>Categorias</h3>
                <ul class="vqh-home-categories-list">
                    <?php foreach ($single_categories as $parent): ?>
                        <?php $has_children = !empty($parent['children']) && is_array($parent['children']); ?>
                        <li<?php echo $has_children ? ' class="vqh-home-categories-parent"' : ''; ?>>
                            <a href="<?php echo esc_url($parent['url']); ?>">
                                <?php echo esc_html($parent['name']); ?>
                                <span class="vqh-cat-count">(<?php echo (int) $parent['count']; ?>)</span>
                            </a>

                            <?php if ($has_children): ?>
                                <ul>
                                    <?php foreach ($parent['children'] as $child): ?>
                                        <li>
                                            <a href="<?php echo esc_url($child['url']); ?>">
                                                <?php echo esc_html($child['name']); ?>
                                                <span class="vqh-cat-count">(<?php echo (int) $child['count']; ?>)</span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                </ul>
            </aside>
        <?php endif; ?>
    </aside>

</div>

<?php
$schema_start_date = $selected_occurrence ? $selected_occurrence : $start_date;
$schema_end_date = $selected_occurrence ? $selected_occurrence : $start_date;
$schema = array(
    '@context' => 'https://schema.org',
    '@type' => 'Event',
    'name' => get_the_title(),
    'url' => get_permalink(),
    'description' => wp_strip_all_tags(get_the_excerpt() ? get_the_excerpt() : get_the_content()),
    'startDate' => function_exists('vqh_format_iso_datetime') ? vqh_format_iso_datetime($schema_start_date, $start_time) : '',
    'endDate' => function_exists('vqh_format_iso_datetime') ? vqh_format_iso_datetime($schema_end_date, $end_time) : '',
);

if ($event_schedule_type === 'recurring' && !empty($recurrence_weekdays) && !empty($end_date)) {
    $rrule_days = function_exists('vqh_get_event_weekday_rrule_days') ? vqh_get_event_weekday_rrule_days($recurrence_weekdays) : array();
    if (!empty($rrule_days)) {
        $schema['recurrenceRule'] = 'RRULE:FREQ=WEEKLY;BYDAY=' . implode(',', $rrule_days) . ';UNTIL=' . date('Ymd', strtotime($end_date));
    }
}
?>
<script type="application/ld+json">
    <?php echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); ?>
</script>

<?php get_footer(); ?>