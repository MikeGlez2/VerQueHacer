<?php
/**
 * Migrar todos los eventos (post_type='event') a listados (post_type='listado')
 * Ejecutar SOLO UNA VEZ y luego borrar
 */

require_once('wp-load.php');

// Verificar que el CPT 'listado' exista
if (!post_type_exists('listado')) {
    die('❌ Error: El CPT "listado" no existe. Regístralo primero en functions.php');
}

// Obtener todos los eventos
$eventos = get_posts(array(
    'post_type'      => 'event',
    'posts_per_page' => -1,
    'post_status'    => 'any',
));

echo "<h1>🔄 Migración de Eventos a Listados</h1>";
echo "<p>Encontrados: <strong>" . count($eventos) . "</strong> eventos</p>";

if (empty($eventos)) {
    die("<p style='color:red;'>No hay eventos para migrar.</p>");
}

$contador = 0;
$errores = 0;

foreach($eventos as $evento) {
    // Actualizar el post_type de 'event' a 'listado'
    $updated = wp_update_post(array(
        'ID'        => $evento->ID,
        'post_type' => 'listado',
    ));
    
    if (is_wp_error($updated)) {
        echo "<p style='color:red;'>❌ Error en ID {$evento->ID}: " . $updated->get_error_message() . "</p>";
        $errores++;
        continue;
    }
    
    $contador++;
    echo "<p style='color:green;'>✅ Migrado: {$evento->post_title} (ID: {$evento->ID})</p>";
}

// Limpiar caché de reescritura
flush_rewrite_rules();

echo "<hr>";
echo "<h3>✅ Migración completada:</h3>";
echo "<p><strong>{$contador}</strong> eventos migrados a listados</p>";
echo "<p><strong>{$errores}</strong> errores</p>";
echo "<p><a href='/wp-admin/edit.php?post_type=listado'>Ver listados migrados</a></p>";
echo "<p style='color:red; font-weight:bold;'>⚠️ BORRA ESTE ARCHIVO AHORA</p>";
?>