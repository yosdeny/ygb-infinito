<?php
/**
 * Desinstalación de YGB Scroll Infinito WooCommerce.
 *
 * WordPress ejecuta este fichero automáticamente cuando el plugin se elimina
 * desde el panel de administración (Plugins > Eliminar) o vía WP-CLI
 * (`wp plugin uninstall ygb-infinito`). Se ejecuta en un contexto aislado:
 * ninguna clase, función ni constante del plugin está disponible.
 *
 * Por ese motivo el cleanup es autocontenido y no depende de
 * YGB_Scroll_Infinito ni de YGB_INFINITO_VERSION.
 *
 * @package YGB_Scroll_Infinito
 */

// Seguridad: solo se ejecuta desde el flujo de desinstalación de WordPress.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Elimina las opciones y transients del plugin en el sitio actual.
 *
 * No usa la clase del plugin porque no está cargada en este contexto.
 *
 * @return void
 */
function ygb_infinito_uninstall_site(): void {
    global $wpdb;

    // --- Opciones principales ---
    delete_option('ygb_infinito_options');
    delete_option('ygb_infinito_version');

    // --- Transient de activación (aviso admin) ---
    delete_transient('ygb_infinito_activated');

    // --- Transients de rate limiting por IP ---
    // Se usa esc_like() para que los guiones bajos literales ("_") no actúen
    // como comodines SQL de un solo carácter.
    $like_data    = $wpdb->esc_like('_transient_ygb_rate_limit_') . '%';
    $like_timeout = $wpdb->esc_like('_transient_timeout_ygb_rate_limit_') . '%';

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $like_data
        )
    );
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $like_timeout
        )
    );
    // phpcs:enable
}

if (is_multisite()) {
    // Recorrer todos los sitios de la red. `number => 0` desactiva el límite
    // por defecto (100) para no dejar sitios sin limpiar en redes grandes.
    $site_ids = get_sites([
        'fields' => 'ids',
        'number' => 0,
    ]);

    if (is_array($site_ids)) {
        foreach ($site_ids as $site_id) {
            switch_to_blog((int) $site_id);
            ygb_infinito_uninstall_site();
            restore_current_blog();
        }
    }
} else {
    ygb_infinito_uninstall_site();
}