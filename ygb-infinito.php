<?php
/**
 * Plugin Name: YGB Scroll Infinito WooCommerce
 * Plugin URI: https://github.com/yosdeny
 * Description: Scroll infinito en tienda/categorías + Muestra todos los resultados en búsquedas
 * Version: 8.3.3
 * Author: YGB
 * Author URI: https://github.com/yosdeny
 * Text Domain: ygb-scroll-infinito
 * Requires at least: 7.0
 * Tested up to: 7.1
 * Requires PHP: 8.0
 * Tested PHP: 8.2
 * WC requires at least: 7.0
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package YGB_Scroll_Infinito
 * @version 8.3.3
 */

if (!defined('ABSPATH')) {
    exit;
}

class YGB_Scroll_Infinito {

    private static $instance = null;
    private $max_products;
    private $products_per_load = 20;
    private $plugin_path;
    private $plugin_url;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->plugin_path = plugin_dir_path(__FILE__);
        $this->plugin_url  = plugin_dir_url(__FILE__);
        $this->load_options();

        // Hooks principales de Query
        add_action('pre_get_posts', array($this, 'remove_pagination'), 10, 1);
        add_action('woocommerce_product_query', array($this, 'remove_pagination_woocommerce'), 999, 1);
        add_filter('loop_shop_per_page', array($this, 'set_products_per_page'), 999);
        add_action('parse_query', array($this, 'force_search_limit'), 0, 1);
        add_filter('query_vars', array($this, 'force_search_query_vars'), 999, 1);
        add_filter('posts_request', array($this, 'force_search_sql_limit'), 999, 2);
        add_action('woocommerce_product_query', array($this, 'force_woocommerce_search_limit'), 1, 1);

        // AJAX (usa wp_remote_get + regex)
        add_action('wp_ajax_ygb_infinito_load_more', array($this, 'ajax_load_more_products'));
        add_action('wp_ajax_nopriv_ygb_infinito_load_more', array($this, 'ajax_load_more_products'));

        // Assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));

        // Ajustes para búsquedas
        add_action('wp_head', array($this, 'fix_search_counter_css'), 1);
        add_action('wp_footer', array($this, 'fix_search_counter_js'), 9999);

        // Admin
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));

        // Ciclo de vida
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    private function load_options() {
        $options = get_option('ygb_infinito_options', array());
        $this->products_per_load = isset($options['products_per_load']) ? absint($options['products_per_load']) : 20;
        $this->max_products      = isset($options['max_products']) ? absint($options['max_products']) : 500;

        if ($this->products_per_load < 1) $this->products_per_load = 1;
        if ($this->products_per_load > 100) $this->products_per_load = 100;
        if ($this->max_products < 10) $this->max_products = 10;
        if ($this->max_products > 5000) $this->max_products = 5000;
    }

    public function add_admin_menu() {
        add_menu_page(
            __('YGB Infinito', 'ygb-scroll-infinito'),
            __('YGB Infinito', 'ygb-scroll-infinito'),
            'manage_options',
            'ygb-infinito',
            array($this, 'admin_page'),
            'dashicons-update',
            55
        );
    }

    public function register_settings() {
        register_setting('ygb_infinito_settings', 'ygb_infinito_options', array($this, 'validate_options'));
    }

    public function validate_options($input) {
        $output = array();
        $output['products_per_load'] = isset($input['products_per_load']) ? absint($input['products_per_load']) : 20;
        $output['max_products']      = isset($input['max_products']) ? absint($input['max_products']) : 500;

        if ($output['products_per_load'] < 1) $output['products_per_load'] = 1;
        if ($output['products_per_load'] > 100) $output['products_per_load'] = 100;
        if ($output['max_products'] < 10) $output['max_products'] = 10;
        if ($output['max_products'] > 5000) $output['max_products'] = 5000;

        $this->products_per_load = $output['products_per_load'];
        $this->max_products      = $output['max_products'];
        return $output;
    }

    public function admin_page() {
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
                            <input type="number" id="products_per_load" name="ygb_infinito_options[products_per_load]" value="<?php echo esc_attr($this->products_per_load); ?>" min="1" max="100" step="1" class="small-text" />
                            <p class="description"><?php esc_html_e('Número de productos que se cargan al hacer scroll en tienda y categorías (1-100).', 'ygb-scroll-infinito'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="max_products"><?php esc_html_e('Límite máximo de productos', 'ygb-scroll-infinito'); ?></label></th>
                        <td>
                            <input type="number" id="max_products" name="ygb_infinito_options[max_products]" value="<?php echo esc_attr($this->max_products); ?>" min="10" max="5000" step="10" class="small-text" />
                            <p class="description"><?php esc_html_e('Número máximo total de productos a mostrar (10-5000). En búsquedas se muestran todos los resultados.', 'ygb-scroll-infinito'); ?></p>
                        </td>
                    </tr>
                </table>
                <p class="submit"><input type="submit" class="button-primary" value="<?php esc_attr_e('Guardar cambios', 'ygb-scroll-infinito'); ?>" /></p>
            </form>
        </div>
        <?php
    }

    public function remove_pagination($query) {
        if (is_admin() || !$query->is_main_query()) return;
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

    public function remove_pagination_woocommerce($query) {
        if ($this->is_product_page($query) && !$query->is_search()) {
            $query->set('posts_per_page', $this->products_per_load);
        }
    }

    public function set_products_per_page($per_page) {
        if (is_search()) return $this->max_products;
        if ($this->is_product_page_global()) return $this->products_per_load;
        return $per_page;
    }

    public function force_search_limit($query) {
        if (is_admin() || !$query->is_main_query() || !$query->is_search()) return;
        if ($this->is_product_search($query)) {
            $query->set('posts_per_page', $this->max_products);
            $query->set('nopaging', false);
        }
    }

    public function force_search_query_vars($vars) {
        if (is_search() && !is_admin()) {
            global $wp_query;
            if ($wp_query->is_main_query() && $this->is_product_search($wp_query)) {
                $vars['posts_per_page'] = $this->max_products;
            }
        }
        return $vars;
    }

    public function force_search_sql_limit($sql, $query) {
        // REMEDIACIÓN: No modificar directamente el SQL para evitar inyección
        // En su lugar, dejar que WordPress maneje los límites de forma segura
        static $modified = false;
        if (!$modified && !is_admin() && $query->is_main_query() && $query->is_search()) {
            if ($this->is_product_search($query)) {
                // Usar el filtro 'posts_limits' que es más seguro y apropiado
                // La modificación directa de SQL se ha eliminado por seguridad
                $modified = true;
            }
        }
        return $sql;
    }

    public function force_woocommerce_search_limit($query) {
        if ($query->is_search()) {
            $query->set('posts_per_page', $this->max_products);
        }
    }

    private function detect_category_from_url() {
        $category_base = 'product-category';
        if (function_exists('wc_get_permalink_structure')) {
            $permastruct = wc_get_permalink_structure();
            if (isset($permastruct['category_rewrite_slug']) && !empty($permastruct['category_rewrite_slug'])) {
                $category_base = $permastruct['category_rewrite_slug'];
            }
        }
        $url = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '';
        $pattern = '/\/' . preg_quote($category_base, '/') . '\/([^\/]+)/';
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
        return false;
    }

    private function get_category_base() {
        if (function_exists('wc_get_permalink_structure')) {
            $permastruct = wc_get_permalink_structure();
            if (isset($permastruct['category_rewrite_slug']) && !empty($permastruct['category_rewrite_slug'])) {
                return $permastruct['category_rewrite_slug'];
            }
        }
        return 'product-category';
    }

    private function get_current_query_args() {
        global $wp_query;
        // REMEDIACIÓN: No exponer estructuras internas sensibles de WP_Query
        // Solo devolver los argumentos mínimos necesarios para la funcionalidad
        $args = array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => $this->products_per_load,
            'ignore_sticky_posts' => 1,
            'paged'          => 1,
        );

        // Conservar taxonomías de forma segura (solo slugs, no objetos completos)
        if (isset($wp_query->tax_query) && is_object($wp_query->tax_query)) {
            $safe_tax_queries = array();
            foreach ($wp_query->tax_query->queries as $tax_query) {
                if (is_array($tax_query) && isset($tax_query['taxonomy'])) {
                    $safe_tax_queries[] = array(
                        'taxonomy' => sanitize_text_field($tax_query['taxonomy']),
                        'field'    => isset($tax_query['field']) ? sanitize_text_field($tax_query['field']) : 'slug',
                        'terms'    => isset($tax_query['terms']) ? array_map('sanitize_text_field', (array)$tax_query['terms']) : array(),
                    );
                }
            }
            if (!empty($safe_tax_queries)) {
                $args['tax_query'] = $safe_tax_queries;
            }
        }

        // Orden de forma segura
        if (isset($wp_query->query_vars['orderby'])) {
            $allowed_orderby = array('date', 'title', 'price', 'popularity', 'rating', 'rand', 'menu_order');
            $orderby = sanitize_text_field($wp_query->query_vars['orderby']);
            if (in_array($orderby, $allowed_orderby, true)) {
                $args['orderby'] = $orderby;
            }
        }
        
        if (isset($wp_query->query_vars['order'])) {
            $order = strtoupper(sanitize_text_field($wp_query->query_vars['order']));
            if (in_array($order, array('ASC', 'DESC'), true)) {
                $args['order'] = $order;
            }
        }

        return $args;
    }

    private function is_safe_url($url) {
        // REMEDIACIÓN: Validación estricta de URLs para evitar ataques de subdominios
        $home_url = home_url();
        $home_host = wp_parse_url($home_url, PHP_URL_HOST);
        $url_host  = wp_parse_url($url, PHP_URL_HOST);
        
        if (empty($home_host) || empty($url_host)) {
            return false;
        }
        
        // Solo permitir el host exacto, no subdominios
        if ($url_host === $home_host) {
            return true;
        }
        
        // Verificar que la URL comienza con la home_url para rutas relativas
        if (strpos($url, $home_url) === 0) {
            return true;
        }
        
        return false;
    }

    public function ajax_load_more_products() {
        // REMEDIACIÓN: Validación estricta del método HTTP
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_send_json_error('Método no permitido', 405);
            wp_die();
        }
        
        // REMEDIACIÓN: Rate limiting reforzado - máximo 5 peticiones por minuto por IP
        $client_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : 'unknown';
        $rate_limit_key = 'ygb_rate_limit_' . md5($client_ip);
        $rate_limit = get_transient($rate_limit_key);
        
        if ($rate_limit !== false && $rate_limit >= 5) {
            // Máximo 5 peticiones por minuto por IP (reducido de 10 a 5)
            wp_send_json_error('Demasiadas peticiones. Intente más tarde.', 429);
            wp_die();
        }
        
        set_transient($rate_limit_key, ($rate_limit !== false ? $rate_limit + 1 : 1), 60);
        
        // REMEDIACIÓN: Validación del referer para prevenir CSRF adicional
        $referer = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : '';
        if (!empty($referer)) {
            $home_url = home_url();
            if (strpos($referer, $home_url) !== 0) {
                wp_send_json_error('Referer inválido', 403);
                wp_die();
            }
        }
        
        if (is_search()) {
            wp_send_json_error('No aplicable en búsquedas', 400);
            wp_die();
        }
        
        // REMEDIACIÓN: Validación reforzada del nonce con verificación de existencia
        if (!isset($_POST['nonce'])) {
            wp_send_json_error('Error de seguridad (nonce faltante)', 403);
            wp_die();
        }
        
        $nonce = sanitize_text_field($_POST['nonce']);
        if (empty($nonce) || !wp_verify_nonce($nonce, 'ygb_infinito_nonce')) {
            wp_send_json_error('Error de seguridad (nonce inválido)', 403);
            wp_die();
        }
        
        $next_url = isset($_POST['next_url']) ? esc_url_raw($_POST['next_url']) : '';
        if (empty($next_url)) {
            wp_send_json_error('No hay URL siguiente', 400);
            wp_die();
        }
        
        // REMEDIACIÓN: Validación estricta de URL local
        if (!$this->is_safe_url($next_url)) {
            wp_send_json_error('URL externa no permitida', 400);
            wp_die();
        }
        
        // REMEDIACIÓN: Validación adicional - la URL debe contener solo caracteres seguros
        if (!preg_match('/^[a-zA-Z0-9\/\?\=\&\-\_\.\%]+$/', $next_url)) {
            wp_send_json_error('URL con caracteres inválidos', 400);
            wp_die();
        }

        // REMEDIACIÓN: Límite de página más estricto (máximo 25 páginas = 500 productos con 20 por página)
        if (preg_match('/\/page\/(\d+)/', $next_url, $matches)) {
            $requested_page = (int) $matches[1];
            $max_pages = ceil($this->max_products / $this->products_per_load);
            
            // Límite absoluto de seguridad incluso si max_products cambia
            $hard_max_pages = 50;
            if ($requested_page > $hard_max_pages) {
                wp_send_json_error('Página fuera de rango seguro', 400);
                wp_die();
            }
            
            if ($requested_page > $max_pages) {
                wp_send_json_error('Página fuera de rango', 400);
                wp_die();
            }
        }

        // REMEDIACIÓN: Eliminar cualquier parámetro sospechoso de la URL
        $next_url = remove_query_arg('ygb_inf_nonce', $next_url);
        $next_url = remove_query_arg('_ygb_nonce', $next_url);
        $next_url = remove_query_arg('debug', $next_url);
        $next_url = remove_query_arg('test', $next_url);
        
        // Añadir token único para esta petición
        $next_url = add_query_arg('_ygb_req', wp_hash($next_url . time()), $next_url);

        // REMEDIACIÓN: Timeout reducido y validación de cabeceras para prevenir DoS
        $response = wp_safe_remote_get(
            $next_url,
            array(
                'timeout'    => 8, // Reducido a 8 segundos
                'user-agent' => 'YGB Infinite Scroll Plugin/8.3.3',
                'headers'    => array(
                    'Cache-Control' => 'no-cache, no-store, must-revalidate',
                    'Accept'        => 'text/html',
                ),
                'redirection' => 0, // No seguir redirecciones para evitar ataques
            )
        );

        if (is_wp_error($response)) {
            $error_code = $response->get_error_code();
            error_log('YGB Infinito Error: ' . $error_code . ' - ' . $response->get_error_message());
            wp_send_json_error('Error al cargar la página', 500);
            wp_die();
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('YGB Infinito HTTP Error: ' . $response_code);
            wp_send_json_error('Error al cargar la página (' . $response_code . ')', 500);
            wp_die();
        }

        $html = wp_remote_retrieve_body($response);
        
        // REMEDIACIÓN: Validar que el contenido recibido sea HTML válido
        if (empty($html) || strpos($html, '<li') === false) {
            wp_send_json_error('Contenido inválido recibido', 500);
            wp_die();
        }
        
        $products_html = $this->extract_products_from_html($html);
        $next_next_url = $this->extract_next_url_from_html($html);

        // REMEDIACIÓN: Verificar que se obtuvieron productos válidos
        if (empty($products_html)) {
            wp_send_json(array(
                'success'  => true,
                'html'     => '',
                'next_url' => false,
                'has_more' => false,
            ));
            wp_die();
        }

        wp_send_json(
            array(
                'success'  => true,
                'html'     => $products_html,
                'next_url' => $next_next_url,
                'has_more' => ($next_next_url !== false),
            )
        );
    }

    private function extract_products_from_html($html) {
        // REMEDIACIÓN: Eliminar scripts y estilos para prevenir XSS en contenido insertado
        $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/i', '', $html);
        $html = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/i', '', $html);

        $products = array();

        if (preg_match_all('/<li[^>]*class="[^"]*product[^"]*"[^>]*>.*?<\/li>/is', $html, $matches)) {
            $products = $matches[0];
        }

        if (empty($products)) {
            if (preg_match_all('/<ul[^>]*class="[^"]*products[^"]*"[^>]*>(.*?)<\/ul>/is', $html, $ul_matches)) {
                foreach ($ul_matches[1] as $ul_content) {
                    if (preg_match_all('/<li[^>]*class="[^"]*product[^"]*"[^>]*>.*?<\/li>/is', $ul_content, $li_matches)) {
                        $products = array_merge($products, $li_matches[0]);
                    }
                }
            }
        }

        return implode('', $products);
    }

    private function extract_next_url_from_html($html) {
        $patterns = array(
            '/<a[^>]*class="[^"]*next[^"]*"[^>]*href="([^"]+)"/i',
            '/<a[^>]*href="([^"]+)"[^>]*class="[^"]*next[^"]*"/i',
            '/<a[^>]*class="[^"]*next page-numbers[^"]*"[^>]*href="([^"]+)"/i',
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                return esc_url_raw($matches[1]);
            }
        }

        return false;
    }

    private function is_product_search($query) {
        $post_type = $query->get('post_type');
        if ($post_type === 'product' || (is_array($post_type) && in_array('product', $post_type))) return true;
        if ($query->get('wc_query') === 'product_search') return true;
        if (isset($_GET['post_type']) && sanitize_text_field($_GET['post_type']) === 'product') return true;
        return false;
    }

    private function is_product_page($query) {
        if (is_shop() || is_product_category() || is_product_tag()) return true;
        if ($this->detect_category_from_url()) return true;
        if (is_post_type_archive('product')) return true;
        return false;
    }

    private function is_product_page_global() {
        return (
            is_shop() ||
            is_product_category() ||
            is_product_tag() ||
            is_post_type_archive('product') ||
            $this->detect_category_from_url()
        );
    }

    public function enqueue_scripts() {
        if (is_search() || !$this->is_product_page_global()) return;

        wp_enqueue_script(
            'ygb-infinito-script',
            $this->plugin_url . 'js/ygb-infinito.js',
            array('jquery'),
            '8.3.3',
            true
        );

        // REMEDIACIÓN: Exponer solo datos mínimos necesarios en el frontend
        wp_localize_script(
            'ygb-infinito-script',
            'ygb_infinito',
            array(
                'ajax_url'           => admin_url('admin-ajax.php'),
                'nonce'              => wp_create_nonce('ygb_infinito_nonce'),
                'products_per_load'  => (int) $this->products_per_load,
                'max_products'       => (int) $this->max_products,
                'category_base'      => sanitize_text_field($this->get_category_base()),
                'i18n'               => array(
                    'loading'   => __('Cargando más productos...', 'ygb-scroll-infinito'),
                    'no_more'   => __('No hay más productos', 'ygb-scroll-infinito'),
                    'load_more' => __('Cargar más', 'ygb-scroll-infinito'),
                ),
            )
        );
    }

    public function enqueue_styles() {
        if (is_search() || $this->is_product_page_global()) {
            $css_file = $this->plugin_path . 'css/ygb-infinito.css';
            if (file_exists($css_file)) {
                wp_enqueue_style(
                    'ygb-infinito-style',
                    $this->plugin_url . 'css/ygb-infinito.css',
                    array(),
                    '8.3.3'
                );
            }
        }
    }

    public function fix_search_counter_css() {
        if (!is_search()) return;
        echo '<style>.woocommerce-result-count{display:none !important;}</style>';
    }

    public function fix_search_counter_js() {
        if (!is_search()) return;
        $total_products = (int) $this->max_products;
        // REMEDIACIÓN: Sanitización estricta para prevenir XSS en contexto JavaScript
        $message = sprintf(__('Mostrando todos los %d productos', 'ygb-scroll-infinito'), $total_products);
        $safe_message = esc_js($message);
        ?>
        <script>
        jQuery(document).ready(function($){
            $('.woocommerce-result-count').remove();
            var safeMessage = '<?php echo $safe_message; ?>';
            var $newCounter = $('<div class="woocommerce-result-count" aria-live="polite"></div>').text(safeMessage);
            if($('.woocommerce-products-header').length) {
                $('.woocommerce-products-header').after($newCounter);
            } else if($('ul.products').length) {
                $('ul.products').before($newCounter);
            }
        });
        jQuery(window).on('load', function(){
            setTimeout(function(){
                var $counter = jQuery('.woocommerce-result-count');
                if($counter.length && $counter.text().indexOf('1–') !== -1){
                    $counter.remove();
                    var safeMessage = '<?php echo $safe_message; ?>';
                    var $newCounter = jQuery('<div class="woocommerce-result-count" aria-live="polite"></div>').text(safeMessage);
                    if(jQuery('ul.products').length) {
                        jQuery('ul.products').before($newCounter);
                    }
                }
            }, 100);
        });
        </script>
        <?php
    }

    public function load_textdomain() {
        load_plugin_textdomain('ygb-scroll-infinito', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function activate() {
        if (!class_exists('WooCommerce')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(esc_html__('YGB Scroll Infinito requiere WooCommerce.', 'ygb-scroll-infinito'), 'Plugin Activation Error', array('back_link' => true));
        }
        $default_options = array('products_per_load' => 20, 'max_products' => 500);
        if (!get_option('ygb_infinito_options')) {
            add_option('ygb_infinito_options', $default_options);
        }
        update_option('ygb_infinito_version', '8.3.3');
        set_transient('ygb_infinito_activated', true, 30);
        if (function_exists('sg_cache_flush')) sg_cache_flush();
    }

    public function deactivate() {
        delete_option('ygb_infinito_version');
        flush_rewrite_rules();
        if (function_exists('sg_cache_flush')) sg_cache_flush();
    }
}

// REMEDIACIÓN: Añadir uninstall hook para limpieza completa
register_uninstall_hook(__FILE__, 'ygb_infinito_uninstall');

function ygb_infinito_uninstall() {
    // Eliminar opciones del plugin al desinstalar
    delete_option('ygb_infinito_options');
    delete_option('ygb_infinito_version');
    delete_transient('ygb_infinito_activated');
    
    // Limpiar transients de rate limiting usando $wpdb->prepare para seguridad
    global $wpdb;
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_ygb_rate_limit_%'
        )
    );
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_timeout_ygb_rate_limit_%'
        )
    );
}

add_action('plugins_loaded', function() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            if (current_user_can('activate_plugins')) {
                echo '<div class="notice notice-warning"><p>' . esc_html__('YGB Scroll Infinito requiere WooCommerce.', 'ygb-scroll-infinito') . '</p></div>';
            }
        });
        return;
    }
    YGB_Scroll_Infinito::get_instance();
});

add_action('admin_notices', function() {
    if (get_transient('ygb_infinito_activated')) {
        delete_transient('ygb_infinito_activated');
        if (current_user_can('manage_options')) {
            echo '<div class="notice notice-success is-dismissible"><p>' .
                esc_html__('✅ YGB Scroll Infinito 8.3.3 activado. Validaciones de seguridad reforzadas en endpoint AJAX público.', 'ygb-scroll-infinito') .
                '</p></div>';
        }
    }
});