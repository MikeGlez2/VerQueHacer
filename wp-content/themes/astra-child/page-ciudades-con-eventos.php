<?php
get_header();
?>

<div class="ast-container">
    <main id="primary" class="site-main">
        <header class="entry-header">
            <h1 class="entry-title">Ciudades con eventos</h1>
            <p>Explora las ciudades que tienen eventos desde hoy en adelante.</p>
        </header>
        <?php echo do_shortcode('[vqh_ciudades_landing]'); ?>
    </main>
</div>

<?php get_footer(); ?>