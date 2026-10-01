<?php
/**
 * Plugin Name: YGB Scroll Infinito WooCommerce
 * Plugin URI: https://github.com/yosdeny
 * Description: Scroll infinito en tienda/categorías + Muestra todos los resultados en búsquedas
 * Version: 8.3.6
 * Author: YGB
 * Author URI: https://github.com/yosdeny
 * Text Domain: ygb-scroll-infinito
 * Requires at least: 7.0
 * Tested up to: 7.1.2
 * Requires PHP: 8.0
 * Tested PHP: 8.2
 * WC requires at least: 7.0
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package YGB_Scroll_Infinito
 * @version 8.3.6
 */

if (!defined('ABSPATH')) {
    exit;
}

define('YGB_INFINITO_VERSION', '8.3.6');
define('YGB_INFINITO_FILE', __FILE__);

class YGB_Scroll_Infinito {

    /** @var self|null */
    private static $instance = null;

    /** @var int */
    private $max_products = 500;

    /** @var int */
    private $products_per_load = 20;

    /** @var string */
    private $plugin_path;

    /** @var string */
    private $plugin_url;

    public static function get_instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->plugin_path = plugin_dir_path(YGB_INFINITO_FILE);
        $this->plugin_url  = plugin_dir_url(YGB_INFINITO_FILE);
        $this->load_options();

        // Hooks de query.
        add_action('pre_get_posts', [$this, 'remove_pagination'], 10, 1);
        add_action('woocommerce_product_query', [$this, 'remove_pagination_woocommerce'], 999, 1);
        add_filter('loop_shop_per_page', [$this, 'set_products_per_page'], 999);
        add_action('parse_query', [$this, 'force_search_limit'], 0, 1);
        add_action('woocommerce_product_query', [$this, 'force_woocommerce_search_limit'], 1, 1);

        // AJAX.
        add_action('wp_ajax_ygb_infinito_load_more', [$this, 'ajax_load_more_products']);
        add_action('wp_ajax_nopriv_ygb_infinito_load_more', [$this, 'ajax_load_more_products']);

        // Assets y traducciones.
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);
        // WP 6.7+: load_plugin_textdomain debe ejecutarse en init o posterior.
        add_action('init', [$this, 'load_textdomain']);

        // Contador de búsquedas.
        add_action('wp_head', [$this, 'fix_search_counter_css'], 1);
        add_action('wp_footer', [$this, 'fix_search_counter_js'], 9999);

        // Admin.
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    private function load_options(): void {
        $options = get_option('ygb_infinito_options', []);
        $this->products_per_load = isset($options['products_per_load']) ? absint($options['products_per_load']) : 20;
        $this->max_products      = isset($options['max_products']) ? absint($options['max_products']) : 500;

        if ($this->products_per_load < 1) {
            $this->products_per_load = 1;
        }
        if ($this->products_per_load > 100) {
            $this->products_per_load = 100;
        }
        if ($this->max_products < 10) {
            $this->max_products = 10;
        }
        if ($this->max_products > 5000) {
            $this->max_products = 5000;
        }
    }

    public function add_admin_menu(): void {
        add_menu_page(
            __('YGB Infinito', 'ygb-scroll-infinito'),
            __('YGB Infinito', 'ygb-scroll-infinito'),
            'manage_options',
            'ygb-infinito',
            [$this, 'admin_page'],
            'dashicons-update',
            55
        );
    }

    public function register_settings(): void {
        register_setting('ygb_infinito_settings', 'ygb_infinito_options', [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'validate_options'],
            'default'           => [
                'products_per_load' => 20,
                'max_products'      => 500,
            ],
        ]);
    }

    public function validate_options($input): array {
        $output = [
            'products_per_load' => isset($input['products_per_load']) ? absint($input['products_per_load']) : 20,
            'max_products'      => isset($input['max_products']) ? absint($input['max_products']) : 500,
        ];

        if ($output['products_per_load'] < 1) {
            $output['products_per_load'] = 1;
        }
        if ($output['products_per_load'] > 100) {
            $output['products_per_load'] = 100;
        }
        if ($output['max_products'] < 10) {
            $output['max_products'] = 10;
        }
        if ($output['max_products'] > 5000) {
            $output['max_products'] = 5000;
        }

        $this->products_per_load = $output['products_per_load'];
        $this->max_products      = $output['max_products'];

        return $output;
    }

    public function admin_page(): void {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('YGB Scroll Infinito', 'ygb-scroll-infinito'); ?></h1>
            <div class="notice notice-info"><p><?php esc_html_e('Configura el comportamiento del scroll infinito en tu tienda WooCommerce.', 'ygb-scroll-infinito'); ?></p></div>
            <form method="post" action="options.php">
                <?php settings_fields('ygb_infinito_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="products_per_load"><?php esc_html_e('Productos por carga', 'ygb-scroll-infinito'); ?></label></th>
                        <td>
                            <input type="number" id="products_per_load" name="ygb_infinito_options[products_per_load]" value="<?php echo esc_attr((string) $this->products_per_load); ?>" min="1" max="100" step="1" class="small-text" />
                            <p class="description"><?php esc_html_e('Número de productos que se cargan al hacer scroll en tienda y categorías (1-100).', 'ygb-scroll-infinito'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="max_products"><?php esc_html_e('Límite máximo de productos', 'ygb-scroll-infinito'); ?></label></th>
                        <td>
                            <input type="number" id="max_products" name="ygb_infinito_options[max_products]" value="<?php echo esc_attr((string) $this->max_products); ?>" min="10" max="5000" step="10" class="small-text" />
                            <p class="description"><?php esc_html_e('Número máximo total de productos a mostrar (10-5000). En búsquedas se muestran todos los resultados.', 'ygb-scroll-infinito'); ?></p>
                        </td>
                    </tr>
                </table>
                <p class="submit"><input type="submit" class="button-primary" value="<?php esc_attr_e('Guardar cambios', 'ygb-scroll-infinito'); ?>" /></p>
            </form>
        </div>
        <?php
    }

    public function remove_pagination($query): void {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }
        if ($query->is_search()) {
            if ($this->is_product_search($query)) {
                $query->set('posts_per_page', $this->max_products);
                $query->set('nopaging', false);
            }
            return;
        }
        if ($this->is_product_page($query)) {
            $query->set('posts_per_page', $this->products_per_load);
            $query->set('nopaging', false);
        }
    }

    public function remove_pagination_woocommerce($query): void {
        if ($this->is_product_page($query) && !$query->is_search()) {
            $query->set('posts_per_page', $this->products_per_load);
        }
    }

    public function set_products_per_page($per_page) {
        if (is_search()) {
            return $this->max_products;
        }
        if ($this->is_product_page_global()) {
            return $this->products_per_load;
        }
        return $per_page;
    }

    public function force_search_limit($query): void {
        if (is_admin() || !$query->is_main_query() || !$query->is_search()) {
            return;
        }
        if ($this->is_product_search($query)) {
            $query->set('posts_per_page', $this->max_products);
            $query->set('nopaging', false);
        }
    }

    public function force_woocommerce_search_limit($query): void {
        if ($query->is_search()) {
            $query->set('posts_per_page', $this->max_products);
        }
    }

    private function detect_category_from_url() {
        $category_base = $this->get_category_base();
        $url = isset($_SERVER['REQUEST_URI'])
            ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']))
            : '';
        $pattern = '/\/' . preg_quote($category_base, '/') . '\/([^\/]+)/';
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
        return false;
    }

    private function get_category_base(): string {
        if (function_exists('wc_get_permalink_structure')) {
            $permastruct = wc_get_permalink_structure();
            if (isset($permastruct['category_rewrite_slug']) && !empty($permastruct['category_rewrite_slug'])) {
                return (string) $permastruct['category_rewrite_slug'];
            }
        }
        return 'product-category';
    }

    /**
     * Comprueba si una URL pertenece al propio sitio (host + puerto exactos).
     *
     * Corrige el bypass por prefijo: `https://midominio.com.evil.com` ya no pasa.
     *
     * @param string $url
     * @return bool
     */
    private function is_safe_url($url): bool {
        if (!is_string($url) || $url === '') {
            return false;
        }

        $home = wp_parse_url(home_url('/'));
        $test = wp_parse_url($url);

        if (empty($home['host']) || empty($test['host'])) {
            return false;
        }

        $home_scheme = isset($home['scheme']) ? strtolower((string) $home['scheme']) : 'https';
        $test_scheme = isset($test['scheme']) ? strtolower((string) $test['scheme']) : '';

        if ($test_scheme !== '' && !in_array($test_scheme, ['http', 'https'], true)) {
            return false;
        }

        if (strtolower((string) $test['host']) !== strtolower((string) $home['host'])) {
            return false;
        }

        $home_port = isset($home['port'])
            ? (int) $home['port']
            : ($home_scheme === 'https' ? 443 : 80);
        $test_port = isset($test['port'])
            ? (int) $test['port']
            : ($test_scheme === 'https' ? 443 : ($test_scheme === 'http' ? 80 : $home_port));

        return $home_port === $test_port;
    }

    public function ajax_load_more_products(): void {
        // 1) Método HTTP.
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            wp_send_json_error('Método no permitido', 405);
        }

        // 2) Rate limiting (best-effort con transient por IP).
        $client_ip      = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
        $rate_limit_key = 'ygb_rate_limit_' . md5($client_ip);
        $rate_limit     = get_transient($rate_limit_key);

        if ($rate_limit !== false && (int) $rate_limit >= 20) {
            wp_send_json_error('Demasiadas peticiones. Intente más tarde.', 429);
        }

        set_transient($rate_limit_key, ($rate_limit !== false ? ((int) $rate_limit + 1) : 1), 60);

        // 3) Referer (solo si el navegador lo envía). Comparación por host, no por prefijo.
        $referer = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : '';
        if ($referer !== '') {
            $referer_host = wp_parse_url($referer, PHP_URL_HOST);
            $home_host    = wp_parse_url(home_url('/'), PHP_URL_HOST);
            if (empty($referer_host) || empty($home_host)
                || strtolower((string) $referer_host) !== strtolower((string) $home_host)) {
                wp_send_json_error('Referer inválido', 403);
            }
        }

        // 4) Nonce.
        if (!isset($_POST['nonce'])) {
            wp_send_json_error('Error de seguridad (nonce faltante)', 403);
        }
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce']));
        if ($nonce === '' || !wp_verify_nonce($nonce, 'ygb_infinito_nonce')) {
            wp_send_json_error('Error de seguridad (nonce inválido)', 403);
        }

        // 5) next_url.
        $next_url = isset($_POST['next_url']) ? esc_url_raw(wp_unslash($_POST['next_url'])) : '';
        if ($next_url === '') {
            wp_send_json_error('No hay URL siguiente', 400);
        }

        if (!$this->is_safe_url($next_url)) {
            wp_send_json_error('URL externa no permitida', 400);
        }

        // Solo caracteres seguros en la URL de paginación.
        if (!preg_match('/^[A-Za-z0-9\/\?\=\&\-\_\.\%\:]+$/', $next_url)) {
            wp_send_json_error('URL con caracteres inválidos', 400);
        }

        // 6) Límite de página alineado con los ajustes reales del plugin.
        if (preg_match('/\/page\/(\d+)/', $next_url, $matches)) {
            $requested_page = (int) $matches[1];
            $per_load       = max(1, (int) $this->products_per_load);
            $max_pages      = max(1, (int) ceil($this->max_products / $per_load));
            $absolute_max   = 2000; // Red de seguridad absoluta, no de configuración.

            if ($requested_page > $max_pages || $requested_page > $absolute_max) {
                wp_send_json_error('Página fuera de rango', 400);
            }
        }

        // 7) Limpieza de parámetros sospechosos.
        $next_url = remove_query_arg(['ygb_inf_nonce', '_ygb_nonce', 'debug', 'test'], $next_url);

        // 8) Petición remota segura.
        $response = wp_safe_remote_get(
            $next_url,
            [
                'timeout'     => 8,
                'user-agent'  => 'YGB Infinite Scroll Plugin/' . YGB_INFINITO_VERSION,
                'headers'     => [
                    'Cache-Control' => 'no-cache, no-store, must-revalidate',
                    'Accept'        => 'text/html',
                ],
                'redirection' => 0,
            ]
        );

        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('YGB Infinito Error: ' . $response->get_error_code() . ' - ' . $response->get_error_message());
            }
            wp_send_json_error('Error al cargar la página', 500);
        }

        $response_code = (int) wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('YGB Infinito HTTP Error: ' . $response_code);
            }
            wp_send_json_error('Error al cargar la página (' . $response_code . ')', 500);
        }

        $html = (string) wp_remote_retrieve_body($response);
        if ($html === '' || strpos($html, '<li') === false) {
            wp_send_json_error('Contenido inválido recibido', 500);
        }

        $products_html = $this->extract_products_from_html($html);
        $next_next_url = $this->extract_next_url_from_html($html);

        // 9) Anti-bucle en el servidor.
        if ($next_next_url !== false) {
            $current_norm = untrailingslashit($next_url);
            $next_norm    = untrailingslashit($next_next_url);

            if ($current_norm === $next_norm) {
                $next_next_url = false;
            } else {
                $current_page_num = 0;
                $next_page_num    = 0;

                if (preg_match('/\/page\/(\d+)/', $next_url, $m)) {
                    $current_page_num = (int) $m[1];
                }
                if (preg_match('/\/page\/(\d+)/', $next_next_url, $m)) {
                    $next_page_num = (int) $m[1];
                }

                if ($current_page_num > 0 && $next_page_num > 0 && $next_page_num <= $current_page_num) {
                    $next_next_url = false;
                }
            }
        }

        if ($products_html === '') {
            wp_send_json([
                'success'  => true,
                'html'     => '',
                'next_url' => false,
                'has_more' => false,
            ]);
        }

        wp_send_json([
            'success'  => true,
            'html'     => $products_html,
            'next_url' => $next_next_url,
            'has_more' => ($next_next_url !== false),
        ]);
    }

    /**
     * Extrae los <li class="...product..."> del HTML paginado.
     *
     * Nota: se mantiene el enfoque regex por compatibilidad con el resto del
     * plugin, pero se documenta que WP_HTML_Tag_Processor (WP 6.2+) sería la
     * alternativa robusta. Los <li> anidados dentro de un producto (menús,
     * widgets) no son habituales en WooCommerce; si aparecieran, el regex
     * cortaría el HTML antes de tiempo.
     */
    private function extract_products_from_html(string $html): string {
        $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/is', '', $html);

        $products = [];

        if (preg_match_all('/<li[^>]*class="[^"]*product[^"]*"[^>]*>.*?<\/li>/is', (string) $html, $matches)) {
            $products = $matches[0];
        }

        if (empty($products) && preg_match_all('/<ul[^>]*class="[^"]*products[^"]*"[^>]*>(.*?)<\/ul>/is', (string) $html, $ul_matches)) {
            foreach ($ul_matches[1] as $ul_content) {
                if (preg_match_all('/<li[^>]*class="[^"]*product[^"]*"[^>]*>.*?<\/li>/is', $ul_content, $li_matches)) {
                    $products = array_merge($products, $li_matches[0]);
                }
            }
        }

        return implode('', $products);
    }

    /**
     * Extrae la URL "siguiente" del HTML remoto.
     *
     * Solo acepta enlaces dentro de contenedores de paginación conocidos
     * (WooCommerce, Astra) o con la clase exacta "next page-numbers" para
     * evitar capturar botones de carrusel o sliders de terceros.
     *
     * @param string $html
     * @return string|false
     */
    private function extract_next_url_from_html(string $html) {
        $patterns = [
            '/<nav[^>]*class="[^"]*woocommerce-pagination[^"]*"[^>]*>.*?<a[^>]*class="[^"]*next[^"]*"[^>]*href="([^"]+)"/is',
            '/<nav[^>]*class="[^"]*ast-pagination[^"]*"[^>]*>.*?<a[^>]*class="[^"]*next[^"]*"[^>]*href="([^"]+)"/is',
            '/<a[^>]*class="[^"]*next page-numbers[^"]*"[^>]*href="([^"]+)"/i',
            '/<a[^>]*href="([^"]+)"[^>]*class="[^"]*next page-numbers[^"]*"/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                return esc_url_raw($matches[1]);
            }
        }

        return false;
    }

    private function is_product_search($query): bool {
        $post_type = $query->get('post_type');
        if ($post_type === 'product' || (is_array($post_type) && in_array('product', $post_type, true))) {
            return true;
        }
        if ($query->get('wc_query') === 'product_search') {
            return true;
        }
        if (isset($_GET['post_type']) && sanitize_text_field(wp_unslash($_GET['post_type'])) === 'product') {
            return true;
        }
        return false;
    }

    private function is_product_page($query): bool {
        if (is_shop() || is_product_category() || is_product_tag()) {
            return true;
        }
        if ($this->detect_category_from_url()) {
            return true;
        }
        if (is_post_type_archive('product')) {
            return true;
        }
        return false;
    }

    private function is_product_page_global(): bool {
        return (
            is_shop() ||
            is_product_category() ||
            is_product_tag() ||
            is_post_type_archive('product') ||
            (bool) $this->detect_category_from_url()
        );
    }

    public function enqueue_scripts(): void {
        if (is_search() || !$this->is_product_page_global()) {
            return;
        }

        wp_enqueue_script(
            'ygb-infinito-script',
            $this->plugin_url . 'js/ygb-infinito.js',
            ['jquery'],
            YGB_INFINITO_VERSION,
            true
        );

        wp_localize_script(
            'ygb-infinito-script',
            'ygb_infinito',
            [
                'ajax_url'          => admin_url('admin-ajax.php'),
                'nonce'             => wp_create_nonce('ygb_infinito_nonce'),
                'products_per_load' => (int) $this->products_per_load,
                'max_products'      => (int) $this->max_products,
                'category_base'     => sanitize_text_field($this->get_category_base()),
                'i18n'              => [
                    'loading'   => __('Cargando más productos...', 'ygb-scroll-infinito'),
                    'no_more'   => __('No hay más productos', 'ygb-scroll-infinito'),
                    'load_more' => __('Cargar más', 'ygb-scroll-infinito'),
                ],
            ]
        );
    }

    public function enqueue_styles(): void {
        if (!is_search() && !$this->is_product_page_global()) {
            return;
        }
        $css_file = $this->plugin_path . 'css/ygb-infinito.css';
        if (file_exists($css_file)) {
            wp_enqueue_style(
                'ygb-infinito-style',
                $this->plugin_url . 'css/ygb-infinito.css',
                [],
                YGB_INFINITO_VERSION
            );
        }
    }

    public function fix_search_counter_css(): void {
        if (!is_search()) {
            return;
        }
        echo '<style>.woocommerce-result-count{display:none !important;}</style>';
    }

    public function fix_search_counter_js(): void {
        if (!is_search()) {
            return;
        }
        $total_products = (int) $this->max_products;
        $message        = sprintf(
            /* translators: %d: total de productos */
            __('Mostrando todos los %d productos', 'ygb-scroll-infinito'),
            $total_products
        );
        $safe_message   = esc_js($message);
        ?>
        <script>
        jQuery(function($){
            $('.woocommerce-result-count').remove();
            var safeMessage = '<?php echo $safe_message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado con esc_js() ?>';
            var $newCounter = $('<div class="woocommerce-result-count" aria-live="polite"></div>').text(safeMessage);
            if ($('.woocommerce-products-header').length) {
                $('.woocommerce-products-header').after($newCounter);
            } else if ($('ul.products').length) {
                $('ul.products').before($newCounter);
            }
        });
        jQuery(window).on('load', function(){
            setTimeout(function(){
                var $counter = jQuery('.woocommerce-result-count');
                if ($counter.length && $counter.text().indexOf('1–') !== -1) {
                    $counter.remove();
                    var safeMessage = '<?php echo $safe_message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado con esc_js() ?>';
                    var $newCounter = jQuery('<div class="woocommerce-result-count" aria-live="polite"></div>').text(safeMessage);
                    if (jQuery('ul.products').length) {
                        jQuery('ul.products').before($newCounter);
                    }
                }
            }, 100);
        });
        </script>
        <?php
    }

    public function load_textdomain(): void {
        load_plugin_textdomain('ygb-scroll-infinito', false, dirname(plugin_basename(YGB_INFINITO_FILE)) . '/languages');
    }

    /**
     * Callback estático de activación.
     *
     * Registrado a nivel de fichero con register_activation_hook() para que
     * se ejecute realmente. Antes vivía dentro del constructor, que corre en
     * plugins_loaded — demasiado tarde para que WordPress procese el hook.
     */
    public static function activate(): void {
        if (!class_exists('WooCommerce')) {
            deactivate_plugins(plugin_basename(YGB_INFINITO_FILE));
            wp_die(
                esc_html__('YGB Scroll Infinito requiere WooCommerce.', 'ygb-scroll-infinito'),
                'Plugin Activation Error',
                ['back_link' => true]
            );
        }

        $default_options = ['products_per_load' => 20, 'max_products' => 500];
        if (!get_option('ygb_infinito_options')) {
            add_option('ygb_infinito_options', $default_options);
        }
        update_option('ygb_infinito_version', YGB_INFINITO_VERSION);
        set_transient('ygb_infinito_activated', true, 30);

        if (function_exists('sg_cache_flush')) {
            sg_cache_flush();
        }
    }

    /**
     * Callback estático de desactivación.
     */
    public static function deactivate(): void {
        delete_option('ygb_infinito_version');
        flush_rewrite_rules();
        if (function_exists('sg_cache_flush')) {
            sg_cache_flush();
        }
    }
}

// -----------------------------------------------------------------------------
// Ciclo de vida del plugin.
//
// IMPORTANTE: register_activation_hook() y register_deactivation_hook() se
// registran aquí, a nivel de fichero, NO dentro del constructor de la clase.
// El constructor se ejecuta en plugins_loaded, momento en el que WordPress ya
// ha procesado los hooks de activación/desactivación, por lo que registrarlos
// allí los dejaba inertes.
//
// La DESINSTALACIÓN se gestiona en uninstall.php (mecanismo recomendado por
// WordPress). No se usa register_uninstall_hook() para evitar dos mecanismos
// compitiendo; WordPress da prioridad a uninstall.php cuando existe.
// -----------------------------------------------------------------------------
register_activation_hook(YGB_INFINITO_FILE, ['YGB_Scroll_Infinito', 'activate']);
register_deactivation_hook(YGB_INFINITO_FILE, ['YGB_Scroll_Infinito', 'deactivate']);

// -----------------------------------------------------------------------------
// Bootstrap: instancia el plugin tras WooCommerce (prioridad 20).
// -----------------------------------------------------------------------------
add_action('plugins_loaded', static function (): void {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', static function (): void {
            if (current_user_can('activate_plugins')) {
                echo '<div class="notice notice-warning"><p>'
                    . esc_html__('YGB Scroll Infinito requiere WooCommerce.', 'ygb-scroll-infinito')
                    . '</p></div>';
            }
        });
        return;
    }
    YGB_Scroll_Infinito::get_instance();
}, 20);

add_action('admin_notices', static function (): void {
    if (get_transient('ygb_infinito_activated')) {
        delete_transient('ygb_infinito_activated');
        if (current_user_can('manage_options')) {
            echo '<div class="notice notice-success is-dismissible"><p>'
                . esc_html__('✅ YGB Scroll Infinito 8.3.6 activado.', 'ygb-scroll-infinito')
                . '</p></div>';
        }
    }
});