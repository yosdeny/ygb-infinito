/**
 * YGB Scroll Infinito - v8.3.2 (con selectores ampliados, logs y reintentos)
 */
jQuery(function($) {
    if (window.location.href.indexOf('s=') !== -1) {
        console.log('[YGB] Búsqueda detectada - scroll infinito desactivado');
        return;
    }

    var DEBUG = true;  // Cambiar a false en producción
    var loading = false;
    var hasMore = true;
    var nextUrl = null;
    var scrollTimeout = null;
    var retryCount = 0;
    var MAX_RETRIES = 3;

    function log(msg, data) {
        if (DEBUG) {
            if (data !== undefined) {
                console.log('[YGB] ' + msg, data);
            } else {
                console.log('[YGB] ' + msg);
            }
        }
    }

    function warn(msg, data) {
        if (DEBUG) {
            if (data !== undefined) {
                console.warn('[YGB] ' + msg, data);
            } else {
                console.warn('[YGB] ' + msg);
            }
        }
    }

    function error(msg, data) {
        if (DEBUG) {
            if (data !== undefined) {
                console.error('[YGB] ' + msg, data);
            } else {
                console.error('[YGB] ' + msg);
            }
        }
    }

    function getNextPageUrl() {
        var selectors = [
            '.woocommerce-pagination a.next',
            '.woocommerce-pagination .next a',
            '.woocommerce-pagination .next.page-numbers',
            '.woocommerce-pagination a.next.page-numbers',
            '.woocommerce-pagination .page-numbers .next',
            '.woocommerce-pagination li a.next',
            '.woocommerce-pagination li .next',
            '.woocommerce-pagination .next',
            '.shop-pagination a.next',
            '.pagination a.next',
            '.pagination .next a',
            '.navigation.pagination .next a',
            'nav.woocommerce-pagination a.next',
            'nav.woocommerce-pagination .next a',
            'a.next.page-numbers',
            '.next.page-numbers',
            '.load-more a',
            '.woocommerce-load-more a',
            'a.load-more'
        ];

        for (var i = 0; i < selectors.length; i++) {
            var link = $(selectors[i]);
            if (link.length) {
                var href = link.prop('href');
                if (href) {
                    log('Selector encontrado: ' + selectors[i] + ' -> ' + href);
                    return href;
                }
            }
        }

        var allLinks = $('a[href*="page/"]');
        if (allLinks.length) {
            var candidates = allLinks.filter('.next, .page-numbers');
            if (candidates.length) {
                var href = candidates.last().prop('href');
                if (href) {
                    log('Fallback: último .page-numbers -> ' + href);
                    return href;
                }
            }
        }

        warn('No se encontró ningún enlace de paginación.');
        return false;
    }

    function updateCounter() {
        var productCount = $('ul.products li.product').length;
        var counter = $('.woocommerce-result-count');
        if (counter.length) {
            var currentText = counter.text();
            var newText = currentText.replace(/Mostrando \d+[–-]\d+/, 'Mostrando 1–' + productCount);
            counter.text(newText);
        }
        log('Contador actualizado: ' + productCount + ' productos cargados');
    }

    function getTotalProducts() {
        var counter = $('.woocommerce-result-count').text();
        var match = counter.match(/de (\d+)/);
        return match ? parseInt(match[1], 10) : 0;
    }

    function init() {
        nextUrl = getNextPageUrl();
        hasMore = (nextUrl !== false);
        totalProductsFound = getTotalProducts();
        totalProductsLoaded = $('ul.products li.product').length;
        $('.woocommerce-pagination, .astra-pagination').hide();
        log('Inicializado. Productos: ' + totalProductsLoaded + '/' + totalProductsFound + ', nextUrl: ' + nextUrl);
        if (!hasMore) {
            warn('No hay más productos desde el inicio.');
        }
    }

    function triggerLazyLoad() {
        log('Notificando lazy loaders...');

        $(document).trigger('ygb_infinito_loaded');
        if (window.wp && wp.hooks) {
            wp.hooks.doAction('ygb_infinito_loaded');
        }

        if (window.lazyLoadInstance && typeof window.lazyLoadInstance.update === 'function') {
            window.lazyLoadInstance.update();
            log('vanilla-lazyload actualizado');
        }

        // LiteSpeed - múltiples estrategias
        function activateLiteSpeed() {
            var ls = window.LiteSpeed || window.litespeed || window.LS;
            if (ls) {
                log('Objeto LiteSpeed detectado:', ls);
                var methods = ['lazyLoad', 'load', 'init', 'initLazyLoad', 'lazyLoadImages'];
                for (var i = 0; i < methods.length; i++) {
                    if (typeof ls[methods[i]] === 'function') {
                        try {
                            ls[methods[i]]();
                            log('LiteSpeed.' + methods[i] + '() ejecutado');
                            return true;
                        } catch (e) {
                            warn('Error llamando a LiteSpeed.' + methods[i] + '():', e);
                        }
                    }
                }
                if (typeof ls === 'function') {
                    try {
                        ls();
                        return true;
                    } catch (e) {}
                }
                return false;
            }
            return false;
        }

        var success = activateLiteSpeed();

        if (!success) {
            var attempts = 0;
            var maxAttempts = 5;
            var interval = setInterval(function() {
                attempts++;
                if (activateLiteSpeed()) {
                    clearInterval(interval);
                } else if (attempts >= maxAttempts) {
                    clearInterval(interval);
                    warn('No se pudo activar LiteSpeed después de ' + maxAttempts + ' intentos.');
                }
            }, 300);
        }

        $(document).trigger('lazy-load');
        $(document).trigger('litespeed_lazyload_init');
        $('ul.products').trigger('lazy-load');

        $(window).trigger('resize');
        $(window).trigger('scroll');

        setTimeout(function() {
            $('ul.products li.product:not(.ygb-processed)').each(function() {
                var $item = $(this);
                $item.addClass('ygb-processed');
                $item.find('img').each(function() {
                    var $img = $(this);
                    var dataSrc = $img.attr('data-src') || $img.attr('data-lazy-src') || $img.attr('data-lazy');
                    if (dataSrc) {
                        if (!$img.attr('src') || $img.attr('src').indexOf('data:image') !== -1 || $img.attr('src') === '') {
                            $img.attr('src', dataSrc);
                            $img.removeAttr('data-src data-lazy-src data-lazy');
                            $img.removeClass('lazyload lazy-loading jetpack-lazy-image');
                            log('Imagen forzada manualmente: ' + dataSrc);
                        }
                    }
                });
            });
        }, 500);
    }

    function loadMoreProducts() {
        if (loading) {
            log('Carga en progreso, ignorando.');
            return;
        }
        if (!hasMore) {
            log('No hay más productos (hasMore=false)');
            return;
        }
        if (!nextUrl) {
            warn('No hay nextUrl, intentando obtener de nuevo...');
            nextUrl = getNextPageUrl();
            if (!nextUrl) {
                hasMore = false;
                warn('No se pudo obtener nextUrl, deteniendo.');
                return;
            }
        }

        loading = true;
        log('Cargando más productos desde ' + nextUrl);

        if ($('.ygb-infinito-loader').length === 0) {
            $('ul.products').after('<div class="ygb-infinito-loader" aria-live="polite">' + ygb_infinito.i18n.loading + '</div>');
        }
        $('.ygb-infinito-loader').show();

        $.post(
            ygb_infinito.ajax_url,
            {
                action: 'ygb_infinito_load_more',
                nonce: ygb_infinito.nonce,
                next_url: nextUrl
            },
            function(response) {
                log('Respuesta AJAX recibida:', response);
                if (response.success && response.html && response.html.trim() !== '') {
                    var tempDiv = $('<div>').html(response.html);
                    tempDiv.find('script').remove();

                    var newProductsCount = tempDiv.find('li.product').length;
                    log('Productos nuevos encontrados: ' + newProductsCount);

                    if (newProductsCount > 0) {
                        $('ul.products').append(tempDiv.children());
                        updateCounter();
                        triggerLazyLoad();
                        retryCount = 0;
                    }

                    nextUrl = response.next_url || false;
                    hasMore = (nextUrl !== false && response.has_more !== false);
                    log('Nuevo nextUrl: ' + nextUrl + ', hasMore: ' + hasMore);

                    if (!hasMore) {
                        $('.ygb-infinito-loader').hide();
                        if ($('.ygb-infinito-no-more').length === 0) {
                            $('ul.products').after('<div class="ygb-infinito-no-more">' + ygb_infinito.i18n.no_more + '</div>');
                        }
                        log('No hay más productos');
                    } else {
                        $('.ygb-infinito-loader').hide();
                    }
                } else {
                    warn('Respuesta sin productos o fallida:', response);
                    $('.ygb-infinito-loader').hide();
                    if (response.has_more === false || response.html === '') {
                        hasMore = false;
                        if ($('.ygb-infinito-no-more').length === 0) {
                            $('ul.products').after('<div class="ygb-infinito-no-more">' + ygb_infinito.i18n.no_more + '</div>');
                        }
                    } else {
                        if (retryCount < MAX_RETRIES) {
                            retryCount++;
                            warn('Reintento ' + retryCount + ' de ' + MAX_RETRIES);
                            setTimeout(function() {
                                loading = false;
                                loadMoreProducts();
                            }, 2000);
                        } else {
                            error('Máximo de reintentos alcanzado.');
                            if ($('.ygb-infinito-error').length === 0) {
                                $('ul.products').after('<div class="ygb-infinito-error">Error al cargar. Intenta recargar la página.</div>');
                            }
                        }
                    }
                }
                loading = false;
            }
        ).fail(function(jqXHR, textStatus, errorThrown) {
            error('Error AJAX: ' + textStatus, errorThrown);
            $('.ygb-infinito-loader').hide();
            loading = false;
            if (retryCount < MAX_RETRIES) {
                retryCount++;
                warn('Reintento ' + retryCount + ' de ' + MAX_RETRIES + ' por error AJAX');
                setTimeout(function() {
                    loadMoreProducts();
                }, 2000);
            } else {
                error('Máximo de reintentos alcanzado por error AJAX.');
                if ($('.ygb-infinito-error').length === 0) {
                    $('ul.products').after('<div class="ygb-infinito-error">Error de conexión. Intenta recargar la página.</div>');
                }
            }
        });
    }

    $(window).on('scroll', function() {
        if (scrollTimeout) clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(function() {
            if (!loading && hasMore) {
                var scrollTop = $(window).scrollTop();
                var windowHeight = $(window).height();
                var docHeight = $(document).height();
                if (scrollTop + windowHeight >= docHeight - 200) {
                    $('.ygb-infinito-error').remove();
                    loadMoreProducts();
                }
            }
        }, 100);
    });

    $(window).on('resize', function() {
        if (!loading && hasMore) {
            var scrollTop = $(window).scrollTop();
            var windowHeight = $(window).height();
            var docHeight = $(document).height();
            if (scrollTop + windowHeight >= docHeight - 200) {
                loadMoreProducts();
            }
        }
    });

    $(document).ready(function() {
        init();
        if (!hasMore && nextUrl === false) {
            setTimeout(function() {
                var newUrl = getNextPageUrl();
                if (newUrl) {
                    nextUrl = newUrl;
                    hasMore = true;
                    log('Reinicialización: se encontró nextUrl después de retardo: ' + nextUrl);
                }
            }, 1000);
        }
    });

    $(window).on('load', function() {
        if (!hasMore && nextUrl === false) {
            var newUrl = getNextPageUrl();
            if (newUrl) {
                nextUrl = newUrl;
                hasMore = true;
                log('Reinicialización en load: se encontró nextUrl: ' + nextUrl);
            }
        }
        setTimeout(function() {
            var scrollTop = $(window).scrollTop();
            var windowHeight = $(window).height();
            var docHeight = $(document).height();
            if (scrollTop + windowHeight >= docHeight - 200) {
                loadMoreProducts();
            }
        }, 500);
    });

    window.ygb_infinito_debug = {
        loadMore: loadMoreProducts,
        getNextUrl: getNextPageUrl,
        state: function() {
            return { loading: loading, hasMore: hasMore, nextUrl: nextUrl, retryCount: retryCount };
        }
    };
});