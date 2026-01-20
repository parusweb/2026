<?php
/**
 * ============================================================================
 * МОДУЛЬ: БАЗОВЫЕ РАСЧЕТЫ ТОВАРОВ
 * ============================================================================
 * 
 * @package ParusWeb_Functions
 * @subpackage Core
 */

if (!defined('ABSPATH')) exit;

// ============================================================================
// РАСЧЕТ ПЛОЩАДИ И ОБЪЕМА
// ============================================================================

/**
 * Расчет площади товара в м²
 */
function parusweb_calculate_area($width_mm, $length_m, $quantity = 1) {
    if ($width_mm <= 0 || $length_m <= 0) return 0;
    
    $width_m = $width_mm / 1000;
    $area = $width_m * $length_m * $quantity;
    
    return round($area, 4);
}

/**
 * Расчет объема товара в м³
 */
function parusweb_calculate_volume($width_mm, $thickness_mm, $length_m, $quantity = 1) {
    if ($width_mm <= 0 || $thickness_mm <= 0 || $length_m <= 0) return 0;
    
    $width_m = $width_mm / 1000;
    $thickness_m = $thickness_mm / 1000;
    $volume = $width_m * $thickness_m * $length_m * $quantity;
    
    return round($volume, 6);
}

// ============================================================================
// РАСЧЕТ ЦЕН
// ============================================================================

/**
 * Расчет цены товара с учетом множителей
 */
function parusweb_calculate_product_price($product_id, $width_mm, $length_m, $quantity = 1) {
    $product = wc_get_product($product_id);
    if (!$product) return 0;
    
    $base_price = floatval($product->get_regular_price());
    if ($base_price <= 0) return 0;
    
    // Получаем множитель
    $multiplier = parusweb_get_price_multiplier($product_id);
    
    // Расчет площади
    $area = parusweb_calculate_area($width_mm, $length_m, $quantity);
    
    // Итоговая цена
    $total_price = $base_price * $area * $multiplier;
    
    return round($total_price, 2);
}

/**
 * Получение множителя цены
 */
function parusweb_get_price_multiplier($product_id) {
    // Сначала проверяем множитель товара
    $product_multiplier = get_post_meta($product_id, '_price_multiplier', true);
    
    if (!empty($product_multiplier) && is_numeric($product_multiplier)) {
        return floatval($product_multiplier);
    }
    
    // Затем проверяем множитель категории
    $product_cats = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
    
    if (!is_wp_error($product_cats) && !empty($product_cats)) {
        foreach ($product_cats as $cat_id) {
            $cat_multiplier = get_term_meta($cat_id, 'category_price_multiplier', true);
            
            if (!empty($cat_multiplier) && is_numeric($cat_multiplier)) {
                return floatval($cat_multiplier);
            }
        }
    }
    
    return 1.0; // По умолчанию
}

// ============================================================================
// ПЕРЕСЧЕТ ЦЕНЫ ДЛЯ ПИЛОМАТЕРИАЛОВ БЕЗ КАЛЬКУЛЯТОРА
// ============================================================================

/**
 * Пересчет цены для пиломатериалов БЕЗ калькулятора
 * Конвертирует цену из ₽/м³ в ₽/шт по размерам товара
 */
add_filter('woocommerce_product_get_price', 'parusweb_convert_timber_price_simple', 20, 2);
add_filter('woocommerce_product_variation_get_price', 'parusweb_convert_timber_price_simple', 20, 2);

function parusweb_convert_timber_price_simple($price, $product) {
    // Проверяем, что цена - это валидное число
    if (empty($price) || !is_numeric($price)) {
        return $price;
    }
    
    $product_id = $product->get_id();
    
    // Только для пиломатериалов и листовых
    $timber_cats = array_merge([87, 310], range(88, 93), [190, 191, 94, 301]);
    if (!has_term($timber_cats, 'product_cat', $product_id)) {
        return $price;
    }
    
    // Только если калькулятор ВЫКЛЮЧЕН
    if (get_field('calc_enabled', $product_id)) {
        return $price;
    }
    
    // Получаем размеры из ACF полей
    $width = floatval(get_field('width', $product_id));
    $thickness = floatval(get_field('thickness', $product_id));
    $length = floatval(get_field('length', $product_id));
    
    // Если в полях нет размеров - парсим из названия
    if (!$width || !$thickness || !$length) {
        $title = $product->get_name();
        // Поддержка форматов: 145*20*3000, 40/30/3000, 145×20×3000
        if (preg_match('/(\d+)[*\/×xх](\d+)[*\/×xх](\d+)/', $title, $matches)) {
            $width = floatval($matches[1]);
            $thickness = floatval($matches[2]);
            $length = floatval($matches[3]);
        }
    }
    
    if (!$width || !$thickness || !$length) {
        return $price;
    }
    
    // Расчет: ₽/м³ → ₽/шт
    $volume_m3 = ($length / 1000) * ($width / 1000) * ($thickness / 1000);
    $price_per_piece = floatval($price) * $volume_m3;
    
    return round($price_per_piece, 2);
}