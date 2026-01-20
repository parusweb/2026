<?php
/**
 * ============================================================================
 * ИНТЕГРАЦИЯ С JETENGINE: Пиломатериалы + Листовые
 * ============================================================================
 * 
 * ОБНОВЛЁННАЯ ВЕРСИЯ
 */

if (!defined('ABSPATH')) exit;

/**
 * Синхронизация цены за м² при сохранении товара
 */
add_action('woocommerce_update_product', 'parusweb_sync_jetengine_price_m2', 10, 1);
add_action('woocommerce_new_product', 'parusweb_sync_jetengine_price_m2', 10, 1);

function parusweb_sync_jetengine_price_m2($product_id) {
    
    // Категории пиломатериалов и листовых
    $timber_categories = array_merge([87, 310], range(88, 93), [190, 191, 301, 94]);
    $leaf_categories = [190, 191, 301, 94];
    
    $is_timber = has_term($timber_categories, 'product_cat', $product_id);
    $is_leaf = has_term($leaf_categories, 'product_cat', $product_id);
    
    if (!$is_timber && !$is_leaf) {
        return;
    }
    
    $product = wc_get_product($product_id);
    if (!$product) return;
    
    $price_per_m3 = floatval($product->get_regular_price());
    
    if ($price_per_m3 <= 0) return;
    
    // ========================================================================
    // ПИЛОМАТЕРИАЛЫ И ЛИСТОВЫЕ: пересчёт ₽/м³ → ₽/м²
    // ========================================================================
    if ($is_timber || $is_leaf) {
        if (!function_exists('get_cubic_product_params')) {
            error_log("Function get_cubic_product_params not found for product $product_id");
            return;
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
        
        update_post_meta($product_id, '_display_price_m2', round($price_per_m2, 2));
        
        error_log(sprintf(
            "Product %d (TIMBER): synced _display_price_m2 = %.2f (thickness=%dmm, price_m3=%.2f)",
            $product_id,
            $price_per_m2,
            $thickness,
            $price_per_m3
        ));
    }
}

/**
 * ============================================================================
 * МАССОВАЯ СИНХРОНИЗАЦИЯ
 * ============================================================================
 */

function parusweb_bulk_sync_jetengine_prices() {
    
    $timber_categories = array_merge([87, 310], range(88, 93), [190, 191, 301, 94]);
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
    $synced = 0;
    $errors = 0;
    
    foreach ($products as $post) {
        parusweb_sync_jetengine_price_m2($post->ID);
        $synced++;
    }
    
    return [
        'synced' => $synced,
        'errors' => $errors,
        'total' => count($products)
    ];
}