<?php
/**
 * ============================================================================
 * МОДУЛЬ: ФУНКЦИОНАЛ КОРЗИНЫ
 * ============================================================================
 * 
 * Добавление данных калькуляторов в корзину:
 * - Калькулятор площади
 * - Калькулятор размеров
 * - Калькулятор множителя
 * - Калькулятор погонных метров
 * - Калькулятор квадратных метров
 * - Калькулятор реечных перегородок
 * - Обычные покупки без калькулятора
 * - Покупки из карточек товаров
 * 
 * @package ParusWeb_Functions
 * @subpackage Cart
 * @version 2.0.0
 */

if (!defined('ABSPATH')) exit;

// ============================================================================
// БЛОК 0: ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// ============================================================================

/**
 * Проверка - является ли товар крепежом
 * 
 * @param int $product_id ID товара
 * @return bool true если товар из категории крепежа
 */
function parusweb_is_fastener_product($product_id) {
    // Категории крепежа (ID: 77 - род. категория крепежа)
    $fastener_categories = [77, 299, 300, 80, 123];
    
    $product_categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
    
    if (is_wp_error($product_categories) || empty($product_categories)) {
        return false;
    }
    
    // Проверяем прямое совпадение
    foreach ($product_categories as $cat_id) {
        if (in_array($cat_id, $fastener_categories)) {
            return true;
        }
        
        // Проверяем родительские категории
        $ancestors = get_ancestors($cat_id, 'product_cat');
        foreach ($ancestors as $ancestor_id) {
            if (in_array($ancestor_id, $fastener_categories)) {
                return true;
            }
        }
    }
    
    return false;
}

// ============================================================================
// БЛОК 1: ДОБАВЛЕНИЕ ДАННЫХ КАЛЬКУЛЯТОРОВ В КОРЗИНУ
// ============================================================================

add_filter('woocommerce_add_cart_item_data', 'parusweb_add_calculator_data_FIXED', 5, 3);

function parusweb_add_calculator_data_FIXED($cart_item_data, $product_id, $variation_id) {
    
    // Категории крепежа
    $fastener_categories = [77, 299, 300, 80, 123];
    $is_fastener = false;
    foreach ($fastener_categories as $cat_id) {
        if (has_term($cat_id, 'product_cat', $product_id)) {
            $is_fastener = true;
            break;
        }
    }
    
    // Покраска (НЕ для крепежа)
    $painting_service = null;
    if (!$is_fastener && !empty($_POST['painting_service_id'])) {
        $painting_service = [
            'id' => sanitize_text_field($_POST['painting_service_id']),
            'name' => sanitize_text_field($_POST['painting_service_name'] ?? ''),
            'price_per_m2' => round(floatval($_POST['painting_service_price_per_m2'] ?? 0), 2),
            'area' => round(floatval($_POST['painting_service_area'] ?? 0), 3),
            'total_cost' => round(floatval($_POST['painting_service_cost'] ?? 0), 2)
        ];
    }
    
    // Схема покраски
    if (!empty($_POST['pm_selected_scheme_name'])) {
        $cart_item_data['pm_selected_scheme_name'] = sanitize_text_field($_POST['pm_selected_scheme_name']);
        $cart_item_data['pm_selected_scheme_slug'] = sanitize_text_field($_POST['pm_selected_scheme_slug'] ?? '');
        
        if (!empty($_POST['pm_selected_color'])) {
            $cart_item_data['pm_selected_color'] = sanitize_text_field($_POST['pm_selected_color']);
        }
        if (!empty($_POST['pm_selected_color_image'])) {
            $cart_item_data['pm_selected_color_image'] = esc_url_raw($_POST['pm_selected_color_image']);
        }
        if (!empty($_POST['pm_selected_color_filename'])) {
            $cart_item_data['pm_selected_color_filename'] = sanitize_text_field($_POST['pm_selected_color_filename']);
        }
    }
    
    
    // ДИАГНОСТИКА START
error_log("=== ДИАГНОСТИКА: Обработка area-calc ===");
error_log("custom_area_packs: " . ($_POST['custom_area_packs'] ?? 'НЕТ'));
error_log("custom_area_area_value: " . ($_POST['custom_area_area_value'] ?? 'НЕТ'));
error_log("painting_service_id: " . ($_POST['painting_service_id'] ?? 'НЕТ'));
error_log("painting_service_cost: " . ($_POST['painting_service_cost'] ?? 'НЕТ'));

$test_painting = parusweb_get_painting_service_from_post();
error_log("Результат parusweb_get_painting_service_from_post():");
error_log(print_r($test_painting, true));
// ДИАГНОСТИКА END
    
    // ========================================================================
    // КАЛЬКУЛЯТОР ПЛОЩАДИ
    // ========================================================================
    if (!empty($_POST['custom_area_packs']) && !empty($_POST['custom_area_area_value'])) {
        
        $product = wc_get_product($product_id);
        $leaf_parent_id = 190;
        $leaf_children = [191, 127, 94];
        $leaf_ids = array_merge([$leaf_parent_id], $leaf_children);
        $is_leaf = has_term($leaf_ids, 'product_cat', $product_id);
        
        $cart_item_data['custom_area_calc'] = [
            'packs' => intval($_POST['custom_area_packs']),
            'area' => round(floatval($_POST['custom_area_area_value']), 3),
            'total_price' => round(floatval($_POST['custom_area_total_price']), 2),
            'grand_total' => floatval($_POST['custom_area_grand_total'] ?? $_POST['custom_area_total_price']),
            'is_leaf' => $is_leaf,
            'painting_service' => $painting_service
        ];
        
        return $cart_item_data;
    }
    
    // ========================================================================
    // КАЛЬКУЛЯТОР РАЗМЕРОВ (СТОЛЕШНИЦЫ)
    // ========================================================================
    if (!empty($_POST['custom_width_val']) && !empty($_POST['custom_length_val'])) {
        
        $product = wc_get_product($product_id);
        $leaf_parent_id = 190;
        $leaf_children = [191, 127, 94];
        $leaf_ids = array_merge([$leaf_parent_id], $leaf_children);
        $is_leaf = has_term($leaf_ids, 'product_cat', $product_id);
        
        $cart_item_data['custom_dimensions'] = [
            'width' => intval($_POST['custom_width_val']),
            'length' => intval($_POST['custom_length_val']),
            'price'=> round(floatval($_POST['custom_dim_price']), 2),
            'grand_total' => round(floatval($_POST['custom_dim_grand_total'] ?? $_POST['custom_dim_price']), 2),
            'is_leaf' => $is_leaf,
            'painting_service' => $painting_service
        ];

        return $cart_item_data;
    }
    
    // ========================================================================
    // КАЛЬКУЛЯТОР МНОЖИТЕЛЯ (ПОДОКОННИКИ, РЕЕЧНЫЕ ПЕРЕГОРОДКИ)
    // ========================================================================
    if (!empty($_POST['custom_mult_width']) && !empty($_POST['custom_mult_length'])) {
        
        $width = intval($_POST['custom_mult_width']);
        $length = round(floatval($_POST['custom_mult_length']), 3);
        $area_per_item = round(floatval($_POST['custom_mult_area_per_item'] ?? (($width / 1000) * $length)), 3);
        $quantity = intval($_POST['custom_mult_quantity'] ?? 1);
        
        $cart_item_data['custom_multiplier_calc'] = [
            'multiplier' => intval($_POST['custom_mult_multiplier'] ?? 1),
            'width' => $width,
            'length' => $length,
            'area_per_item' => $area_per_item,
            'total_area' => floatval($_POST['custom_mult_total_area'] ?? ($area_per_item * $quantity)),
            'total_price' => floatval($_POST['custom_mult_price']),
            'grand_total' => floatval($_POST['custom_mult_grand_total'] ?? $_POST['custom_mult_price']),
            'painting_service' => $painting_service
        ];
        
        // ФАСКА для подоконников
        if (!empty($_POST['selected_faska_type'])) {
            $cart_item_data['custom_multiplier_calc']['faska_type'] = sanitize_text_field($_POST['selected_faska_type']);
            
            if (!empty($_POST['selected_faska_image'])) {
                $cart_item_data['custom_multiplier_calc']['faska_image'] = esc_url_raw($_POST['selected_faska_image']);
            }
        }
        
        error_log("✅ SAVED custom_multiplier_calc: " . json_encode($cart_item_data['custom_multiplier_calc']));
        return $cart_item_data;
    }
    
    // ========================================================================
    // КАЛЬКУЛЯТОР ПОГОННЫХ МЕТРОВ (ФАЛЬШБАЛКИ)
    // ========================================================================
if (!empty($_POST['custom_rm_length'])) {
    $cart_item_data['custom_running_meter_calc'] = [
        'length' => round(floatval($_POST['custom_rm_length']), 3),
        'price_per_meter' => round(floatval($_POST['custom_rm_price_per_meter'] ?? $base_price_m2), 2),
        'total_price' => round(floatval($_POST['custom_rm_price']), 2),
        'grand_total' => round(floatval($_POST['custom_rm_grand_total'] ?? $_POST['custom_rm_price']), 2),
        'painting_service' => $painting_service
    ];
    
    // ФОРМА СЕЧЕНИЯ
    if (!empty($_POST['custom_rm_shape'])) {
        $cart_item_data['custom_running_meter_calc']['shape'] = sanitize_text_field($_POST['custom_rm_shape']);
        $cart_item_data['custom_running_meter_calc']['shape_label'] = sanitize_text_field($_POST['custom_rm_shape_label'] ?? '');
    }
    
    // ШИРИНА
    if (!empty($_POST['custom_rm_width'])) {
        $cart_item_data['custom_running_meter_calc']['width'] = intval($_POST['custom_rm_width']);
    }
    
    // ВЫСОТА(Ы)
    if (!empty($_POST['custom_rm_height'])) {
        $cart_item_data['custom_running_meter_calc']['height'] = intval($_POST['custom_rm_height']);
    }
    
    if (!empty($_POST['custom_rm_height2'])) {
        $cart_item_data['custom_running_meter_calc']['height2'] = intval($_POST['custom_rm_height2']);
    }
    
    // ФАСКА
    if (!empty($_POST['custom_rm_faska'])) {
        $cart_item_data['custom_running_meter_calc']['faska'] = sanitize_text_field($_POST['custom_rm_faska']);
        $cart_item_data['custom_running_meter_calc']['faska_label'] = sanitize_text_field($_POST['custom_rm_faska_label'] ?? '');
    }
    
    error_log("✅ SAVED custom_running_meter_calc: " . json_encode($cart_item_data['custom_running_meter_calc']));
    
    return $cart_item_data;
}
    
    // ========================================================================
    // КАЛЬКУЛЯТОР КВАДРАТНЫХ МЕТРОВ
    // ========================================================================
    if (!empty($_POST['custom_sq_width']) && !empty($_POST['custom_sq_area'])) {
        
        $cart_item_data['custom_square_meter_calc'] = [
            'width' => intval($_POST['custom_sq_width']),
            'area' => round(floatval($_POST['custom_sq_area']), 3),
            'total_price' => round(floatval($_POST['custom_sq_price']), 2),
            'grand_total' => round(floatval($_POST['custom_sq_grand_total'] ?? $_POST['custom_sq_price']), 2),
            'painting_service' => $painting_service
        ];
        
        error_log("✅ SAVED custom_square_meter_calc: " . json_encode($cart_item_data['custom_square_meter_calc']));
        return $cart_item_data;
    }
    
    // ========================================================================
    // КАЛЬКУЛЯТОР РЕЕЧНЫХ ПЕРЕГОРОДОК (КУБОМЕТРЫ)
    // ========================================================================
    if (!empty($_POST['custom_part_width']) && !empty($_POST['custom_part_length']) && !empty($_POST['custom_part_thickness'])) {
        
        $cart_item_data['custom_partition_slat_calc'] = [
            'width' => intval($_POST['custom_part_width']),
            'length' => round(floatval($_POST['custom_part_length']), 3),
            'thickness' => intval($_POST['custom_part_thickness']),
            'volume' => round(floatval($_POST['custom_part_volume']), 3),
            'total_price' => round(floatval($_POST['custom_part_price']), 2),
            'painting_service' => $painting_service
        ];
        
        error_log("✅ SAVED custom_partition_slat_calc: " . json_encode($cart_item_data['custom_partition_slat_calc']));
        return $cart_item_data;
    }
    
    // ========================================================================
    // ПОКУПКА ИЗ КАРТОЧКИ
    // ========================================================================
    if (!empty($_POST['card_pack_purchase'])) {
        
        $product = wc_get_product($product_id);
        $pack_area = floatval($product->get_meta('_pack_area'));
        $base_price = floatval($product->get_regular_price() ?: $product->get_price());
        
        $leaf_parent_id = 190;
        $leaf_children = [191, 127, 94];
        $leaf_ids = array_merge([$leaf_parent_id], $leaf_children);
        $is_leaf = has_term($leaf_ids, 'product_cat', $product_id);
        
        if ($pack_area > 0) {
            $cart_item_data['card_pack_purchase'] = [
                'area' => $pack_area,
                'price_per_m2' => $base_price,
                'total_price' => $base_price * $pack_area,
                'is_leaf' => $is_leaf,
                'unit_type' => $is_leaf ? 'лист' : 'упаковка',
                'painting_service' => $painting_service
            ];
            
            error_log("✅ SAVED card_pack_purchase: " . json_encode($cart_item_data['card_pack_purchase']));
        }
        
        return $cart_item_data;
    }
    
    // ========================================================================
    // СТАНДАРТНАЯ ПОКУПКА (БЕЗ КАЛЬКУЛЯТОРА, НО ИЗ ЦЕЛЕВЫХ КАТЕГОРИЙ)
    // ========================================================================
    $target_categories = [
        87, 88, 90, 91, 92, 93,  // Пиломатериалы
        127, 191, 94,             // Листовые
        193, 194, 195, 196, 197   // ДПК
    ];
    
    if (has_term($target_categories, 'product_cat', $product_id)) {
        $product = wc_get_product($product_id);
        $pack_area = floatval($product->get_meta('_pack_area'));
        $base_price = floatval($product->get_regular_price() ?: $product->get_price());
        
        $leaf_parent_id = 190;
        $leaf_children = [191, 127, 94];
        $leaf_ids = array_merge([$leaf_parent_id], $leaf_children);
        $is_leaf = has_term($leaf_ids, 'product_cat', $product_id);
        
        if ($pack_area > 0) {
            $cart_item_data['standard_pack_purchase'] = [
                'area' => $pack_area,
                'price_per_m2' => $base_price,
                'total_price' => $base_price * $pack_area,
                'is_leaf' => $is_leaf,
                'unit_type' => $is_leaf ? 'лист' : 'упаковка',
                'painting_service' => $painting_service
            ];
            
            error_log("✅ SAVED standard_pack_purchase: " . json_encode($cart_item_data['standard_pack_purchase']));
        }
    }
    
    // ========================================================================
    // ЛКМ (ТАРА)
    // ========================================================================
    if (!empty($_POST['tara'])) {
        $cart_item_data['tara'] = floatval($_POST['tara']);
        error_log("✅ SAVED tara: " . $cart_item_data['tara']);
    }
    
    return $cart_item_data;
}

// ============================================================================
// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// ============================================================================

function parusweb_get_painting_service_from_post() {
    if (empty($_POST['painting_service_id'])) {
        return null;
    }
    
    $painting_service = [
        'id' => intval($_POST['painting_service_id']),
        'name' => sanitize_text_field($_POST['painting_service_name'] ?? ''),
        'price_per_m2' => round(floatval($_POST['painting_service_price_per_m2'] ?? 0), 2),
        'area' => round(floatval($_POST['painting_service_area'] ?? 0), 3),
        'total_cost' => round(floatval($_POST['painting_service_cost'] ?? 0), 2)
    ];
    
    if (!empty($_POST['painting_service_color'])) {
        $painting_service['color'] = sanitize_text_field($_POST['painting_service_color']);
    }
    
    $painting_service['name_with_color'] = $painting_service['name'];
    if (!empty($painting_service['color'])) {
        $painting_service['name_with_color'] .= ' (' . $painting_service['color'] . ')';
    }
    
    return $painting_service;
}

function parusweb_get_scheme_data_from_post() {
    if (empty($_POST['pm_selected_scheme'])) {
        return null;
    }
    
    $scheme_data = [
        'pm_selected_scheme_id' => intval($_POST['pm_selected_scheme']),
        'pm_selected_scheme_name' => sanitize_text_field($_POST['pm_selected_scheme_name'] ?? ''),
    ];
    
    if (!empty($_POST['pm_selected_color'])) {
        $scheme_data['pm_selected_color'] = sanitize_text_field($_POST['pm_selected_color']);
    }
    
    if (!empty($_POST['pm_selected_color_image'])) {
        $scheme_data['pm_selected_color_image'] = esc_url_raw($_POST['pm_selected_color_image']);
    }
    
    if (!empty($_POST['pm_selected_color_filename'])) {
        $scheme_data['pm_selected_color_filename'] = sanitize_text_field($_POST['pm_selected_color_filename']);
    }
    
    return !empty($scheme_data['pm_selected_scheme_id']) ? $scheme_data : null;
}

// ============================================================================
// БЛОК 2: УСТАНОВКА ПРАВИЛЬНОГО КОЛИЧЕСТВА
// ============================================================================

add_filter('woocommerce_add_to_cart_quantity', 'parusweb_adjust_cart_quantity', 10, 2);

function parusweb_adjust_cart_quantity($quantity, $product_id) {
    if (!is_in_target_categories($product_id)) {
        return $quantity;
    }
    
    if (isset($_POST['custom_area_packs']) && !empty($_POST['custom_area_packs']) && 
        isset($_POST['custom_area_area_value']) && !empty($_POST['custom_area_area_value'])) {
        return intval($_POST['custom_area_packs']);
    }
    
    if (isset($_POST['custom_width_val']) && !empty($_POST['custom_width_val']) && 
        isset($_POST['custom_length_val']) && !empty($_POST['custom_length_val'])) {
        return 1;
    }
    
    return $quantity;
}

// ============================================================================
// БЛОК 3: КОРРЕКТИРОВКА ПОСЛЕ ДОБАВЛЕНИЯ
// ============================================================================

add_action('woocommerce_add_to_cart', 'parusweb_correct_cart_quantity', 10, 6);

function parusweb_correct_cart_quantity($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
    if (!is_in_target_categories($product_id)) {
        return;
    }
    
    if (isset($cart_item_data['custom_area_calc'])) {
        $packs = intval($cart_item_data['custom_area_calc']['packs']);
        if ($packs > 0 && $quantity !== $packs) {
            WC()->cart->set_quantity($cart_item_key, $packs);
        }
    }
}

// ============================================================================
// БЛОК 4: ПЕРЕСЧЕТ ЦЕН В КОРЗИНЕ
// ============================================================================

add_action('woocommerce_before_calculate_totals', 'parusweb_recalculate_cart_prices', 10, 1);

function parusweb_recalculate_cart_prices($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }
    
    if (did_action('woocommerce_before_calculate_totals') >= 2) {
        return;
    }
    
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        $product = $cart_item['data'];
        $product_id = $product->get_id();
        
        // Пропускаем крепёж
        if (function_exists('parusweb_is_fastener_product') && parusweb_is_fastener_product($product_id)) {
            continue;
        }
        
        // ====================================================================
        // ПРОВЕРКА ДПК/МПК - КАТЕГОРИИ 193-197
        // ====================================================================
        $dpk_categories = [193, 194, 195, 196, 197]; // Все категории ДПК
        $is_dpk = has_term($dpk_categories, 'product_cat', $product_id);
        
        // ====================================================================
        // КАЛЬКУЛЯТОР ПЛОЩАДИ
        // ====================================================================
        if (isset($cart_item['custom_area_calc'])) {
            $data = $cart_item['custom_area_calc'];
            
            // СПЕЦИАЛЬНАЯ ОБРАБОТКА ДПК!
            if ($is_dpk) {
                $base_price_m2 = floatval($product->get_regular_price() ?: $product->get_price());
                
                // Получаем площадь штуки из названия
                if (function_exists('extract_area_with_qty')) {
                    $title = $product->get_name();
                    $board_area = extract_area_with_qty($title, $product_id);
                    
                    if ($board_area && $board_area > 0) {
                        // Цена за одну штуку = базовая × площадь
                        $price_per_piece = $base_price_m2 * $board_area;
                        
                        // Добавляем покраску если есть
                        if (isset($data['painting_service']) && !empty($data['painting_service'])) {
                            $painting_per_pack = floatval($data['painting_service']['total_cost']) / intval($data['packs']);
                            $price_per_piece += $painting_per_pack;
                        }
                        
                        $product->set_price($price_per_piece);
                        continue;
                    }
                }
                
                // Если не смогли извлечь - fallback
                $price_per_pack = floatval($data['total_price']) / intval($data['packs']);
                $product->set_price($price_per_pack);
                continue;
            }
            
            // СТАНДАРТНАЯ ОБРАБОТКА (не-ДПК)
            $price_per_pack = floatval($data['total_price']) / intval($data['packs']);
            
            if (isset($data['painting_service']) && !empty($data['painting_service'])) {
                $painting_per_pack = floatval($data['painting_service']['total_cost']) / intval($data['packs']);
                $price_per_pack += $painting_per_pack;
            }
            
            $product->set_price($price_per_pack);
            continue;
        }
        
        // ====================================================================
        // ОСТАЛЬНЫЕ КАЛЬКУЛЯТОРЫ (БЕЗ ИЗМЕНЕНИЙ)
        // ====================================================================
        
        // Калькулятор размеров
        if (isset($cart_item['custom_dimensions'])) {
            $data = $cart_item['custom_dimensions'];
            $price = floatval($data['grand_total']);
            $product->set_price($price);
            continue;
        }
        
        // Калькулятор множителя
        if (isset($cart_item['custom_multiplier_calc'])) {
            $data = $cart_item['custom_multiplier_calc'];
            $price = floatval($data['grand_total']);
            $product->set_price($price);
            continue;
        }
        
        // Калькулятор погонных метров
        if (isset($cart_item['custom_running_meter_calc'])) {
            $data = $cart_item['custom_running_meter_calc'];
            $price = floatval($data['grand_total']);
            $product->set_price($price);
            continue;
        }
        
        // Калькулятор квадратных метров
        if (isset($cart_item['custom_square_meter_calc'])) {
            $data = $cart_item['custom_square_meter_calc'];
            $price = floatval($data['grand_total']);
            $product->set_price($price);
            continue;
        }
        
        // Калькулятор реечных перегородок
        if (isset($cart_item['custom_partition_slat_calc'])) {
            $data = $cart_item['custom_partition_slat_calc'];
            $price = floatval($data['grand_total']);
            $product->set_price($price);
            continue;
        }
        
        // Покупка из карточки
        if (isset($cart_item['card_pack_purchase'])) {
            $data = $cart_item['card_pack_purchase'];
            $price = floatval($data['total_price']);
            
            if (isset($data['painting_service']) && !empty($data['painting_service'])) {
                $price += floatval($data['painting_service']['total_cost']);
            }
            
            $product->set_price($price);
            continue;
        }
        
        // Стандартная покупка
        if (isset($cart_item['standard_pack_purchase'])) {
            $data = $cart_item['standard_pack_purchase'];
            $price = floatval($data['total_price']);
            
            if (isset($data['painting_service']) && !empty($data['painting_service'])) {
                $price += floatval($data['painting_service']['total_cost']);
            }
            
            $product->set_price($price);
            continue;
        }
        
        // Товары за литр
        if (isset($cart_item['tara'])) {
            $base_price = floatval($product->get_regular_price());
            $volume = floatval($cart_item['tara']);
            $price = $base_price * $volume;
            
            if ($volume >= 9) {
                $price *= 0.9;
            }
            
            $product->set_price($price);
            continue;
        }
        
        // Пиломатериалы без калькулятора
        $cubic_categories = [87, 310, 88, 90, 91, 92, 93, 190, 191, 127, 94];
        if (has_term($cubic_categories, 'product_cat', $product_id) && 
            function_exists('calculate_cubic_package_price')) {
            
            $base_price_per_m3 = floatval($product->get_regular_price() ?: $product->get_price());
            $cubic_calc = calculate_cubic_package_price($product_id, $base_price_per_m3);
            
            if ($cubic_calc) {
                $price = $cubic_calc['price_per_pack'];
                $product->set_price($price);
            }
        }
    }
         // ====================================================================
        // ДПК БЕЗ КАЛЬКУЛЯТОРА (добавлен кнопкой "В корзину")
        // ====================================================================
        $dpk_categories = [193, 194, 195, 196, 197];
        if (has_term($dpk_categories, 'product_cat', $product_id)) {
            // ДПК товар, но без данных калькулятора
            // Проверяем что НЕ обработали выше
            $already_processed = isset($cart_item['custom_area_calc']) ||
                                isset($cart_item['custom_dimensions']) ||
                                isset($cart_item['card_pack_purchase']) ||
                                isset($cart_item['standard_pack_purchase']);
            
            if (!$already_processed && function_exists('extract_area_with_qty')) {
                $title = $product->get_name();
                $board_area = extract_area_with_qty($title, $product_id);
                
                if ($board_area && $board_area > 0) {
                    $base_price_m2 = floatval($product->get_regular_price() ?: $product->get_price());
                    $price_per_piece = $base_price_m2 * $board_area;
                    
                    $product->set_price($price_per_piece);
                }
            }
        }
}

// ====================================================================
// ОТЛАДКА: Добавить временно для проверки
// ====================================================================

add_action('woocommerce_before_calculate_totals', 'debug_dpk_in_cart', 5, 1);

function debug_dpk_in_cart($cart) {
    if (!is_admin() && WP_DEBUG) {
        foreach ($cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            $product_id = $product->get_id();
            
            $dpk_categories = [193, 194, 195, 196, 197];
            $is_dpk = has_term($dpk_categories, 'product_cat', $product_id);
            
            if ($is_dpk && isset($cart_item['custom_area_calc'])) {
                error_log("=== ДПК В КОРЗИНЕ ===");
                error_log("Product ID: $product_id");
                error_log("Product name: " . $product->get_name());
                error_log("Base price: " . $product->get_regular_price());
                error_log("Is DPK: " . ($is_dpk ? 'YES' : 'NO'));
                
                if (function_exists('extract_area_with_qty')) {
                    $board_area = extract_area_with_qty($product->get_name(), $product_id);
                    error_log("Board area: $board_area");
                    error_log("Expected price per piece: " . ($product->get_regular_price() * $board_area));
                }
                
                error_log("custom_area_calc: " . print_r($cart_item['custom_area_calc'], true));
            }
        }
    }
}

// ============================================================================
// БЛОК 5: JAVASCRIPT ДЛЯ КАРТОЧЕК ТОВАРОВ
// ============================================================================

add_action('wp_footer', 'parusweb_card_purchase_script');

function parusweb_card_purchase_script() {
    if (!is_shop() && !is_product_category() && !is_product_tag()) {
        return;
    }
    
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const addToCartButtons = document.querySelectorAll('.add_to_cart_button:not(.product_type_variable)');
        
        addToCartButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                const productId = this.getAttribute('data-product_id');
                
                if (!productId) return;
                
                const formData = new FormData();
                formData.append('card_pack_purchase', '1');
                formData.append('product_id', productId);
                formData.append('quantity', this.getAttribute('data-quantity') || 1);
                
                const href = this.getAttribute('href');
                if (href && href.includes('add-to-cart=')) {
                    e.preventDefault();
                    
                    fetch(wc_add_to_cart_params.wc_ajax_url.toString().replace('%%endpoint%%', 'add_to_cart'), {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            console.error('Error adding to cart:', data);
                            return;
                        }
                        
                        jQuery(document.body).trigger('added_to_cart', [data.fragments, data.cart_hash, button]);
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
                }
            });
        });
    });
    </script>
    <?php
}

// ============================================================================
// КОНЕЦ ФАЙЛА
// ============================================================================