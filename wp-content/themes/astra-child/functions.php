<?php
// DIAGNÓSTICO TEMPRANO
if (isset($_GET['eco_cat'])) {
    error_log("¡ALERTA! Se recibió eco_cat: " . $_GET['eco_cat'] . " en URL: " . $_SERVER['REQUEST_URI']);
}
if (isset($_GET['category'])) {
    error_log("¡ALERTA! Se recibió category: " . $_GET['category'] . " en URL: " . $_SERVER['REQUEST_URI']);
}
/**
 * Astra Child Theme - VerQueHacer
 * Versión FINAL limpia - Sin duplicados
 */

// =================================================================
// 1. CARGAR ESTILOS
// =================================================================
function vqh_child_enqueue_styles()
{
    $child_css_path = get_stylesheet_directory() . '/style.css';
    $child_css_ver = file_exists($child_css_path) ? (string) filemtime($child_css_path) : '1.0.0';

    wp_enqueue_style(
        'astra-parent',
        get_template_directory_uri() . '/style.css',
        array(),
        '1.0.0'
    );
    wp_enqueue_style(
        'astra-child',
        get_stylesheet_directory_uri() . '/style.css',
        array('astra-parent'),
        $child_css_ver
    );

    // En single-listado usamos iconos Dashicons en breadcrumb y compartir.
    if (is_singular('listado')) {
        wp_enqueue_style('dashicons');
    }
}
add_action('wp_enqueue_scripts', 'vqh_child_enqueue_styles');

function vqh_post_matches_search_query($post_id, $search_query)
{
    if (empty($post_id) || empty($search_query)) {
        return true;
    }

    $search_terms = preg_split('/\s+/u', trim(wp_strip_all_tags($search_query)), -1, PREG_SPLIT_NO_EMPTY);
    if (empty($search_terms)) {
        return true;
    }

    $haystacks = array();
    $post = get_post($post_id);
    if ($post) {
        $haystacks[] = $post->post_title;
        $haystacks[] = $post->post_excerpt;
        $haystacks[] = $post->post_content;
    }

    $address = get_post_meta($post_id, 'address', true);
    if (!empty($address)) {
        $haystacks[] = $address;
    }

    $city_id = get_post_meta($post_id, 'post_city_id', true);
    if (!empty($city_id)) {
        global $wpdb;
        $city_name = $wpdb->get_var($wpdb->prepare(
            "SELECT cityname FROM {$wpdb->prefix}multicity WHERE city_id = %d LIMIT 1",
            (int) $city_id
        ));
        if (!empty($city_name)) {
            $haystacks[] = $city_name;
        }
    }

    $terms = get_the_terms($post_id, 'ecategory');
    if (!empty($terms) && !is_wp_error($terms)) {
        foreach ($terms as $term) {
            $haystacks[] = $term->name;
            $haystacks[] = $term->slug;
        }
    }

    $combined_text = strtolower(remove_accents(implode(' ', array_filter($haystacks))));

    foreach ($search_terms as $search_term) {
        $normalized_term = strtolower(remove_accents($search_term));
        if ($normalized_term !== '' && strpos($combined_text, $normalized_term) !== false) {
            return true;
        }
    }

    return false;
}

function vqh_enqueue_single_share_tracking_script()
{
    if (is_admin() || !is_singular('listado')) {
        return;
    }

    $script_path = get_stylesheet_directory() . '/js/single-share-tracking.js';
    $script_ver = file_exists($script_path) ? (string) filemtime($script_path) : '1.0.0';

    wp_enqueue_script(
        'vqh-single-share-tracking',
        get_stylesheet_directory_uri() . '/js/single-share-tracking.js',
        array(),
        $script_ver,
        true
    );

    $post_id = get_the_ID();
    $city_slug = get_query_var('city_slug');
    $primary_category_slug = '';
    $terms = get_the_terms($post_id, 'ecategory');
    if (!empty($terms) && !is_wp_error($terms)) {
        $primary_category_slug = isset($terms[0]->slug) ? (string) $terms[0]->slug : '';
    }

    wp_localize_script('vqh-single-share-tracking', 'vqhShareTrackingData', array(
        'postId' => get_the_ID(),
        'title' => get_the_title(),
        'url' => get_permalink(),
        'postType' => get_post_type(),
        'citySlug' => $city_slug ? (string) $city_slug : '',
        'primaryCategory' => $primary_category_slug,
    ));
}
add_action('wp_enqueue_scripts', 'vqh_enqueue_single_share_tracking_script');

function vqh_enqueue_single_event_map_script()
{
    if (is_admin() || !is_singular('listado')) {
        return;
    }

    $script_path = get_stylesheet_directory() . '/js/single-event-map.js';
    $script_ver = file_exists($script_path) ? (string) filemtime($script_path) : '1.0.0';

    wp_enqueue_script(
        'vqh-single-event-map',
        get_stylesheet_directory_uri() . '/js/single-event-map.js',
        array(),
        $script_ver,
        true
    );

    $post_id = get_the_ID();
    $city_slug = get_query_var('city_slug');
    $primary_category_slug = '';
    $terms = get_the_terms($post_id, 'ecategory');
    if (!empty($terms) && !is_wp_error($terms)) {
        $primary_category_slug = isset($terms[0]->slug) ? (string) $terms[0]->slug : '';
    }

    wp_localize_script('vqh-single-event-map', 'vqhMapTrackingData', array(
        'postId' => $post_id,
        'title' => get_the_title($post_id),
        'url' => get_permalink($post_id),
        'postType' => get_post_type($post_id),
        'citySlug' => $city_slug ? (string) $city_slug : '',
        'primaryCategory' => $primary_category_slug,
    ));
}
add_action('wp_enqueue_scripts', 'vqh_enqueue_single_event_map_script');

// =================================================================
// 1.5. OCULTAR POSTS RECURRENTES DEL ADMIN - FILTRO SQL
// =================================================================
add_filter('posts_where', 'vqh_exclude_recurring_posts', 10, 2);
function vqh_exclude_recurring_posts($where, $query)
{
    global $wpdb;

    // Solo en el Admin
    if (!is_admin()) {
        return $where;
    }

    // Solo para post type 'listado'
    if (isset($query->query_vars['post_type']) && $query->query_vars['post_type'] === 'listado') {
        // Excluir posts con status 'recurring'
        $where .= " AND {$wpdb->posts}.post_status != 'recurring'";
    }

    return $where;
}

// =================================================================
// 2. REGISTRAR CPT 'LISTADO'
// =================================================================
add_action('init', 'vqh_register_listado_cpt');
function vqh_register_listado_cpt()
{
    if (post_type_exists('listado')) {
        return;
    }

    $labels = array(
        'name'                  => 'Listados',
        'singular_name'         => 'Listado',
        'menu_name'             => 'Listados',
        'add_new'               => 'Añadir nuevo',
        'add_new_item'          => 'Añadir nuevo listado',
        'edit_item'             => 'Editar listado',
        'new_item'              => 'Nuevo listado',
        'view_item'             => 'Ver listado',
        'search_items'          => 'Buscar listados',
        'not_found'             => 'No se encontraron listados',
        'not_found_in_trash'    => 'No hay listados en la papelera',
    );

    register_post_type('listado', array(
        'labels'                => $labels,
        'public'                => true,
        'has_archive'           => true,
        'publicly_queryable'    => true,
        'show_in_nav_menus'     => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 5,
        'menu_icon'             => 'dashicons-location',
        'supports'              => array('title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'),
        'rewrite'               => array('slug' => '%city%', 'with_front' => true),
        'query_var'             => true,
        'show_in_rest'          => true,
    ));
}

// =================================================================
// 3. REGISTRAR TAXONOMÍA 'ecategory'
// =================================================================
add_action('init', 'vqh_register_ecategory_taxonomy', 1);
function vqh_register_ecategory_taxonomy()
{
    if (taxonomy_exists('ecategory')) {
        return;
    }

    $labels = array(
        'name'              => 'Categorías de Eventos',
        'singular_name'     => 'Categoría',
        'search_items'      => 'Buscar categorías',
        'all_items'         => 'Todas las categorías',
        'edit_item'         => 'Editar categoría',
        'update_item'       => 'Actualizar categoría',
        'add_new_item'      => 'Añadir nueva categoría',
        'menu_name'         => 'Categorías',
    );

    register_taxonomy('ecategory', array('listado'), array(
        'labels'            => $labels,
        'public'            => true,
        'hierarchical'      => true,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array('slug' => 'categoria'),
        'show_in_rest'      => true,
    ));
}

// =================================================================
// 4. MENÚS
// =================================================================
function vqh_register_menus()
{
    register_nav_menus(array(
        'footer-menu' => __('Menú Footer', 'astra-child'),
        'primary'     => __('Menú Principal', 'astra-child'),
    ));
}
add_action('after_setup_theme', 'vqh_register_menus');

// =================================================================
// 5. SOPORTE DEL TEMA
// =================================================================
function vqh_theme_features()
{
    add_theme_support('custom-logo');
    add_theme_support('custom-header');
    add_theme_support('post-thumbnails');
    add_theme_support('title-tag');
}
add_action('after_setup_theme', 'vqh_theme_features', 20);

function vqh_homepage_editorial_block()
{
    if (! is_home() && ! is_front_page()) {
        return;
    }

    echo '<section class="vqh-hero-home">';
    echo '<div class="vqh-hero-copy">';
    echo '<span class="vqh-hero-kicker">Agenda editorial</span>';
    echo '<h1>Descubre las mejores experiencias de tu ciudad</h1>';
    echo '<p>Eventos, conciertos, teatro y ocio en una sola guía clara, actualizada y fácil de explorar.</p>';
    echo '<p class="vqh-hero-meta">Edicion nacional · Curaduria local · Actualizacion diaria</p>';
    echo '<a class="vqh-hero-btn" href="#proximos-eventos">Ver próximos eventos</a>';
    echo '</div>';
    echo '<div class="vqh-hero-cards">';
    echo '<div class="vqh-hero-card"><h3>Próximos eventos</h3><p>Consulta las propuestas más recientes y cercanas.</p></div>';
    echo '<div class="vqh-hero-card"><h3>Explora por ciudad</h3><p>Encuentra planes por provincia y localidad.</p></div>';
    echo '<div class="vqh-hero-card"><h3>Experiencias recomendadas</h3><p>Ideas seleccionadas para disfrutar al máximo.</p></div>';
    echo '</div>';
    echo '</section>';

    echo '<section class="vqh-editorial-grid">';
    echo '<article class="vqh-editorial-card vqh-editorial-card--featured">';
    echo '<span class="vqh-card-label">Destacado de la semana</span>';
    echo '<h2>Conciertos, teatro y escapadas urbanas en una sola guía</h2>';
    echo '<p>Una selección curada para quienes buscan propuestas que merecen la pena y una agenda más elegante.</p>';
    echo '<a class="vqh-card-link" href="#proximos-eventos">Explorar agenda</a>';
    echo '</article>';
    echo '<div class="vqh-editorial-stack">';
    echo '<article class="vqh-editorial-card vqh-editorial-card--mini">';
    echo '<span class="vqh-card-label">Cultura</span>';
    echo '<h3>Exposiciones y funciones de fin de semana</h3>';
    echo '</article>';
    echo '<article class="vqh-editorial-card vqh-editorial-card--mini">';
    echo '<span class="vqh-card-label">Noche</span>';
    echo '<h3>Rutas de ocio y conciertos recomendados</h3>';
    echo '</article>';
    echo '</div>';
    echo '</section>';

    echo '<section class="vqh-event-feed" id="proximos-eventos">';
}
add_action('astra_entry_content_before', 'vqh_homepage_editorial_block');

// =================================================================
// 6. META BOX - Campos personalizados
// =================================================================
add_filter('rwmb_meta_boxes', 'vqh_register_meta_boxes');
function vqh_register_meta_boxes($meta_boxes)
{
    // Box 1: Fechas y recurrencia
    $meta_boxes[] = array(
        'title'      => '📅 Fechas del Evento',
        'post_types' => array('listado'),
        'priority'   => 'high',
        'fields'     => array(
            array(
                'name'    => 'Tipo de evento',
                'id'      => 'event_schedule_type',
                'type'    => 'radio',
                'options' => array(
                    'unique'    => 'Evento único',
                    'recurring' => 'Evento recurrente',
                ),
                'std'     => 'unique',
                'desc'    => 'Marca si este evento se repite regularmente o si solo ocurre una vez.',
            ),
            array(
                'name'       => 'Días de repetición',
                'id'         => 'recurrence_weekdays',
                'type'       => 'checkbox_list',
                'options'    => array(
                    '1' => 'Lunes',
                    '2' => 'Martes',
                    '3' => 'Miércoles',
                    '4' => 'Jueves',
                    '5' => 'Viernes',
                    '6' => 'Sábado',
                    '7' => 'Domingo',
                ),
                'desc'       => 'Marca los días de la semana en los que se repite el evento. Solo tiene sentido cuando el evento es recurrente.',
                'visible'    => array(
                    'event_schedule_type',
                    '=',
                    'recurring',
                ),
            ),
            array(
                'name' => 'Fecha de inicio',
                'id'   => 'st_date',
                'type' => 'date',
                'js_options' => array('dateFormat' => 'yy-mm-dd'),
            ),
            array(
                'name' => 'Fecha de fin',
                'id'   => 'end_date',
                'type' => 'date',
                'js_options' => array('dateFormat' => 'yy-mm-dd'),
            ),
            array(
                'name' => 'Hora de inicio',
                'id'   => 'st_time',
                'type' => 'time',
            ),
            array(
                'name' => 'Hora de fin',
                'id'   => 'end_time',
                'type' => 'time',
            ),
        ),
    );

    // Box 2: Ubicación Completa
    $meta_boxes[] = array(
        'title'      => '📍 Ubicación Completa',
        'post_types' => array('listado'),
        'priority'   => 'high',
        'fields'     => array(
            array(
                'name' => 'Dirección completa',
                'id'   => 'address',
                'type' => 'textarea',
                'rows' => 2,
                'desc' => 'Dirección física completa del evento',
            ),
            array(
                'name' => 'Provincia/Estado',
                'id'   => 'zones_id',
                'type' => 'select',
                'options' => vqh_get_spain_zones(),
                'desc' => 'Selecciona la provincia (solo España)',
            ),
            array(
                'name' => 'Ciudad',
                'id'   => 'post_city_id',
                'type' => 'select',
                'options' => array(),
                'desc' => 'Selecciona la ciudad (según provincia)',
            ),
            array(
                'name' => 'Latitud',
                'id'   => 'geo_latitude',
                'type' => 'text',
                'desc' => 'Coordenada GPS - Latitud',
            ),
            array(
                'name' => 'Longitud',
                'id'   => 'geo_longitude',
                'type' => 'text',
                'desc' => 'Coordenada GPS - Longitud',
            ),
        ),
    );

    // Box 3: Precios
    $meta_boxes[] = array(
        'title'      => '💰 Precios y Registro',
        'post_types' => array('listado'),
        'priority'   => 'high',
        'fields'     => array(
            array(
                'name' => 'Tarifa de registro',
                'id'   => 'reg_fees',
                'type' => 'text',
            ),
            array(
                'name' => 'Cantidad pagada',
                'id'   => 'paid_amount',
                'type' => 'text',
            ),
        ),
    );

    // Box 4: Categorías
    if (taxonomy_exists('ecategory')) {
        $categories = get_terms(array(
            'taxonomy'   => 'ecategory',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ));

        $category_options = array();

        if (!is_wp_error($categories) && !empty($categories)) {
            foreach ($categories as $category) {
                $prefix = ($category->parent > 0) ? '— ' : '';
                $category_options[$category->term_id] = $prefix . $category->name;
            }
        }

        $meta_boxes[] = array(
            'title'      => '📂 Categorías del Evento',
            'post_types' => array('listado'),
            'priority'   => 'high',
            'context'    => 'normal',
            'fields'     => array(
                array(
                    'name'    => 'Selecciona las categorías',
                    'id'      => 'vqh_event_categories',
                    'type'    => 'checkbox_list',
                    'options' => $category_options,
                    'desc'    => 'Marca todas las categorías que apliquen',
                ),
            ),
        );
    }

    return $meta_boxes;
}

// =================================================================
// 7. FUNCIONES DE UBICACIÓN
// =================================================================
function vqh_get_spain_zones()
{
    global $wpdb;

    $spain_country_id = $wpdb->get_var("
        SELECT country_id 
        FROM {$wpdb->prefix}countries 
        WHERE iso_code_2 = 'ES' 
        LIMIT 1
    ");

    if (!$spain_country_id) {
        return array();
    }

    $zones = $wpdb->get_results($wpdb->prepare("
        SELECT zones_id, zone_name, zone_code
        FROM {$wpdb->prefix}zones
        WHERE country_id = %d
        ORDER BY zone_name ASC
    ", $spain_country_id));

    $options = array();
    foreach ($zones as $zone) {
        $options[$zone->zones_id] = $zone->zone_name;
    }

    return $options;
}

function vqh_get_cities_by_zone($zone_id)
{
    global $wpdb;

    if (empty($zone_id)) {
        return array();
    }

    $cities = $wpdb->get_results($wpdb->prepare("
        SELECT city_id, cityname, city_slug
        FROM {$wpdb->prefix}multicity
        WHERE zones_id = %d
        ORDER BY cityname ASC
    ", $zone_id));

    $options = array();
    foreach ($cities as $city) {
        $options[$city->city_id] = $city->cityname;
    }

    return $options;
}

add_action('wp_ajax_vqh_get_cities_by_zone', 'vqh_ajax_get_cities_by_zone');
function vqh_ajax_get_cities_by_zone()
{
    check_ajax_referer('vqh_location_nonce', 'nonce');

    $zone_id = isset($_POST['zone_id']) ? intval($_POST['zone_id']) : 0;
    $cities = vqh_get_cities_by_zone($zone_id);

    if (!empty($cities)) {
        echo '<option value="">-- Selecciona una ciudad --</option>';
        foreach ($cities as $id => $name) {
            echo '<option value="' . esc_attr($id) . '">' . esc_html($name) . '</option>';
        }
    } else {
        echo '<option value="">No hay ciudades disponibles</option>';
    }

    wp_die();
}

// =================================================================
// Fallback meta-boxes when Meta Box (RWMB) plugin is not available
// =================================================================
add_action('admin_init', 'vqh_register_fallback_meta_boxes_if_needed');
function vqh_register_fallback_meta_boxes_if_needed()
{
    if (is_admin() && !class_exists('RW_Meta_Box') && !function_exists('rwmb_meta')) {
        add_action('add_meta_boxes', 'vqh_add_fallback_meta_boxes', 20);
        add_action('save_post', 'vqh_save_fallback_meta_boxes', 10, 2);
    }
}

function vqh_add_fallback_meta_boxes()
{
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ($screen && $screen->post_type !== 'listado') {
        return;
    }

    add_meta_box(
        'vqh_event_dates',
        'Fechas del Evento',
        'vqh_render_fallback_event_dates',
        'listado',
        'normal',
        'high'
    );

    add_meta_box(
        'vqh_event_location',
        'Ubicación y Datos',
        'vqh_render_fallback_event_location',
        'listado',
        'normal',
        'high'
    );
}

function vqh_render_fallback_event_dates($post)
{
    wp_nonce_field('vqh_fallback_meta', 'vqh_fallback_meta_nonce');

    $event_schedule_type = get_post_meta($post->ID, 'event_schedule_type', true) ?: 'unique';
    $recurrence_weekdays = get_post_meta($post->ID, 'recurrence_weekdays', false);
    $st_date = get_post_meta($post->ID, 'st_date', true);
    $end_date = get_post_meta($post->ID, 'end_date', true);
    $st_time = get_post_meta($post->ID, 'st_time', true);
    $end_time = get_post_meta($post->ID, 'end_time', true);

?>
    <p>
        <label><strong>Tipo de evento:</strong></label><br>
        <label><input type="radio" name="event_schedule_type" value="unique" <?php checked($event_schedule_type, 'unique'); ?>> Evento único</label>
        &nbsp;&nbsp;
        <label><input type="radio" name="event_schedule_type" value="recurring" <?php checked($event_schedule_type, 'recurring'); ?>> Evento recurrente</label>
    </p>

    <p>
        <label><strong>Días de repetición:</strong></label><br>
        <?php
        $days = array(1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo');
        foreach ($days as $k => $label) {
            $checked = in_array((string)$k, (array)$recurrence_weekdays, true) ? 'checked' : '';
            echo '<label style="margin-right:8px;"><input type="checkbox" name="recurrence_weekdays[]" value="' . esc_attr($k) . '" ' . $checked . '> ' . esc_html($label) . '</label>';
        }
        ?>
    </p>

    <p>
        <label>Fecha de inicio: <input type="date" name="st_date" value="<?php echo esc_attr($st_date); ?>"></label>
    </p>
    <p>
        <label>Fecha de fin: <input type="date" name="end_date" value="<?php echo esc_attr($end_date); ?>"></label>
    </p>
    <p>
        <label>Hora de inicio: <input type="time" name="st_time" value="<?php echo esc_attr($st_time); ?>"></label>
    </p>
    <p>
        <label>Hora de fin: <input type="time" name="end_time" value="<?php echo esc_attr($end_time); ?>"></label>
    </p>
<?php
}

function vqh_render_fallback_event_location($post)
{
    wp_nonce_field('vqh_fallback_meta', 'vqh_fallback_meta_nonce');

    $address = get_post_meta($post->ID, 'address', true);
    $zones_id = get_post_meta($post->ID, 'zones_id', true);
    $post_city_id = get_post_meta($post->ID, 'post_city_id', true);
    $geo_latitude = get_post_meta($post->ID, 'geo_latitude', true);
    $geo_longitude = get_post_meta($post->ID, 'geo_longitude', true);

    $zones = vqh_get_spain_zones();

    global $wpdb;
    $cities = $wpdb->get_results("SELECT city_id, cityname FROM {$wpdb->prefix}multicity ORDER BY cityname ASC");

?>
    <p>
        <label>Dirección:<br>
            <textarea name="address" rows="3" style="width:100%;"><?php echo esc_textarea($address); ?></textarea>
        </label>
    </p>

    <p>
        <label>Provincia:
            <select name="zones_id">
                <option value="">-- Selecciona --</option>
                <?php foreach ($zones as $id => $name) : ?>
                    <option value="<?php echo esc_attr($id); ?>" <?php selected($zones_id, $id); ?>><?php echo esc_html($name); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </p>

    <p>
        <label>Ciudad:
            <select name="post_city_id">
                <option value="">-- Selecciona --</option>
                <?php foreach ($cities as $c) : ?>
                    <option value="<?php echo esc_attr($c->city_id); ?>" <?php selected($post_city_id, $c->city_id); ?>><?php echo esc_html($c->cityname); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </p>

    <p>
        <label>Latitud: <input type="text" name="geo_latitude" value="<?php echo esc_attr($geo_latitude); ?>"></label>
        &nbsp; <label>Longitud: <input type="text" name="geo_longitude" value="<?php echo esc_attr($geo_longitude); ?>"></label>
    </p>
<?php
}

function vqh_save_fallback_meta_boxes($post_id, $post)
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!isset($_POST['vqh_fallback_meta_nonce']) || !wp_verify_nonce($_POST['vqh_fallback_meta_nonce'], 'vqh_fallback_meta')) return;
    if ($post->post_type !== 'listado') return;

    // Fechas y recurrencia
    if (isset($_POST['event_schedule_type'])) {
        update_post_meta($post_id, 'event_schedule_type', sanitize_text_field($_POST['event_schedule_type']));
    } else {
        delete_post_meta($post_id, 'event_schedule_type');
    }

    if (isset($_POST['recurrence_weekdays']) && is_array($_POST['recurrence_weekdays'])) {
        delete_post_meta($post_id, 'recurrence_weekdays');
        foreach ($_POST['recurrence_weekdays'] as $val) {
            add_post_meta($post_id, 'recurrence_weekdays', sanitize_text_field($val), false);
        }
    } else {
        delete_post_meta($post_id, 'recurrence_weekdays');
    }

    if (isset($_POST['st_date'])) update_post_meta($post_id, 'st_date', sanitize_text_field($_POST['st_date']));
    if (isset($_POST['end_date'])) update_post_meta($post_id, 'end_date', sanitize_text_field($_POST['end_date']));
    if (isset($_POST['st_time'])) update_post_meta($post_id, 'st_time', sanitize_text_field($_POST['st_time']));
    if (isset($_POST['end_time'])) update_post_meta($post_id, 'end_time', sanitize_text_field($_POST['end_time']));

    // Ubicación
    if (isset($_POST['address'])) update_post_meta($post_id, 'address', sanitize_text_field($_POST['address']));
    if (isset($_POST['zones_id'])) update_post_meta($post_id, 'zones_id', intval($_POST['zones_id']));
    if (isset($_POST['post_city_id'])) update_post_meta($post_id, 'post_city_id', intval($_POST['post_city_id']));
    if (isset($_POST['geo_latitude'])) update_post_meta($post_id, 'geo_latitude', sanitize_text_field($_POST['geo_latitude']));
    if (isset($_POST['geo_longitude'])) update_post_meta($post_id, 'geo_longitude', sanitize_text_field($_POST['geo_longitude']));
}

function vqh_enqueue_location_script($hook)
{
    global $post_type, $post;

    if (!in_array($post_type, array('listado'))) {
        return;
    }

    wp_enqueue_script(
        'vqh-location-script',
        get_stylesheet_directory_uri() . '/js/location-fields.js',
        array('jquery'),
        '1.3',
        true
    );

    $saved_zone_id = '';
    $saved_city_id = '';

    if ($post) {
        $saved_zone_id = get_post_meta($post->ID, 'zones_id', true);
        $saved_city_id = get_post_meta($post->ID, 'post_city_id', true);
    }

    wp_localize_script('vqh-location-script', 'vqh_location', array(
        'ajax_url'      => admin_url('admin-ajax.php'),
        'nonce'         => wp_create_nonce('vqh_location_nonce'),
        'saved_zone_id' => $saved_zone_id,
        'saved_city_id' => $saved_city_id,
    ));
}
add_action('admin_enqueue_scripts', 'vqh_enqueue_location_script');

// =================================================================
// 8. FILTROS ADMIN DE CIUDAD
// =================================================================
function vqh_get_all_cities_options()
{
    global $wpdb;

    $cities = $wpdb->get_results("SELECT city_id, cityname FROM {$wpdb->prefix}multicity ORDER BY cityname ASC");
    $options = array();
    foreach ($cities as $city) {
        $options[$city->city_id] = $city->cityname;
    }
    return $options;
}

function vqh_render_listado_city_filter()
{
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'listado' || $screen->base !== 'edit') {
        return;
    }

    $selected_city_id = isset($_GET['filter_city_id']) ? intval($_GET['filter_city_id']) : 0;
    $cities = vqh_get_all_cities_options();

    if (empty($cities)) {
        return;
    }

    echo '<select name="filter_city_id" id="filter_city_id" class="postform">';
    echo '<option value="">' . esc_html__('Todas las ciudades', 'astra-child') . '</option>';
    foreach ($cities as $city_id => $city_name) {
        printf(
            '<option value="%d"%s>%s</option>',
            $city_id,
            selected($selected_city_id, $city_id, false),
            esc_html($city_name)
        );
    }
    echo '</select>';
}
add_action('restrict_manage_posts', 'vqh_render_listado_city_filter');

function vqh_filter_listado_by_city($query)
{
    if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'listado') {
        return;
    }

    if (isset($_GET['filter_city_id']) && $_GET['filter_city_id'] !== '') {
        $city_id = intval($_GET['filter_city_id']);
        if ($city_id > 0) {
            $meta_query = $query->get('meta_query');
            if (!is_array($meta_query)) {
                $meta_query = array();
            }
            $meta_query[] = array(
                'key'     => 'post_city_id',
                'value'   => $city_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            );
            $query->set('meta_query', $meta_query);
        }
    }
}
add_action('pre_get_posts', 'vqh_filter_listado_by_city', 20);

// =================================================================
// 9. HELPER METADATOS
// =================================================================
function vqh_get_meta($post_id, $key, $label = '')
{
    $value = function_exists('rwmb_meta') ? rwmb_meta($key, '', $post_id) : get_post_meta($post_id, $key, true);

    if (!empty($value)) {
        if (is_array($value)) $value = implode(', ', $value);
        return $label ? '<p><strong>' . esc_html($label) . ':</strong> ' . esc_html($value) . '</p>' : esc_html($value);
    }
    return '';
}

function vqh_parse_event_weekdays($raw)
{
    if (empty($raw)) {
        return array();
    }

    if (is_array($raw)) {
        $items = $raw;
    } elseif (is_string($raw)) {
        $items = preg_split('/[\s,;|]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
    } else {
        return array();
    }

    $map = array(
        '1' => 'monday',
        'lunes' => 'monday',
        'mon' => 'monday',
        'monday' => 'monday',
        '2' => 'tuesday',
        'martes' => 'tuesday',
        'tue' => 'tuesday',
        'tuesday' => 'tuesday',
        '3' => 'wednesday',
        'miercoles' => 'wednesday',
        'miércoles' => 'wednesday',
        'wed' => 'wednesday',
        'wednesday' => 'wednesday',
        '4' => 'thursday',
        'jueves' => 'thursday',
        'thu' => 'thursday',
        'thursday' => 'thursday',
        '5' => 'friday',
        'viernes' => 'friday',
        'fri' => 'friday',
        'friday' => 'friday',
        '6' => 'saturday',
        'sabado' => 'saturday',
        'sábado' => 'saturday',
        'sat' => 'saturday',
        'saturday' => 'saturday',
        '7' => 'sunday',
        'domingo' => 'sunday',
        'sun' => 'sunday',
        'sunday' => 'sunday',
    );

    $normalized = array();
    foreach ($items as $item) {
        $item = trim(strtolower($item));
        if (isset($map[$item])) {
            $normalized[$map[$item]] = $map[$item];
        }
    }

    return array_values($normalized);
}

function vqh_get_event_weekday_labels(array $weekdays)
{
    $labels = array(
        'monday' => 'Lunes',
        'tuesday' => 'Martes',
        'wednesday' => 'Miércoles',
        'thursday' => 'Jueves',
        'friday' => 'Viernes',
        'saturday' => 'Sábado',
        'sunday' => 'Domingo',
    );

    $result = array();
    foreach ($weekdays as $weekday) {
        if (isset($labels[$weekday])) {
            $result[] = $labels[$weekday];
        }
    }

    return $result;
}

function vqh_get_event_weekday_rrule_days(array $weekdays)
{
    $rrule = array(
        'monday' => 'MO',
        'tuesday' => 'TU',
        'wednesday' => 'WE',
        'thursday' => 'TH',
        'friday' => 'FR',
        'saturday' => 'SA',
        'sunday' => 'SU',
    );

    $result = array();
    foreach ($weekdays as $weekday) {
        if (isset($rrule[$weekday])) {
            $result[] = $rrule[$weekday];
        }
    }

    return $result;
}

// Normalize legacy/saved recurrence weekday values to numeric array (1..7)
function vqh_normalize_recurrence_weekdays_value($raw)
{
    if (empty($raw)) return array();
    if (is_string($raw)) {
        $items = preg_split('/[\s,;|]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
    } elseif (is_array($raw)) {
        $items = $raw;
    } else {
        return array();
    }

    $map = array(
        '1' => 1,
        'lunes' => 1,
        'mon' => 1,
        'monday' => 1,
        '2' => 2,
        'martes' => 2,
        'tue' => 2,
        'tuesday' => 2,
        '3' => 3,
        'miercoles' => 3,
        'miércoles' => 3,
        'wed' => 3,
        'wednesday' => 3,
        '4' => 4,
        'jueves' => 4,
        'thu' => 4,
        'thursday' => 4,
        '5' => 5,
        'viernes' => 5,
        'fri' => 5,
        'friday' => 5,
        '6' => 6,
        'sabado' => 6,
        'sábado' => 6,
        'sat' => 6,
        'saturday' => 6,
        '7' => 7,
        'domingo' => 7,
        'sun' => 7,
        'sunday' => 7,
    );

    $nums = array();
    foreach ($items as $it) {
        $it = trim(strtolower((string)$it));
        if ($it === '') continue;
        if (isset($map[$it])) {
            $nums[$map[$it]] = $map[$it];
        }
    }
    ksort($nums, SORT_NUMERIC);
    return array_values($nums);
}

// Ensure stored meta is normalized when saving a post
function vqh_save_post_normalize_recurrence($post_id)
{
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'listado') {
        return;
    }
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $event_schedule_type = isset($_POST['event_schedule_type'])
        ? sanitize_text_field($_POST['event_schedule_type'])
        : get_post_meta($post_id, 'event_schedule_type', true);

    if ($event_schedule_type !== 'recurring') {
        delete_post_meta($post_id, 'recurrence_weekdays');
        return;
    }

    $raw = isset($_POST['recurrence_weekdays'])
        ? $_POST['recurrence_weekdays']
        : get_post_meta($post_id, 'recurrence_weekdays', true);

    $nums = vqh_normalize_recurrence_weekdays_value($raw);
    delete_post_meta($post_id, 'recurrence_weekdays');
    if (!empty($nums)) {
        foreach ($nums as $value) {
            add_post_meta($post_id, 'recurrence_weekdays', $value, false);
        }
    }
}
add_action('rwmb_after_save_post', 'vqh_save_post_normalize_recurrence', 10, 1);

// Admin fix: ensure RWMB checkboxes reflect stored meta on edit pages (works around client-side issues)
function vqh_admin_enqueue_recurrence_fix($hook_suffix)
{
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'listado' || $screen->base !== 'post') return;
    global $post;
    if (empty($post) || !isset($post->ID)) return;
    $values = get_post_meta($post->ID, 'recurrence_weekdays', false);
    wp_register_script('vqh-admin-recurrence-fix', false, array('jquery'), false, true);
    $script = '(function($){
        var vals = ' . json_encode(array_values($values)) . ';
        $(function(){
            if (!vals || !vals.length) return;
            vals = vals.map(String);
            $("input.rwmb-checkbox_list").each(function(){
                var $i = $(this);
                var v = String($i.val());
                if (vals.indexOf(v) !== -1) {
                    $i.prop("checked", true);
                }
            });
        });
    })(jQuery);';
    wp_add_inline_script('vqh-admin-recurrence-fix', $script);
    wp_enqueue_script('vqh-admin-recurrence-fix');
}
add_action('admin_enqueue_scripts', 'vqh_admin_enqueue_recurrence_fix');

function vqh_get_post_meta_fallback($post_id, array $keys, $single = true)
{
    foreach ($keys as $key) {
        $value = get_post_meta($post_id, $key, $single);
        if ($single) {
            if ($value !== '' && $value !== null && $value !== false) {
                return $value;
            }
        } elseif (!empty($value)) {
            return $value;
        }
    }

    return $single ? '' : array();
}

function vqh_get_event_start_date($post_id)
{
    return vqh_get_post_meta_fallback($post_id, array('st_date', 'event_start_date'));
}

function vqh_get_event_end_date($post_id)
{
    return vqh_get_post_meta_fallback($post_id, array('end_date', 'event_end_date'));
}

function vqh_get_event_start_time($post_id)
{
    return vqh_get_post_meta_fallback($post_id, array('st_time', 'event_start_time'));
}

function vqh_get_event_end_time($post_id)
{
    return vqh_get_post_meta_fallback($post_id, array('end_time', 'event_end_time'));
}

function vqh_get_event_schedule_type($post_id)
{
    $schedule_type = get_post_meta($post_id, 'event_schedule_type', true);
    if (!empty($schedule_type)) {
        return $schedule_type;
    }

    $start_date = vqh_get_event_start_date($post_id);
    $end_date = vqh_get_event_end_date($post_id);
    if (!empty($start_date) && !empty($end_date) && $start_date !== $end_date) {
        return 'recurring';
    }

    return 'unique';
}

function vqh_get_event_occurrences($start_date, $end_date, array $weekdays, $limit = 12)
{
    if (empty($start_date) || empty($end_date) || empty($weekdays)) {
        return array();
    }

    $start = date_create_from_format('Y-m-d', $start_date) ?: date_create($start_date);
    $end = date_create_from_format('Y-m-d', $end_date) ?: date_create($end_date);

    if (!$start || !$end || $start > $end) {
        return array();
    }

    $start->setTime(0, 0, 0);
    $end->setTime(0, 0, 0);

    $interval = new DateInterval('P1D');
    $period = new DatePeriod(clone $start, $interval, $end->modify('+1 day'));
    $dates = array();

    foreach ($period as $date) {
        $weekday = strtolower($date->format('l'));
        if (in_array($weekday, $weekdays, true)) {
            $dates[] = $date->format('Y-m-d');
            if (count($dates) >= $limit) {
                break;
            }
        }
    }

    return $dates;
}

function vqh_get_event_selected_date_from_param($param, array $occurrences)
{
    if (empty($param) || empty($occurrences)) {
        return '';
    }

    $date = date_create_from_format('Y-m-d', $param);
    if (!$date) {
        $date = date_create_from_format('d/m/Y', $param);
    }

    if (!$date) {
        return '';
    }

    $normalized = $date->format('Y-m-d');
    return in_array($normalized, $occurrences, true) ? $normalized : '';
}

function vqh_get_event_next_occurrence(array $occurrences, $reference = null)
{
    if (empty($occurrences)) {
        return '';
    }

    $reference_date = $reference ? date_create_from_format('Y-m-d', $reference) : new DateTime('today');
    if (!$reference_date) {
        $reference_date = new DateTime('today');
    }

    $reference = $reference_date->format('Y-m-d');

    foreach ($occurrences as $date) {
        if ($date >= $reference) {
            return $date;
        }
    }

    return $occurrences[0];
}

function vqh_get_event_next_occurrence_for_post($post_id, $limit = 12, $reference = null)
{
    $event_schedule_type = vqh_get_event_schedule_type($post_id);
    $start_date = vqh_get_event_start_date($post_id);
    $end_date = vqh_get_event_end_date($post_id);
    $recurrence_weekdays = vqh_parse_event_weekdays(get_post_meta($post_id, 'recurrence_weekdays', true));

    if ($event_schedule_type === 'recurring' && !empty($start_date) && !empty($end_date) && !empty($recurrence_weekdays)) {
        $occurrences = vqh_get_event_occurrences($start_date, $end_date, $recurrence_weekdays, $limit);
        if (!empty($occurrences)) {
            return vqh_get_event_next_occurrence($occurrences, $reference);
        }
    }

    return $start_date;
}

function vqh_format_iso_datetime($date, $time = '')
{
    if (empty($date)) {
        return '';
    }

    $date_object = date_create_from_format('Y-m-d', $date) ?: date_create($date);
    if (!$date_object) {
        return '';
    }

    if (!empty($time)) {
        $parts = explode(':', $time);
        $hours = isset($parts[0]) ? intval($parts[0]) : 0;
        $minutes = isset($parts[1]) ? intval($parts[1]) : 0;
        $date_object->setTime($hours, $minutes, 0);
    }

    return $date_object->format('c');
}

function vqh_single_listado_canonical()
{
    if (!is_singular('listado')) {
        return;
    }

    if (!empty($_GET['fecha'])) {
        echo '<link rel="canonical" href="' . esc_url(get_permalink()) . '" />' . "\n";
    }
}
add_action('wp_head', 'vqh_single_listado_canonical');

// =================================================================
// 9. GUARDAR CATEGORÍAS
// =================================================================
add_action('save_post', 'vqh_save_event_categories', 10, 2);
function vqh_save_event_categories($post_id, $post)
{
    if (!in_array($post->post_type, array('listado'), true)) {
        return;
    }

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // En Gutenberg/REST, WordPress guarda taxonomías por su cuenta.
    // Si nuestro campo personalizado no viene en la petición, no tocar términos
    // para evitar borrar categorías existentes al pulsar "Guardar".
    if (!isset($_POST['vqh_event_categories'])) {
        return;
    }

    $selected_categories = isset($_POST['vqh_event_categories']) ? $_POST['vqh_event_categories'] : array();

    if (!is_array($selected_categories)) {
        $selected_categories = array($selected_categories);
    }

    $selected_categories = array_map('intval', $selected_categories);
    $selected_categories = array_filter($selected_categories, function ($term_id) {
        return $term_id > 0;
    });

    wp_set_object_terms($post_id, $selected_categories, 'ecategory', false);
}

// =================================================================
// 10. FILTRO PARA REEMPLAZAR %city% CON LA CIUDAD REAL
// =================================================================
add_filter('post_type_link', 'vqh_replace_city_placeholder', 1, 3);
function vqh_replace_city_placeholder($permalink, $post, $leavename)
{
    if (strpos($permalink, '%city%') === false) {
        return $permalink;
    }

    if ($post->post_type !== 'listado') {
        return $permalink;
    }

    $city_id = get_post_meta($post->ID, 'post_city_id', true);

    if (empty($city_id)) {
        $zones_id = get_post_meta($post->ID, 'zones_id', true);
        if (!empty($zones_id)) {
            global $wpdb;
            $city_id = $wpdb->get_var($wpdb->prepare("
                SELECT city_id FROM {$wpdb->prefix}multicity WHERE zones_id = %d LIMIT 1
            ", $zones_id));
        }
    }

    if (empty($city_id)) {
        return str_replace('%city%', 'sin-ciudad', $permalink);
    }

    global $wpdb;
    $city_slug = $wpdb->get_var($wpdb->prepare("
        SELECT city_slug FROM {$wpdb->prefix}multicity WHERE city_id = %d LIMIT 1
    ", $city_id));

    if ($city_slug) {
        return str_replace('%city%', $city_slug, $permalink);
    }

    return str_replace('%city%', 'sin-ciudad', $permalink);
}

// =================================================================
// 11. ARCHIVOS POR CIUDAD Y EVENTOS INDIVIDUALES
// =================================================================
add_filter('query_vars', 'vqh_add_city_query_vars');
function vqh_add_city_query_vars($vars)
{
    $vars[] = 'city_archive';
    $vars[] = 'city_slug';
    $vars[] = 'city';
    $vars[] = 'event';
    $vars[] = 'vqh_city_event';
    $vars[] = 'vqh_show_home';
    $vars[] = 'vqh_change_city'; // ← AÑADIR: CRÍTICO para que WP reconozca el parámetro
    $vars[] = 'year';   // ← AÑADIR: CRÍTICO para navegación del calendario
    $vars[] = 'month';  // ← AÑADIR: CRÍTICO para navegación del calendario
    return $vars;
}

// Evitar que Yoast SEO elimine / redirija los parámetros year/month de calendario
add_filter('Yoast\WP\SEO\allowlist_permalink_vars', 'vqh_allow_yoast_permalink_vars');
function vqh_allow_yoast_permalink_vars($allowed_extravars)
{
    error_log('[VQH DEBUG] vqh_allow_yoast_permalink_vars called for URL: ' . $_SERVER['REQUEST_URI']);
    error_log('[VQH DEBUG] allowed_extravars before merge: ' . print_r($allowed_extravars, true));
    $allowed_extravars = array_merge($allowed_extravars, array(
        'year',
        'month',
        'eco_cat',
        'city_filter',
        'vqh_show_home',
    ));
    $result = array_unique($allowed_extravars);
    error_log('[VQH DEBUG] allowed_extravars after merge: ' . print_r($result, true));
    return $result;
}

// 12.2. Añadir rewrite rules EXCLUYENDO slugs reservados
add_action('init', 'vqh_add_city_archive_rules', 20);
function vqh_add_city_archive_rules()
{
    // Lista de slugs que NO son ciudades
    $reserved_slugs = array(
        'blog',
        'page',
        'wp-admin',
        'wp-login',
        'wp-content',
        'wp-includes',
        'feed',
        'comments',
        'wp-json',
        'author',
        'category',
        'tag',
        'attachment',
        'sitemap',
        'mapa',
        'ciudades',
        'ciudades-con-eventos'
    );

    // Crear patrón de exclusión
    $reserved_pattern = '(' . implode('|', $reserved_slugs) . ')';

    // 1. Evento individual: /ciudad/slug-del-evento/ (excluyendo reservados)
    add_rewrite_rule(
        '^((?!' . $reserved_pattern . ')[^/]+)/([^/]+)/?$',
        'index.php?post_type=listado&name=$matches[2]&city_slug=$matches[1]',
        'top'
    );

    // 2. Archivo con paginación: /ciudad/page/2/ (excluyendo reservados)
    add_rewrite_rule(
        '^((?!' . $reserved_pattern . ')[^/]+)/page/([0-9]+)/?$',
        'index.php?city_archive=1&city_slug=$matches[1]&paged=$matches[2]',
        'top'
    );

    // 3. Archivo por ciudad: /ciudad/ (excluyendo reservados)
    add_rewrite_rule(
        '^((?!' . $reserved_pattern . ')[^/]+)/?$',
        'index.php?city_archive=1&city_slug=$matches[1]',
        'top'
    );
}

function vqh_get_reserved_city_slugs()
{
    return array(
        'blog',
        'page',
        'wp-admin',
        'wp-login',
        'wp-content',
        'wp-includes',
        'feed',
        'comments',
        'wp-json',
        'author',
        'category',
        'tag',
        'attachment',
        'sitemap',
        'mapa',
        'ciudades',
        'ciudades-con-eventos',
        'listado',
        'index.php',
    );
}

function vqh_is_valid_city_slug($slug)
{
    static $cache = array();

    $slug = sanitize_title($slug);
    if ($slug === '') {
        return false;
    }

    if (isset($cache[$slug])) {
        return $cache[$slug];
    }

    global $wpdb;
    $exists = (bool) $wpdb->get_var($wpdb->prepare(
        "SELECT city_id FROM {$wpdb->prefix}multicity WHERE city_slug = %s LIMIT 1",
        $slug
    ));

    $cache[$slug] = $exists;
    return $exists;
}

function vqh_get_path_city_slug()
{
    $path = trim((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    if ($path === '') {
        return '';
    }

    $parts = explode('/', $path);
    $slug = $parts[0];

    if (in_array($slug, vqh_get_reserved_city_slugs(), true)) {
        return '';
    }

    return $slug;
}

add_filter('request', 'vqh_normalize_city_request', 1);
function vqh_normalize_city_request($query_vars)
{
    if (!empty($query_vars['city_archive']) && !empty($query_vars['city_slug'])) {
        return $query_vars;
    }

    $slug = '';
    if (!empty($query_vars['city_slug'])) {
        $slug = sanitize_title($query_vars['city_slug']);
    } elseif (!empty($query_vars['city'])) {
        $slug = sanitize_title($query_vars['city']);
    } else {
        $slug = vqh_get_path_city_slug();
    }

    if (!$slug || !vqh_is_valid_city_slug($slug)) {
        return $query_vars;
    }

    $query_vars['city_slug'] = $slug;
    $query_vars['city_archive'] = 1;
    $query_vars['post_type'] = 'listado';

    unset(
        $query_vars['pagename'],
        $query_vars['page'],
        $query_vars['name'],
        $query_vars['category_name'],
        $query_vars['attachment']
    );

    return $query_vars;
}

add_action('pre_get_posts', 'vqh_handle_city_archive_query', 10);
function vqh_handle_city_archive_query($query)
{
    if (is_admin() || !$query->is_main_query() || is_singular('listado')) {
        return;
    }

    if (!$query->get('city_archive')) {
        return;
    }

    $city_slug = $query->get('city_slug');
    if (empty($city_slug)) {
        return;
    }

    global $wpdb;
    $city_id = $wpdb->get_var($wpdb->prepare(
        "SELECT city_id FROM {$wpdb->prefix}multicity WHERE city_slug = %s LIMIT 1",
        $city_slug
    ));

    if (!$city_id) {
        $query->set_404();
        return;
    }

    $query->set('post_type', 'listado');
    $query->set('post_status', 'publish');
    $query->is_home = false;
    $query->is_front_page = false;
    $query->is_archive = true;
    $query->is_post_type_archive = true;
    $query->set('posts_per_page', get_option('posts_per_page', 10));
    $query->set('meta_query', array(
        array(
            'key'     => 'post_city_id',
            'value'   => $city_id,
            'compare' => '=',
            'type'    => 'NUMERIC',
        ),
    ));
}

// 12.5. Forzar plantilla correcta (archive O single según corresponda)
add_filter('template_include', 'vqh_force_correct_template', 99);
function vqh_force_correct_template($template)
{
    // Si es un evento individual, usar single-listado.php
    if (is_singular('listado')) {
        $new_template = locate_template(array('single-listado.php'));
        if ($new_template) {
            return $new_template;
        }
    }

    // Si es archivo de ciudad, usar archive-listado.php
    if (get_query_var('city_archive')) {
        $new_template = locate_template(array('archive-listado.php'));
        if ($new_template) {
            return $new_template;
        }
    }

    return $template;
}

add_filter('template_include', 'vqh_force_show_home_template', 98);
function vqh_force_show_home_template($template)
{
    $is_special_home = get_query_var('vqh_show_home') || isset($_GET['vqh_show_home']);
    $is_site_home = (is_front_page() || is_home()) && !is_paged() && !is_singular() && !is_archive() && !is_search() && !is_404();

    if ($is_special_home || $is_site_home) {
        $new_template = locate_template(array('archive-listado.php'));
        if ($new_template) {
            return $new_template;
        }
    }

    return $template;
}

function vqh_force_archive_listado_template($template)
{
    $new_template = locate_template(array('archive-listado.php'));
    if ($new_template) {
        return $new_template;
    }
    return $template;
}

add_filter('document_title_parts', 'vqh_city_archive_title', 99);
function vqh_city_archive_title($title)
{
    if (is_singular('listado')) {
        return $title;
    }

    $city_slug = get_query_var('city_slug');

    if (!empty($city_slug)) {
        global $wpdb;
        $city_name = $wpdb->get_var($wpdb->prepare(
            "SELECT cityname FROM {$wpdb->prefix}multicity WHERE city_slug = %s LIMIT 1",
            $city_slug
        ));

        if ($city_name) {
            $title['title'] = 'Eventos en ' . $city_name . ' | VerQueHacer';
        }
    }

    return $title;
}

// =================================================================
// 12. REDIRECCIONES 301
// =================================================================
add_action('template_redirect', 'vqh_redirect_old_listado_urls');
function vqh_redirect_old_listado_urls()
{
    if (is_singular('listado') && strpos($_SERVER['REQUEST_URI'], '/listados/') !== false) {
        wp_redirect(get_permalink(), 301);
        exit;
    }
}

// =================================================================
// 13. CALENDARIO DE EVENTOS POR CIUDAD (CON TOOLTIPS)
// =================================================================
function vqh_get_monthly_events_by_city($city_slug, $year = null, $month = null)
{
    $year = intval($year);
    $month = intval($month);
    if (!$year) {
        $year = intval(date('Y'));
    }
    if (!$month) {
        $month = intval(date('n'));
    }
    $month = sprintf('%02d', $month);

    global $wpdb;
    $city_id = $wpdb->get_var($wpdb->prepare("
        SELECT city_id FROM {$wpdb->prefix}multicity WHERE city_slug = %s LIMIT 1
    ", $city_slug));

    if (!$city_id) {
        return array();
    }

    $first_day = $year . '-' . $month . '-01';
    $last_day = date('Y-m-t', strtotime($first_day));

    $events = $wpdb->get_results($wpdb->prepare("
        SELECT p.ID, p.post_title, p.post_name,
            COALESCE(pm_start.meta_value, pm_alt.meta_value) as start_date
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm_start ON p.ID = pm_start.post_id AND pm_start.meta_key = 'st_date'
        LEFT JOIN {$wpdb->postmeta} pm_alt ON p.ID = pm_alt.post_id AND pm_alt.meta_key = 'event_start_date'
        INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'post_city_id'
        WHERE p.post_type = 'listado'
        AND p.post_status = 'publish'
        AND COALESCE(pm_start.meta_value, pm_alt.meta_value) BETWEEN %s AND %s
        AND pm2.meta_value = %d
        ORDER BY COALESCE(pm_start.meta_value, pm_alt.meta_value) ASC
    ", $first_day, $last_day, $city_id));

    $events_by_day = array();
    foreach ($events as $event) {
        $day = date('j', strtotime($event->start_date));
        if (!isset($events_by_day[$day])) {
            $events_by_day[$day] = array();
        }
        $events_by_day[$day][] = array(
            'id' => $event->ID,
            'title' => $event->post_title,
            'slug' => $event->post_name,
            'url' => home_url('/' . $city_slug . '/' . $event->post_name . '/'),
        );
    }
    return $events_by_day;
}

// 24.2. Función para generar el HTML del calendario CON TOOLTIPS
function vqh_render_city_calendar($city_slug, $year = null, $month = null)
{
    $year = intval($year);
    $month = intval($month);
    if (!$year) {
        $year = intval(date('Y'));
    }
    if (!$month) {
        $month = intval(date('n'));
    }

    $events_by_day = vqh_get_monthly_events_by_city($city_slug, $year, sprintf('%02d', $month));

    $month_names = array(
        '01' => 'Enero',
        '02' => 'Febrero',
        '03' => 'Marzo',
        '04' => 'Abril',
        '05' => 'Mayo',
        '06' => 'Junio',
        '07' => 'Julio',
        '08' => 'Agosto',
        '09' => 'Septiembre',
        '10' => 'Octubre',
        '11' => 'Noviembre',
        '12' => 'Diciembre'
    );
    $day_names = array('Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom');

    $first_day_timestamp = mktime(0, 0, 0, $month, 1, $year);
    $first_day_of_week = date('N', $first_day_timestamp);
    $days_in_month = date('t', $first_day_timestamp);

    $prev_month = intval(date('m', strtotime('-1 month', $first_day_timestamp)));
    $prev_year = intval(date('Y', strtotime('-1 month', $first_day_timestamp)));
    $next_month = intval(date('m', strtotime('+1 month', $first_day_timestamp)));
    $next_year = intval(date('Y', strtotime('+1 month', $first_day_timestamp)));

    // Construcción segura de URLs usando add_query_arg
    // NOTA: add_query_arg() puede tener problemas si la base URL tiene trailing slash y parámetros
    // Solución: construir la URL base sin trailing slash, luego add_query_arg la agrega
    $base_url_no_slash = home_url('/' . $city_slug);
    $prev_url = add_query_arg(array('year' => $prev_year, 'month' => $prev_month), $base_url_no_slash . '/');
    $next_url = add_query_arg(array('year' => $next_year, 'month' => $next_month), $base_url_no_slash . '/');

    ob_start();
?>
    <div class="vqh-calendar-mini">
        <div class="vqh-calendar-mini-header">
            <!-- Botones de navegación manteniendo la ruta de la ciudad -->
            <a href="<?php echo esc_url($prev_url); ?>" class="vqh-cal-nav">«</a>
            <span><?php echo $month_names[sprintf('%02d', $month)]; ?></span>
            <a href="<?php echo esc_url($next_url); ?>" class="vqh-cal-nav">»</a>
        </div>
        <div class="vqh-calendar-mini-grid">
            <?php foreach ($day_names as $day): ?>
                <div class="vqh-cal-weekday"><?php echo $day; ?></div>
            <?php endforeach; ?>

            <?php
            for ($i = 1; $i < $first_day_of_week; $i++) {
                echo '<div class="vqh-cal-day vqh-cal-empty"></div>';
            }

            $today_day = intval(date('j'));
            $today_month = intval(date('n'));
            $today_year = intval(date('Y'));

            for ($day = 1; $day <= $days_in_month; $day++) {
                $has_events = isset($events_by_day[$day]) && !empty($events_by_day[$day]);
                $today_class = ($day == $today_day && $month == $today_month && $year == $today_year) ? 'vqh-cal-today' : '';

                echo '<div class="vqh-cal-day ' . $today_class . ($has_events ? ' vqh-cal-has-events' : '') . '">';
                echo '<span>' . $day . '</span>';

                if ($has_events) {
                    echo '<span class="vqh-cal-dot"></span>';

                    // TOOLTIP CON EVENTOS Y ENLACES
                    echo '<div class="vqh-cal-tooltip">';
                    echo '<div class="vqh-cal-tooltip-title">' . $day . ' ' . $month_names[sprintf('%02d', $month)] . '</div>';
                    foreach ($events_by_day[$day] as $event) {
                        echo '<a href="' . esc_url($event['url']) . '" class="vqh-cal-tooltip-event">' . esc_html($event['title']) . '</a>';
                    }
                    echo '</div>';
                }
                echo '</div>';
            }
            ?>
        </div>
    </div>
<?php
    return ob_get_clean();
}

// =================================================================
// 14. SOLUCIÓN CALENDARIO: FILTRO REQUEST + JAVASCRIPT
// =================================================================

// A. UN SOLO filtro request (limpio, sin duplicados)
add_filter('request', 'vqh_force_city_on_calendar_request', 9999);
function vqh_force_city_on_calendar_request($query_vars)
{
    if (isset($_GET['year']) && isset($_GET['month'])) {
        $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        $parts = explode('/', $path);
        $slug = !empty($parts[0]) ? $parts[0] : '';

        if (!empty($slug) && !in_array($slug, array('blog', 'page', 'wp-admin', 'wp-login'))) {
            global $wpdb;
            $city_id = $wpdb->get_var($wpdb->prepare(
                "SELECT city_id FROM {$wpdb->prefix}multicity WHERE city_slug = %s LIMIT 1",
                $slug
            ));

            if ($city_id) {
                $query_vars['city_slug'] = $slug;
                $query_vars['city_archive'] = 1;
                $query_vars['post_type'] = 'listado';
                unset($query_vars['name'], $query_vars['pagename'], $query_vars['category_name']);
                return $query_vars;
            }
        }
    }
    return $query_vars;
}

// Cargar script de navegación del calendario DIRECTAMENTE en footer para evitar problemas de enqueue
function vqh_load_calendar_fix_script()
{
    $calendar_fix_path = get_stylesheet_directory() . '/js/calendar-fix.js';
    if (file_exists($calendar_fix_path)) {
        $calendar_fix_content = file_get_contents($calendar_fix_path);
        if ($calendar_fix_content) {
            echo '<script>' . $calendar_fix_content . '</script>';
        }
    }
}
add_action('wp_footer', 'vqh_load_calendar_fix_script', 9998);

// Evitar que Yoast SEO redirija nuestras URLs de calendario de ciudad con year/month
add_action('wp', 'vqh_disable_yoast_calendar_redirect', 0);
function vqh_disable_yoast_calendar_redirect()
{
    if (!isset($_GET['year']) || !isset($_GET['month'])) {
        return;
    }

    $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $parts = explode('/', $path);
    $slug = !empty($parts[0]) ? $parts[0] : '';
    if (empty($slug)) {
        return;
    }

    $reserved_slugs = array(
        'blog',
        'page',
        'wp-admin',
        'wp-login',
        'wp-content',
        'wp-includes',
        'feed',
        'comments',
        'wp-json',
        'author',
        'category',
        'tag',
        'attachment',
        'sitemap',
        'ciudades-con-eventos',
    );

    if (in_array($slug, $reserved_slugs, true)) {
        return;
    }

    global $wpdb;
    $city_id = $wpdb->get_var($wpdb->prepare(
        "SELECT city_id FROM {$wpdb->prefix}multicity WHERE city_slug = %s LIMIT 1",
        $slug
    ));

    if (!$city_id) {
        return;
    }

    if (!function_exists('YoastSEO')) {
        error_log('[VQH DEBUG] vqh_disable_yoast_calendar_redirect: YoastSEO function not available');
        return;
    }

    global $wp_filter;

    $removed = false;
    $hooks = array(
        'wp' => array('archive_redirect'),
        'template_redirect' => array('disable_date_queries', 'clean_permalinks'),
    );

    foreach ($hooks as $hook_name => $methods) {
        if (empty($wp_filter[$hook_name]->callbacks)) {
            continue;
        }

        foreach ($wp_filter[$hook_name]->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $id => $callback_data) {
                if (!is_array($callback_data['function'])) {
                    continue;
                }
                $callback_obj = $callback_data['function'][0];
                $callback_method = $callback_data['function'][1];
                if (!is_object($callback_obj) || !in_array($callback_method, $methods, true)) {
                    continue;
                }
                $class_name = get_class($callback_obj);
                if (strpos($class_name, 'Yoast\\WP\\SEO\\') !== 0) {
                    continue;
                }

                remove_action($hook_name, $callback_data['function'], $priority);
                $removed = true;
                error_log('[VQH DEBUG] vqh_disable_yoast_calendar_redirect: removed Yoast redirect callback ' . $class_name . '::' . $callback_method . ' on hook ' . $hook_name . ' priority ' . $priority);
            }
        }
    }

    if (! $removed) {
        error_log('[VQH DEBUG] vqh_disable_yoast_calendar_redirect: no Yoast redirect callbacks found to remove');
    }
}
// =================================================================
// FORZAR PLANTILLA SINGLE-LISTADO.PHP
// =================================================================
/**
 * Forzar carga de single-listado.php incluso si WP no reconoce la URL como 'single'
 * Esto soluciona el problema de que la URL cambia pero el contenido no.
 */
add_action('template_redirect', 'vqh_force_single_listado_logic');
function vqh_force_single_listado_logic()
{
    global $wp_query, $post;

    // 1. Detectar si estamos en una URL que parece un evento: /ciudad/slug-evento/
    // Verificamos que haya 2 partes en la ruta y que no sea una página estática conocida
    $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $parts = explode('/', $path);

    // Si la URL tiene estructura de ciudad/evento (ej: salamanca/nombre-evento)
    if (count($parts) === 2 && !is_admin()) {
        $city_slug = $parts[0];
        $event_slug = $parts[1];

        // 2. Buscar manualmente el post tipo 'listado' con ese slug
        // Nota: Asumimos que el slug del evento es único. Si hay duplicados entre ciudades, 
        // habría que refinar la consulta buscando también por taxonomía o meta_city_id si fuera necesario.
        $event_post = get_page_by_path($event_slug, OBJECT, 'listado');

        if ($event_post) {
            // 3. FORZAR la variable global $post
            $post = $event_post;
            setup_postdata($post);

            // 4. Engañar a WP diciendo que es un single
            $wp_query->is_singular = true;
            $wp_query->is_single = true;
            $wp_query->is_archive = false;
            $wp_query->is_page = false;
            $wp_query->queried_object = $post;
            $wp_query->queried_object_id = $post->ID;

            // 5. Cargar explícitamente la plantilla correcta
            locate_template(array('single-listado.php'), true);
            exit; // Importante: detener ejecución para evitar que cargue otra plantilla después
        }
    }
}

// Mantenemos tu filtro original como respaldo por si WP ya lo reconoce correctamente
add_filter('single_template', 'vqh_force_single_listado_template');
function vqh_force_single_listado_template($template)
{
    global $post;
    if ($post && $post->post_type === 'listado') {
        $single_template = locate_template(array('single-listado.php'));
        if ($single_template) {
            return $single_template;
        }
    }
    return $template;
}

// Añadir clase al body para identificar single-listado
add_filter('body_class', 'vqh_add_single_listado_body_class');
function vqh_add_single_listado_body_class($classes)
{
    if (is_singular('listado')) {
        $classes[] = 'single-listado';
    }
    return $classes;
}

// =================================================================
// 30. GEOLOCALIZACIÓN DE EVENTOS (MÓDULO INDEPENDIENTE)
// =================================================================

function vqh_format_geo_cities($cities)
{
    $cities_data = array();
    foreach ($cities as $city) {
        $cities_data[] = array(
            'id'   => (int) $city->city_id,
            'name' => $city->cityname,
            'slug' => $city->city_slug,
            'lat'  => $city->lat,
            'lng'  => $city->lng,
        );
    }

    return $cities_data;
}

function vqh_get_geo_cities($upcoming_only = false)
{
    global $wpdb;
    $today = date('Y-m-d');

    if ($upcoming_only) {
        $sql = $wpdb->prepare(
            "
            SELECT DISTINCT
                m.city_id,
                m.cityname,
                m.city_slug,
                m.lat,
                m.lng
            FROM {$wpdb->prefix}multicity m
            WHERE EXISTS (
                SELECT 1
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->prefix}postmeta pm_city ON p.ID = pm_city.post_id AND pm_city.meta_key = 'post_city_id'
                LEFT JOIN {$wpdb->prefix}postmeta pm_start ON p.ID = pm_start.post_id AND pm_start.meta_key = 'st_date'
                LEFT JOIN {$wpdb->prefix}postmeta pm_start_alt ON p.ID = pm_start_alt.post_id AND pm_start_alt.meta_key = 'event_start_date'
                LEFT JOIN {$wpdb->prefix}postmeta pm_end ON p.ID = pm_end.post_id AND pm_end.meta_key = 'end_date'
                LEFT JOIN {$wpdb->prefix}postmeta pm_end_alt ON p.ID = pm_end_alt.post_id AND pm_end_alt.meta_key = 'event_end_date'
                LEFT JOIN {$wpdb->prefix}postmeta pm_addr ON p.ID = pm_addr.post_id AND pm_addr.meta_key = 'address'
                WHERE p.post_type = 'listado'
                AND p.post_status = 'publish'
                AND (
                    COALESCE(NULLIF(pm_start.meta_value, ''), NULLIF(pm_start_alt.meta_value, '')) >= %s
                    OR COALESCE(NULLIF(pm_end.meta_value, ''), NULLIF(pm_end_alt.meta_value, '')) >= %s
                )
                AND (
                    CAST(pm_city.meta_value AS UNSIGNED) = m.city_id
                    OR (
                        (pm_city.meta_value IS NULL OR pm_city.meta_value = '')
                        AND (
                            p.post_title LIKE CONCAT('%', m.cityname, '%')
                            OR pm_addr.meta_value LIKE CONCAT('%', m.cityname, '%')
                        )
                    )
                )
            )
            ORDER BY m.cityname ASC
            ",
            $today,
            $today
        );
    } else {
        $sql = "
            SELECT DISTINCT
                m.city_id,
                m.cityname,
                m.city_slug,
                m.lat,
                m.lng
            FROM {$wpdb->prefix}multicity m
            INNER JOIN {$wpdb->prefix}postmeta pm_city ON m.city_id = pm_city.meta_value
            INNER JOIN {$wpdb->posts} p ON pm_city.post_id = p.ID
            WHERE pm_city.meta_key = 'post_city_id'
            AND p.post_type = 'listado'
            AND p.post_status = 'publish'
            ORDER BY m.cityname ASC
        ";
    }

    return $wpdb->get_results($sql);
}

function vqh_get_map_events()
{
    global $wpdb;

    $today = current_time('Y-m-d');
    $sql = $wpdb->prepare(
        "
        SELECT
            p.ID,
            p.post_title,
            COALESCE(NULLIF(pm_start.meta_value, ''), NULLIF(pm_start_alt.meta_value, '')) AS start_date,
            COALESCE(NULLIF(pm_end.meta_value, ''), NULLIF(pm_end_alt.meta_value, '')) AS end_date,
            pm_time.meta_value AS start_time,
            pm_address.meta_value AS address,
            m.city_id,
            m.cityname,
            m.city_slug,
            m.lat AS city_lat,
            m.lng AS city_lng,
            pm_lat.meta_value AS event_lat,
            pm_lng.meta_value AS event_lng
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm_city
            ON pm_city.post_id = p.ID
            AND pm_city.meta_key = 'post_city_id'
        LEFT JOIN {$wpdb->prefix}multicity m
            ON m.city_id = CAST(pm_city.meta_value AS UNSIGNED)
        LEFT JOIN {$wpdb->postmeta} pm_start
            ON pm_start.post_id = p.ID
            AND pm_start.meta_key = 'st_date'
        LEFT JOIN {$wpdb->postmeta} pm_start_alt
            ON pm_start_alt.post_id = p.ID
            AND pm_start_alt.meta_key = 'event_start_date'
        LEFT JOIN {$wpdb->postmeta} pm_end
            ON pm_end.post_id = p.ID
            AND pm_end.meta_key = 'end_date'
        LEFT JOIN {$wpdb->postmeta} pm_end_alt
            ON pm_end_alt.post_id = p.ID
            AND pm_end_alt.meta_key = 'event_end_date'
        LEFT JOIN {$wpdb->postmeta} pm_time
            ON pm_time.post_id = p.ID
            AND pm_time.meta_key = 'st_time'
        LEFT JOIN {$wpdb->postmeta} pm_address
            ON pm_address.post_id = p.ID
            AND pm_address.meta_key = 'address'
        LEFT JOIN {$wpdb->postmeta} pm_lat
            ON pm_lat.post_id = p.ID
            AND pm_lat.meta_key = 'geo_latitude'
        LEFT JOIN {$wpdb->postmeta} pm_lng
            ON pm_lng.post_id = p.ID
            AND pm_lng.meta_key = 'geo_longitude'
        WHERE p.post_type = 'listado'
          AND p.post_status = 'publish'
          AND (
              COALESCE(NULLIF(pm_start.meta_value, ''), NULLIF(pm_start_alt.meta_value, '')) >= %s
              OR COALESCE(NULLIF(pm_end.meta_value, ''), NULLIF(pm_end_alt.meta_value, '')) >= %s
          )
        GROUP BY p.ID
        ORDER BY start_date ASC, p.post_title ASC
        LIMIT 500
        ",
        $today,
        $today
    );

    $rows = $wpdb->get_results($sql, ARRAY_A);
    $events = array();
    $cities = $wpdb->get_results(
        "SELECT city_id, cityname, city_slug, lat, lng FROM {$wpdb->prefix}multicity ORDER BY CHAR_LENGTH(cityname) DESC, cityname ASC"
    );

    foreach ($rows as $row) {
        $city_id = (int) $row['city_id'];
        $city_name = $row['cityname'];
        $city_slug = $row['city_slug'];
        $city_latitude = $row['city_lat'];
        $city_longitude = $row['city_lng'];

        if (!$city_id) {
            foreach ($cities as $city) {
                $city_pattern = '/(?<!\p{L})' . preg_quote($city->cityname, '/') . '(?!\p{L})/iu';
                if (preg_match($city_pattern, $row['post_title'])) {
                    $city_id = (int) $city->city_id;
                    $city_name = $city->cityname;
                    $city_slug = $city->city_slug;
                    $city_latitude = $city->lat;
                    $city_longitude = $city->lng;
                    break;
                }
            }
        }

        if (!$city_id) {
            foreach ($cities as $city) {
                $city_pattern = '/(?<!\p{L})' . preg_quote($city->cityname, '/') . '(?!\p{L})/iu';
                if (preg_match($city_pattern, $row['address'])) {
                    $city_id = (int) $city->city_id;
                    $city_name = $city->cityname;
                    $city_slug = $city->city_slug;
                    $city_latitude = $city->lat;
                    $city_longitude = $city->lng;
                    break;
                }
            }
        }

        if (!$city_id) {
            continue;
        }

        $latitude = is_numeric($row['event_lat']) ? (float) $row['event_lat'] : (float) $city_latitude;
        $longitude = is_numeric($row['event_lng']) ? (float) $row['event_lng'] : (float) $city_longitude;
        if (!$latitude || !$longitude) {
            continue;
        }

        if (!isset($events[$city_id])) {
            $events[$city_id] = array(
                'city_id' => $city_id,
                'city_name' => $city_name,
                'city_slug' => $city_slug,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'items' => array(),
            );
        }

        $events[$city_id]['items'][] = array(
            'id' => (int) $row['ID'],
            'title' => $row['post_title'],
            'url' => get_permalink((int) $row['ID']),
            'date' => $row['start_date'],
            'end_date' => $row['end_date'],
            'time' => $row['start_time'],
            'address' => $row['address'],
            'approximate' => !is_numeric($row['event_lat']) || !is_numeric($row['event_lng']),
        );
    }

    return array_values($events);
}

function vqh_enqueue_map_assets()
{
    if (!is_page('mapa')) {
        return;
    }

    wp_enqueue_style('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4');
    wp_enqueue_script('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true);
    wp_enqueue_script(
        'vqh-map',
        get_stylesheet_directory_uri() . '/js/mapa.js',
        array('leaflet'),
        filemtime(get_stylesheet_directory() . '/js/mapa.js'),
        true
    );
    wp_localize_script('vqh-map', 'vqhMapData', array(
        'cities' => vqh_get_map_events(),
        'homeUrl' => home_url('/'),
    ));
}
add_action('wp_enqueue_scripts', 'vqh_enqueue_map_assets');

function vqh_ensure_map_page_exists()
{
    if (get_page_by_path('mapa')) {
        return;
    }

    wp_insert_post(array(
        'post_title' => 'Mapa',
        'post_name' => 'mapa',
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_content' => 'Explora los eventos próximos por ciudad en el mapa de España.',
    ));
}
add_action('init', 'vqh_ensure_map_page_exists', 20);

function vqh_get_footer_event_categories()
{
    return array(
        'musica' => array('label' => 'Música', 'terms' => array('musical')),
        'teatro' => array('label' => 'Teatro', 'terms' => array('escena', 'teatro')),
        'cine' => array('label' => 'Cine', 'terms' => array('cine')),
        'exposiciones' => array('label' => 'Exposiciones', 'terms' => array('exposiciones')),
        'ferias' => array('label' => 'Ferias', 'terms' => array('ferias', 'fiestas')),
    );
}

function vqh_get_category_directory_events($category_slug)
{
    $categories = vqh_get_footer_event_categories();
    if (empty($categories[$category_slug])) {
        return array();
    }

    $term_ids = array();
    foreach ($categories[$category_slug]['terms'] as $term_slug) {
        $term = get_term_by('slug', $term_slug, 'ecategory');
        if ($term && !is_wp_error($term)) {
            $term_ids[] = (int) $term->term_id;
        }
    }

    if (empty($term_ids)) {
        return array();
    }

    $today = current_time('Y-m-d');
    $query = new WP_Query(array(
        'post_type' => 'listado',
        'post_status' => 'publish',
        'posts_per_page' => 100,
        'orderby' => 'date',
        'order' => 'ASC',
        'tax_query' => array(
            array(
                'taxonomy' => 'ecategory',
                'field' => 'term_id',
                'terms' => $term_ids,
                'include_children' => true,
            ),
        ),
        'meta_query' => array(
            'relation' => 'OR',
            array('key' => 'st_date', 'value' => $today, 'compare' => '>=', 'type' => 'DATE'),
            array('key' => 'event_start_date', 'value' => $today, 'compare' => '>=', 'type' => 'DATE'),
            array('key' => 'end_date', 'value' => $today, 'compare' => '>=', 'type' => 'DATE'),
            array('key' => 'event_end_date', 'value' => $today, 'compare' => '>=', 'type' => 'DATE'),
        ),
        'ignore_sticky_posts' => true,
    ));

    $events = array();
    foreach ($query->posts as $post) {
        $start_date = get_post_meta($post->ID, 'st_date', true);
        if (empty($start_date)) {
            $start_date = get_post_meta($post->ID, 'event_start_date', true);
        }

        $events[] = array(
            'id' => (int) $post->ID,
            'title' => get_the_title($post->ID),
            'url' => get_permalink($post->ID),
            'date' => $start_date,
            'time' => get_post_meta($post->ID, 'st_time', true),
        );
    }

    return $events;
}

add_filter('query_vars', function ($vars) {
    $vars[] = 'vqh_category_archive';
    $vars[] = 'vqh_category_slug';
    return $vars;
});

add_action('init', function () {
    add_rewrite_rule(
        '^eventos/([^/]+)/?$',
        'index.php?vqh_category_archive=1&vqh_category_slug=$matches[1]',
        'top'
    );
});

add_filter('template_include', function ($template) {
    if (!get_query_var('vqh_category_archive')) {
        return $template;
    }

    $category_template = locate_template('page-eventos-categoria.php');
    return $category_template ?: $template;
}, 99);

function vqh_haversine_km($lat1, $lng1, $lat2, $lng2)
{
    $earth_radius = 6371;
    $lat1 = deg2rad((float) $lat1);
    $lng1 = deg2rad((float) $lng1);
    $lat2 = deg2rad((float) $lat2);
    $lng2 = deg2rad((float) $lng2);

    $delta_lat = $lat2 - $lat1;
    $delta_lng = $lng2 - $lng1;
    $a = sin($delta_lat / 2) * sin($delta_lat / 2)
        + cos($lat1) * cos($lat2) * sin($delta_lng / 2) * sin($delta_lng / 2);

    return $earth_radius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
}

function vqh_find_nearest_city($lat, $lng, $cities = null)
{
    if ($cities === null) {
        $cities = vqh_get_geo_cities(false);
    }

    $nearest = null;
    $best_distance = PHP_FLOAT_MAX;

    foreach ($cities as $city) {
        if ($city->lat === '' || $city->lng === '') {
            continue;
        }

        $distance = vqh_haversine_km($lat, $lng, $city->lat, $city->lng);
        if ($distance < $best_distance) {
            $best_distance = $distance;
            $nearest = $city;
        }
    }

    if (!$nearest) {
        return null;
    }

    return array(
        'id'       => (int) $nearest->city_id,
        'name'     => $nearest->cityname,
        'slug'     => $nearest->city_slug,
        'lat'      => $nearest->lat,
        'lng'      => $nearest->lng,
        'distance' => round($best_distance, 2),
    );
}

function vqh_get_client_ip()
{
    $candidates = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');

    foreach ($candidates as $key) {
        if (empty($_SERVER[$key])) {
            continue;
        }

        $value = sanitize_text_field(wp_unslash($_SERVER[$key]));
        foreach (explode(',', $value) as $ip) {
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return '';
}

function vqh_is_private_ip($ip)
{
    return !filter_var(
        $ip,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    );
}

function vqh_get_coords_from_ip($ip = null)
{
    if ($ip === null) {
        $ip = vqh_get_client_ip();
    }

    if ($ip === '' || vqh_is_private_ip($ip)) {
        return null;
    }

    $response = wp_remote_get(
        'https://ipapi.co/' . rawurlencode($ip) . '/json/',
        array('timeout' => 5)
    );

    if (is_wp_error($response)) {
        return null;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($data['latitude']) || empty($data['longitude'])) {
        return null;
    }

    return array(
        'lat' => (float) $data['latitude'],
        'lng' => (float) $data['longitude'],
    );
}

function vqh_is_site_home_request()
{
    $request_uri = trim((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $home_path = trim((string) parse_url(home_url('/'), PHP_URL_PATH), '/');

    return $request_uri === $home_path
        || $request_uri === $home_path . '/index.php'
        || $request_uri === 'index.php'
        || $request_uri === '';
}

function vqh_set_city_cookie($city_slug)
{
    setcookie(
        'vqh_city_selected',
        sanitize_text_field($city_slug),
        time() + (30 * DAY_IN_SECONDS),
        '/'
    );
}

function vqh_redirect_to_city($city_slug)
{
    wp_safe_redirect(home_url('/' . trailingslashit(sanitize_title($city_slug))));
    exit;
}

// 30.1. AJAX: Obtener ciudades para banner y geolocalización
add_action('wp_ajax_vqh_get_all_cities', 'vqh_ajax_get_all_cities');
add_action('wp_ajax_nopriv_vqh_get_all_cities', 'vqh_ajax_get_all_cities');
function vqh_ajax_get_all_cities()
{
    $map_events = vqh_get_map_events();
    $cities = array();

    foreach ($map_events as $map_city) {
        $cities[] = (object) array(
            'city_id' => $map_city['city_id'],
            'cityname' => $map_city['city_name'],
            'city_slug' => $map_city['city_slug'],
            'lat' => $map_city['latitude'],
            'lng' => $map_city['longitude'],
        );
    }

    if (empty($cities)) {
        $cities = vqh_get_geo_cities(false);
    }

    wp_send_json_success(vqh_format_geo_cities($cities));
}

add_action('wp_ajax_vqh_get_nearest_city', 'vqh_ajax_get_nearest_city');
add_action('wp_ajax_nopriv_vqh_get_nearest_city', 'vqh_ajax_get_nearest_city');
function vqh_ajax_get_nearest_city()
{
    $lat = isset($_POST['lat']) ? floatval($_POST['lat']) : 0;
    $lng = isset($_POST['lng']) ? floatval($_POST['lng']) : 0;

    if (!$lat && !$lng) {
        wp_send_json_error(array('message' => 'Coordenadas inválidas'));
    }

    $nearest = vqh_find_nearest_city($lat, $lng);
    if (!$nearest) {
        wp_send_json_error(array('message' => 'No se encontró ninguna ciudad cercana'));
    }

    wp_send_json_success($nearest);
}

// 30.2. ENCULAR SCRIPT (CARGA CON PARÁMETRO)
// =================================================================
function vqh_enqueue_geolocation_script()
{
    if (is_admin()) {
        return;
    }

    // Usar funciones nativas de WordPress para detectar home
    $is_home_wp = is_front_page() || is_home();
    $is_change_city = get_query_var('vqh_change_city') || isset($_GET['vqh_change_city']);
    $request_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $is_ciudades_page = is_page('ciudades-con-eventos') || is_page('Ciudades con Eventos') || $request_path === 'ciudades-con-eventos';

    // Encolar el script en el frontend y dejar que el propio JS decida cuándo mostrar el banner.
    if (!is_admin()) {
        wp_enqueue_script(
            'vqh-geolocation',
            get_stylesheet_directory_uri() . '/js/geolocation.js',
            array(),
            file_exists(get_stylesheet_directory() . '/js/geolocation.js') ? filemtime(get_stylesheet_directory() . '/js/geolocation.js') : '1.0.0',
            false
        );

        wp_localize_script('vqh-geolocation', 'vqhGeolocationData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'homeUrl' => trailingslashit(home_url('/')),
        ));

        // Si hay parámetro de cambio de ciudad O es página de ciudades, forzar clase CSS para mostrar banner
        if ($is_change_city || $is_ciudades_page) {
            add_filter('body_class', 'vqh_add_show_banner_class');
        }
    }
}
add_action('wp_enqueue_scripts', 'vqh_enqueue_geolocation_script');

function vqh_enqueue_home_ads_script()
{
    if (is_admin()) {
        return;
    }

    if (!is_front_page() && !is_home()) {
        return;
    }

    wp_enqueue_script(
        'vqh-home-ads',
        get_stylesheet_directory_uri() . '/js/home-ads.js',
        array(),
        file_exists(get_stylesheet_directory() . '/js/home-ads.js') ? filemtime(get_stylesheet_directory() . '/js/home-ads.js') : '1.0.0',
        true
    );

    wp_localize_script('vqh-home-ads', 'vqhHomeAdsData', array(
        'categories' => vqh_get_home_ads_categories_data(),
    ));
}
add_action('wp_enqueue_scripts', 'vqh_enqueue_home_ads_script');

function vqh_get_home_ads_categories_data()
{
    if (!taxonomy_exists('ecategory')) {
        return array();
    }

    $parents = get_terms(array(
        'taxonomy' => 'ecategory',
        'hide_empty' => false,
        'parent' => 0,
        'orderby' => 'name',
        'order' => 'ASC',
    ));

    if (is_wp_error($parents) || empty($parents)) {
        return array();
    }

    $result = array();

    foreach ($parents as $parent) {
        $parent_count = (int) $parent->count;
        $children = get_terms(array(
            'taxonomy' => 'ecategory',
            'hide_empty' => false,
            'parent' => (int) $parent->term_id,
            'orderby' => 'name',
            'order' => 'ASC',
        ));

        $children_data = array();
        $children_total = 0;
        if (!is_wp_error($children) && !empty($children)) {
            foreach ($children as $child) {
                $child_count = (int) $child->count;
                if ($child_count <= 0) {
                    continue;
                }

                $children_total += $child_count;
                $children_data[] = array(
                    'name' => $child->name,
                    'slug' => $child->slug,
                    'count' => $child_count,
                    'url' => add_query_arg(array('eco_cat' => $child->slug), home_url('/')),
                );
            }
        }

        // Mostrar solo categorías con eventos reales.
        // Si el padre no tiene conteo propio pero sí hijos activos,
        // mostramos el padre con suma de hijos para facilitar navegación.
        $display_count = $parent_count > 0 ? $parent_count : $children_total;
        if ($display_count <= 0 && empty($children_data)) {
            continue;
        }

        $result[] = array(
            'name' => $parent->name,
            'slug' => $parent->slug,
            'count' => $display_count,
            'url' => add_query_arg(array('eco_cat' => $parent->slug), home_url('/')),
            'children' => $children_data,
        );
    }

    return $result;
}

/**
 * Añade la clase .show-banner al body cuando se solicita cambio de ciudad
 */
function vqh_add_show_banner_class($classes)
{
    $classes[] = 'show-banner';
    return $classes;
}


// =================================================================
// 30.3. REDIRECCIÓN POR GEOLOCALIZACIÓN
// =================================================================
add_action('template_redirect', 'vqh_first_visit_geo_redirect', 1);
function vqh_first_visit_geo_redirect()
{
    if (is_admin() || wp_doing_ajax()) {
        return;
    }

    if (get_query_var('vqh_change_city') || isset($_GET['vqh_change_city'])) {
        return;
    }

    if (get_query_var('vqh_show_home') || isset($_GET['vqh_show_home'])) {
        return;
    }

    if (isset($_COOKIE['vqh_city_selected']) || isset($_COOKIE['vqh_city_dismissed'])) {
        return;
    }

    if (!vqh_is_site_home_request()) {
        return;
    }

    $coords = vqh_get_coords_from_ip();
    if (!$coords) {
        return;
    }

    $nearest = vqh_find_nearest_city($coords['lat'], $coords['lng']);
    if (!$nearest || empty($nearest['slug'])) {
        return;
    }

    vqh_set_city_cookie($nearest['slug']);
    vqh_redirect_to_city($nearest['slug']);
}

add_action('template_redirect', 'vqh_geolocation_redirect', 99);
function vqh_geolocation_redirect()
{
    if (get_query_var('vqh_change_city') || isset($_GET['vqh_change_city'])) {
        return;
    }

    if (get_query_var('vqh_show_home') || isset($_GET['vqh_show_home'])) {
        return;
    }

    if (get_query_var('city_archive') || get_query_var('city')) {
        return;
    }

    $path_city = vqh_get_path_city_slug();
    if ($path_city && vqh_is_valid_city_slug($path_city)) {
        return;
    }

    if (!vqh_is_site_home_request()) {
        return;
    }

    if (!isset($_COOKIE['vqh_city_selected'])) {
        return;
    }

    $city_slug = sanitize_text_field(wp_unslash($_COOKIE['vqh_city_selected']));
    if ($city_slug === 'home') {
        return;
    }

    global $wpdb;
    $city_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT city_id FROM {$wpdb->prefix}multicity WHERE city_slug = %s LIMIT 1",
        $city_slug
    ));

    if ($city_exists) {
        vqh_redirect_to_city($city_slug);
    }
}

// DEBUG: Log cuando se accede con vqh_show_home
add_action('template_redirect', 'vqh_debug_show_home', 0);
function vqh_debug_show_home()
{
    if (isset($_GET['vqh_show_home'])) {
        error_log('[VQH DEBUG] vqh_show_home=1 detectado en: ' . $_SERVER['REQUEST_URI']);
        error_log('[VQH DEBUG] Cookie: ' . (isset($_COOKIE['vqh_city_selected']) ? $_COOKIE['vqh_city_selected'] : 'NO EXISTE'));
    }
}


// 30.4. Reset de geolocalización (para testing)
add_action('template_redirect', 'vqh_geolocation_reset');
function vqh_geolocation_reset()
{
    if (isset($_GET['vqh_reset_geo']) && current_user_can('manage_options')) {
        setcookie('vqh_city_selected', '', time() - 3600, '/');
        wp_redirect(home_url('/'));
        exit;
    }
}

// 30.5. Banner para admin: mostrar cookie actual y permitir reset
add_action('wp_footer', 'vqh_geo_debug_info');
function vqh_geo_debug_info()
{
    if (!current_user_can('manage_options')) {
        return;
    }
    $cookie = isset($_COOKIE['vqh_city_selected']) ? $_COOKIE['vqh_city_selected'] : 'NO EXISTE';
    $current_uri = $_SERVER['REQUEST_URI'];
    echo '<div style="position:fixed;bottom:10px;right:10px;background:#000;color:#0f0;padding:15px;z-index:99999;font-size:12px;font-family:monospace;border:2px solid #0f0;">';
    echo '<strong>🔍 GEO DEBUG</strong><br>';
    echo 'Cookie: ' . esc_html($cookie) . '<br>';
    echo 'URI: ' . esc_html($current_uri) . '<br>';
    echo 'vqh_show_home: ' . (isset($_GET['vqh_show_home']) ? 'SI' : 'NO') . '<br>';
    echo 'Script cargado: ' . (wp_script_is('vqh-geolocation', 'enqueued') ? 'SI' : 'NO') . '<br>';
    echo '<a href="' . home_url('/?vqh_reset_geo=1') . '" style="color:#ff0;text-decoration:underline;">RESET COOKIE</a> | ';
    echo '<a href="' . home_url('/?vqh_show_home=1') . '" style="color:#ff0;text-decoration:underline;">FORZAR HOME</a>';
    echo '</div>';
}

// DEBUG: Inserta un pequeño script inline para verificar que este functions.php se carga
add_action('wp_footer', 'vqh_inline_footer_test', 9998);
function vqh_inline_footer_test()
{
    // Mensaje de consola simple para detectar el tema activo y confirmar carga
    echo "<script>console.error('VQH inline test: functions.php from " . esc_js(get_stylesheet_directory_uri()) . "');document.documentElement.setAttribute('data-vqh-inline-footer-test','true');</script>";
}

// =================================================================
// SHORTCODE: LANDING DE CIUDADES CON CONTADORES
// Uso: [vqh_ciudades_landing]
// =================================================================
add_shortcode('vqh_ciudades_landing', 'vqh_render_ciudades_landing');
/*
function vqh_render_ciudades_landing()
{
    global $wpdb;

    // Fecha actual para filtrar eventos futuros
    $today = date('Y-m-d');

    // Consulta optimizada: Ciudades con al menos 1 evento publicado y fecha >= hoy
    $ciudades = $wpdb->get_results($wpdb->prepare("
        SELECT DISTINCT 
            m.city_id,
            m.cityname,
            m.city_slug,
            m.lat,
            m.lng,
            COUNT(p.ID) as event_count
        FROM {$wpdb->prefix}multicity m
        INNER JOIN {$wpdb->prefix}postmeta pm_city ON m.city_id = pm_city.meta_value
        INNER JOIN {$wpdb->prefix}posts p ON pm_city.post_id = p.ID
        INNER JOIN {$wpdb->prefix}postmeta pm_date ON p.ID = pm_date.post_id
        WHERE pm_city.meta_key = 'post_city_id'
        AND pm_date.meta_key = 'st_date'
        AND p.post_type = 'listado'
        AND p.post_status = 'publish'
        AND m.lat IS NOT NULL AND m.lat != ''
        AND m.lng IS NOT NULL AND m.lng != ''
        AND pm_date.meta_value >= %s
        GROUP BY m.city_id, m.cityname, m.city_slug, m.lat, m.lng
        HAVING COUNT(p.ID) > 0
        ORDER BY event_count DESC, m.cityname ASC
    ", $today));

    if (empty($ciudades)) {
        return '<p class="vqh-no-cities" style="text-align:center;padding:3rem;color:#666;">No hay ciudades con eventos próximos disponibles.</p>';
    }

    $total_ciudades = count($ciudades);
    $total_eventos = array_sum(array_column($ciudades, 'event_count'));

    ob_start();
?>
    <div class="vqh-ciudades-landing">
        <div class="vqh-landing-header">
            <h1 class="vqh-landing-title">Agenda de Ocio y Cultura en España</h1>
            <p class="vqh-landing-subtitle">
                Descubre <strong><?php echo intval($total_eventos); ?> eventos</strong> en
                <strong><?php echo intval($total_ciudades); ?> ciudades</strong>
            </p>
        </div>

        <div class="vqh-ciudades-grid">
            <?php foreach ($ciudades as $ciudad):
                // Gradiente según cantidad de eventos
                $gradient = $ciudad->event_count > 10 ?
                    'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' : ($ciudad->event_count > 5 ?
                        'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)' :
                        'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)');
            ?>
                <a href="<?php echo esc_url(home_url('/' . $ciudad->city_slug . '/')); ?>"
                    class="vqh-ciudad-card"
                    style="background: <?php echo $gradient; ?>;">
                    <div class="vqh-ciudad-info">
                        <h2 class="vqh-ciudad-nombre"><?php echo esc_html($ciudad->cityname); ?></h2>
                        <div class="vqh-ciudad-stats">
                            <span class="vqh-ciudad-eventos">
                                <?php echo intval($ciudad->event_count); ?>
                                <?php echo $ciudad->event_count === 1 ? 'evento' : 'eventos'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="vqh-ciudad-arrow">→</div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <style>
        .vqh-ciudades-landing {
            max-width: 1200px;
            margin: 0 auto;
            padding: 3rem 1.5rem;
        }

        .vqh-landing-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .vqh-landing-title {
            font-size: 2.5rem;
            color: #2c3e50;
            margin-bottom: 1rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .vqh-landing-subtitle {
            font-size: 1.2rem;
            color: #666;
        }

        .vqh-landing-subtitle strong {
            color: #d32f2f;
        }

        .vqh-ciudades-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .vqh-ciudad-card {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-radius: 12px;
            color: #fff;
            text-decoration: none;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .vqh-ciudad-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .vqh-ciudad-info {
            flex: 1;
        }

        .vqh-ciudad-nombre {
            font-size: 1.4rem;
            margin: 0 0 0.5rem 0;
            font-weight: 600;
            line-height: 1.3;
        }

        .vqh-ciudad-stats {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .vqh-ciudad-eventos {
            font-size: 1rem;
            opacity: 0.95;
            font-weight: 500;
        }

        .vqh-ciudad-arrow {
            font-size: 2rem;
            opacity: 0.8;
            margin-left: 1rem;
        }

        .vqh-no-cities {
            text-align: center;
            padding: 3rem;
            color: #666;
            font-size: 1.2rem;
        }

        @media (max-width: 768px) {
            .vqh-landing-title {
                font-size: 2rem;
            }

            .vqh-ciudades-grid {
                grid-template-columns: 1fr;
            }

            .vqh-ciudad-card {
                padding: 1.25rem;
            }
        }
    </style>
<?php
    return ob_get_clean();
}
*/
/**
 * Shortcode para listar todas las ciudades con eventos
 * Usa la misma lógica de DB que el selector de ciudades (tabla multicity)
 * Uso: [vqh_ciudades_landing]
 */
function vqh_ciudades_landing_shortcode()
{
    global $wpdb;

    // Fecha actual para filtrar eventos futuros o de hoy
    $today = date('Y-m-d');

    // Consulta compatible con tanto st_date/end_date como event_start_date/event_end_date.
    $sql = $wpdb->prepare("
        SELECT DISTINCT 
            m.city_id,
            m.cityname,
            m.city_slug,
            m.lat,
            m.lng,
            COUNT(DISTINCT p.ID) as event_count
        FROM {$wpdb->prefix}multicity m
        INNER JOIN {$wpdb->prefix}postmeta pm_city ON m.city_id = pm_city.meta_value
        INNER JOIN {$wpdb->prefix}posts p ON pm_city.post_id = p.ID
        LEFT JOIN {$wpdb->prefix}postmeta pm_date_1 ON p.ID = pm_date_1.post_id AND pm_date_1.meta_key = 'st_date'
        LEFT JOIN {$wpdb->prefix}postmeta pm_date_2 ON p.ID = pm_date_2.post_id AND pm_date_2.meta_key = 'event_start_date'
        LEFT JOIN {$wpdb->prefix}postmeta pm_date_3 ON p.ID = pm_date_3.post_id AND pm_date_3.meta_key = 'end_date'
        LEFT JOIN {$wpdb->prefix}postmeta pm_date_4 ON p.ID = pm_date_4.post_id AND pm_date_4.meta_key = 'event_end_date'
        WHERE pm_city.meta_key = 'post_city_id'
        AND p.post_type = 'listado'
        AND p.post_status = 'publish'
        AND m.lat IS NOT NULL AND m.lat != ''
        AND m.lng IS NOT NULL AND m.lng != ''
        AND (
            (pm_date_1.meta_value IS NOT NULL AND CAST(pm_date_1.meta_value AS DATE) >= %s)
            OR (pm_date_2.meta_value IS NOT NULL AND CAST(pm_date_2.meta_value AS DATE) >= %s)
            OR (pm_date_3.meta_value IS NOT NULL AND CAST(pm_date_3.meta_value AS DATE) >= %s)
            OR (pm_date_4.meta_value IS NOT NULL AND CAST(pm_date_4.meta_value AS DATE) >= %s)
        )
        GROUP BY m.city_id, m.cityname, m.city_slug, m.lat, m.lng
        HAVING COUNT(DISTINCT p.ID) > 0
        ORDER BY event_count DESC, m.cityname ASC
    ", $today, $today, $today, $today);

    $cities = $wpdb->get_results($sql);

    if (empty($cities)) {
        return '<div class="vqh-no-cities"><p>No hay ciudades con eventos disponibles en este momento.</p></div>';
    }

    ob_start();
?>

    <div class="vqh-ciudades-list-container">
        <h2 class="vqh-ciudades-title">Selecciona una ciudad para ver sus eventos</h2>
        <div class="vqh-ciudades-grid">
            <?php foreach ($cities as $city) :
                // Construcción segura de la URL basada en el slug
                $city_url = home_url('/' . trim($city->city_slug, '/') . '/');
            ?>
                <a href="<?php echo esc_url($city_url); ?>" class="vqh-ciudad-card">
                    <div class="vqh-ciudad-name"><?php echo esc_html($city->cityname); ?></div>
                    <div class="vqh-ciudad-count"><?php echo intval($city->event_count); ?> eventos</div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <style>
        .vqh-ciudades-list-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
            text-align: center;
        }

        .vqh-ciudades-title {
            margin-bottom: 30px;
            font-size: 2rem;
            color: #333;
        }

        .vqh-ciudades-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }

        .vqh-ciudad-card {
            display: block;
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            text-decoration: none;
            color: #333;
            transition: transform 0.2s, box-shadow 0.2s;
            border: 1px solid #eee;
        }

        .vqh-ciudad-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            background: #fff;
            border-color: #0073aa;
        }

        .vqh-ciudad-name {
            font-weight: bold;
            font-size: 1.2rem;
            margin-bottom: 5px;
            display: block;
        }

        .vqh-ciudad-count {
            font-size: 0.9rem;
            color: #666;
        }

        .vqh-no-cities {
            text-align: center;
            padding: 40px;
            color: #666;
        }
    </style>

<?php
    return ob_get_clean();
}
add_shortcode('vqh_ciudades_landing', 'vqh_ciudades_landing_shortcode');

function vqh_get_future_city_event_count($city_id)
{
    global $wpdb;

    $today = date('Y-m-d');

    $count = $wpdb->get_var($wpdb->prepare(
        "
        SELECT COUNT(DISTINCT p.ID)
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm_city ON p.ID = pm_city.post_id AND pm_city.meta_key = 'post_city_id'
        LEFT JOIN {$wpdb->postmeta} pm_start ON p.ID = pm_start.post_id AND pm_start.meta_key = 'st_date'
        LEFT JOIN {$wpdb->postmeta} pm_start_alt ON p.ID = pm_start_alt.post_id AND pm_start_alt.meta_key = 'event_start_date'
        WHERE p.post_type = 'listado'
          AND p.post_status = 'publish'
          AND CAST(pm_city.meta_value AS UNSIGNED) = %d
          AND (
              (pm_start.meta_value IS NOT NULL AND CAST(pm_start.meta_value AS DATE) >= %s)
              OR (pm_start_alt.meta_value IS NOT NULL AND CAST(pm_start_alt.meta_value AS DATE) >= %s)
          )
        ",
        intval($city_id),
        $today,
        $today
    ));

    return intval($count ?: 0);
}

function vqh_get_city_directory_priority_map()
{
    return array(
        'albacete' => 1,
        'barakaldo' => 1,
        'barcelona' => 1,
        'el-puerto-de-santa-maria' => 1,
        'granada' => 1,
        'madrid' => 1,
        'salamanca' => 1,
    );
}

function vqh_get_cities_with_future_event_count()
{
    global $wpdb;

    $cities = $wpdb->get_results(
        "
        SELECT city_id, cityname, city_slug
        FROM {$wpdb->prefix}multicity
        ORDER BY cityname ASC
        "
    );

    if (empty($cities)) {
        return array();
    }

    $priority_map = vqh_get_city_directory_priority_map();
    $filtered = array();

    foreach ($cities as $city) {
        $city_slug = sanitize_title((string) $city->city_slug);
        $event_count = vqh_get_future_city_event_count($city->city_id);

        if ($event_count <= 0 && !isset($priority_map[$city_slug])) {
            continue;
        }

        $city->event_count = $event_count > 0 ? $event_count : (isset($priority_map[$city_slug]) ? 1 : 0);

        if ($city->event_count <= 0) {
            continue;
        }

        $filtered[] = $city;
    }

    return $filtered;
}

function vqh_render_ciudades_directory_page()
{
    $cities = vqh_get_cities_with_future_event_count();
    $total_eventos = 0;
    foreach ($cities as $city) {
        $total_eventos += intval($city->event_count);
    }

    ob_start();
?>
    <div class="vqh-ciudades-directory">
        <div class="vqh-ciudades-hero">
            <div>
                <span class="vqh-kicker">Descubre tu ciudad</span>
                <h1 class="vqh-ciudades-directory-title">Ciudades con eventos</h1>
            </div>
            <div class="vqh-ciudades-summary">
                <strong><?php echo esc_html(count($cities)); ?></strong>
                <span>ciudades</span>
            </div>
        </div>

        <p class="vqh-ciudades-intro">Encuentra experiencias próximas en tu zona y reserva tu próxima escapada.</p>

        <?php if (empty($cities)) : ?>
            <p class="vqh-no-cities">No hay ciudades disponibles en este momento.</p>
        <?php else : ?>
            <ul class="vqh-ciudades-directory-list">
                <?php foreach ($cities as $city) :
                    $city_slug = trim((string) $city->city_slug);
                    $city_url = home_url('/' . $city_slug . '/');
                    $event_count = intval($city->event_count);
                ?>
                    <li>
                        <a href="<?php echo esc_url($city_url); ?>"
                            class="vqh-ciudad-directory-link"
                            data-city-slug="<?php echo esc_attr($city_slug); ?>"
                            data-city-name="<?php echo esc_attr($city->cityname); ?>"
                            onclick="(function(){try{document.cookie='vqh_city_selected=' + encodeURIComponent(this.getAttribute('data-city-slug')) + '; path=/; max-age=2592000';}catch(e){}return true;}).call(this);">
                            <span class="vqh-city-name"><?php echo esc_html($city->cityname); ?></span>
                            <span class="vqh-city-meta">
                                <span class="vqh-city-count"><?php echo esc_html($event_count); ?></span>
                                <span class="vqh-city-label"><?php echo _n('evento', 'eventos', $event_count, 'astra-child'); ?></span>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <style>
        .vqh-ciudades-directory {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2.5rem 1rem 4rem;
        }

        .vqh-ciudades-hero {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 0.75rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #ebeff5;
        }

        .vqh-kicker {
            display: inline-block;
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #4c79d7;
            margin-bottom: 0.5rem;
        }

        .vqh-ciudades-directory-title {
            margin: 0;
            font-size: clamp(2.2rem, 3vw, 3rem);
            line-height: 1.1;
            color: #1b2b3b;
            letter-spacing: -0.03em;
        }

        .vqh-ciudades-summary {
            min-width: 150px;
            text-align: center;
            padding: 0.9rem 1.1rem;
            border-radius: 14px;
            background: linear-gradient(135deg, #f2f7ff 0%, #edf4ff 100%);
            border: 1px solid #dde9fd;
            box-shadow: 0 10px 18px rgba(51, 92, 172, 0.08);
        }

        .vqh-ciudades-summary strong {
            display: block;
            font-size: 2rem;
            line-height: 1;
            color: #1d4ec5;
        }

        .vqh-ciudades-summary span {
            display: block;
            font-size: 0.8rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #54657d;
            margin-top: 0.3rem;
        }

        .vqh-ciudades-intro {
            margin: 1rem 0 1.5rem;
            font-size: 1.04rem;
            color: #586779;
            max-width: 720px;
        }

        .vqh-ciudades-directory-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 1rem;
        }

        .vqh-ciudades-directory-list li {
            margin: 0;
        }

        .vqh-ciudad-directory-link {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            width: 100%;
            min-height: 92px;
            padding: 1rem 1.1rem;
            border: 1px solid #e7edf5;
            border-radius: 16px;
            background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
            color: #1d2a39;
            text-decoration: none;
            box-shadow: 0 8px 20px rgba(17, 38, 60, 0.04);
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        }

        .vqh-ciudad-directory-link:hover {
            transform: translateY(-2px);
            border-color: #cfe0ff;
            box-shadow: 0 16px 28px rgba(22, 59, 118, 0.12);
            text-decoration: none;
        }

        .vqh-city-name {
            font-size: 1.04rem;
            font-weight: 700;
            line-height: 1.35;
            color: #1b2b3b;
        }

        .vqh-city-meta {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.45rem 0.7rem;
            border-radius: 999px;
            background: #edf4ff;
            color: #23499a;
            font-weight: 700;
            white-space: nowrap;
        }

        .vqh-city-count {
            font-size: 1.05rem;
            line-height: 1;
        }

        .vqh-city-label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .vqh-no-cities {
            color: #5d6d7e;
            font-size: 1rem;
            padding: 1.25rem 0;
        }

        @media (max-width: 640px) {
            .vqh-ciudades-hero {
                flex-direction: column;
                align-items: flex-start;
            }

            .vqh-ciudades-summary {
                width: 100%;
                text-align: left;
            }

            .vqh-ciudad-directory-link {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
<?php
    return ob_get_clean();
}

add_shortcode('vqh_ciudades_directory', 'vqh_render_ciudades_directory_page');

function vqh_get_blog_events_grouped_by_city()
{
    global $wpdb;

    $today = current_time('Y-m-d');
    $sql = $wpdb->prepare(
        "
        SELECT
            p.ID,
            p.post_title,
            p.post_name,
            pm_date.meta_value AS st_date,
            pm_time.meta_value AS st_time,
            pm_city.meta_value AS city_id,
            m.cityname,
            m.city_slug
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->term_relationships} tr
            ON tr.object_id = p.ID
        INNER JOIN {$wpdb->term_taxonomy} tt
            ON tt.term_taxonomy_id = tr.term_taxonomy_id
            AND tt.taxonomy = %s
        INNER JOIN {$wpdb->terms} t
            ON t.term_id = tt.term_id
            AND t.slug = %s
        LEFT JOIN {$wpdb->postmeta} pm_date
            ON pm_date.post_id = p.ID
            AND pm_date.meta_key = 'st_date'
        LEFT JOIN {$wpdb->postmeta} pm_time
            ON pm_time.post_id = p.ID
            AND pm_time.meta_key = 'st_time'
        LEFT JOIN {$wpdb->postmeta} pm_city
            ON pm_city.post_id = p.ID
            AND pm_city.meta_key = 'post_city_id'
        LEFT JOIN {$wpdb->prefix}multicity m
            ON m.city_id = CAST(pm_city.meta_value AS UNSIGNED)
        WHERE p.post_type = %s
          AND p.post_status = %s
          AND (pm_date.meta_value IS NULL OR pm_date.meta_value >= %s)
        GROUP BY p.ID
        ORDER BY m.cityname ASC, pm_date.meta_value ASC, p.post_title ASC
        LIMIT 200
        ",
        'ecategory',
        'blog',
        'listado',
        'publish',
        $today
    );

    $rows = $wpdb->get_results($sql, ARRAY_A);
    $groups = array();

    foreach ($rows as $row) {
        $city_name = !empty($row['cityname']) ? $row['cityname'] : 'Sin ciudad';
        $city_slug = !empty($row['city_slug']) ? trim((string) $row['city_slug']) : '';

        if (!isset($groups[$city_name])) {
            $groups[$city_name] = array(
                'city_name' => $city_name,
                'city_slug' => $city_slug,
                'items'     => array(),
            );
        }

        $groups[$city_name]['items'][] = array(
            'id'    => (int) $row['ID'],
            'title' => $row['post_title'],
            'url'   => get_permalink((int) $row['ID']),
            'date'  => $row['st_date'],
            'time'  => $row['st_time'],
        );
    }

    uasort($groups, function ($a, $b) {
        return strcasecmp($a['city_name'], $b['city_name']);
    });

    return $groups;
}

function vqh_render_blog_directory_page()
{
    $groups = vqh_get_blog_events_grouped_by_city();

    ob_start();
?>
    <div class="vqh-blog-directory">
        <div class="vqh-blog-header">
            <div>
                <span class="vqh-blog-kicker">Editorial</span>
                <h1 class="vqh-blog-title">Blog y noticias por ciudad</h1>
            </div>
            <div class="vqh-blog-summary">
                <strong><?php echo esc_html(count($groups)); ?></strong>
                <span>ciudades</span>
            </div>
        </div>

        <p class="vqh-blog-intro">Ideas, propuestas y artículos destacados de cada ciudad.</p>

        <?php if (empty($groups)) : ?>
            <p class="vqh-no-cities">No hay entradas de blog programadas en este momento.</p>
        <?php else : ?>
            <ul class="vqh-blog-city-list">
                <?php foreach ($groups as $city) : ?>
                    <li class="vqh-blog-city-item">
                        <h2 class="vqh-blog-city-name">
                            <?php if (!empty($city['city_slug'])) : ?>
                                <a href="<?php echo esc_url(home_url('/' . trim($city['city_slug'], '/') . '/')); ?>"><?php echo esc_html($city['city_name']); ?></a>
                            <?php else : ?>
                                <?php echo esc_html($city['city_name']); ?>
                            <?php endif; ?>
                        </h2>

                        <ul class="vqh-blog-event-list">
                            <?php foreach ($city['items'] as $event) : ?>
                                <li class="vqh-blog-event-item">
                                    <a href="<?php echo esc_url($event['url']); ?>"><?php echo esc_html($event['title']); ?></a>
                                    <?php if (!empty($event['date']) || !empty($event['time'])) : ?>
                                        <span class="vqh-blog-event-meta">
                                            <?php
                                            $meta_parts = array();
                                            if (!empty($event['date'])) {
                                                $meta_parts[] = esc_html(date_i18n('d M Y', strtotime($event['date'])));
                                            }
                                            if (!empty($event['time'])) {
                                                $meta_parts[] = esc_html($event['time']);
                                            }
                                            echo implode(' · ', $meta_parts);
                                            ?>
                                        </span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <style>
        .vqh-blog-directory {
            max-width: 1100px;
            margin: 0 auto;
            padding: 2.5rem 1rem 4rem;
        }

        .vqh-blog-header {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 0.9rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #ebeff5;
        }

        .vqh-blog-kicker {
            display: inline-block;
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #7a4ef0;
            margin-bottom: 0.5rem;
        }

        .vqh-blog-title {
            margin: 0;
            font-size: clamp(2.1rem, 3vw, 3rem);
            line-height: 1.1;
            color: #1b2b3b;
            letter-spacing: -0.03em;
        }

        .vqh-blog-summary {
            min-width: 140px;
            text-align: center;
            padding: 0.85rem 1rem;
            border-radius: 14px;
            background: linear-gradient(135deg, #f6f0ff 0%, #f1efff 100%);
            border: 1px solid #e7ddff;
            box-shadow: 0 10px 18px rgba(122, 78, 240, 0.08);
        }

        .vqh-blog-summary strong {
            display: block;
            font-size: 2rem;
            line-height: 1;
            color: #5b38d9;
        }

        .vqh-blog-summary span {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #544597;
        }

        .vqh-blog-intro {
            margin: 0 0 1.5rem;
            color: #4a5d73;
            font-size: 1.02rem;
        }

        .vqh-blog-city-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            gap: 1.5rem;
        }

        .vqh-blog-city-item {
            padding: 1.2rem 1.25rem;
            border: 1px solid #ecf0f7;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 2px 10px rgba(13, 31, 59, 0.04);
        }

        .vqh-blog-city-name {
            margin: 0 0 0.75rem;
            padding-left: 0.75rem;
            font-size: 1.3rem;
            color: #1f2c3d;
        }

        .vqh-blog-city-name a {
            color: inherit;
            text-decoration: none;
        }

        .vqh-blog-event-list {
            margin: 0 0 0 1.2rem;
            padding-left: 1.4rem;
            list-style: disc;
            color: #4a5d73;
        }

        .vqh-blog-event-item {
            margin-bottom: 0.55rem;
            padding-left: 0.25rem;
        }

        .vqh-blog-event-item a {
            color: #1d4ec5;
            text-decoration: none;
            font-weight: 600;
        }

        .vqh-blog-event-item a:hover {
            text-decoration: underline;
        }

        .vqh-blog-event-meta {
            display: block;
            margin-top: 0.08rem;
            color: #6b7a8d;
            font-size: 0.82rem;
        }

        @media (max-width: 768px) {
            .vqh-blog-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .vqh-blog-summary {
                width: 100%;
                text-align: left;
            }
        }
    </style>
<?php
    return ob_get_clean();
}

add_shortcode('vqh_blog_directory', 'vqh_render_blog_directory_page');

function vqh_ensure_blog_page_exists()
{
    if (is_admin()) {
        return;
    }

    $page = get_page_by_path('blog');
    if ($page instanceof WP_Post) {
        return;
    }

    $existing_posts_page_id = (int) get_option('page_for_posts');
    if ($existing_posts_page_id > 0 && get_post($existing_posts_page_id)) {
        return;
    }

    $blog_page = get_page_by_title('Blog');
    if ($blog_page instanceof WP_Post) {
        wp_update_post(array(
            'ID'          => $blog_page->ID,
            'post_name'   => 'blog',
            'post_status' => 'publish',
        ));
        return;
    }

    wp_insert_post(array(
        'post_title'   => 'Blog',
        'post_name'    => 'blog',
        'post_type'    => 'page',
        'post_status'  => 'publish',
        'post_content' => '[vqh_blog_directory]',
    ));
}
add_action('init', 'vqh_ensure_blog_page_exists', 20);

add_filter('template_include', 'vqh_force_blog_page_template', 20);
function vqh_force_blog_page_template($template)
{
    $request_path = trim((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $is_blog_request = $request_path === 'blog' || is_page('blog');

    if (!$is_blog_request) {
        return $template;
    }

    $blog_template = locate_template(array('page-blog.php'));
    if ($blog_template) {
        return $blog_template;
    }

    return $template;
}

// Si la URL coincide con la ruta de ciudades pero no existe una página real, renderizar el shortcode directamente.
add_action('template_redirect', 'vqh_handle_ciudades_route', 1);
function vqh_handle_ciudades_route()
{
    if (is_admin()) {
        return;
    }

    $request_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    if ($request_path !== 'ciudades') {
        if ($request_path !== 'ciudades-con-eventos') {
            return;
        }
    }

    global $wp_query;
    status_header(200);
    $wp_query->is_page = true;
    $wp_query->is_home = false;
    $wp_query->is_posts_page = false;
    $wp_query->is_404 = false;
    $page_slug = ($request_path === 'ciudades') ? 'ciudades' : 'ciudades-con-eventos';
    $wp_query->set('pagename', $page_slug);
    $wp_query->set('page_id', 0);
    $wp_query->set('post_type', 'page');
    $wp_query->set('name', $page_slug);
    $wp_query->queried_object = null;
    $wp_query->queried_object_id = 0;

    add_filter('the_content', 'vqh_render_ciudades_content', 20);
}

add_filter('template_include', 'vqh_force_page_template_for_ciudades', 99);
function vqh_force_page_template_for_ciudades($template)
{
    // Detectar página de ciudades por slug o por ID (más robusto que is_page)
    global $wp_query;
    $is_ciudades_page = false;

    // Método 1: Por query var (cuando está configurada como página de posts)
    if (isset($wp_query->queried_object) && is_object($wp_query->queried_object)) {
        if (isset($wp_query->queried_object->post_name) && $wp_query->queried_object->post_name === 'ciudades-con-eventos') {
            $is_ciudades_page = true;
        }
        if (isset($wp_query->queried_object->ID)) {
            // Método 2: Verificar si es la página configurada como "Ciudades con Eventos"
            $ciudades_page_id = get_option('page_for_posts'); // Normalmente la página de posts
            if ($wp_query->queried_object->ID == $ciudades_page_id) {
                // Verificar que el slug coincida
                $queried_slug = get_post_field('post_name', $wp_query->queried_object->ID);
                if ($queried_slug === 'ciudades-con-eventos') {
                    $is_ciudades_page = true;
                }
            }
        }
    }

    // Método 3: Fallback por REQUEST_URI
    if (!$is_ciudades_page) {
        $request_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        if ($request_path === 'ciudades-con-eventos') {
            $is_ciudades_page = true;
        }
    }

    if ($is_ciudades_page) {
        // Añadir clase show-banner si hay parámetro de cambio
        if (isset($_GET['vqh_change_city'])) {
            add_filter('body_class', 'vqh_add_show_banner_class');
        }

        $new_template = locate_template(array('page.php'));
        if ($new_template) {
            return $new_template;
        }
    }
    return $template;
}
/**
 * Fuerza la carga correcta de la página "Ciudades con Eventos"
 * evitando conflictos con la configuración de "Página para entradas"
 */
function vqh_force_ciudades_template($template)
{
    global $wp_query;

    $is_ciudades_page = false;
    $request_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

    if ($request_path === 'ciudades' || $request_path === 'ciudades-con-eventos') {
        $is_ciudades_page = true;
    }

    if (isset($wp_query->query_vars['pagename']) && in_array($wp_query->query_vars['pagename'], array('ciudades', 'ciudades-con-eventos'), true)) {
        $is_ciudades_page = true;
    }

    $page_id = get_page_by_path('ciudades');
    if ($page_id && isset($wp_query->queried_object_id) && $wp_query->queried_object_id == $page_id->ID) {
        $is_ciudades_page = true;
    }

    $page_id_alt = get_page_by_path('ciudades-con-eventos');
    if ($page_id_alt && isset($wp_query->queried_object_id) && $wp_query->queried_object_id == $page_id_alt->ID) {
        $is_ciudades_page = true;
    }

    if ($is_ciudades_page) {
        $template_name = ($request_path === 'ciudades') ? 'page-ciudades.php' : 'page-ciudades-con-eventos.php';
        $custom_template = locate_template($template_name);
        if ($custom_template) {
            return $custom_template;
        }

        return locate_template('page.php');
    }

    return $template;
}
add_filter('template_include', 'vqh_force_ciudades_template', 99);

add_action('template_redirect', 'vqh_handle_calendario_route', 1);
function vqh_handle_calendario_route()
{
    if (is_admin()) {
        return;
    }

    $request_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    if ($request_path !== 'calendario') {
        return;
    }

    global $wp_query;
    status_header(200);
    $wp_query->is_page = true;
    $wp_query->is_home = false;
    $wp_query->is_posts_page = false;
    $wp_query->is_404 = false;
    $wp_query->set('pagename', 'calendario');
    $wp_query->set('page_id', 0);
    $wp_query->set('post_type', 'page');
    $wp_query->set('name', 'calendario');
    $wp_query->queried_object = null;
    $wp_query->queried_object_id = 0;
}

add_filter('template_include', 'vqh_force_calendar_template', 99);
function vqh_force_calendar_template($template)
{
    $request_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    if ($request_path !== 'calendario') {
        return $template;
    }

    $custom_template = locate_template('page-calendario.php');
    if ($custom_template) {
        return $custom_template;
    }

    return $template;
}

function vqh_render_global_calendar($year = null, $month = null)
{
    $year = intval($year ?: date('Y'));
    $month = intval($month ?: date('n'));
    if ($month < 1 || $month > 12) {
        $month = intval(date('n'));
    }

    $today_date = date('Y-m-d');
    $month_start = sprintf('%04d-%02d-01', $year, $month);
    $month_end = date('Y-m-t', strtotime($month_start));

    $events = get_posts(array(
        'post_type' => 'listado',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'meta_value',
        'meta_key' => 'st_date',
        'order' => 'ASC',
        'meta_query' => array(
            'relation' => 'OR',
            array(
                'key' => 'st_date',
                'value' => array($month_start, $month_end),
                'compare' => 'BETWEEN',
                'type' => 'DATE',
            ),
            array(
                'key' => 'event_start_date',
                'value' => array($month_start, $month_end),
                'compare' => 'BETWEEN',
                'type' => 'DATE',
            ),
        ),
    ));

    $events_by_day = array();
    foreach ($events as $event) {
        $event_id = $event->ID;
        $start_date = get_post_meta($event_id, 'st_date', true);
        if (empty($start_date)) {
            $start_date = get_post_meta($event_id, 'event_start_date', true);
        }
        if (!empty($start_date) && strtotime($start_date) >= strtotime($month_start) && strtotime($start_date) <= strtotime($month_end)) {
            $day = (int) date('j', strtotime($start_date));
            $events_by_day[$day][] = array(
                'title' => get_the_title($event_id),
                'url' => get_permalink($event_id),
            );
        }
    }

    $month_names = array(
        '01' => 'Enero',
        '02' => 'Febrero',
        '03' => 'Marzo',
        '04' => 'Abril',
        '05' => 'Mayo',
        '06' => 'Junio',
        '07' => 'Julio',
        '08' => 'Agosto',
        '09' => 'Septiembre',
        '10' => 'Octubre',
        '11' => 'Noviembre',
        '12' => 'Diciembre'
    );
    $day_names = array('Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom');

    $first_day_timestamp = mktime(0, 0, 0, $month, 1, $year);
    $first_day_of_week = date('N', $first_day_timestamp);
    $days_in_month = date('t', $first_day_timestamp);

    $prev_month = intval(date('m', strtotime('-1 month', $first_day_timestamp)));
    $prev_year = intval(date('Y', strtotime('-1 month', $first_day_timestamp)));
    $next_month = intval(date('m', strtotime('+1 month', $first_day_timestamp)));
    $next_year = intval(date('Y', strtotime('+1 month', $first_day_timestamp)));

    $base_url = home_url('/calendario');
    $prev_url = add_query_arg(array('year' => $prev_year, 'month' => $prev_month), $base_url);
    $next_url = add_query_arg(array('year' => $next_year, 'month' => $next_month), $base_url);

    ob_start();
?>
    <div class="vqh-calendar-mini">
        <div class="vqh-calendar-mini-header">
            <a href="<?php echo esc_url($prev_url); ?>" class="vqh-cal-nav">«</a>
            <span><?php echo esc_html($month_names[sprintf('%02d', $month)]); ?></span>
            <a href="<?php echo esc_url($next_url); ?>" class="vqh-cal-nav">»</a>
        </div>
        <div class="vqh-calendar-mini-grid">
            <?php foreach ($day_names as $day): ?>
                <div class="vqh-cal-weekday"><?php echo esc_html($day); ?></div>
            <?php endforeach; ?>

            <?php
            for ($i = 1; $i < $first_day_of_week; $i++) {
                echo '<div class="vqh-cal-day vqh-cal-empty"></div>';
            }

            $today_day = intval(date('j'));
            $today_month = intval(date('n'));
            $today_year = intval(date('Y'));

            for ($day = 1; $day <= $days_in_month; $day++) {
                $has_events = !empty($events_by_day[$day]);
                $today_class = ($day == $today_day && $month == $today_month && $year == $today_year) ? 'vqh-cal-today' : '';

                echo '<div class="vqh-cal-day ' . $today_class . ($has_events ? ' vqh-cal-has-events' : '') . '">';
                echo '<span>' . esc_html($day) . '</span>';

                if ($has_events) {
                    echo '<span class="vqh-cal-dot"></span>';
                    echo '<div class="vqh-cal-tooltip">';
                    echo '<div class="vqh-cal-tooltip-title">' . esc_html($day) . ' ' . esc_html($month_names[sprintf('%02d', $month)]) . '</div>';
                    foreach ($events_by_day[$day] as $event) {
                        echo '<a href="' . esc_url($event['url']) . '" class="vqh-cal-tooltip-event">' . esc_html($event['title']) . '</a>';
                    }
                    echo '</div>';
                }
                echo '</div>';
            }
            ?>
        </div>
    </div>
<?php
    return ob_get_clean();
}

/**
 * Inserta el listado de eventos en la home del sitio, debajo del texto introductorio
 * y antes del pie de página, reutilizando las tarjetas del listado de ciudad.
 */
function vqh_render_home_events_content($content)
{
    if (is_admin()) {
        return $content;
    }

    $request_path = trim((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $is_blog_page = $request_path === 'blog' || is_page('blog');

    if ($is_blog_page) {
        return $content;
    }

    $is_home_request = vqh_is_site_home_request() || is_front_page() || is_home();
    if (!$is_home_request) {
        return $content;
    }

    if (!empty($GLOBALS['vqh_home_events_content_rendered'])) {
        return $content;
    }
    $GLOBALS['vqh_home_events_content_rendered'] = true;

    $today_date = date('Y-m-d');
    $date_clauses = array(
        'relation' => 'OR',
        array(
            'key' => 'st_date',
            'value' => $today_date,
            'compare' => '>=',
            'type' => 'DATE'
        ),
        array(
            'key' => 'event_start_date',
            'value' => $today_date,
            'compare' => '>=',
            'type' => 'DATE'
        ),
        array(
            'key' => 'end_date',
            'value' => $today_date,
            'compare' => '>=',
            'type' => 'DATE'
        ),
        array(
            'key' => 'event_end_date',
            'value' => $today_date,
            'compare' => '>=',
            'type' => 'DATE'
        )
    );

    $featured_args = array(
        'post_type' => 'listado',
        'posts_per_page' => 12,
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'ASC',
        'meta_query' => array(
            'relation' => 'AND',
            $date_clauses
        ),
        'suppress_filters' => true,
        'ignore_sticky_posts' => true,
    );

    // Permite filtrar la home por categoría con ?eco_cat=slug
    // y mantiene compatibilidad con ?category=slug.
    $category_filter = '';
    if (!empty($_GET['eco_cat'])) {
        $category_filter = sanitize_text_field(wp_unslash($_GET['eco_cat']));
    } elseif (!empty($_GET['category'])) {
        $category_filter = sanitize_text_field(wp_unslash($_GET['category']));
    }

    if (!empty($category_filter) && taxonomy_exists('ecategory')) {
        $term = get_term_by('slug', $category_filter, 'ecategory');

        if (!$term && is_numeric($category_filter)) {
            $term = get_term_by('id', (int) $category_filter, 'ecategory');
        }

        if (!$term) {
            $term = get_term_by('name', $category_filter, 'ecategory');
        }

        if ($term && !is_wp_error($term)) {
            $featured_args['tax_query'] = array(
                array(
                    'taxonomy' => 'ecategory',
                    'field' => 'term_id',
                    'terms' => array((int) $term->term_id),
                    'include_children' => true,
                    'operator' => 'IN',
                ),
            );
        }
    }

    $featured_query = new WP_Query($featured_args);

    ob_start();
    echo '<div class="ast-container vqh-city-page" style="padding-top:0; padding-bottom:0;">';
    echo '<section class="vqh-featured-events">';
    echo '<h2 class="vqh-section-title">Próximos Eventos</h2>';

    if ($featured_query->have_posts()) {
        echo '<div class="vqh-featured-grid">';
        while ($featured_query->have_posts()) {
            $featured_query->the_post();
            get_template_part('template-parts/event', 'card');
        }
        echo '</div>';
        wp_reset_postdata();
    } else {
        echo '<p class="vqh-no-events">No hay eventos próximos programados.</p>';
    }

    echo '</section></div>';
    $listing_html = ob_get_clean();

    return $content . $listing_html;
}
add_action('the_content', 'vqh_force_blog_page_content', 5);
function vqh_force_blog_page_content($content)
{
    if (is_admin()) {
        return $content;
    }

    $blog_page_id = (int) get_option('page_for_posts');
    $current_page_id = (int) get_queried_object_id();
    $is_blog_page = is_page('blog') || ($blog_page_id > 0 && $current_page_id === $blog_page_id);

    if ($is_blog_page) {
        return do_shortcode('[vqh_blog_directory]');
    }

    return $content;
}

add_filter('the_content', 'vqh_render_home_events_content', 25);

/**
 * Filtra el contenido para asegurar que se muestre el listado de ciudades
 * incluso si WordPress intenta mostrar los "posts" por configuración de lectura
 */
function vqh_render_ciudades_content($content)
{
    global $wp_query;

    // Solo actuar si estamos en la página de ciudades
    $is_ciudades = false;
    if (isset($wp_query->query_vars['pagename']) && $wp_query->query_vars['pagename'] === 'ciudades-con-eventos') {
        $is_ciudades = true;
    }

    if ($is_ciudades && !is_admin()) {
        // Ignorar el contenido original (que podría ser el loop de posts)
        // y devolver nuestro shortcode o HTML personalizado
        return do_shortcode('[vqh_ciudades_landing]');
    }

    return $content;
}
add_filter('the_content', 'vqh_render_ciudades_content', 20);

/**
 * Forzar que la página 'ciudades-con-eventos' muestre su contenido estático
 * y no el archivo de blog, incluso si está configurada como 'Página para entradas'.
 */
function vqh_fix_ciudades_page_query($query)
{
    if (!is_admin() && $query->is_main_query()) {
        // Detectar si estamos en la página de ciudades por slug o ID
        $is_ciudades_page = is_page('ciudades-con-eventos') ||
            (isset($query->query['pagename']) && $query->query['pagename'] === 'ciudades-con-eventos');

        if ($is_ciudades_page) {
            // Si es la página de ciudades, aseguramos que sea tratada como página estática
            $query->is_home = false;
            $query->is_posts_page = false;
            $query->is_page = true;
            $query->is_singular = true;
        }
    }
}
add_action('pre_get_posts', 'vqh_fix_ciudades_page_query', 99);

add_action('template_redirect', 'vqh_fix_single_listado_context', 5); // Prioridad alta (antes que la plantilla)
function vqh_fix_single_listado_context()
{
    if (is_singular('listado') || (isset($GLOBALS['wp_query']) && $GLOBALS['wp_query']->is_singular)) {
        global $wp_query, $post;

        // Intentar obtener el post por la URL actual (slug)
        $current_url = trailingslashit($_SERVER['REQUEST_URI']);
        // Eliminar parámetros de query si los hubiera
        $path = parse_url($current_url, PHP_URL_PATH);
        $parts = explode('/', trim($path, '/'));
        $event_slug = end($parts);

        if (!empty($event_slug)) {
            $real_post = get_page_by_path($event_slug, OBJECT, 'listado');

            if ($real_post && $post->ID != $real_post->ID) {
                // Si el post global no coincide con el de la URL, forzamos el correcto
                $post = $real_post;
                setup_postdata($post);
                $wp_query->post = $post;
                $wp_query->posts = array($post);
                $wp_query->post_count = 1;
                $wp_query->is_single = true;
                $wp_query->is_singular = true;

                // Limpiar cachés de consultas anteriores si las hubiera
                unset($wp_query->queried_object);
            }
        }
    }
}
// =================================================================
// 16. BREADCRUMB PARA EVENTOS
// =================================================================
/**
 * Renderiza el breadcrumb para páginas de eventos individuales
 * Formato: Inicio > Ciudad > Categoría > Evento
 */
function vqh_render_breadcrumb()
{
    // Solo mostrar en single-listado
    if (!is_singular('listado')) {
        return;
    }

    global $post, $wpdb;

    // Obtener city_slug de la URL
    $city_slug = get_query_var('city_slug');
    $city_name = '';

    if (!empty($city_slug)) {
        $city_name = $wpdb->get_var($wpdb->prepare(
            "SELECT cityname FROM {$wpdb->prefix}multicity WHERE city_slug = %s LIMIT 1",
            $city_slug
        ));
    }

    // Si no hay city_slug por query var, intentar obtenerlo del meta del post
    if (empty($city_slug) && !empty($post)) {
        $city_id = get_post_meta($post->ID, 'post_city_id', true);
        if ($city_id) {
            $city_data = $wpdb->get_row($wpdb->prepare(
                "SELECT city_slug, cityname FROM {$wpdb->prefix}multicity WHERE city_id = %d LIMIT 1",
                $city_id
            ));
            if ($city_data) {
                $city_slug = $city_data->city_slug;
                $city_name = $city_data->cityname;
            }
        }
    }

    // Obtener categoría principal (la primera asignada)
    $categories = get_the_terms($post->ID, 'ecategory');
    $primary_category = null;
    if ($categories && !is_wp_error($categories) && !empty($categories)) {
        $primary_category = $categories[0];
    }

    // Construir HTML del breadcrumb
    ob_start();
?>
    <nav class="vqh-breadcrumb" aria-label="Breadcrumb">
        <ol class="vqh-breadcrumb-list">
            <!-- Inicio -->
            <li class="vqh-breadcrumb-item">
                <a href="<?php echo esc_url(home_url('/')); ?>" class="vqh-breadcrumb-link vqh-breadcrumb-home-link">
                    <span class="dashicons dashicons-admin-home" aria-hidden="true"></span>
                    <span class="vqh-breadcrumb-home-text">Inicio</span>
                </a>
            </li>

            <!-- Ciudad -->
            <?php if (!empty($city_slug) && !empty($city_name)): ?>
                <li class="vqh-breadcrumb-item">
                    <span class="vqh-breadcrumb-separator">/</span>
                    <a href="<?php echo esc_url(home_url('/' . trim($city_slug, '/') . '/')); ?>" class="vqh-breadcrumb-link">
                        <?php echo esc_html($city_name); ?>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Categoría -->
            <?php if ($primary_category): ?>
                <li class="vqh-breadcrumb-item">
                    <span class="vqh-breadcrumb-separator">/</span>
                    <a href="<?php echo esc_url(get_term_link($primary_category)); ?>" class="vqh-breadcrumb-link">
                        <?php echo esc_html($primary_category->name); ?>
                    </a>
                </li>
            <?php endif; ?>

            <!-- Evento (página actual, sin enlace) -->
            <li class="vqh-breadcrumb-item vqh-breadcrumb-current" aria-current="page">
                <span class="vqh-breadcrumb-separator">/</span>
                <span class="vqh-breadcrumb-link"><?php the_title(); ?></span>
            </li>
        </ol>
    </nav>

    <style>
        .vqh-breadcrumb {
            margin: 20px 0;
            padding: 10px 0;
            font-size: 14px;
        }

        .vqh-breadcrumb-list {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .vqh-breadcrumb-item {
            display: flex;
            align-items: center;
        }

        .vqh-breadcrumb-link {
            color: #0073aa;
            text-decoration: none;
            transition: color 0.2s;
        }

        .vqh-breadcrumb-link:hover {
            color: #005177;
            text-decoration: underline;
        }

        .vqh-breadcrumb-separator {
            margin: 0 8px;
            color: #999;
        }

        .vqh-breadcrumb-current .vqh-breadcrumb-link {
            color: #333;
            font-weight: 600;
        }

        .vqh-breadcrumb-current .vqh-breadcrumb-link:hover {
            text-decoration: none;
        }

        .vqh-breadcrumb-home-link {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .vqh-breadcrumb-home-text {
            font-weight: 600;
            color: #0073aa;
        }

        .vqh-breadcrumb .dashicons {
            font-size: 16px;
            width: 16px;
            height: 16px;
        }

        .screen-reader-text {
            position: absolute !important;
            clip: rect(1px, 1px, 1px, 1px);
            width: 1px;
            height: 1px;
            overflow: hidden;
        }
    </style>
<?php
    echo ob_get_clean();
}
/**
 * SOLUCIÓN DEFINITIVA: Interceptación temprana y forzoso de consulta
 */
add_action('template_redirect', 'nvqh_force_event_filter_early', 1);
function nvqh_force_event_filter_early()
{
    // Solo actuar si hay nuestro parámetro personalizado y no estamos en admin
    if (is_admin() || ! isset($_GET['eco_cat'])) {
        return;
    }

    $cat_slug = sanitize_text_field($_GET['eco_cat']);

    // Verificar si el término existe
    $term = get_term_by('slug', $cat_slug, 'ecategory');

    if (! $term || is_wp_error($term)) {
        return; // Si no existe, dejar que WP actúe normal
    }

    // 2. Forzar las variables de consulta ANTES de que WP_Query se ejecute
    global $wp_query;

    // Preparar el tax_query combinando lo que ya haya (ej. ciudad por URL) con la categoría
    $new_tax_query = array(
        array(
            'taxonomy'         => 'ecategory',
            'field'            => 'term_id',
            'terms'            => $term->term_id,
            'include_children' => true,
            'operator'         => 'IN',
        ),
    );

    // Si ya existe un tax_query (por ejemplo, por la ciudad /madrid/), lo fusionamos
    if (! empty($wp_query->tax_query)) {
        // Nota: En template_redirect, $wp_query ya puede estar parcialmente construida
        // Pero lo seguro es modificar los query_vars directamente
        $existing_relation = isset($wp_query->query_vars['tax_query']['relation']) ? $wp_query->query_vars['tax_query']['relation'] : 'AND';

        $wp_query->query_vars['tax_query'] = array(
            'relation' => 'AND',
            $wp_query->query_vars['tax_query'], // Mantiene filtros existentes (ciudad)
            $new_tax_query[0]                  // Añade filtro categoría
        );

        // Limpieza de seguridad por si hay conflictos de slug
        unset($wp_query->query_vars['category_name']);
        unset($wp_query->query_vars['cat']);

        error_log("DEBUG ÉXITO: Filtro combinado forzado en template_redirect para '{$term->name}' + Ciudad existente.");
    } else {
        // Si no hay tax_query previo, establecemos el nuevo
        $wp_query->query_vars['tax_query'] = $new_tax_query;
        error_log("DEBUG ÉXITO: Filtro ecategory '{$term->name}' forzado en template_redirect.");
    }

    // 3. Asegurar que el post_type sea 'listado' si no lo está
    if (empty($wp_query->query_vars['post_type'])) {
        $wp_query->query_vars['post_type'] = 'listado';
    }

    // 4. Volver a ejecutar la consulta con las nuevas variables
    $wp_query->query($wp_query->query_vars);
}
/**
 * SOLUCIÓN ÚNICA Y DEFINITIVA PARA FILTRO COMBINADO
 * 1. Bloquea redirección canónica con prioridad máxima.
 * 2. Inyecta el filtro de categoría respetando la ciudad implícita en la URL.
 */

// 2. Inyección segura del filtro de taxonomía
add_action('pre_get_posts', 'vqh_final_inject_ecategory', 9999);
function vqh_final_inject_ecategory($query)
{
    // Solo actuar en la consulta principal, no en admin, y solo si hay eco_cat
    if (is_admin() || !$query->is_main_query() || !isset($_GET['eco_cat']) || empty($_GET['eco_cat'])) {
        return;
    }

    $slug = sanitize_text_field($_GET['eco_cat']);
    $term = get_term_by('slug', $slug, 'ecategory');

    if (!$term || is_wp_error($term)) {
        return;
    }

    // Obtener tax_query existente (puede venir de la URL /madrid/)
    $existing_tax_query = $query->get('tax_query');

    // Estructurar correctamente si es nuevo o si le falta la relación
    if (empty($existing_tax_query)) {
        $existing_tax_query = array('relation' => 'AND');
    } elseif (!isset($existing_tax_query['relation'])) {
        $new_query = array('relation' => 'AND');
        foreach ($existing_tax_query as $item) {
            if (is_array($item)) {
                $new_query[] = $item;
            }
        }
        $existing_tax_query = $new_query;
    }

    // Añadir nuestro filtro de categoría
    $existing_tax_query[] = array(
        'taxonomy'         => 'ecategory',
        'field'            => 'term_id',
        'terms'            => $term->term_id,
        'include_children' => true,
        'operator'         => 'IN',
    );

    $query->set('tax_query', $existing_tax_query);

    // Debug opcional
    // error_log("VQH QUERY INJECTED: ecategory ID " . $term->term_id . " sobre URL: " . $_SERVER['REQUEST_URI']);
}
/**
 * SOLUCIÓN AGRESIVA: Interceptación temprana para evitar redirección
 * Se ejecuta en 'template_redirect' justo antes de cargar la plantilla.
 * Si detecta eco_cat, fuerza la consulta y termina la ejecución evitando cualquier redirección posterior.
 */
add_action('template_redirect', 'nvqh_force_render_no_redirect', 1);
function nvqh_force_render_no_redirect()
{
    // Solo actuar si existe el parámetro eco_cat y no estamos en admin
    if (is_admin() || ! isset($_GET['eco_cat'])) {
        return;
    }

    $slug = sanitize_text_field($_GET['eco_cat']);

    // Verificar si el término existe
    $term = get_term_by('slug', $slug, 'ecategory');

    if (! $term || is_wp_error($term)) {
        return; // Si no existe, dejamos que WordPress haga lo que quiera
    }

    // DESACTIVAR completamente la redirección canónica para esta petición
    remove_filter('redirect_canonical', 'redirect_canonical');
    add_filter('redirect_canonical', '__return_false', 9999);

    // Forzar la consulta global manualmente ANTES de que WP la resuelva
    global $wp_query;

    // Construir argumentos de consulta combinando lo que WP ya tenga (ej. ciudad por URL)
    // y añadiendo nuestra categoría.
    $args = array(
        'post_type'      => 'listado', // Asegúrate que es 'listado'
        'posts_per_page' => get_option('posts_per_page'),
        'paged'          => get_query_var('paged') ? get_query_var('paged') : 1,
        'tax_query'      => array(
            'relation' => 'AND',
        ),
    );

    // 1. Capturar filtros existentes (como la ciudad de la URL /madrid/)
    // WordPress suele poner la taxonomía de la URL en $wp_query->tax_query o en query_vars
    $existing_tax_query = $wp_query->get('tax_query');
    if (! empty($existing_tax_query)) {
        if (! isset($existing_tax_query['relation'])) {
            $existing_tax_query = array('relation' => 'AND', $existing_tax_query);
        }
        // Fusionar cuidadosamente
        foreach ($existing_tax_query as $key => $val) {
            if ($key !== 'relation' && is_array($val)) {
                $args['tax_query'][] = $val;
            }
        }
    } else {
        // Fallback: Si la URL es /madrid/, WP a veces pone 'ciudad' en query_vars directamente
        $city_slug = get_query_var('ciudad'); // O el nombre que use tu tema para la ciudad
        if (! empty($city_slug)) {
            $args['tax_query'][] = array(
                'taxonomy' => 'ciudad',
                'field'    => 'slug',
                'terms'    => $city_slug,
            );
        }
    }

    // 2. Añadir nuestro filtro de categoría
    $args['tax_query'][] = array(
        'taxonomy'         => 'ecategory',
        'field'            => 'term_id',
        'terms'            => $term->term_id,
        'include_children' => true,
        'operator'         => 'IN',
    );

    // Ejecutar la nueva consulta
    $wp_query = new WP_Query($args);

    // Log de confirmación
    error_log("INTERCEPCIÓN EXITOSA: Query forzada para Ciudad + Categoría '{$term->name}' sin redirección.");

    // NO hacemos exit() aquí para permitir que WordPress cargue el template archive-listado.php normalmente
    // Pero al haber forzado $wp_query antes del loop, el template mostrará los resultados correctos.
}

/**
 * BLOQUEO TEMPRANO DE REDIRECCIONES CANÓNICAS PARA CALENDARIO
 * Se ejecuta en template_redirect con prioridad máxima (0) antes que cualquier otro hook
 * Esto previene que Yoast o WordPress elimine los parámetros year/month de la URL
 */
add_action('template_redirect', 'vqh_block_canonical_redirect_for_calendar', 0);
function vqh_block_canonical_redirect_for_calendar()
{
    if (isset($_GET['year']) || isset($_GET['month']) || isset($_GET['eco_cat'])) {
        remove_filter('redirect_canonical', 'redirect_canonical');
        remove_filter('redirect_canonical', array('WPSEO_Frontend', 'redirect_canonical'));
        return;
    }

    if (get_query_var('city_archive')) {
        remove_filter('redirect_canonical', 'redirect_canonical');
        remove_filter('redirect_canonical', array('WPSEO_Frontend', 'redirect_canonical'));
    }
}

/**
 * DESACTIVAR LA CANONICAL DE YOAST Y MANTENER PARÁMETROS DE CALENDARIO
 * Esto evita que Yoast fuerce la redirección a versiones sin query strings
 */
add_filter('wpseo_canonical', 'vqh_disable_yoast_canonical_for_calendar_and_filters');
function vqh_disable_yoast_canonical_for_calendar_and_filters($canonical)
{
    // Mantener canonical si hay parámetros de fecha del calendario
    if (isset($_GET['year']) || isset($_GET['month'])) {
        return false; // Devolver false anula la canonical de Yoast
    }
    // Mantener canonical si hay filtro de categoría
    if (isset($_GET['eco_cat']) && ! empty($_GET['eco_cat'])) {
        return false; // Devolver false anula la canonical de Yoast
    }
    return $canonical;
}

// Bloquear redirecciones canónicas que remuevan parámetros de querystring para calendario y categorías
add_filter('redirect_canonical', 'vqh_block_wp_redirect_for_calendar_and_filters', 9999, 2);
function vqh_block_wp_redirect_for_calendar_and_filters($redirect_url, $requested_url)
{
    if (isset($_GET['year']) || isset($_GET['month']) || isset($_GET['eco_cat'])) {
        return false;
    }

    $path = trim((string) parse_url($requested_url, PHP_URL_PATH), '/');
    if ($path !== '') {
        $parts = explode('/', $path);
        if (! empty($parts[0]) && vqh_is_valid_city_slug($parts[0])) {
            return false;
        }
    }

    return $redirect_url;
}
/**
 * SOLUCIÓN CALENDARIO: Respetar parámetros ?year= y ?month= en la consulta principal
 * y mantener el filtro de ciudad si existe.
 */
add_action('pre_get_posts', 'fix_calendar_month_navigation');
function fix_calendar_month_navigation($query)
{
    // Solo actuar en la consulta principal, no en admin, y solo si hay parámetros de fecha
    if (is_admin() || ! $query->is_main_query()) {
        return;
    }

    if (isset($_GET['year']) && isset($_GET['month'])) {
        $year  = intval($_GET['year']);
        $month = intval($_GET['month']);

        // Validar fechas básicas
        if ($year >= 1970 && $year <= 2100 && $month >= 1 && $month <= 12) {

            // Forzar la fecha en la consulta
            $query->set('year', $year);
            $query->set('monthnum', $month);

            // NOTA IMPORTANTE: Si usas un plugin de calendario específico que genera su propia query interna,
            // a veces es necesario también filtrar la variable global 'm' (formato YYYYMM)
            // $query->set( 'm', sprintf('%04d%02d', $year, $month) ); 

            error_log("CALENDARIO FIX: Navegando a {$year}/{$month}");
        }
    }
}
/**
 * FIX CALENDARIO: Forzar año y mes desde URL (?year=...&month=...)
 * Se ejecuta con prioridad alta para sobrescribir posibles conflictos.
 */
add_action('pre_get_posts', 'vqh_force_calendar_date', 999);
function vqh_force_calendar_date($query)
{
    if (is_admin() || ! $query->is_main_query()) {
        return;
    }

    // Solo actuar si existen los parámetros en la URL
    if (isset($_GET['year']) && isset($_GET['month'])) {
        $year  = intval($_GET['year']);
        $month = intval($_GET['month']);

        if ($year >= 2000 && $year <= 2100 && $month >= 1 && $month <= 12) {
            $query->set('year', $year);
            $query->set('monthnum', $month);

            // IMPORTANTE: Asegurar que no se pierda la taxonomía de ciudad si viene en la URL
            // Si la ciudad viene por rewrite (ej: /valencia/), WordPress ya la tiene en query_vars.
            // Si viene por GET (?city_filter=...), la inyectamos manualmente si fuera necesario,
            // pero lo ideal es que el Paso 1 (JS) mantenga la URL limpia o consistente.
        }
    }
}
?>