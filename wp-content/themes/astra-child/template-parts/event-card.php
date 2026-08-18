<article class="vqh-event-card" id="post-<?php the_ID(); ?>">
    <?php
    $st_date = function_exists('vqh_get_event_start_date') ? vqh_get_event_start_date(get_the_ID()) : get_post_meta(get_the_ID(), 'st_date', true);
    $st_time = function_exists('vqh_get_event_start_time') ? vqh_get_event_start_time(get_the_ID()) : get_post_meta(get_the_ID(), 'st_time', true);
    $end_time = function_exists('vqh_get_event_end_time') ? vqh_get_event_end_time(get_the_ID()) : get_post_meta(get_the_ID(), 'end_time', true);
    $event_schedule_type = function_exists('vqh_get_event_schedule_type') ? vqh_get_event_schedule_type(get_the_ID()) : get_post_meta(get_the_ID(), 'event_schedule_type', true);
    $recurrence_weekdays = vqh_parse_event_weekdays(get_post_meta(get_the_ID(), 'recurrence_weekdays', true));
    $today_date = date('Y-m-d');
    $next_occurrence = vqh_get_event_next_occurrence_for_post(get_the_ID(), 24, $today_date);
    $display_date = $next_occurrence ? $next_occurrence : $st_date;

    // Si el evento sigue activo pero su inicio fue pasado, mostramos hoy para evitar fechas antiguas.
    if (!empty($display_date) && $display_date < $today_date) {
        $end_date = function_exists('vqh_get_event_end_date') ? vqh_get_event_end_date(get_the_ID()) : get_post_meta(get_the_ID(), 'end_date', true);
        if (!empty($end_date) && $end_date >= $today_date) {
            $display_date = $today_date;
        }
    }
    $card_permalink = $display_date && $event_schedule_type === 'recurring' ? add_query_arg('fecha', $display_date, get_permalink()) : get_permalink();
    ?>
    <!-- Imagen -->
    <?php if (has_post_thumbnail()) : ?>
        <a href="<?php echo esc_url($card_permalink); ?>" class="vqh-event-card-image">
            <?php the_post_thumbnail('medium', array('loading' => 'lazy')); ?>
        </a>
    <?php else : ?>
        <div class="vqh-event-card-image vqh-no-image">
            <span class="vqh-no-image-text">Sin imagen</span>
        </div>
    <?php endif; ?>

    <!-- Contenido -->
    <div class="vqh-event-card-content">
        <!-- DEBUG CATEGORÍAS -->
        <?php
        $debug_cats = get_the_terms(get_the_ID(), 'ecategory');
        $debug_meta = get_post_meta(get_the_ID(), 'vqh_event_categories', true);
        echo '<!-- DEBUG EVENT ' . get_the_ID() . ': ecategory terms=' . ($debug_cats && !is_wp_error($debug_cats) ? count($debug_cats) : '0') . ', meta vqh_event_categories=' . (is_array($debug_meta) ? implode(',', $debug_meta) : $debug_meta) . ' -->';
        ?>

        <!-- Categoría -->
        <?php
        $categories = get_the_terms(get_the_ID(), 'ecategory');
        if ($categories && !is_wp_error($categories)) :
            $first_category = reset($categories);
        ?>
            <span class="vqh-event-category">
                <span class="dashicons dashicons-category"></span>
                <?php echo esc_html($first_category->name); ?>
            </span>
        <?php else: ?>
            <!-- DEBUG: No hay categorías o es error -->
            <?php if (is_wp_error($categories)): ?>
                <!-- ERROR: <?php echo $categories->get_error_message(); ?> -->
            <?php endif; ?>
        <?php endif; ?>

        <!-- Fecha -->
        <?php
        if ($display_date):
            $date_obj = DateTime::createFromFormat('Y-m-d', $display_date);
        ?>
            <div class="vqh-event-date">
                <span class="vqh-day"><?php echo esc_html($date_obj ? $date_obj->format('d') : ''); ?></span>
                <span class="vqh-month"><?php echo esc_html($date_obj ? $date_obj->format('M') : ''); ?></span>
            </div>
        <?php endif; ?>

        <!-- Título -->
        <h3 class="vqh-event-title">
            <a href="<?php echo esc_url($card_permalink); ?>"><?php the_title(); ?></a>
        </h3>

        <!-- Ubicación -->
        <?php
        $address = get_post_meta(get_the_ID(), 'address', true);
        if ($address):
        ?>
            <p class="vqh-event-location">
                <span class="dashicons dashicons-location"></span>
                <?php echo esc_html($address); ?>
            </p>
        <?php endif; ?>

        <!-- Fecha y Hora -->
        <?php if ($display_date): ?>
            <p class="vqh-event-datetime">
                <span class="dashicons dashicons-calendar-alt"></span>
                <?php echo esc_html(date_i18n('l, j \d\e F \d\e Y', strtotime($display_date))); ?>
                <?php if ($st_time): ?>
                    <br>
                    <span class="dashicons dashicons-clock"></span>
                    <?php echo esc_html($st_time); ?>
                    <?php if ($end_time): ?>
                        - <?php echo esc_html($end_time); ?>
                    <?php endif; ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <?php if ($event_schedule_type === 'recurring' && !empty($recurrence_weekdays)) : ?>
            <p class="vqh-event-recurrence">
                <span class="dashicons dashicons-repeat"></span>
                Repite: <?php echo esc_html(implode(', ', vqh_get_event_weekday_labels($recurrence_weekdays))); ?>
            </p>
        <?php endif; ?>

        <!-- Botón -->
        <a href="<?php echo esc_url($card_permalink); ?>" class="vqh-btn-ver-mas">Ver más detalles</a>
    </div>
</article>