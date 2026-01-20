<?php
/**
 * ============================================================================
 * ИНТЕГРАЦИЯ МОДУЛЯ КОЛЕРОВКИ С ДРУГИМИ МОДУЛЯМИ
 * ============================================================================
 * 
 * Этот файл добавить в parusweb-functions.php после подключения всех модулей
 * 
 * @package ParusWeb_Functions
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

// ============================================================================
// ИНТЕГРАЦИЯ С МОДУЛЕМ LITER-PRODUCTS
// ============================================================================

/**
 * Интеграция колеровки с калькулятором объёма тары
 * Обновляет итоговую цену с учётом колеровки
 */
function parusweb_integrate_tinting_with_liter_calculator() {
    if (!is_product()) {
        return;
    }
    
    global $product;
    if (!$product) {
        return;
    }
    
    $product_id = $product->get_id();
    
    // Проверяем, что это ЛКМ с колеровкой
    if (!function_exists('parusweb_is_liter_product') || !parusweb_is_liter_product($product_id)) {
        return;
    }
    
    if (!function_exists('parusweb_is_tinting_available') || !parusweb_is_tinting_available($product_id)) {
        return;
    }
    
    ?>
    <script>
    jQuery(document).ready(function($) {
        'use strict';
        
        // Слушаем изменение объёма тары
        $(document).on('change', '#product_liter_volume', function() {
            recalculateTotalWithTinting();
        });
        
        // Слушаем изменение колеровки
        $(document.body).on('parusweb_tinting_changed', function(e, data) {
            recalculateTotalWithTinting();
        });
        
        /**
         * Пересчёт итоговой цены с учётом объёма и колеровки
         */
        function recalculateTotalWithTinting() {
            const $volumeSelect = $('#product_liter_volume');
            const $priceDisplay = $('.parusweb-liter-product .calculated-price');
            const $tintingWrapper = $('.parusweb-tinting-wrapper');
            
            if (!$volumeSelect.length || !$priceDisplay.length) {
                return;
            }
            
            const volume = parseFloat($volumeSelect.val()) || 0;
            const pricePerLiter = parseFloat($volumeSelect.data('base-price')) || 0;
            const discount = parseFloat($volumeSelect.data('discount')) || 0;
            
            if (volume === 0 || pricePerLiter === 0) {
                return;
            }
            
            // Рассчитываем базовую цену с учётом скидки
            let totalPrice = volume * pricePerLiter;
            
            if (volume >= 9 && discount > 0) {
                totalPrice = totalPrice * (1 - discount / 100);
            }
            
            // Добавляем стоимость колеровки
            const tintingEnabled = $tintingWrapper.find('input[name="tinting_enabled"]').val() === '1';
            
            if (tintingEnabled) {
                const tintingCost = parseFloat($tintingWrapper.data('tinting-cost')) || 0;
                totalPrice += tintingCost;
            }
            
            // Обновляем отображение
            $priceDisplay.html('<strong>' + formatPrice(totalPrice) + '</strong>');
            
            // Показываем детализацию
            updatePriceBreakdown(volume, pricePerLiter, discount, tintingEnabled);
        }
        
        /**
         * Отображение детализации цены
         */
        function updatePriceBreakdown(volume, pricePerLiter, discount, tintingEnabled) {
            let breakdownHtml = '<div class="price-breakdown">';
            
            breakdownHtml += '<div class="breakdown-item">';
            breakdownHtml += '<span>Базовая цена:</span>';
            breakdownHtml += '<span>' + formatPrice(volume * pricePerLiter) + '</span>';
            breakdownHtml += '</div>';
            
            if (volume >= 9 && discount > 0) {
                const discountAmount = (volume * pricePerLiter) * (discount / 100);
                breakdownHtml += '<div class="breakdown-item discount">';
                breakdownHtml += '<span>Скидка (-' + discount + '%):</span>';
                breakdownHtml += '<span>-' + formatPrice(discountAmount) + '</span>';
                breakdownHtml += '</div>';
            }
            
            if (tintingEnabled) {
                const tintingCost = parseFloat($('.parusweb-tinting-wrapper').data('tinting-cost')) || 0;
                breakdownHtml += '<div class="breakdown-item tinting">';
                breakdownHtml += '<span>Колеровка:</span>';
                breakdownHtml += '<span>+' + formatPrice(tintingCost) + '</span>';
                breakdownHtml += '</div>';
            }
            
            breakdownHtml += '</div>';
            
            // Вставляем или обновляем детализацию
            let $breakdown = $('.price-breakdown');
            if ($breakdown.length) {
                $breakdown.replaceWith(breakdownHtml);
            } else {
                $('.parusweb-liter-product .calculated-price').after(breakdownHtml);
            }
        }
        
        /**
         * Форматирование цены
         */
        function formatPrice(price) {
            return new Intl.NumberFormat('ru-RU', {
                style: 'currency',
                currency: 'RUB',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(price);
        }
        
        // Инициализация при загрузке
        if ($('#product_liter_volume').val()) {
            recalculateTotalWithTinting();
        }
    });
    </script>
    
    <style>
    .price-breakdown {
        margin-top: 15px;
        padding: 15px;
        background: #f5f5f5;
        border-radius: 6px;
        font-size: 14px;
    }
    
    .price-breakdown .breakdown-item {
        display: flex;
        justify-content: space-between;
        margin-bottom: 8px;
        padding-bottom: 8px;
        border-bottom: 1px solid #e0e0e0;
    }
    
    .price-breakdown .breakdown-item:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }
    
    .price-breakdown .breakdown-item.discount span:last-child {
        color: #4CAF50;
        font-weight: 600;
    }
    
    .price-breakdown .breakdown-item.tinting span:last-child {
        color: #2196F3;
        font-weight: 600;
    }
    </style>
    <?php
}
add_action('wp_footer', 'parusweb_integrate_tinting_with_liter_calculator');

// ============================================================================
// ИНТЕГРАЦИЯ С КОРЗИНОЙ
// ============================================================================

/**
 * Улучшенное отображение колеровки в корзине с цветным квадратом
 */
function parusweb_enhanced_tinting_display_in_cart($item_data, $cart_item) {
    if (!isset($cart_item['tinting_enabled']) || !$cart_item['tinting_enabled']) {
        return $item_data;
    }
    
    $color_hex = $cart_item['tinting_color_hex'] ?? '';
    $color_code = $cart_item['tinting_color_code'] ?? '';
    $color_name = $cart_item['tinting_color_name'] ?? '';
    $palette = $cart_item['tinting_palette'] ?? '';
    
    // Основная информация о колеровке
    $tinting_info = sprintf(
        '<strong>%s</strong>: %s - %s',
        esc_html($palette),
        esc_html($color_code),
        esc_html($color_name)
    );
    
    // Добавляем цветной квадрат
    if ($color_hex) {
        $tinting_info = sprintf(
            '<span class="tinting-color-swatch" style="background-color:%s;"></span> %s',
            esc_attr($color_hex),
            $tinting_info
        );
    }
    
    // Заменяем стандартный вывод
    foreach ($item_data as $key => $data) {
        if ($data['key'] === 'Колеровка') {
            unset($item_data[$key]);
        }
    }
    
    $item_data[] = [
        'key' => 'Колеровка',
        'value' => $tinting_info,
        'display' => $tinting_info
    ];
    
    return $item_data;
}
// Заменяем стандартный хук более высоким приоритетом
remove_filter('woocommerce_get_item_data', 'parusweb_display_tinting_in_cart', 10);
add_filter('woocommerce_get_item_data', 'parusweb_enhanced_tinting_display_in_cart', 10, 2);

// ============================================================================
// ИНТЕГРАЦИЯ С ЗАКАЗОМ
// ============================================================================

/**
 * Улучшенное отображение в деталях заказа
 */
function parusweb_display_tinting_in_order_details($formatted_meta, $order_item) {
    foreach ($formatted_meta as $key => $meta) {
        if ($meta->key === '_tinting_color_hex') {
            // Скрываем технические поля
            unset($formatted_meta[$key]);
        }
        
        if ($meta->key === 'Колеровка') {
            // Получаем HEX код для отображения квадрата
            $color_hex = $order_item->get_meta('_tinting_color_hex');
            
            if ($color_hex) {
                $formatted_meta[$key]->display_value = sprintf(
                    '<span class="tinting-color-swatch" style="background-color:%s;"></span> %s',
                    esc_attr($color_hex),
                    esc_html($meta->display_value)
                );
            }
        }
    }
    
    return $formatted_meta;
}
add_filter('woocommerce_order_item_get_formatted_meta_data', 'parusweb_display_tinting_in_order_details', 10, 2);

// ============================================================================
// ВАЛИДАЦИЯ
// ============================================================================

/**
 * Валидация данных колеровки перед добавлением в корзину
 */
function parusweb_validate_tinting_before_add_to_cart($passed, $product_id) {
    // Проверяем, что колеровка включена
    if (isset($_POST['tinting_enabled']) && $_POST['tinting_enabled'] === '1') {
        
        // Проверяем обязательные поля
        if (empty($_POST['tinting_color_code']) || empty($_POST['tinting_color_hex'])) {
            wc_add_notice(
                __('Пожалуйста, выберите цвет для колеровки.', 'parusweb'),
                'error'
            );
            return false;
        }
        
        // Валидация HEX кода
        $hex = $_POST['tinting_color_hex'];
        if (!preg_match('/^#[a-fA-F0-9]{6}$/', $hex)) {
            wc_add_notice(
                __('Некорректный код цвета. Пожалуйста, выберите цвет из списка.', 'parusweb'),
                'error'
            );
            return false;
        }
    }
    
    return $passed;
}
add_filter('woocommerce_add_to_cart_validation', 'parusweb_validate_tinting_before_add_to_cart', 10, 2);

// ============================================================================
// AJAX ДЛЯ ОБНОВЛЕНИЯ ЦЕНЫ
// ============================================================================

/**
 * AJAX endpoint для получения детальной информации о цене
 */
function parusweb_ajax_get_tinting_price_details() {
    check_ajax_referer('parusweb_tinting', 'nonce');
    
    $product_id = intval($_POST['product_id'] ?? 0);
    $volume = floatval($_POST['volume'] ?? 0);
    $tinting_enabled = ($_POST['tinting_enabled'] ?? 'false') === 'true';
    
    if (!$product_id || !$volume) {
        wp_send_json_error(['message' => 'Invalid parameters']);
        return;
    }
    
    $product = wc_get_product($product_id);
    if (!$product) {
        wp_send_json_error(['message' => 'Product not found']);
        return;
    }
    
    // Базовая цена
    $base_price = $product->get_price();
    $total_price = $base_price * $volume;
    
    // Скидка за объём
    $discount = 0;
    if ($volume >= 9) {
        $discount = 10; // 10%
        $total_price = $total_price * 0.9;
    }
    
    // Колеровка
    $tinting_cost = 0;
    if ($tinting_enabled && function_exists('parusweb_get_tinting_cost')) {
        $tinting_cost = parusweb_get_tinting_cost($product_id);
        $total_price += $tinting_cost;
    }
    
    wp_send_json_success([
        'base_price' => $base_price * $volume,
        'discount_percent' => $discount,
        'discount_amount' => ($base_price * $volume) * ($discount / 100),
        'tinting_cost' => $tinting_cost,
        'total_price' => $total_price,
        'formatted' => [
            'base_price' => wc_price($base_price * $volume),
            'discount_amount' => wc_price(($base_price * $volume) * ($discount / 100)),
            'tinting_cost' => wc_price($tinting_cost),
            'total_price' => wc_price($total_price)
        ]
    ]);
}
add_action('wp_ajax_parusweb_get_tinting_price', 'parusweb_ajax_get_tinting_price_details');
add_action('wp_ajax_nopriv_parusweb_get_tinting_price', 'parusweb_ajax_get_tinting_price_details');

/**
 * Добавление nonce для AJAX
 */
function parusweb_add_tinting_ajax_nonce() {
    if (is_product()) {
        ?>
        <script>
        var paruswebTintingAjax = {
            ajaxurl: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('parusweb_tinting'); ?>'
        };
        </script>
        <?php
    }
}
add_action('wp_head', 'parusweb_add_tinting_ajax_nonce');

// ============================================================================
// СОВМЕСТИМОСТЬ С КЭШИРОВАНИЕМ
// ============================================================================

/**
 * Исключаем товары с колеровкой из кэша
 */
function parusweb_exclude_tinted_products_from_cache($cache_key, $cart_item) {
    if (isset($cart_item['tinting_enabled']) && $cart_item['tinting_enabled']) {
        $cache_key .= '_tinted_' . ($cart_item['unique_key'] ?? '');
    }
    
    return $cache_key;
}
add_filter('woocommerce_cart_item_cache_key', 'parusweb_exclude_tinted_products_from_cache', 10, 2);

// ============================================================================
// АДМИНИСТРАТИВНЫЕ ФУНКЦИИ
// ============================================================================

/**
 * Добавление колонки с колеровкой в список заказов
 */
function parusweb_add_tinting_column_to_orders($columns) {
    $new_columns = [];
    
    foreach ($columns as $key => $column) {
        $new_columns[$key] = $column;
        
        if ($key === 'order_number') {
            $new_columns['tinting_items'] = 'Колеровка';
        }
    }
    
    return $new_columns;
}
add_filter('manage_edit-shop_order_columns', 'parusweb_add_tinting_column_to_orders', 20);

/**
 * Отображение данных в колонке колеровки
 */
function parusweb_display_tinting_column_content($column, $post_id) {
    if ($column === 'tinting_items') {
        $order = wc_get_order($post_id);
        
        if (!$order) {
            return;
        }
        
        $has_tinting = false;
        
        foreach ($order->get_items() as $item) {
            if ($item->get_meta('_tinting_color_code')) {
                $has_tinting = true;
                echo '<span style="color:#4CAF50;font-weight:bold;">✓</span>';
                break;
            }
        }
        
        if (!$has_tinting) {
            echo '<span style="color:#999;">—</span>';
        }
    }
}
add_action('manage_shop_order_posts_custom_column', 'parusweb_display_tinting_column_content', 10, 2);

// ============================================================================
// ОТЧЁТЫ И АНАЛИТИКА
// ============================================================================

/**
 * Получение статистики по колеровке
 */
function parusweb_get_tinting_statistics($date_from = null, $date_to = null) {
    global $wpdb;
    
    $where = "WHERE oi.order_id = o.ID AND oim.order_item_id = oi.order_item_id";
    $where .= " AND oim.meta_key = '_tinting_color_code'";
    
    if ($date_from) {
        $where .= $wpdb->prepare(" AND o.post_date >= %s", $date_from);
    }
    
    if ($date_to) {
        $where .= $wpdb->prepare(" AND o.post_date <= %s", $date_to);
    }
    
    $query = "
        SELECT 
            COUNT(DISTINCT oi.order_id) as orders_with_tinting,
            COUNT(oi.order_item_id) as tinted_items,
            SUM(CAST(oim2.meta_value AS DECIMAL(10,2))) as total_tinting_revenue
        FROM {$wpdb->prefix}woocommerce_order_items oi
        INNER JOIN {$wpdb->posts} o ON oi.order_id = o.ID
        INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
        LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim2 
            ON oi.order_item_id = oim2.order_item_id 
            AND oim2.meta_key = '_tinting_cost'
        {$where}
    ";
    
    return $wpdb->get_row($query);
}

/**
 * Виджет статистики колеровки в админ-панели
 */
function parusweb_add_tinting_dashboard_widget() {
    wp_add_dashboard_widget(
        'parusweb_tinting_stats',
        'Статистика колеровки ЛКМ',
        'parusweb_tinting_dashboard_widget_content'
    );
}
add_action('wp_dashboard_setup', 'parusweb_add_tinting_dashboard_widget');

/**
 * Содержимое виджета статистики
 */
function parusweb_tinting_dashboard_widget_content() {
    $stats = parusweb_get_tinting_statistics(
        date('Y-m-01'), // Начало месяца
        date('Y-m-t')   // Конец месяца
    );
    
    if (!$stats) {
        echo '<p>Нет данных за текущий месяц.</p>';
        return;
    }
    
    ?>
    <div class="tinting-stats-widget">
        <div class="stat-item">
            <strong>Заказов с колеровкой:</strong>
            <span><?php echo esc_html($stats->orders_with_tinting); ?></span>
        </div>
        <div class="stat-item">
            <strong>Товаров с колеровкой:</strong>
            <span><?php echo esc_html($stats->tinted_items); ?></span>
        </div>
        <div class="stat-item">
            <strong>Доход от колеровки:</strong>
            <span><?php echo wc_price($stats->total_tinting_revenue); ?></span>
        </div>
    </div>
    
    <style>
    .tinting-stats-widget .stat-item {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid #eee;
    }
    
    .tinting-stats-widget .stat-item:last-child {
        border-bottom: none;
    }
    
    .tinting-stats-widget .stat-item span {
        font-size: 16px;
        font-weight: bold;
        color: #4CAF50;
    }
    </style>
    <?php
}