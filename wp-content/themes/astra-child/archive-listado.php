<?php
error_log('[VQH DEBUG] archive-listado.php loaded for URI: ' . $_SERVER['REQUEST_URI']);
get_header();
?>

<div class="ast-container vqh-city-page">
    <?php
    $city_slug = get_query_var('city_slug');
    if (empty($city_slug) && isset($_GET['city_slug']) && $_GET['city_slug'] !== '') {
        $city_slug = sanitize_title(wp_unslash($_GET['city_slug']));
    } elseif (empty($city_slug) && isset($_GET['city']) && $_GET['city'] !== '') {
        $city_slug = sanitize_title(wp_unslash($_GET['city']));
    }

    // Fallback robusto para URLs tipo /granada/?eco_cat=musica cuando WP no inyecta city_slug.
    if (empty($city_slug) && function_exists('vqh_get_path_city_slug')) {
        $path_city_slug = vqh_get_path_city_slug();
        if (!empty($path_city_slug)) {
            $city_slug = sanitize_title($path_city_slug);
        }
    }

    $city_name = '';
    $selected_city_id = 0;
    $selected_city_name = '';

    if (!empty($city_slug)) {
        global $wpdb;
        $selected_city_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT city_id FROM {$wpdb->prefix}multicity WHERE city_slug = %s LIMIT 1",
            $city_slug
        ));

        if ($selected_city_id > 0) {
            $selected_city_name = (string) $wpdb->get_var($wpdb->prepare(
                "SELECT cityname FROM {$wpdb->prefix}multicity WHERE city_id = %d LIMIT 1",
                $selected_city_id
            ));
            $city_name = $selected_city_name;
        }
    }

    $matches_selected_city = function ($post_id) use (&$city_slug, &$selected_city_id, &$selected_city_name) {
        if (empty($city_slug)) {
            return true;
        }

        $post_city_id = (int) get_post_meta($post_id, 'post_city_id', true);
        if ($selected_city_id > 0 && $post_city_id === $selected_city_id) {
            return true;
        }

        if ($post_city_id === 0 && $selected_city_name !== '') {
            $address = (string) get_post_meta($post_id, 'address', true);
            $title = (string) get_the_title($post_id);
            $haystack = strtolower(remove_accents($title . ' ' . $address));
            $needle = strtolower(remove_accents($selected_city_name));
            return $needle !== '' && strpos($haystack, $needle) !== false;
        }

        return false;
    };
    ?>

    <?php
    if (function_exists('vqh_render_archive_breadcrumb')) {
        vqh_render_archive_breadcrumb();
    }
    ?>

    <header class="vqh-city-header">
        <h1 class="vqh-city-title">
            <?php echo $city_name ? 'Eventos en ' . esc_html($city_name) : 'Agenda de Eventos'; ?>
        </h1>
        <p class="vqh-city-description">
            <?php if ($city_name): ?>
                Descubre los mejores conciertos, festivales y actividades culturales en <?php echo esc_html($city_name); ?>.
                Seleccionamos solo propuestas relevantes y de calidad para tu ciudad.
            <?php else: ?>
                Explora eventos en todas nuestras ciudades
            <?php endif; ?>
        </p>
    </header>

    <div class="vqh-city-content">
        <main class="vqh-main-content">
            <section class="vqh-featured-events">
                <?php
                $today_date = date('Y-m-d');

                $search_query = '';
                if (isset($_GET['s']) && $_GET['s'] !== '') {
                    $search_query = sanitize_text_field(wp_unslash($_GET['s']));
                }

                $category_filter = '';
                $category_term = null;
                if (isset($_GET['eco_cat']) && $_GET['eco_cat'] !== '') {
                    $category_filter = sanitize_text_field($_GET['eco_cat']);
                } elseif (isset($_GET['category']) && $_GET['category'] !== '') {
                    $category_filter = sanitize_text_field($_GET['category']);
                }

                if (!empty($category_filter)) {
                    $category_term = get_term_by('slug', $category_filter, 'ecategory');
                    if (!$category_term && is_numeric($category_filter)) {
                        $category_term = get_term_by('id', intval($category_filter), 'ecategory');
                    }
                    if (!$category_term) {
                        $category_term = get_term_by('name', $category_filter, 'ecategory');
                    }
                }

                if (!empty($search_query)) {
                    $section_title = 'Resultados para "' . esc_html($search_query) . '"';
                } elseif ($category_term && !is_wp_error($category_term)) {
                    $section_title = 'Proximos Eventos · ' . esc_html($category_term->name);
                } else {
                    $section_title = 'Proximos Eventos';
                }
                echo '<h2 class="vqh-section-title">' . esc_html($section_title) . '</h2>';

                $date_clauses = array(
                    'relation' => 'OR',
                    array('key' => 'st_date', 'value' => $today_date, 'compare' => '>=', 'type' => 'DATE'),
                    array('key' => 'event_start_date', 'value' => $today_date, 'compare' => '>=', 'type' => 'DATE'),
                    array('key' => 'end_date', 'value' => $today_date, 'compare' => '>=', 'type' => 'DATE'),
                    array('key' => 'event_end_date', 'value' => $today_date, 'compare' => '>=', 'type' => 'DATE'),
                );

                $featured_args = array(
                    'post_type' => 'listado',
                    'posts_per_page' => -1,
                    'post_status' => 'publish',
                    'orderby' => 'date',
                    'order' => 'ASC',
                    'meta_query' => array(
                        'relation' => 'AND',
                        $date_clauses,
                    ),
                    'ignore_sticky_posts' => true,
                    'suppress_filters' => true,
                );

                if (!empty($search_query)) {
                    $featured_args['s'] = $search_query;
                }

                if ($category_term && !is_wp_error($category_term)) {
                    $featured_args['tax_query'] = array(
                        array(
                            'taxonomy' => 'ecategory',
                            'field' => 'term_id',
                            'terms' => array((int) $category_term->term_id),
                            'include_children' => true,
                            'operator' => 'IN',
                        )
                    );
                }

                $featured_query = new WP_Query($featured_args);

                $matching_post_ids = array();
                $matching_dates = array();
                if ($featured_query->have_posts()) {
                    while ($featured_query->have_posts()) {
                        $featured_query->the_post();
                        $post_id = get_the_ID();

                        $effective_date = '';
                        if (function_exists('vqh_get_event_next_occurrence_for_post')) {
                            $effective_date = vqh_get_event_next_occurrence_for_post($post_id, 24, $today_date);
                        }
                        if (empty($effective_date) && function_exists('vqh_get_event_start_date')) {
                            $effective_date = vqh_get_event_start_date($post_id);
                        }
                        if (empty($effective_date) && function_exists('vqh_get_event_end_date')) {
                            $effective_date = vqh_get_event_end_date($post_id);
                        }
                        if (!empty($effective_date) && $effective_date < $today_date && function_exists('vqh_get_event_end_date')) {
                            $end_date = vqh_get_event_end_date($post_id);
                            if (!empty($end_date) && $end_date >= $today_date) {
                                $effective_date = $today_date;
                            }
                        }

                        if (empty($effective_date) || $effective_date < $today_date) {
                            continue;
                        }

                        if (!$matches_selected_city($post_id)) {
                            continue;
                        }

                        if (!empty($search_query) && function_exists('vqh_post_matches_search_query') && !vqh_post_matches_search_query($post_id, $search_query)) {
                            continue;
                        }

                        $matching_post_ids[] = $post_id;
                        $matching_dates[$post_id] = $effective_date;
                    }
                    wp_reset_postdata();
                }

                usort($matching_post_ids, function ($a, $b) use ($matching_dates) {
                    $date_a = isset($matching_dates[$a]) ? $matching_dates[$a] : '';
                    $date_b = isset($matching_dates[$b]) ? $matching_dates[$b] : '';
                    if ($date_a === $date_b) {
                        return 0;
                    }
                    if ($date_a === '') {
                        return 1;
                    }
                    if ($date_b === '') {
                        return -1;
                    }
                    return $date_a < $date_b ? -1 : 1;
                });

                if (!empty($matching_post_ids)) {
                    if (!empty($search_query)) {
                        echo '<p class="vqh-search-results-summary">Se encontraron ' . esc_html(count($matching_post_ids)) . ' resultados para tu busqueda.</p>';
                    }

                    echo '<div class="vqh-featured-grid">';
                    foreach ($matching_post_ids as $post_id) {
                        $post = get_post($post_id);
                        if (!$post) {
                            continue;
                        }
                        setup_postdata($post);
                        get_template_part('template-parts/event', 'card');
                    }
                    echo '</div>';
                    wp_reset_postdata();
                } else {
                    if (!empty($search_query)) {
                        echo '<div class="vqh-no-results">';
                        echo '<h3>No encontramos eventos con "' . esc_html($search_query) . '"</h3>';
                        echo '<p>Prueba con otra palabra, con menos terminos o revisa los eventos de la ciudad seleccionada.</p>';
                        echo '</div>';
                    } else {
                        echo '<p class="vqh-no-events">No hay eventos proximos programados.</p>';
                    }
                }
                ?>
            </section>
        </main>

        <aside class="vqh-sidebar">
            <div class="vqh-widget vqh-calendar-widget">
                <h3 class="vqh-widget-title">Calendario</h3>
                <?php
                $current_year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
                $current_month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('n'));
                if ($current_year < 2020 || $current_year > 2030) {
                    $current_year = intval(date('Y'));
                }
                if ($current_month < 1 || $current_month > 12) {
                    $current_month = intval(date('n'));
                }

                $current_category = '';
                if (isset($_GET['eco_cat']) && $_GET['eco_cat'] !== '') {
                    $current_category = sanitize_text_field($_GET['eco_cat']);
                } elseif (isset($_GET['category']) && $_GET['category'] !== '') {
                    $current_category = sanitize_text_field($_GET['category']);
                }

                if (!empty($city_slug)) {
                    echo vqh_render_city_calendar($city_slug, $current_year, $current_month, $current_category);
                }
                ?>
            </div>

            <div class="vqh-widget vqh-categories-widget">
                <h3 class="vqh-widget-title">Categorias</h3>
                <ul class="vqh-category-list">
                    <?php
                    $city_category_counts = array();
                    $sidebar_args = array(
                        'post_type' => 'listado',
                        'posts_per_page' => -1,
                        'post_status' => 'publish',
                        'meta_query' => array(
                            'relation' => 'OR',
                            array('key' => 'st_date', 'value' => $today_date, 'compare' => '>=', 'type' => 'DATE'),
                            array('key' => 'event_start_date', 'value' => $today_date, 'compare' => '>=', 'type' => 'DATE'),
                            array('key' => 'end_date', 'value' => $today_date, 'compare' => '>=', 'type' => 'DATE'),
                            array('key' => 'event_end_date', 'value' => $today_date, 'compare' => '>=', 'type' => 'DATE'),
                        ),
                        'ignore_sticky_posts' => true,
                        'suppress_filters' => true,
                    );

                    $sidebar_query = new WP_Query($sidebar_args);
                    if ($sidebar_query->have_posts()) {
                        while ($sidebar_query->have_posts()) {
                            $sidebar_query->the_post();
                            $post_id = get_the_ID();

                            if (!$matches_selected_city($post_id)) {
                                continue;
                            }

                            $effective_date = '';
                            if (function_exists('vqh_get_event_next_occurrence_for_post')) {
                                $effective_date = vqh_get_event_next_occurrence_for_post($post_id, 24, $today_date);
                            }
                            if (empty($effective_date) && function_exists('vqh_get_event_start_date')) {
                                $effective_date = vqh_get_event_start_date($post_id);
                            }
                            if (empty($effective_date) && function_exists('vqh_get_event_end_date')) {
                                $effective_date = vqh_get_event_end_date($post_id);
                            }
                            if (!empty($effective_date) && $effective_date < $today_date && function_exists('vqh_get_event_end_date')) {
                                $end_date = vqh_get_event_end_date($post_id);
                                if (!empty($end_date) && $end_date >= $today_date) {
                                    $effective_date = $today_date;
                                }
                            }
                            if (empty($effective_date) || $effective_date < $today_date) {
                                continue;
                            }

                            $terms = get_the_terms($post_id, 'ecategory');
                            if (!empty($terms) && !is_wp_error($terms)) {
                                foreach ($terms as $term) {
                                    if (!isset($city_category_counts[$term->term_id])) {
                                        $city_category_counts[$term->term_id] = 0;
                                    }
                                    $city_category_counts[$term->term_id]++;
                                }
                            }
                        }
                        wp_reset_postdata();
                    }

                    if (!empty($city_category_counts)) {
                        $categories = get_terms(array(
                            'taxonomy' => 'ecategory',
                            'include' => array_keys($city_category_counts),
                            'hide_empty' => false,
                        ));

                        if (!is_wp_error($categories) && !empty($categories)) {
                            usort($categories, function ($a, $b) {
                                return strcasecmp($a->name, $b->name);
                            });

                            foreach ($categories as $category) {
                                $cat_count = isset($city_category_counts[$category->term_id]) ? (int) $city_category_counts[$category->term_id] : 0;
                                if ($cat_count <= 0) {
                                    continue;
                                }

                                $cat_url = !empty($city_slug)
                                    ? home_url('/' . $city_slug . '/?eco_cat=' . $category->slug)
                                    : home_url('/?eco_cat=' . $category->slug);

                                echo '<li><a href="' . esc_url($cat_url) . '">' . esc_html($category->name) . '<span class="vqh-cat-count">(' . $cat_count . ')</span></a></li>';
                            }
                        }
                    }
                    ?>
                </ul>
            </div>
        </aside>
    </div>
</div>

<?php get_footer(); ?>