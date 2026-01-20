<?php
/**
 * ============================================================================
 * МОДУЛЬ: АВТОМАТИЧЕСКИЙ РАСЧЕТ БАЗОВОЙ ЦЕНЫ
 * ============================================================================
 * 
 * ВАЖНО: Этот модуль НЕ обрабатывает столярные изделия (категории 265-273)!
 * Для столярки цена уже правильно рассчитывается в другом модуле.
 * 
 * @package ParusWeb_Functions
 * @subpackage Admin
 * @version 3.2.0 - ИСКЛЮЧЕНА СТОЛЯРКА
 */

if (!defined('ABSPATH')) exit;

// ============================================================================
// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// ============================================================================

if (!function_exists('parusweb_get_calc_settings_for_product')) {
    function parusweb_get_calc_settings_for_product($product_id) {
        $width_min = floatval(get_post_meta($product_id, '_calc_width_min', true));
        $length_min = floatval(get_post_meta($product_id, '_calc_length_min', true));
        
        if ($width_min > 0 && $length_min > 0) {
            return [
                'width_min' => $width_min,
                'width_max' => floatval(get_post_meta($product_id, '_calc_width_max', true)) ?: 500,
                'width_step' => floatval(get_post_meta($product_id, '_calc_width_step', true)) ?: 10,
                'length_min' => $length_min,
                'length_max' => floatval(get_post_meta($product_id, '_calc_length_max', true)) ?: 3.0,
                'length_step' => floatval(get_post_meta($product_id, '_calc_length_step', true)) ?: 0.1,
            ];
        }
        
        $product_cats = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
        if (is_wp_error($product_cats)) return null;
        
        $multiplier_categories = [265, 266, 267, 268, 270, 271, 273];
        
        foreach ($product_cats as $cat_id) {
            if (in_array($cat_id, $multiplier_categories)) {
                $cat_width = floatval(get_term_meta($cat_id, 'calc_category_width_min', true));
                $cat_length = floatval(get_term_meta($cat_id, 'calc_category_length_min', true));
                
                if ($cat_width > 0 && $cat_length > 0) {
                    return [
                        'width_min' => $cat_width,
                        'width_max' => floatval(get_term_meta($cat_id, 'calc_category_width_max', true)) ?: 500,
                        'width_step' => floatval(get_term_meta($cat_id, 'calc_category_width_step', true)) ?: 10,
                        'length_min' => $cat_length,
                        'length_max' => floatval(get_term_meta($cat_id, 'calc_category_length_max', true)) ?: 3.0,
                        'length_step' => floatval(get_term_meta($cat_id, 'calc_category_length_step', true)) ?: 0.1,
                    ];
                }
            }
        }
        
        return null;
    }
}

if (!function_exists('parusweb_calculate_final_price')) {
    function parusweb_calculate_final_price($product_id, $base_price) {
        
        $product_cats = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
        if (is_wp_error($product_cats)) return $base_price;
        
        // ============================================================
        // КРИТИЧНО: ПРОПУСКАЕМ СТОЛЯРКУ!
        // Для столярки (категории 265-273) цена УЖЕ правильно 
        // рассчитывается в другом модуле!
        // ============================================================
        $multiplier_categories = [265, 266, 267, 268, 270, 271, 273];
        
        foreach ($product_cats as $cat_id) {
            if (in_array($cat_id, $multiplier_categories)) {
                // Это столярка - НЕ ТРОГАЕМ!
                return $base_price;
            }
        }
        
        // Для других категорий - работаем как обычно
        $calc_settings = parusweb_get_calc_settings_for_product($product_id);
        if (!$calc_settings) return $base_price;
        
        $width_min = floatval($calc_settings['width_min']);
        $length_min = floatval($calc_settings['length_min']);
        
        if ($width_min <= 0 || $length_min <= 0) return $base_price;
        
        $min_area = ($width_min / 1000) * $length_min;
        $price_per_sqm = floatval($base_price);
        
        if ($price_per_sqm <= 0) return $base_price;
        
        $price_without_multiplier = $price_per_sqm * $min_area;
        
        // Получаем множитель (для не-столярки)
        $price_multiplier = 1.0;
        $product_mult = get_post_meta($product_id, '_price_multiplier', true);
        
        if (!empty($product_mult) && is_numeric($product_mult)) {
            $price_multiplier = floatval($product_mult);
        } else {
            foreach ($product_cats as $cat_id) {
                $cat_mult = floatval(get_term_meta($cat_id, 'category_price_multiplier', true));
                if ($cat_mult > 0) {
                    $price_multiplier = $cat_mult;
                    break;
                }
            }
        }
        
        return $price_without_multiplier * $price_multiplier;
    }
}

// ============================================================================
// ФИЛЬТРЫ ЦЕНЫ (НЕ ПРИМЕНЯЮТСЯ К СТОЛЯРКЕ!)
// ============================================================================

add_filter('woocommerce_product_get_price', 'parusweb_modify_product_price', 10, 2);
add_filter('woocommerce_product_get_regular_price', 'parusweb_modify_product_price', 10, 2);

function parusweb_modify_product_price($price, $product) {
    $product_id = $product->get_id();
    $final_price = parusweb_calculate_final_price($product_id, $price);
    return $final_price;
}

add_filter('woocommerce_get_price_html', 'parusweb_modify_price_display_multiplier', 10, 2);

function parusweb_modify_price_display_multiplier($price_html, $product) {
    if (!is_product()) return $price_html;
    
    $product_id = $product->get_id();
    $product_cats = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
    
    if (is_wp_error($product_cats)) return $price_html;
    
    // ============================================================
    // КРИТИЧНО: НЕ ДОБАВЛЯЕМ СУФФИКС ДЛЯ СТОЛЯРКИ!
    // ============================================================
    $multiplier_categories = [265, 266, 267, 268, 270, 271, 273];
    
    foreach ($product_cats as $cat_id) {
        if (in_array($cat_id, $multiplier_categories)) {
            // Это столярка - пропускаем
            return $price_html;
        }
    }
    
    $calc_settings = parusweb_get_calc_settings_for_product($product_id);
    if (!$calc_settings) return $price_html;
    
    $width_min = floatval($calc_settings['width_min']);
    $length_min = floatval($calc_settings['length_min']);
    
    if ($width_min <= 0 || $length_min <= 0) return $price_html;
    
    $min_area = ($width_min / 1000) * $length_min;
    
    $price_html .= ' <span class="woocommerce-price-suffix">за шт. (' . number_format($min_area, 3, '.', '') . ' м²)</span>';
    
    return $price_html;
}