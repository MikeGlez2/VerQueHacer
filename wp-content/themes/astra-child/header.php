<?php

/**
 * Header personalizado - Astra Child
 * Sin menú de navegación
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="profile" href="https://gmpg.org/xfn/11">
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
    <?php wp_body_open(); ?>
    <header class="site-header" id="masthead">
        <div class="ast-container">
            <div class="site-branding" style="display: flex; align-items: center; gap: 12px;">
                <img src="<?php echo get_stylesheet_directory_uri(); ?>/assets/logo-header.svg" alt="Logo" style="height: 40px; width: auto;">
                <?php if (has_custom_logo()) : ?>
                    <?php the_custom_logo(); ?>
                <?php else : ?>
                    <h1 class="site-title">
                        <a href="<?php echo esc_url(home_url('/')); ?>" rel="home">
                            <?php bloginfo('name'); ?>
                        </a>
                    </h1>
                <?php endif; ?>
            </div>
        </div>
    </header>
    <main class="site-content" id="primary">