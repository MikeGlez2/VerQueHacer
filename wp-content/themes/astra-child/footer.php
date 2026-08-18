<?php ?>
<footer class="site-footer">
    <div class="footer-container">
        <div class="footer-grid">
            <div class="footer-column footer-brand">
                <a href="<?php echo esc_url(home_url('/')); ?>" class="footer-brand-link" aria-label="Ir a la home">
                    <?php if (has_custom_logo()) : ?>
                        <span class="footer-brand-logo"><?php the_custom_logo(); ?></span>
                    <?php else : ?>
                        <span class="footer-brand-logo">
                            <img src="<?php echo esc_url(get_stylesheet_directory_uri() . '/assets/logo-header.svg'); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>" />
                        </span>
                    <?php endif; ?>
                </a>
                <p>
                    Descubre eventos, conciertos, teatro y actividades en tu ciudad.
                    Encuentra tus próximas experiencias y disfruta del mejor ocio.
                </p>
                <a href="<?php echo esc_url(home_url('/contacto')); ?>" class="footer-link">Contacto</a>
            </div>

            <div class="footer-column">
                <h4>Explora</h4>
                <ul>
                    <li><a href="<?php echo esc_url(home_url('/')); ?>">Inicio</a></li>
                    <li><a href="<?php echo esc_url(home_url('/calendario')); ?>">Calendario</a></li>
                    <li><a href="<?php echo esc_url(home_url('/ciudades')); ?>">Ciudades</a></li>
                    <li><a href="<?php echo esc_url(home_url('/blog')); ?>">Blog</a></li>
                    <li><a href="<?php echo esc_url(home_url('/mapa')); ?>">Mapa</a></li>
                </ul>
            </div>

            <div class="footer-column">
                <h4>Categorías</h4>
                <ul>
                    <li><a href="<?php echo esc_url(home_url('/eventos/musica/')); ?>">Música</a></li>
                    <li><a href="<?php echo esc_url(home_url('/eventos/teatro/')); ?>">Teatro</a></li>
                    <li><a href="<?php echo esc_url(home_url('/eventos/cine/')); ?>">Cine</a></li>
                    <li><a href="<?php echo esc_url(home_url('/eventos/exposiciones/')); ?>">Exposiciones</a></li>
                    <li><a href="<?php echo esc_url(home_url('/eventos/ferias/')); ?>">Ferias</a></li>
                </ul>
            </div>

            <div class="footer-column footer-newsletter">
                <h4>Síguenos</h4>
                <p>Recibe las mejores propuestas cada semana.</p>

                <form class="footer-subscribe" action="<?php echo esc_url(home_url('/newsletter')); ?>" method="get">
                    <input type="email" name="email" placeholder="Tu email" aria-label="Email">
                    <button type="submit">Suscribirme</button>
                </form>

                <div class="footer-socials">
                    <a href="<?php echo esc_url(home_url('/instagram')); ?>" aria-label="Instagram">Instagram</a>
                    <a href="<?php echo esc_url(home_url('/facebook')); ?>" aria-label="Facebook">Facebook</a>
                    <a href="<?php echo esc_url(home_url('/x')); ?>" aria-label="X">X</a>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <p class="copyright">&copy; <?php echo esc_html(date('Y')); ?> <?php bloginfo('name'); ?></p>
            <nav class="footer-legal" aria-label="Legal">
                <a href="<?php echo esc_url(home_url('/aviso-legal')); ?>">Aviso legal</a>
                <a href="<?php echo esc_url(home_url('/politica-privacidad')); ?>">Política de privacidad</a>
                <a href="<?php echo esc_url(home_url('/politica-cookies')); ?>">Cookies</a>
            </nav>
        </div>
    </div>
</footer>
<?php wp_footer(); ?>
</body>

</html>