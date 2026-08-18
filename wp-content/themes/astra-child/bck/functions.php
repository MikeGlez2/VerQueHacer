<?php
/**
 * Astra Child Theme - VerQueHacer
 * Versión FINAL limpia - Sin duplicados
 */

// =================================================================
// 1. CARGAR ESTILOS
// =================================================================
function vqh_child_enqueue_styles() {
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
        '1.0.0'
    );
}
add_action('wp_enqueue_scripts', 'vqh_child_enqueue_styles');

// =================================================================
// 2. REGISTRAR CPT 'LISTADO'
// =================================================================
add_action('init', 'vqh_register_listado_cpt');
function vqh_register_listado_cpt() {
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
function vqh_register_ecategory_taxonomy() {
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

    register_taxonomy('ecategory', array('listado', 'event'), array(
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
function vqh_register_menus() {
    register_nav_menus(array(
        'footer-menu' => __('Menú Footer', 'astra-child'),
        'primary'     => __('Menú Principal', 'astra-child'),
    ));
}
add_action('after_setup_theme', 'vqh_register_menus');

// =================================================================
// 5. SOPORTE DEL TEMA
// =================================================================
function vqh_theme_features() {
    add_theme_support('custom-logo');
    add_theme_support('custom-header');
    add_theme_support('post-thumbnails');
    add_theme_support('title-tag');
}
add_action('after_setup_theme', 'vqh_theme_features', 20);

// =================================================================
// 6. META BOX - Campos personalizados
// =================================================================
add_filter('rwmb_meta_boxes', 'vqh_register_meta_boxes');
function vqh_register_meta_boxes($meta_boxes) {
    // Box 1: Fechas
    $meta_boxes[] = array(
        'title'      => '📅 Fechas del Evento',
        'post_types' => array('listado', 'event'),
        'priority'   => 'high',
        'fields'     => array(
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
        'post_types' => array('listado', 'event'),
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
        'post_types' => array('listado', 'event'),
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
            foreach($categories as $category) {
                $prefix = ($category->parent > 0) ? '— ' : '';
                $category_options[$category->term_id] = $prefix . $category->name;
            }
        }
        
        $meta_boxes[] = array(
            'title'      => '📂 Categorías del Evento',
            'post_types' => array('listado', 'event'),
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
function vqh_get_spain_zones() {
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
    foreach($zones as $zone) {
        $options[$zone->zones_id] = $zone->zone_name;
    }
    
    return $options;
}

function vqh_get_cities_by_zone($zone_id) {
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
    foreach($cities as $city) {
        $options[$city->city_id] = $city->cityname;
    }
    
    return $options;
}

add_action('wp_ajax_vqh_get_cities_by_zone', 'vqh_ajax_get_cities_by_zone');
function vqh_ajax_get_cities_by_zone() {
    check_ajax_referer('vqh_location_nonce', 'nonce');
    
    $zone_id = isset($_POST['zone_id']) ? intval($_POST['zone_id']) : 0;
    $cities = vqh_get_cities_by_zone($zone_id);
    
    if (!empty($cities)) {
        echo '<option value="">-- Selecciona una ciudad --</option>';
        foreach($cities as $id => $name) {
            echo '<option value="' . esc_attr($id) . '">' . esc_html($name) . '</option>';
        }
    } else {
        echo '<option value="">No hay ciudades disponibles</option>';
    }
    
    wp_die();
}

function vqh_enqueue_location_script($hook) {
    global $post_type, $post;
    
    if (!in_array($post_type, array('listado', 'event'))) {
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
// 8. HELPER METADATOS
// =================================================================
function vqh_get_meta($post_id, $key, $label = '') {
    $value = function_exists('rwmb_meta') ? rwmb_meta($key, '', $post_id) : get_post_meta($post_id, $key, true);
    
    if (!empty($value)) {
        if (is_array($value)) $value = implode(', ', $value);
        return $label ? '<p><strong>' . esc_html($label) . ':</strong> ' . esc_html($value) . '</p>' : esc_html($value);
    }
    return '';
}

// =================================================================
// 9. GUARDAR CATEGORÍAS
// =================================================================
add_action('save_post', 'vqh_save_event_categories', 10, 2);
function vqh_save_event_categories($post_id, $post) {
    if (!in_array($post->post_type, array('listado', 'event'))) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    
    $selected_categories = isset($_POST['vqh_event_categories']) ? $_POST['vqh_event_categories'] : array();
    
    if (!is_array($selected_categories)) {
        $selected_categories = array($selected_categories);
    }
    
    $selected_categories = array_map('intval', $selected_categories);
    
    wp_set_object_terms($post_id, $selected_categories, 'ecategory', false);
}

// =================================================================
// 10. FILTRO PARA REEMPLAZAR %city% CON LA CIUDAD REAL
// =================================================================
add_filter('post_type_link', 'vqh_replace_city_placeholder', 1, 3);
function vqh_replace_city_placeholder($permalink, $post, $leavename) {
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
function vqh_add_city_query_vars($vars) {
    $vars[] = 'city_archive';
    $vars[] = 'city_slug';
    $vars[] = 'vqh_city_event';
    $vars[] = 'vqh_show_home';
    $vars[] = 'vqh_change_city'; // ← AÑADIR: Permite forzar banner
    return $vars;
}

// 12.2. Añadir rewrite rules EXCLUYENDO slugs reservados
add_action('init', 'vqh_add_city_archive_rules', 20);
function vqh_add_city_archive_rules() {
    // Lista de slugs que NO son ciudades
    $reserved_slugs = array(
        'blog', 'page', 'wp-admin', 'wp-login', 'wp-content',
        'wp-includes', 'feed', 'comments', 'wp-json', 'author',
        'category', 'tag', 'attachment', 'sitemap', 'ciudades-con-eventos'
    );
    
    // Crear patrón de exclusión
    $reserved_pattern = '(' . implode('|', $reserved_slugs) . ')';
    
    // 1. Evento individual: /ciudad/slug-del-evento/ (excluyendo reservados)
    add_rewrite_rule(
        '^((?!' . $reserved_pattern . ')[a-z0-9-]+)/([^/]+)/?$',
        'index.php?post_type=listado&name=$matches[2]&city_slug=$matches[1]',
        'top'
    );
    
    // 2. Archivo con paginación: /ciudad/page/2/ (excluyendo reservados)
    add_rewrite_rule(
        '^((?!' . $reserved_pattern . ')[a-z0-9-]+)/page/([0-9]+)/?$',
        'index.php?city_archive=1&city_slug=$matches[1]&paged=$matches[2]',
        'top'
    );
    
    // 3. Archivo por ciudad: /ciudad/ (excluyendo reservados)
    add_rewrite_rule(
        '^((?!' . $reserved_pattern . ')[a-z0-9-]+)/?$',
        'index.php?city_archive=1&city_slug=$matches[1]',
        'top'
    );
}

// 12.3. Filtrar query para eventos individuales por ciudad
add_action('parse_request', 'vqh_parse_city_event_request', 5);
function vqh_parse_city_event_request($wp) {
    // NO actuar si es una página estática
    if (isset($wp->query_vars['pagename'])) {
        return;
    }
    
    // NO actuar si es un slug reservado
    $reserved_slugs = array('blog', 'page', 'wp-admin', 'wp-login', 'ciudades-con-eventos');
    if (isset($wp->query_vars['city_slug']) && in_array($wp->query_vars['city_slug'], $reserved_slugs)) {
        return;
    }
    
    // Solo si tenemos city_slug y name en la URL
    if (!isset($wp->query_vars['city_slug']) || !isset($wp->query_vars['name'])) {
        return;
    }
    
    $event_slug = $wp->query_vars['name'];
    $city_slug = $wp->query_vars['city_slug'];
    
    if (empty($event_slug) || empty($city_slug)) {
        return;
    }
    
    // Ignorar si es paginación o palabras reservadas
    if (in_array($event_slug, array('page', 'feed', 'comments', 'wp-json'))) {
        return;
    }
    
    global $wpdb;
    
    // Obtener city_id
    $city_id = $wpdb->get_var($wpdb->prepare("
        SELECT city_id FROM {$wpdb->prefix}multicity WHERE city_slug = %s LIMIT 1
    ", $city_slug));
    
    if (!$city_id) {
        return;
    }
    
    // Buscar el evento por slug + city_id
    $post_id = $wpdb->get_var($wpdb->prepare("
        SELECT p.ID
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_name = %s
        AND p.post_type = 'listado'
        AND p.post_status = 'publish'
        AND pm.meta_key = 'post_city_id'
        AND pm.meta_value = %d
        LIMIT 1
    ", $event_slug, $city_id));
    
if ($post_id) {
    // ✅ CORRECCIÓN: Asignar variables sin borrar 'city_slug'
    $wp->query_vars['p'] = $post_id;
    $wp->query_vars['post_type'] = 'listado';
    // 'city_slug' se mantiene intacto para el breadcrumb y consistencia
    $wp->is_singular = true;
    $wp->is_single = true;
    $wp->is_archive = false;
}
}

// 12.4. Filtrar query para archivos por ciudad
add_action('pre_get_posts', 'vqh_handle_city_archive_query', 10);
function vqh_handle_city_archive_query($query) {
    if (is_admin() || !$query->is_main_query()) {
        return;
    }
    
    // NO actuar si es un post individual (ya lo manejó parse_request)
    if (is_singular('listado')) {
        return;
    }
    
    if ($query->get('city_archive')) {
        $city_slug = $query->get('city_slug');
        
        if (empty($city_slug)) {
            return;
        }
        
        global $wpdb;
        $city_id = $wpdb->get_var($wpdb->prepare("
            SELECT city_id FROM {$wpdb->prefix}multicity WHERE city_slug = %s LIMIT 1
        ", $city_slug));
        
        if (!$city_id) {
            $query->set_404();
            return;
        }
        
        $query->set('post_type', 'listado');
        $query->set('post_status', 'publish');
        $query->set('posts_per_page', get_option('posts_per_page', 10));
        $query->set('meta_query', array(
            array(
                'key'     => 'post_city_id',
                'value'   => $city_id,
                'compare' => '=',
                'type'    => 'NUMERIC'
            )
        ));
    }
}

// 12.5. Forzar plantilla correcta (archive O single según corresponda)
add_filter('template_include', 'vqh_force_correct_template', 99);
function vqh_force_correct_template($template) {
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

function vqh_force_archive_listado_template($template) {
    $new_template = locate_template(array('archive-listado.php'));
    if ($new_template) {
        return $new_template;
    }
    return $template;
}

add_filter('document_title_parts', 'vqh_city_archive_title', 99);
function vqh_city_archive_title($title) {
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
function vqh_redirect_old_listado_urls() {
    if (is_singular('listado') && strpos($_SERVER['REQUEST_URI'], '/listados/') !== false) {
        wp_redirect(get_permalink(), 301);
        exit;
    }
}

// =================================================================
// 13. CALENDARIO DE EVENTOS POR CIUDAD (CON TOOLTIPS)
// =================================================================
function vqh_get_monthly_events_by_city($city_slug, $year = null, $month = null) {
    if (!$year) $year = date('Y');
    if (!$month) $month = date('m');
    
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
        SELECT p.ID, p.post_title, p.post_name, pm.meta_value as start_date
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'st_date'
        INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'post_city_id'
        WHERE p.post_type = 'listado'
        AND p.post_status = 'publish'
        AND pm.meta_value BETWEEN %s AND %s
        AND pm2.meta_value = %d
        ORDER BY pm.meta_value ASC
    ", $first_day, $last_day, $city_id));
    
    $events_by_day = array();
    foreach($events as $event) {
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
function vqh_render_city_calendar($city_slug, $year = null, $month = null) {
    if (!$year) $year = date('Y');
    if (!$month) $month = date('m');
    
    $events_by_day = vqh_get_monthly_events_by_city($city_slug, $year, $month);
    
    $month_names = array(
        '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo',
        '04' => 'Abril', '05' => 'Mayo', '06' => 'Junio',
        '07' => 'Julio', '08' => 'Agosto', '09' => 'Septiembre',
        '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
    );
    $day_names = array('Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom');
    
    $first_day_timestamp = mktime(0, 0, 0, $month, 1, $year);
    $first_day_of_week = date('N', $first_day_timestamp);
    $days_in_month = date('t', $first_day_timestamp);
    
    $prev_month = date('m', strtotime('-1 month', $first_day_timestamp));
    $prev_year = date('Y', strtotime('-1 month', $first_day_timestamp));
    $next_month = date('m', strtotime('+1 month', $first_day_timestamp));
    $next_year = date('Y', strtotime('+1 month', $first_day_timestamp));
    
    $base_url = home_url('/' . $city_slug . '/');
    
    ob_start();
    ?>
    <div class="vqh-calendar-mini">
        <div class="vqh-calendar-mini-header">
            <!-- Botones con URL absoluta -->
            <a href="<?php echo esc_url($base_url . '?year=' . $prev_year . '&month=' . $prev_month); ?>" class="vqh-cal-nav">«</a>
            <span><?php echo $month_names[$month] . ' ' . $year; ?></span>
            <a href="<?php echo esc_url($base_url . '?year=' . $next_year . '&month=' . $next_month); ?>" class="vqh-cal-nav">»</a>
        </div>
        <div class="vqh-calendar-mini-grid">
            <?php foreach($day_names as $day): ?>
                <div class="vqh-cal-weekday"><?php echo $day; ?></div>
            <?php endforeach; ?>
            
            <?php
            for ($i = 1; $i < $first_day_of_week; $i++) {
                echo '<div class="vqh-cal-day vqh-cal-empty"></div>';
            }
            
            for ($day = 1; $day <= $days_in_month; $day++) {
                $has_events = isset($events_by_day[$day]) && !empty($events_by_day[$day]);
                $today_class = ($day == date('j') && $month == date('m') && $year == date('Y')) ? 'vqh-cal-today' : '';
                
                echo '<div class="vqh-cal-day ' . $today_class . ($has_events ? ' vqh-cal-has-events' : '') . '">';
                echo '<span>' . $day . '</span>';
                
				if ($has_events) {
					echo '<span class="vqh-cal-dot"></span>';
					
					// TOOLTIP CON EVENTOS Y ENLACES
					echo '<div class="vqh-cal-tooltip">';
					echo '<div class="vqh-cal-tooltip-title">' . $day . ' ' . $month_names[$month] . '</div>';
					foreach($events_by_day[$day] as $event) {
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
function vqh_force_city_on_calendar_request($query_vars) {
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

// Cargar script de navegación del calendario en TODAS las páginas
function vqh_load_calendar_fix_script() {
wp_enqueue_script(
'vqh-calendar-fix',
get_stylesheet_directory_uri() . '/js/calendar-fix.js',
array(),
'1.0.5',
true
);
}
add_action('wp_enqueue_scripts', 'vqh_load_calendar_fix_script', 9999);
// =================================================================
// FORZAR PLANTILLA SINGLE-LISTADO.PHP
// =================================================================
// Forzar plantilla single-listado.php para eventos individuales
add_filter('single_template', 'vqh_force_single_listado_template');
function vqh_force_single_listado_template($template) {
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
function vqh_add_single_listado_body_class($classes) {
    if (is_singular('listado')) {
        $classes[] = 'single-listado';
    }
    return $classes;
}

// =================================================================
// 30. GEOLOCALIZACIÓN DE EVENTOS (MÓDULO INDEPENDIENTE)
// =================================================================

// 30.1. AJAX: Obtener todas las ciudades con coordenadas
add_action('wp_ajax_vqh_get_all_cities', 'vqh_ajax_get_all_cities');
add_action('wp_ajax_nopriv_vqh_get_all_cities', 'vqh_ajax_get_all_cities');
function vqh_ajax_get_all_cities() {
    global $wpdb;
    $cities = $wpdb->get_results("
        SELECT city_id, cityname, city_slug, lat, lng
        FROM {$wpdb->prefix}multicity
        WHERE lat IS NOT NULL
        AND lng IS NOT NULL
        AND lat != ''
        AND lng != ''
        ORDER BY cityname ASC
    ");
    $cities_data = array();
    foreach($cities as $city) {
        $cities_data[] = array(
            'id'   => $city->city_id,
            'name' => $city->cityname,
            'slug' => $city->city_slug,
            'lat'  => $city->lat,
            'lng'  => $city->lng
        );
    }
    wp_send_json_success($cities_data);
}

// =================================================================
// 30.2. ENCOLAR SCRIPT DE GEOLOCALIZACIÓN (GLOBAL)
// =================================================================
function vqh_enqueue_geolocation_script() {
    if (is_admin()) {
        return;
    }
    
    // Cargar SOLO en home
    $request_uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $is_home = (empty($request_uri) || $request_uri === 'index.php');
    $is_change_city = get_query_var('vqh_change_city');
    
    if ($is_home || $is_change_city) {
        wp_enqueue_script(
            'vqh-geolocation',
            get_stylesheet_directory_uri() . '/js/geolocation.js',
            array(),
            file_exists(get_stylesheet_directory() . '/js/geolocation.js') ? filemtime(get_stylesheet_directory() . '/js/geolocation.js') : '1.0.0',
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'vqh_enqueue_geolocation_script');

// =================================================================
// 30.3. REDIRECCIÓN SEGURA (RESPETA PARÁMETROS)
// =================================================================
add_action('template_redirect', 'vqh_geolocation_redirect', 99);
function vqh_geolocation_redirect() {
    // Si el usuario pide cambiar ciudad o ver home, NO redirigir
    if (get_query_var('vqh_change_city') || get_query_var('vqh_show_home')) {
        return;
    }
    
    // Solo redirigir en home exacta
    $request_uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    if (!empty($request_uri) && $request_uri !== 'index.php') {
        return;
    }
    
    if (isset($_COOKIE['vqh_city_selected'])) {
        $city_slug = sanitize_text_field($_COOKIE['vqh_city_selected']);
        if ($city_slug === 'home') {
            return;
        }
        global $wpdb;
        $city_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT city_id FROM {$wpdb->prefix}multicity WHERE city_slug = %s LIMIT 1",
            $city_slug
        ));
        if ($city_exists) {
            wp_redirect(home_url('/' . $city_slug . '/'));
            exit;
        }
    }
}

// DEBUG: Log cuando se accede con vqh_show_home
add_action('template_redirect', 'vqh_debug_show_home', 0);
function vqh_debug_show_home() {
    if (isset($_GET['vqh_show_home'])) {
        error_log('[VQH DEBUG] vqh_show_home=1 detectado en: ' . $_SERVER['REQUEST_URI']);
        error_log('[VQH DEBUG] Cookie: ' . (isset($_COOKIE['vqh_city_selected']) ? $_COOKIE['vqh_city_selected'] : 'NO EXISTE'));
    }
}


// 30.4. Reset de geolocalización (para testing)
add_action('template_redirect', 'vqh_geolocation_reset');
function vqh_geolocation_reset() {
    if (isset($_GET['vqh_reset_geo']) && current_user_can('manage_options')) {
        setcookie('vqh_city_selected', '', time() - 3600, '/');
        wp_redirect(home_url('/'));
        exit;
    }
}

// 30.5. Banner para admin: mostrar cookie actual y permitir reset
add_action('wp_footer', 'vqh_geo_debug_info');
function vqh_geo_debug_info() {
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

// Fallback: Forzar carga del script en el footer
add_action('wp_footer', 'vqh_force_geolocation_script', 9999);
function vqh_force_geolocation_script() {
    $request_uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $is_home = (empty($request_uri) || $request_uri === 'index.php');
    
    if (!$is_home) {
        return;
    }
    
    echo '<script src="' . get_stylesheet_directory_uri() . '/js/geolocation.js"></script>';
}

// =================================================================
// SHORTCODE: LANDING DE CIUDADES CON CONTADORES
// Uso: [vqh_ciudades_landing]
// =================================================================
add_shortcode('vqh_ciudades_landing', 'vqh_render_ciudades_landing');
function vqh_render_ciudades_landing() {
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
            <?php foreach($ciudades as $ciudad): 
                // Gradiente según cantidad de eventos
                $gradient = $ciudad->event_count > 10 ? 
                    'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' : 
                    ($ciudad->event_count > 5 ? 
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
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .vqh-ciudad-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
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

// Crear página de ciudades automáticamente
add_action('after_switch_theme', 'vqh_create_ciudades_page_temp');
function vqh_create_ciudades_page_temp() {
    // Verificar si ya existe
    $page = get_page_by_path('ciudades-con-eventos');
    if (!$page) {
        wp_insert_post(array(
            'post_title' => 'Ciudades con Eventos',
            'post_name' => 'ciudades-con-eventos',
            'post_content' => '[vqh_ciudades_landing]',
            'post_status' => 'publish',
            'post_type' => 'page',
        ));
    }
}

add_filter('template_include', 'vqh_force_page_template_for_ciudades', 99);
function vqh_force_page_template_for_ciudades($template) {
    if (is_page('ciudades-con-eventos') || is_page('Ciudades con Eventos')) {
        $new_template = locate_template(array('page.php'));
        if ($new_template) {
            return $new_template;
        }
    }
    return $template;
}

?>