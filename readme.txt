=== YGB Scroll Infinito WooCommerce ===
Contributors: ygb
Tags: woocommerce, infinite scroll, scroll infinito, pagination, shop, categories, search, products, ajax, performance
Requires at least: 7.0
Tested up to: 7.1.2
Requires PHP: 8.0
Tested PHP: 8.2
WC requires at least: 7.0
Stable tag: 8.3.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Scroll infinito para tienda y categorías de WooCommerce, con límite configurable y visualización completa de resultados en búsquedas.

== Description ==

**YGB Scroll Infinito** mejora la experiencia de navegación en tu tienda WooCommerce eliminando la paginación tradicional y reemplazándola por un sistema de carga progresiva (scroll infinito).

### Características principales

* **Scroll infinito** en la página de tienda y en las categorías de productos.
* **Carga progresiva** mediante AJAX, sin recargar la página.
* **Búsquedas completas**: en lugar de paginar, muestra **todos los resultados** de una sola vez (límite configurable).
* **Contador dinámico**: actualiza automáticamente el número de productos mostrados.
* **Totalmente configurable** desde el panel de administración:
  * Número de productos a cargar por petición (1–100).
  * Límite máximo de productos totales (10–5000).
* **Compatible** con los temas más populares, especialmente **Astra**, **Flatsome**, **Storefront**, y con los sistemas de caché más usados: SG Speed Optimizer y **LiteSpeed Cache**.
* **Ligero y optimizado**: solo carga scripts en las páginas donde se necesita.
* **Seguro**: validación de nonce, sanitización de entradas y salidas, protección contra SSRF, rate limiting y validación estricta de URLs.
* **Soporte avanzado para lazy loading**: las imágenes de los productos cargados mediante scroll infinito se muestran correctamente gracias a la notificación automática a los sistemas de carga perezosa (WP Rocket, vanilla-lazyload, LiteSpeed Cache, etc.).
* **Selectores de paginación ampliados**: compatible con una amplia variedad de temas.
* **Sistema de reintentos**: si la carga falla, reintenta automáticamente hasta 3 veces.
* **Depuración**: logs detallados en consola (activables/desactivables con `DEBUG`).
* **Anti-bucle**: en la última página no repite la carga ni genera saltos visuales. Se detiene y muestra "No hay más productos".

=== Instalación ===

1. Sube la carpeta `ygb-infinito` al directorio `/wp-content/plugins/`, o instala el plugin directamente desde el repositorio de WordPress.
2. Activa el plugin a través del menú "Plugins" en WordPress.
3. Ve a **Administración > YGB Infinito** para ajustar la configuración (opcional).
4. ¡Listo! El scroll infinito comenzará a funcionar automáticamente en tu tienda y categorías.

=== Preguntas frecuentes ===

= ¿Por qué en las búsquedas no hay scroll infinito? =

Por diseño, en las búsquedas se muestran **todos los resultados** de una sola vez para que el usuario pueda ver todos los productos que coinciden con su consulta sin necesidad de hacer scroll infinito. Esto mejora la usabilidad en búsquedas.

= ¿Puedo cambiar el número de productos que se cargan al hacer scroll? =

Sí. Ve a **Administración > YGB Infinito** y ajusta el campo "Productos por carga". El valor puede estar entre 1 y 100.

= ¿Puedo limitar el número total de productos mostrados? =

Sí. En la misma página de ajustes, configura el "Límite máximo de productos". Si tu tienda tiene 10.000 productos, pero solo quieres mostrar 500, este límite lo controla.

= ¿Funciona con cualquier tema de WooCommerce? =

Está probado con temas que siguen el marcado estándar de WooCommerce (Astra, Flatsome, Storefront, etc.). Si tu tema modifica la estructura de la paginación o de los productos, es posible que necesites ajustes menores. Desde la versión 8.3.2, el plugin incluye múltiples selectores de paginación y un fallback genérico para mejorar la compatibilidad.

= ¿Es compatible con plugins de caché? =

Sí. Está optimizado para funcionar con SG Speed Optimizer, LiteSpeed Cache y otros cachés. Se recomienda vaciar la caché después de activar o actualizar el plugin.

= ¿Cómo desactivo el scroll infinito? =

Simplemente desactiva el plugin desde el panel de plugins. La paginación tradicional volverá a funcionar.

= ¿Cuáles son los requisitos mínimos? =

WordPress 7.0+, PHP 8.0+ y WooCommerce 7.0+.

= Las imágenes no se muestran al cargar nuevos productos, ¿qué hago? =

Asegúrate de usar la versión 8.3.2 o superior. Esta versión incluye notificación automática a los sistemas de lazy loading (LiteSpeed, WP Rocket, etc.). Si el problema persiste, verifica que tu plugin de caché esté actualizado. En el caso de LiteSpeed, prueba a desactivar temporalmente "Aplazar JS" o "Minimizado de JS" para descartar conflictos.

= El plugin no muestra las imágenes cuando uso LiteSpeed Cache en modo Avanzado, ¿qué hago? =

Asegúrate de usar la versión 8.3.2 o superior, que incluye compatibilidad específica con LiteSpeed. Si el problema persiste, prueba a desactivar la opción "Aplazar JS" o "Minimizado de JS" en LiteSpeed, o añade el script `ygb-infinito.js` a la lista de exclusión de aplazamiento. También puedes forzar la recarga de la página después de activar el plugin para que los cambios surtan efecto.

= Al llegar al final de la lista, el scroll empieza a saltar y a cargar productos en bucle, ¿qué hago? =

Actualiza a la versión 8.3.5 o superior. Esta versión incluye una triple capa de protección anti-bucle: (1) el servidor rechaza URLs de paginación repetidas o con número de página no creciente, (2) el JavaScript registra cada URL pedida y detiene la carga cuando detecta una URL ya solicitada o cuando el DOM no crece, y (3) existe un tope absoluto de 100 páginas como red de seguridad. Al llegar al final aparece el mensaje "No hay más productos" y el scroll se detiene.

=== Changelog ===

= 8.3.5 (2026-09-29) =
* **CRÍTICO — Anti-bucle en la última página**: corregido el bucle infinito que hacía que al llegar al final de la lista el plugin intentara cargar más productos una y otra vez, produciendo un efecto de salto y saltos repetidos en lugar de mostrar "No hay más productos" y detenerse.
  * **Servidor (`ygb-infinito.php`)**: se comprueba que la URL "siguiente" extraída del HTML no sea la misma que la que se acaba de pedir ni tenga un número de página menor o igual al actual. Antes, cualquier `<a>` con "next" en la clase dentro del HTML remoto (botones de carrusel, sliders de producto, widgets de terceros) se interpretaba como paginación real y generaba un bucle.
  * **Servidor**: la extracción de la URL "siguiente" ahora usa patrones mucho más estrictos que solo aceptan enlaces dentro de contenedores de paginación (`nav.woocommerce-pagination`, `nav.ast-pagination`) o con la clase exacta `next page-numbers`. Se elimina la coincidencia con cualquier `<a>` que contenga "next" en la clase.
  * **Cliente (`ygb-infinito.js`)**: se añade un set `requestedUrls` que registra cada URL pedida. Si el servidor devuelve una URL ya solicitada, si devuelve la misma URL, o si el número de productos en el DOM no ha crecido tras una respuesta 200, se detiene la carga y se muestra el mensaje de "No hay más productos".
  * **Cliente**: tope absoluto de 100 páginas como red de seguridad independiente de lo que diga el servidor.
  * **Cliente**: el loader se oculta siempre al terminar cada petición (éxito, error o "no hay más"), y no se vuelve a disparar `loadMoreProducts()` cuando `hasMore` es `false`, eliminando el salto visual.
* **Ajuste**: `Tested up to` a 7.1.2 (última versión real de WordPress disponible).
* **Compatibilidad**: sin cambios funcionales respecto a 8.3.4 salvo el anti-bucle.

= 8.3.4 (2026-09-29) =
* **Ajuste**: `Tested up to` actualizado a 7.1.2 (última versión real de WordPress disponible).
* **Ajuste**: Rate limiting del endpoint AJAX subido de 5 a 20 peticiones por minuto por IP. El valor anterior penalizaba el scroll rápido en móvil (scroll con inercia dispara varias cargas seguidas) y generaba 429 en usuarios legítimos, obligando al JS a reintentar.
* **Limpieza**: eliminada la función `force_search_sql_limit()` y su `add_filter('posts_request', ...)`. La función no modificaba nada desde la corrección de seguridad de 8.3.2-fix y solo añadía una llamada por cada query de WordPress sin aportar valor.
* **Limpieza**: eliminado el `add_query_arg('_ygb_req', wp_hash(...))` de la URL saliente del endpoint AJAX. Se añadía a la URL remota pero nunca se verificaba en destino, así que no cumplía función CSRF ni cache-busting útil. El nonce y las cabeceras de la petición ya cubren la seguridad.
* **Logs**: los tres `error_log()` de `ajax_load_more_products()` ahora solo se ejecutan si `WP_DEBUG` está activo. En producción se evita llenar el log del hosting con errores de red transitorios.
* **Compatibilidad**: sin cambios en el comportamiento funcional respecto a 8.3.3.

= 8.3.3 (2026-09-11) =
* **MEDIA - Seguridad**: Reforzadas validaciones en endpoint AJAX público `ygb_infinito_load_more` manteniendo acceso público.
* **Seguridad**: Validación estricta del método HTTP (solo POST permitido).
* **Seguridad**: Rate limiting reforzado reducido a 5 peticiones/minuto por IP (antes 10).
* **Seguridad**: Validación del referer HTTP para prevención adicional de CSRF.
* **Seguridad**: Validación reforzada del nonce con verificación explícita de existencia.
* **Seguridad**: Validación estricta de URL local con regex de caracteres seguros.
* **Seguridad**: Límites de página más estrictos (máximo 50 páginas absoluto).
* **Seguridad**: Limpieza de parámetros sospechosos de la URL antes de la petición.
* **Seguridad**: Token único por petición para tracking y auditoría.
* **Rendimiento**: Timeout reducido a 8 segundos en peticiones remotas.
* **Seguridad**: Bloqueo explícito de redirecciones en peticiones HTTP.
* **Seguridad**: Validación exhaustiva de código de respuesta HTTP con logging detallado.
* **Seguridad**: Validación de contenido HTML recibido antes de inyectar en el DOM.
* **Mejora**: Manejo graceful cuando no hay productos disponibles.
* **Compatibilidad**: Mantenidas todas las funcionalidades de scroll infinito y búsquedas completas.

= 8.3.2-fix (2026-09-08) =
* **CRÍTICO - Seguridad**: Eliminada vulnerabilidad de inyección SQL en `force_search_sql_limit()` - ya no se modifica directamente la consulta SQL con regex peligrosos.
* **CRÍTICO - Seguridad**: Corregido XSS reflejado en `fix_search_counter_js()` - ahora se sanitiza rigurosamente con `esc_js()` y `.text()` de jQuery.
* **CRÍTICO - Seguridad**: Validación estricta de URLs en `is_safe_url()` - previene ataques mediante subdominios maliciosos verificando el host exacto.
* **ALTA - Seguridad**: Eliminada exposición de datos sensibles de `WP_Query` en `get_current_query_args()` - ahora se construye un array mínimo con datos sanitizados.
* **ALTA - Seguridad**: Eliminado objeto `current_query_args` del frontend para prevenir exposición de información interna.
* **MEDIA - Seguridad**: Implementado rate limiting (10 peticiones/minuto por IP) en el endpoint AJAX para prevenir abuso.
* **MEDIA - Rendimiento**: Reducido timeout de peticiones remotas de 30s a 10s para prevenir DoS.
* **Seguridad**: Sanitización mejorada de contenido remoto - eliminación de scripts y estilos potencialmente maliciosos.
* **Mejora**: Añadido uninstall hook para limpieza completa de opciones al desinstalar el plugin.
* **Mejora**: Todos los casts de tipo añadidos para garantizar integridad de datos.
* **Compatibilidad**: Mantenidas todas las mejoras de la versión 8.3.2 (selectores ampliados, reintentos, lazy loading).

= 8.3.2 (2026-07-26) =
* **Mejora**: Múltiples selectores de paginación y fallback para compatibilidad con cualquier tema (Astra, Flatsome, Storefront, etc.).
* **Depuración**: Logs detallados en consola (activables con DEBUG = true).
* **Reintentos**: Sistema automático si la carga falla (hasta 3 intentos).
* **Ajuste**: Umbral de scroll reducido a 200px para mayor reactividad.
* **Soporte**: Evento `resize` para mejorar la experiencia en móviles.
* **LiteSpeed**: Mejora en la detección y activación del lazy loading de LiteSpeed.

= 8.2.5 =
* **Solución**: Compatibilidad con LiteSpeed Cache en modo "Avanzado".
* **Mejora**: Soporte para `LiteSpeed.lazyLoad()` y eventos `lazy-load` / `litespeed_lazyload_init`.

= 8.2.4 =
* **Solución**: Las imágenes de los productos ahora se muestran correctamente al cargar más productos mediante scroll infinito.
* **Mejora**: Se disparan eventos personalizados (`ygb_infinito_loaded`) para que otros scripts puedan escucharlos.
* **Mejora**: Se fuerza un evento `resize` en la ventana para recálculo de posiciones.

= 8.2.3 =
* **Requisitos**: Actualizados a WordPress 7.0+, PHP 8.0+ y WooCommerce 7.0+.
* **Seguridad**: Validación de versión en la activación para prevenir incompatibilidades.

= 8.2.2 =
* **Seguridad**: Validación de dominio en peticiones AJAX para prevenir SSRF.
* **Seguridad**: Sanitización del HTML inyectado en el DOM (eliminación de scripts) para prevenir XSS.
* **Mejora**: PHPDoc completo en todos los métodos.
* **Mejora**: Uso de `wp_safe_remote_get`.

= 8.2.1 =
* Versión inicial pública.

= 8.2.0 =
* Funcionalidad básica de scroll infinito y búsquedas completas.

=== Upgrade Notice ===

= 8.3.5 =
**CRÍTICO — ANTI-BUCLE**: corrige el bucle infinito en la última página que producía saltos visuales y cargas repetidas en lugar de detenerse. Triple capa de protección: servidor (URLs de paginación estrictas y sin repetición), cliente (registro de URLs pedidas y tope de 100 páginas) y DOM (no cargar si el número de productos no crece). Actualización recomendada para todos los usuarios. Purga caché tras actualizar.

= 8.3.4 =
**AJUSTES**: Se sube el rate limiting a 20/min para eliminar falsos 429 en móvil, se limpia dead code (`force_search_sql_limit`) y se elimina el `_ygb_req` inútil de la URL. Los `error_log()` ahora solo escriben con `WP_DEBUG` activo. Sin cambios funcionales.

= 8.3.3 =
**SEGURIDAD**: Esta versión refuerza las validaciones del endpoint AJAX público `ygb_infinito_load_more` con rate limiting más estricto (5 peticiones/min), validación de referer, nonce reforzado, límites de página más estrictos y validación exhaustiva de respuestas HTTP. Se recomienda actualizar para mejorar la seguridad manteniendo la funcionalidad pública.

= 8.3.2-fix =
**CRÍTICO - SEGURIDAD**: Esta versión corrige múltiples vulnerabilidades críticas y altas (inyección SQL, XSS, exposición de datos). **Actualización obligatoria inmediata** para todos los usuarios. Mantiene todas las funcionalidades de la 8.3.2.

= 8.3.2 =
**Importante**: Esta versión incluye selectores de paginación ampliados, sistema de reintentos y logs de depuración. Se recomienda actualizar para mejorar la compatibilidad con temas variados.

= 8.2.5 =
**Importante**: Esta versión corrige el problema de imágenes no mostradas al cargar productos mediante scroll infinito con LiteSpeed Cache en modo Avanzado. Se recomienda actualizar.

= 8.2.4 =
**Importante**: Esta versión corrige el problema de imágenes no mostradas al cargar productos mediante scroll infinito con lazy loading. Se recomienda actualizar.

= 8.2.3 =
**Importante**: Esta versión actualiza los requisitos mínimos. Asegúrate de que tu sitio cumple con WordPress 7.0+, PHP 8.0+ y WooCommerce 7.0+ antes de actualizar.

= 8.2.2 =
**Importante**: Esta versión incluye mejoras de seguridad. Se recomienda actualizar cuanto antes.

=== Screenshots ===

1. Pantalla de ajustes del plugin con las opciones de configuración.
2. Ejemplo de scroll infinito en la tienda.
3. Panel de configuración mostrando rate limiting y validación de URLs activas.
4. Mensaje "No hay más productos" al llegar al final (comportamiento anti-bucle desde 8.3.5).