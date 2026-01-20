<?php
/**
 * ============================================================================
 * ГЕНЕРАТОР JSON: Цены за м² для пиломатериалов И листовых материалов
 * ============================================================================
 * 
 * ОБНОВЛЁННАЯ ВЕРСИЯ v2.1 - ИСПРАВЛЕНЫ:
 * ✅ Категория 301 вместо 127
 * ✅ Добавлена страница в админке
 * ✅ Добавлен AJAX обработчик
 * ✅ Поддержка листовых материалов
 */

if (!defined('ABSPATH')) {
    if (file_exists('../../../wp-load.php')) {
        require_once('../../../wp-load.php');
    }
}

/**
 * ============================================================================
 * ОСНОВНАЯ ФУНКЦИЯ ГЕНЕРАЦИИ JSON
 * ============================================================================
 */
function generate_timber_and_leaf_prices_json() {
    
    $timber_categories = array_merge([87, 310], range(88, 93));
    $leaf_categories = [190, 191, 301, 94];
    $all_categories = array_merge($timber_categories, $leaf_categories);
    
    $args = [
        'post_type' => 'product',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'tax_query' => [
            [
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $all_categories,
            ]
        ]
    ];
    
    $products = get_posts($args);
    $prices = [];
    $processed = 0;
    $skipped = 0;
    
    foreach ($products as $post) {
        $product_id = $post->ID;
        $product = wc_get_product($product_id);
        
        if (!$product) {
            $skipped++;
            continue;
        }
        
        $price_per_m3 = floatval($product->get_regular_price());
        
        if ($price_per_m3 <= 0) {
            $skipped++;
            continue;
        }
        
        $is_timber = has_term($timber_categories, 'product_cat', $product_id);
        $is_leaf = has_term($leaf_categories, 'product_cat', $product_id);
        
        // ОБА ТИПА ОБРАБАТЫВАЮТСЯ ОДИНАКОВО: ₽/м³ → ₽/м²
        if ($is_timber || $is_leaf) {
            if (!function_exists('get_cubic_product_params')) {
                error_log("Function get_cubic_product_params not found!");
                $skipped++;
                continue;
            }
            
            $params = get_cubic_product_params($product_id);
            
            if (!$params) {
                $thickness = 20;
                error_log("Product $product_id: using default thickness 20mm");
            } else {
                $thickness = $params['thickness'];
            }
            
            $thickness_m = $thickness / 1000;
            $price_per_m2 = $price_per_m3 * $thickness_m;
            
            $prices[$product_id] = [
                'price_m3' => $price_per_m3,
                'price_m2' => round($price_per_m2, 2),
                'thickness' => $thickness,
                'type' => 'timber',
                'title' => $product->get_name()
            ];
            
            $processed++;
        }
    }
    
    $upload_dir = wp_upload_dir();
    $json_file = $upload_dir['basedir'] . '/timber-prices.json';
    
    $json_data = [
        'generated_at' => current_time('mysql'),
        'total_products' => count($products),
        'processed' => $processed,
        'skipped' => $skipped,
        'timber_count' => count(array_filter($prices, fn($p) => $p['type'] === 'timber')),
        'leaf_count' => count(array_filter($prices, fn($p) => $p['type'] === 'leaf')),
        'prices' => $prices
    ];
    
    $result = file_put_contents($json_file, json_encode($json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    if ($result === false) {
        error_log("Failed to write JSON file: $json_file");
        return false;
    }
    
    error_log("Generated timber+leaf prices JSON: $json_file");
    error_log("Timber: " . $json_data['timber_count'] . ", Leaf: " . $json_data['leaf_count'] . ", Skipped: $skipped");
    
    return [
        'file' => $json_file,
        'url' => $upload_dir['baseurl'] . '/timber-prices.json',
        'processed' => $processed,
        'skipped' => $skipped,
        'total' => count($products),
        'timber_count' => $json_data['timber_count'],
        'leaf_count' => $json_data['leaf_count']
    ];
}

/**
 * ============================================================================
 * AJAX ОБРАБОТЧИК
 * ============================================================================
 */
add_action('wp_ajax_generate_timber_json', 'parusweb_ajax_generate_timber_json');

function parusweb_ajax_generate_timber_json() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Недостаточно прав');
        wp_die();
    }
    
    $result = generate_timber_and_leaf_prices_json();
    
    if ($result) {
        $message = sprintf(
            'JSON успешно создан!<br>' .
            'Файл: %s<br>' .
            'URL: <a href="%s" target="_blank">%s</a><br>' .
            'Обработано: %d (ПМ: %d, Листовые: %d)<br>' .
            'Пропущено: %d<br>' .
            'Всего: %d',
            $result['file'],
            $result['url'],
            $result['url'],
            $result['processed'],
            $result['timber_count'],
            $result['leaf_count'],
            $result['skipped'],
            $result['total']
        );
        wp_send_json_success($message);
    } else {
        wp_send_json_error('Ошибка при создании JSON файла');
    }
    
    wp_die();
}

/**
 * ============================================================================
 * СТРАНИЦА В АДМИНКЕ
 * ============================================================================
 */
add_action('admin_menu', 'parusweb_add_timber_json_admin_page');

function parusweb_add_timber_json_admin_page() {
    add_submenu_page(
        'tools.php',
        'Цены ПМ и листовых',
        'Цены ПМ и листовых',
        'manage_options',
        'timber-prices-generator',
        'parusweb_render_timber_json_admin_page'
    );
}

function parusweb_render_timber_json_admin_page() {
    ?>
    <div class="wrap">
        <h1>🌲 Генератор цен за м² (Пиломатериалы + Листовые)</h1>
        
        <div class="card" style="max-width: 800px; margin: 20px 0;">
            <h2>Описание</h2>
            <p>Этот инструмент создаёт JSON файл с ценами за м² для:</p>
            <ul>
                <li><strong>Пиломатериалы</strong> (категории 87-93, 310) — пересчёт из ₽/м³ в ₽/м²</li>
                <li><strong>Листовые</strong> (категории 190, 191, 301, 94) — пересчёт из ₽/м³ в ₽/м²</li>
            </ul>
        </div>
        
        <div class="card" style="max-width: 800px; margin: 20px 0;">
            <h2>Генерация</h2>
            <p>
                <button id="generate-timber-json" class="button button-primary button-hero">
                    🚀 Сгенерировать timber-prices.json
                </button>
            </p>
            <div id="generation-result" style="margin-top: 20px;"></div>
        </div>
        
        <div class="card" style="max-width: 800px; margin: 20px 0;">
            <h2>Информация</h2>
            <table class="widefat">
                <tr>
                    <td><strong>Файл:</strong></td>
                    <td><code><?php echo wp_upload_dir()['basedir']; ?>/timber-prices.json</code></td>
                </tr>
                <tr>
                    <td><strong>URL:</strong></td>
                    <td>
                        <a href="<?php echo wp_upload_dir()['baseurl']; ?>/timber-prices.json" target="_blank">
                            <?php echo wp_upload_dir()['baseurl']; ?>/timber-prices.json
                        </a>
                    </td>
                </tr>
                <tr>
                    <td><strong>Автообновление:</strong></td>
                    <td>✅ Да (при сохранении товаров)</td>
                </tr>
            </table>
        </div>
        
        <div class="card" style="max-width: 800px; margin: 20px 0;">
            <h2>Категории</h2>
            <h3>Пиломатериалы (₽/м³ → ₽/м²)</h3>
            <p>87, 88, 89, 90, 91, 92, 93, 310</p>
            
            <h3>Листовые (₽/м³ → ₽/м²)</h3>
            <p>190, 191, 301, 94</p>
            </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#generate-timber-json').on('click', function() {
            const $button = $(this);
            const $result = $('#generation-result');
            
            $button.prop('disabled', true).text('⏳ Генерация...');
            $result.html('<p>⏳ Генерация JSON файла...</p>');
            
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'generate_timber_json'
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<div class="notice notice-success is-dismissible"><p>' + response.data + '</p></div>');
                    } else {
                        $result.html('<div class="notice notice-error is-dismissible"><p>❌ ' + response.data + '</p></div>');
                    }
                },
                error: function(xhr, status, error) {
                    $result.html('<div class="notice notice-error is-dismissible"><p>❌ AJAX ошибка: ' + error + '</p></div>');
                },
                complete: function() {
                    $button.prop('disabled', false).html('🚀 Сгенерировать timber-prices.json');
                }
            });
        });
    });
    </script>
    
    <style>
    .card {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        padding: 20px;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
    }
    .card h2 {
        margin-top: 0;
    }
    .widefat td {
        padding: 10px;
    }
    </style>
    <?php
}

/**
 * ============================================================================
 * АВТОМАТИЧЕСКАЯ РЕГЕНЕРАЦИЯ
 * ============================================================================
 */
add_action('woocommerce_update_product', 'parusweb_maybe_regenerate_json', 10, 1);
add_action('woocommerce_new_product', 'parusweb_maybe_regenerate_json', 10, 1);

function parusweb_maybe_regenerate_json($product_id) {
    
    $all_categories = array_merge([87, 310], range(88, 93), [190, 191, 301, 94]);
    
    if (!has_term($all_categories, 'product_cat', $product_id)) {
        return;
    }
    
    $transient_key = 'timber_json_regenerating';
    
    if (get_transient($transient_key)) {
        return;
    }
    
    set_transient($transient_key, true, 300);
    
    generate_timber_and_leaf_prices_json();
    
    error_log("Timber+Leaf JSON regenerated after product $product_id update");
}