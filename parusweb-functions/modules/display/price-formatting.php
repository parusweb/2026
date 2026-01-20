<?php
/**
 * ============================================================================ 
 * МОДУЛЬ: ФОРМАТИРОВАНИЕ ЦЕН
 * ============================================================================ 
 * 
 * Изменение отображения цен в зависимости от типа товара.
 * 
 * @package ParusWeb_Functions
 * @subpackage Display
 * @version 2.0.2 - ИСПРАВЛЕНО: расчёт цены упаковки для террасной доски
 */

if (!defined('ABSPATH')) exit;

// ---------------------------------------------------------------------------
// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ (безопасные объявления)
// ---------------------------------------------------------------------------

if (!function_exists('has_term_or_parent_dpk')) {
    /**
     * Проверяет, есть ли у товара термин или родительский термин с указанным ID.
     */
    function has_term_or_parent_dpk($parent_term_id, $taxonomy, $product_id) {
        if (has_term($parent_term_id, $taxonomy, $product_id)) {
            return true;
        }

        $terms = wp_get_post_terms($product_id, $taxonomy, array('fields' => 'ids'));
        if (is_wp_error($terms) || empty($terms)) {
            return false;
        }

        foreach ($terms as $term_id) {
            if (term_is_ancestor_of($parent_term_id, $term_id, $taxonomy)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('calculate_min_price_partition_slat')) {
    /**
     * Безопасная реализация калькуляции минимальной цены для partition_slat.
     * Возвращает минимальную цену за штуку, используя:
     * 1) площадь из названия (extract_area_with_qty),
     * 2) настройки калькулятора (_calc_width_min/_calc_length_min),
     * 3) запасной падеж — небольшая площадь (0.06 м²).
     *
     * @param int $product_id
     * @param float $base_price_per_m2
     * @return float
     */
    function calculate_min_price_partition_slat($product_id, $base_price_per_m2) {
        // Попробуем извлечь площадь из названия (если функция доступна)
        if (function_exists('extract_area_with_qty')) {
            $area = floatval(extract_area_with_qty(get_the_title($product_id), $product_id));
            $mult = 2.2;
            if ($area > 0) {
                return $base_price_per_m2 * $area * $mult;
            }
        }

        // Попробуем взять минимальные значения из мета (калькулятор размеров)
        $min_width = floatval(get_post_meta($product_id, '_calc_width_min', true));
        $min_length = floatval(get_post_meta($product_id, '_calc_length_min', true));

        if ($min_width > 0) {
            if ($min_length <= 0) {
                $min_length = 0.5; // по умолчанию 0.5 м
            }
            $min_area = ($min_width / 1000) * $min_length;
            return $base_price_per_m2 * $min_area;
        }

        // Последний запасной вариант — взять небольшую стандартную площадь (например 0.06 м²)
        $fallback_area = 0.06;
        return $base_price_per_m2 * $fallback_area;
    }
}

// Безопасная заглушка is_leaf_category если не объявлена в другом месте
if (!function_exists('is_leaf_category')) {
    function is_leaf_category($product_id) {
        $leaf_categories = [190, 191, 301, 94];
        return has_term($leaf_categories, 'product_cat', $product_id);
    }
}

// ---------------------------------------------------------------------------
// ФИЛЬТР ОТОБРАЖЕНИЯ ЦЕН
// ---------------------------------------------------------------------------

add_filter('woocommerce_get_price_html', 'parusweb_format_product_price', 20, 2);

function parusweb_format_product_price($price, $product) {
    // Защита от неожиданных типов
    if (!is_object($product) || !method_exists($product, 'get_id')) {
        return $price;
    }

    $product_id = $product->get_id();

    // КРИТИЧНО: Проверка пиломатериалов ПЕРВОЙ (до is_in_target_categories)
    $timber_categories = array_merge(
        [87, 310],
        range(88, 93),
        [190, 191, 301, 94]
    );
    $is_timber = has_term($timber_categories, 'product_cat', $product_id);

    if ($is_timber && function_exists('format_cubic_meter_price')) {
        $base_price_m3 = floatval($product->get_regular_price() ?: $product->get_price());
        return format_cubic_meter_price($product_id, $base_price_m3);
    }

    // Сначала проверяем категорию ДПК и МПК (197)
    $is_dpk_mpk = has_term_or_parent_dpk(197, 'product_cat', $product_id);
    if ($is_dpk_mpk) {
        return format_dpk_mpk_price($product, $product_id);
    }

    // Проверяем целевые категории (функция должна быть определена в другом месте)
    if (!function_exists('is_in_target_categories') || !is_in_target_categories($product_id)) {
        return $price;
    }

    $base_price_m2 = floatval($product->get_regular_price() ?: $product->get_price());
    $type = function_exists('parusweb_get_product_type') ? parusweb_get_product_type($product_id) : '';

    // Категории для скрытия базовой цены
    $hide_base_price_categories = range(265, 271);
    $should_hide_base_price = has_term($hide_base_price_categories, 'product_cat', $product_id);

    // Форматирование в зависимости от типа
    switch ($type) {
        case 'cubic_meter_pack':
            if (function_exists('format_cubic_meter_price')) {
                return format_cubic_meter_price($product_id, $base_price_m2);
            }
            break;

        case 'partition_slat':
            if (function_exists('format_partition_slat_price')) {
                return format_partition_slat_price($product_id, $base_price_m2, $should_hide_base_price);
            }
            break;

        case 'running_meter':
            if (function_exists('format_running_meter_price')) {
                return format_running_meter_price($product_id, $base_price_m2, $should_hide_base_price);
            }
            break;

        case 'square_meter':
            if (function_exists('format_square_meter_price')) {
                return format_square_meter_price($product_id, $base_price_m2, $should_hide_base_price);
            }
            break;

        case 'multiplier':
            if (function_exists('format_multiplier_price')) {
                return format_multiplier_price($product_id, $base_price_m2, $should_hide_base_price);
            }
            break;

        case 'target':
            if (function_exists('format_target_price')) {
                return format_target_price($product, $product_id, $base_price_m2);
            }
            break;

        case 'liter':
            if (function_exists('format_liter_price')) {
                return format_liter_price($price);
            }
            break;
    }

    return $price;
}

// ---------------------------------------------------------------------------
// ФОРМАТИРОВАНИЕ ПО ТИПАМ
// ---------------------------------------------------------------------------

if (!function_exists('format_dpk_mpk_price')) {
   function format_dpk_mpk_price($product, $product_id) {
    $price_per_m2 = floatval($product->get_regular_price() ?: $product->get_price());
    
    $title = $product->get_name();
    $board_area = extract_area_with_qty($title, $product_id);
    
    if (!$board_area || $board_area <= 0) {
        if (is_product()) {
            return '<span style="font-size:1.3em;"><strong>' . wc_price($price_per_m2) . '</strong> за м²</span>';
        } else {
            return '<span style="font-size:1.1em;"><strong>' . wc_price($price_per_m2) . '</strong> за м²</span>';
        }
    }
    
    $price_per_piece = $price_per_m2 * $board_area;
    
    if (is_product()) {
        return '<span style="font-size:1.3em;"><strong>' . wc_price($price_per_piece) . '</strong> за шт.</span><br>';
    } else {
        return '<span style="font-size:1.1em;"><strong>' . wc_price($price_per_piece) . '</strong> за шт.</span><br>' .
               '<span style="font-size:0.85em !important; color:#666;">(' . number_format($board_area, 3) . ' м²)</span>';
    }
}
}

if (!function_exists('format_timber_price')) {
    function format_timber_price($product, $product_id, $base_price_m2) {
        $title = $product->get_name();
        $current_price = floatval($product->get_price());

        // ПРОВЕРЯЕМ: есть ли площадь упаковки в названии
        $pack_area = function_exists('extract_area_with_qty') ? extract_area_with_qty($title, $product_id) : 0;

        // ПРОВЕРЯЕМ: есть ли настройки калькулятора размеров
        $has_calculator = (
            get_post_meta($product_id, '_calc_width_min', true) ||
            get_post_meta($product_id, '_calc_length_min', true)
        );

        // ========================================================================
        // ВАРИАНТ 1: ТОВАР С УПАКОВКОЙ (есть pack_area, нет калькулятора)
        // Показываем: "XXX ₽ за м²" + "YYY ₽ за упаковку"
        // ========================================================================
        if ($pack_area && !$has_calculator) {
            // Извлекаем размеры для расчета базовой цены за м³
            $thickness = 0;
            $width = 0;
            $length = 0;
            $pieces_in_pack = 1;

            // Парсим размеры: "120(114)/15" или "120×15×4000"
            if (preg_match('/(\d+)\s*(?:\(\d+\))?\s*[\/]\s*(\d+)/ui', $title, $matches)) {
                $width = floatval($matches[1]); // мм
                $thickness = floatval($matches[2]); // мм
            } elseif (preg_match('/(\d+)\s*[×\*хx]\s*(\d+)\s*[×\*хx]\s*(\d+)/ui', $title, $matches)) {
                $dims = array_map('floatval', [$matches[1], $matches[2], $matches[3]]);
                sort($dims);
                $thickness = $dims[0]; // мм
                $width = $dims[1]; // мм
                $length = $dims[2]; // мм
            }

            // Длина из названия: "2 метра" или "2м" или "2 м"
            // ✅ ИСПРАВЛЕНО: Сохраняем в МЕТРАХ, а не в мм!
            if (!$length && preg_match('/(\d+(?:[.,]\d+)?)\s*(?:метра|метров|м(?:\s|$|,|\.|\)|\/|-))/ui', $title, $matches)) {
                $length_str = str_replace(',', '.', $matches[1]);
                $length = floatval($length_str); // МЕТРЫ!
            }

            // Количество штук: "10 шт" или "(4 шт./упак)"
            if (preg_match('/(\d+)\s*шт/ui', $title, $matches)) {
                $pieces_in_pack = intval($matches[1]);
            }

            // Fallback значения
            if (!$thickness) $thickness = 20; // мм
            if (!$width) $width = 120; // мм
            if (!$length) $length = 3.0; // метры (НЕ 3000!)

            // ✅ КРИТИЧНО: Если length > 100, значит она в мм (из размеров типа 120×15×4000)
            if ($length > 100) {
                $length = $length / 1000; // переводим из мм в метры
            }

            // ✅ ПРАВИЛЬНЫЙ расчёт объёма
            $volume_per_piece = ($thickness / 1000) * ($width / 1000) * $length; // м³
            $pack_volume = $volume_per_piece * $pieces_in_pack; // м³

            // Базовая цена за м³
            if ($pack_volume > 0) {
                $base_price_cubic = $current_price / $pack_volume;
            } else {
                $base_price_cubic = $current_price;
            }

            // Цена за м²
            $base_price_per_m2 = $base_price_cubic * ($thickness / 1000);

            // Цена за упаковку (уже есть в $current_price)
            $price_per_pack = $current_price;

            if (is_product()) {
                return wc_price($base_price_per_m2) . '<span style="font-size:1.3em; font-weight:600">&nbsp;за м<sup>2</sup></span><br>' .
                       '<span style="font-size:1.3em;"><strong>' . wc_price($price_per_pack) . '</strong> за упаковку</span>';
            } else {
                return wc_price($base_price_per_m2) . '<span style="font-size:0.9em; font-weight:600">&nbsp;за м<sup>2</sup></span><br>' .
                       '<span style="font-size:1.1em;"><strong>' . wc_price($price_per_pack) . '</strong> за упаковку</span>';
            }
        }

        // ========================================================================
        // ВАРИАНТ 2: ТОВАР С КАЛЬКУЛЯТОРОМ РАЗМЕРОВ (нет pack_area, есть калькулятор)
        // Показываем: "XXX ₽ за м²" + "YYY ₽ за шт. (от Z м²)"
        // ========================================================================
        else {
            // Извлекаем размеры
            $thickness = 0;
            $width = 0;
            $length = 0;

            if (preg_match('/(\d+)\s*(?:\(\d+\))?\s*[\/]\s*(\d+)/ui', $title, $matches)) {
                $width = floatval($matches[1]); // мм
                $thickness = floatval($matches[2]); // мм
            } elseif (preg_match('/(\d+)\s*[×\*хx]\s*(\d+)\s*[×\*хx]\s*(\d+)/ui', $title, $matches)) {
                $dims = array_map('floatval', [$matches[1], $matches[2], $matches[3]]);
                sort($dims);
                $thickness = $dims[0]; // мм
                $width = $dims[1]; // мм
                $length = $dims[2]; // мм
            }

            // ✅ ИСПРАВЛЕНО: Также для варианта 2
            if (!$length && preg_match('/(\d+(?:[.,]\d+)?)\s*(?:метра|метров|м(?:\s|$|,|\.|\)|\/|-))/ui', $title, $matches)) {
                $length_str = str_replace(',', '.', $matches[1]);
                $length = floatval($length_str); // МЕТРЫ!
            }

            if (!$thickness) $thickness = 20; // мм
            if (!$width) $width = 120; // мм
            if (!$length) $length = 3.0; // метры

            // ✅ Проверка на мм
            if ($length > 100) {
                $length = $length / 1000;
            }

            // Объем 1 доски
            $volume_per_piece = ($thickness / 1000) * ($width / 1000) * $length; // м³

            // Базовая цена за м³
            if ($volume_per_piece > 0) {
                $base_price_cubic = $current_price / $volume_per_piece;
            } else {
                $base_price_cubic = $current_price;
            }

            // Цена за м²
            $base_price_per_m2 = $base_price_cubic * ($thickness / 1000);

            // Минимальная цена из calc_settings или размеров товара
            $min_width = floatval(get_post_meta($product_id, '_calc_width_min', true));
            $min_length = floatval(get_post_meta($product_id, '_calc_length_min', true));

            if (!$min_width) $min_width = $width;
            if (!$min_length) $min_length = 0.5;

            $min_area = ($min_width / 1000) * $min_length;
            $min_price = $base_price_per_m2 * $min_area;

            if (is_product()) {
                return wc_price($base_price_per_m2) . '<span style="font-size:1.3em; font-weight:600">&nbsp;за м<sup>2</sup></span><br>' .
                       '<span style="font-size:1.1em;">' . wc_price($min_price) . ' за шт. (от ' . number_format($min_area, 3) . ' м²)</span>';
            } else {
                return wc_price($base_price_per_m2) . '<span style="font-size:0.9em; font-weight:600">&nbsp;за м<sup>2</sup></span><br>' .
                       '<span style="font-size:0.85em;">' . wc_price($min_price) . ' шт.</span>';
            }
        }
    }
}

// ---------------------------------------------------------------------------
// Остальные форматирующие функции (partition_slat, running_meter, square_meter,
// multiplier, target, liter) предполагают, что соответствующие calculate_*
// и вспомогательные функции определены в другом месте в плагине.
// Здесь они вызываются безопасно (проверяется существование).
// ---------------------------------------------------------------------------

// format_partition_slat_price
if (!function_exists('format_partition_slat_price')) {
    function format_partition_slat_price($product_id, $base_price_per_m2, $hide_base) {
        $mult = 2.2;
        $min_price = calculate_min_price_partition_slat($product_id, $base_price_per_m2) * 2.2;

        if (is_product()) {
            if ($hide_base) {
                return '<span style="font-size:1.1em;">' . wc_price($min_price) . ' за шт.</span>';
            }
            return wc_price($base_price_per_m2) . '<span style="font-size:1.3em; font-weight:600"> за м<sup>2</sup></span><br>' .
                   '<span style="font-size:1.1em;">' . wc_price($min_price) . ' за шт.</span>';
        } else {
            if ($hide_base) {
                return '<span style="font-size:0.85em;">' . wc_price($min_price) . ' шт.</span>';
            }
            return wc_price($base_price_per_m2) . '<span style="font-size:0.9em; font-weight:600">&nbsp;за м<sup>2</sup></span><br>' .
                   '<span style="font-size:0.85em;">' . wc_price($min_price) . ' шт.</span>';
        }
    }
}

if (!function_exists('format_running_meter_price')) {
    function format_running_meter_price($product_id, $base_price_per_m, $hide_base) {
        $min_price = function_exists('calculate_min_price_running_meter') ? calculate_min_price_running_meter($product_id, $base_price_per_m) : ($base_price_per_m * 1);
        $min_length = floatval(get_post_meta($product_id, '_calc_length_min', true)) ?: 1;

        if (is_product()) {
            if ($hide_base) {
                return '<span style="font-size:1.1em;">' . wc_price($min_price) . ' за шт.</span>';
            }
            return wc_price($base_price_per_m) . '<span style="font-size:1.3em; font-weight:600">&nbsp;за м<sup>2</sup></span><br>' .
                   '<span style="font-size:1.1em;">' . wc_price($min_price) . ' за шт.</span>';
        } else {
            if ($hide_base) {
                return '<span style="font-size:0.85em;">' . wc_price($min_price) . ' шт.</span>';
            }
            return wc_price($base_price_per_m) . '<span style="font-size:0.9em; font-weight:600">&nbsp;за м<sup>2</sup></span><br>' .
                   '<span style="font-size:0.85em;">' . wc_price($min_price) . ' шт.</span>';
        }
    }
}

if (!function_exists('format_square_meter_price')) {
    function format_square_meter_price($product_id, $base_price_per_m2, $hide_base) {
        $min_price = function_exists('calculate_min_price_square_meter') ? calculate_min_price_square_meter($product_id, $base_price_per_m2) : ($base_price_per_m2 * 0.5);
        $mult = function_exists('get_price_multiplier') ? get_price_multiplier($product_id) : 1;
        $min_area = ($min_price / max($base_price_per_m2, 0.00001)) / max($mult, 1);

        if (is_product()) {
            if ($hide_base) {
                return '<span style="font-size:1.1em;">' . wc_price($min_price) . ' за шт. (' . number_format($min_area, 2) . ' м²)</span>';
            }
            return wc_price($base_price_per_m2) . '<span style="font-size:1.3em; font-weight:600">&nbsp;за м<sup>2</sup></span><br>' .
                   '<span style="font-size:1.1em;">' . wc_price($min_price) . ' за шт. (' . number_format($min_area, 2) . ' м²)</span>';
        } else {
            if ($hide_base) {
                return '<span style="font-size:0.85em;">' . wc_price($min_price) . ' шт.</span>';
            }
            return wc_price($base_price_per_m2) . '<span style="font-size:0.9em; font-weight:600">&nbsp;за м<sup>2</sup></span><br>' .
                   '<span style="font-size:0.85em;">' . wc_price($min_price) . ' шт.</span>';
        }
    }
}

if (!function_exists('format_multiplier_price')) {
function format_multiplier_price($product_id, $base_price_per_m2, $hide_base) {
    $min_price = calculate_min_price_multiplier($product_id, $base_price_per_m2);
    $multiplier = get_price_multiplier($product_id);
    
    // РАСЧЁТ ПЛОЩАДИ ИЗ МИНИМАЛЬНЫХ РАЗМЕРОВ
    $min_area = 0;
    
    // Получаем минимальные размеры из товара
    $product_width_min = floatval(get_post_meta($product_id, '_calc_width_min', true));
    $product_length_min = floatval(get_post_meta($product_id, '_calc_length_min', true));
    
    if ($product_width_min > 0 && $product_length_min > 0) {
        // Из настроек товара
        $min_area = ($product_width_min / 1000) * $product_length_min;
    } else {
        // Ищем в категории
        $product_cats = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
        if (!is_wp_error($product_cats) && !empty($product_cats)) {
            $multiplier_categories = [265, 266, 267, 268, 270, 271, 273];
            
            foreach ($product_cats as $cat_id) {
                if (in_array($cat_id, $multiplier_categories)) {
                    $cat_width_min = floatval(get_term_meta($cat_id, 'calc_category_width_min', true));
                    $cat_length_min = floatval(get_term_meta($cat_id, 'calc_category_length_min', true));
                    
                    if ($cat_width_min > 0 && $cat_length_min > 0) {
                        $min_area = ($cat_width_min / 1000) * $cat_length_min;
                        break;
                    }
                }
            }
        }
    }
    
    // Fallback: если не нашли размеры, вычисляем из цены (старая логика)
    if ($min_area == 0 && $base_price_per_m2 > 0 && $multiplier > 0) {
        $min_area = ($min_price / $base_price_per_m2) / $multiplier;
    }
    
    if (is_product()) {
        if ($hide_base) {
            return '<span style="font-size:1.1em;">' . wc_price($min_price) . ' за шт. (' . number_format($min_area, 3) . ' м²)</span>';
        }
        return wc_price($base_price_per_m2) . '<span style="font-size:1.3em; font-weight:600">&nbsp;за м<sup>2</sup></span><br>' .
               '<span style="font-size:1.1em;">' . wc_price($min_price) . ' за шт.</span>';
    } else {
        if ($hide_base) {
            return '<span style="font-size:0.85em;">' . wc_price($min_price) . ' шт.</span>';
        }
        return wc_price($base_price_per_m2) . '<span style="font-size:0.9em; font-weight:600">&nbsp;за м<sup>2</sup></span><br>' .
               '<span style="font-size:0.85em;">' . wc_price($min_price) . ' шт.</span>';
    }
}
}

if (!function_exists('format_target_price')) {
    function format_target_price($product, $product_id, $base_price_m2) {
        $pack_area = function_exists('extract_area_with_qty') ? extract_area_with_qty($product->get_name(), $product_id) : 0;
        $is_leaf = is_leaf_category($product_id);

        if (!$pack_area) {
            return wc_price($base_price_m2) . '<span style="font-size:0.9em; font-weight:600">&nbsp;за м<sup>2</sup></span>';
        }

        $price_per_pack = $base_price_m2 * $pack_area;
        $unit_text = $is_leaf ? 'лист' : 'упаковку';

        if (is_product()) {
            return wc_price($base_price_m2) . '<span style="font-size:1.3em; font-weight:600">&nbsp;за м<sup>2</sup></span><br>' .
                   '<span style="font-size:1.3em;"><strong>' . wc_price($price_per_pack) . '</strong> за 1 ' . $unit_text . '</span>';
        } else {
            return wc_price($base_price_m2) . '<span style="font-size:0.9em; font-weight:600">&nbsp;за м<sup>2</sup></span><br>' .
                   '<span style="font-size:1.1em;"><strong>' . wc_price($price_per_pack) . '</strong> за ' . $unit_text . '</span>';
        }
    }
}

if (!function_exists('format_liter_price')) {
    function format_liter_price($price) {
        if (strpos($price, 'за литр') !== false) {
            return $price;
        }

        if (preg_match('/(.*)<\/span>(.*)$/i', $price, $matches)) {
            return $matches[1] . '/литр</span>' . $matches[2];
        }

        return $price . ' за литр';
    }
}





/**
 * Форматирование цены для фальшбалок (категория 266)
 * Показывает цену от минимального размера
 */
function parusweb_format_falsebalk_price($price, $product) {
    if (!$product) return $price;
    
    $product_id = $product->get_id();
    
    // Проверяем, является ли товар фальшбалкой
    if (!has_term(266, 'product_cat', $product_id)) {
        return $price;
    }
    
    // Получаем данные фальшбалки
    $shapes_data = get_post_meta($product_id, '_falsebalk_shapes_data', true);
    if (!is_array($shapes_data) || empty($shapes_data)) {
        return $price;
    }
    
    // Получаем базовую цену и множитель
    $base_price_per_m2 = floatval($product->get_price());
    $multiplier = 1.0;
    
    if (function_exists('parusweb_get_price_multiplier')) {
        $multiplier = parusweb_get_price_multiplier($product_id);
    } elseif (function_exists('get_price_multiplier')) {
        $multiplier = get_price_multiplier($product_id);
    }
    
    // Находим минимальные размеры
    $min_area = null;
    $min_dimensions = null;
    
    foreach ($shapes_data as $shape_key => $shape_info) {
        if (!is_array($shape_info) || empty($shape_info['enabled'])) {
            continue;
        }
        
        // Получаем минимальные размеры для этой формы
        $width_min = floatval($shape_info['width_min'] ?? 0);
        $height_min = floatval($shape_info['height_min'] ?? 0);
        $length_min = floatval($shape_info['length_min'] ?? 0);
        
        if ($width_min <= 0 || $length_min <= 0) {
            continue;
        }
        
        // Рассчитываем площадь в зависимости от формы
        $area = 0;
        
        switch ($shape_key) {
            case 'g': // Г-образная
                $area = (($width_min / 1000) + ($height_min / 1000)) * $length_min;
                break;
                
            case 'p': // П-образная
                $height2_min = floatval($shape_info['height2_min'] ?? $height_min);
                $area = (($width_min / 1000) + ($height_min / 1000) + ($height2_min / 1000)) * $length_min;
                break;
                
            case 'o': // О-образная
                $area = 2 * (($width_min / 1000) + ($height_min / 1000)) * $length_min;
                break;
        }
        
        if ($area > 0 && ($min_area === null || $area < $min_area)) {
            $min_area = $area;
            $min_dimensions = [
                'width' => $width_min,
                'height' => $height_min,
                'length' => $length_min,
                'shape' => $shape_key
            ];
        }
    }
    
    // Если не нашли минимальные размеры, возвращаем обычную цену
    if ($min_area === null) {
        return $price;
    }
    
    // Рассчитываем цену за минимальный размер
    $min_price = $min_area * $base_price_per_m2 * $multiplier;
    
    // Цена за м² с учётом множителя
    $price_per_m2_with_multiplier = $base_price_per_m2 * $multiplier;
    
    // Формируем вывод в зависимости от страницы
    if (is_product()) {
        // На странице товара - подробный вывод
        return sprintf(
            '<span style="font-size:1.3em;"><strong>от %s</strong> за шт. (от %s м²)</span><br>' .
            '<span style="font-size:0.9em !important; color:#666;">(%s за м²)</span>',
            wc_price($min_price),
            number_format($min_area, 3),
            wc_price($price_per_m2_with_multiplier)
        );
    } else {
        // В каталоге - краткий вывод
        return sprintf(
            '<span style="font-size:1.1em;"><strong>от %s</strong> за шт.</span><br>' .
            '<span style="font-size:0.85em !important; color:#666;">(%s за м²)</span>',
            wc_price($min_price),
            wc_price($price_per_m2_with_multiplier)
        );
    }
}

// Подключаем фильтр с высоким приоритетом
add_filter('woocommerce_get_price_html', 'parusweb_format_falsebalk_price', 100, 2);


// ============================================================================
// ФУНКЦИИ РАСЧЁТА МИНИМАЛЬНЫХ ЦЕН
// ============================================================================

/**
 * Рассчитать минимальную цену для товаров с множителем (столярка)
 * 
 * @param int $product_id ID товара
 * @param float $base_price_per_m2 Базовая цена за м²
 * @return float Минимальная цена (мин. ширина × мин. длина × цена за м² × множитель)
 */
function calculate_min_price_multiplier($product_id, $base_price_per_m2) {
    // ШАГ 1: Пытаемся получить из ТОВАРА
    $min_width = floatval(get_post_meta($product_id, '_calc_width_min', true));
    $min_length = floatval(get_post_meta($product_id, '_calc_length_min', true));
    
    // ШАГ 2: Если НЕТ в товаре - ищем в КАТЕГОРИИ
    if ((!$min_width || $min_width <= 0) || (!$min_length || $min_length <= 0)) {
        $product_cats = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
        
        if (!is_wp_error($product_cats) && !empty($product_cats)) {
            $multiplier_categories = [265, 266, 267, 268, 270, 271, 273];
            
            foreach ($product_cats as $cat_id) {
                if (in_array($cat_id, $multiplier_categories)) {
                    $cat_width_min = floatval(get_term_meta($cat_id, 'calc_category_width_min', true));
                    $cat_length_min = floatval(get_term_meta($cat_id, 'calc_category_length_min', true));
                    
                    if ($cat_width_min > 0 && $cat_length_min > 0) {
                        $min_width = $cat_width_min;
                        $min_length = $cat_length_min;
                        break;
                    }
                }
            }
        }
    }
    
    // ШАГ 3: FALLBACK
    if (!$min_width || $min_width <= 0) {
        $min_width = 40;
    }
    if (!$min_length || $min_length <= 0) {
        $min_length = 0.3;
    }
    
    // Площадь минимального размера
    $min_area = ($min_width / 1000) * $min_length;
    
    // Получаем множитель
    $multiplier = function_exists('get_price_multiplier') ? get_price_multiplier($product_id) : 1.0;
    
    // Цена = Базовая цена × Площадь × Множитель
    $result = $base_price_per_m2 * $min_area * $multiplier;
    
    return $result;
}

/**
 * Рассчитать минимальную цену для квадратных метров
 * 
 * @param int $product_id ID товара
 * @param float $base_price_per_m2 Базовая цена за м²
 * @return float Минимальная цена
 */
function calculate_min_price_square_meter($product_id, $base_price_per_m2) {
    $min_width = floatval(get_post_meta($product_id, '_calc_width_min', true));
    $min_length = floatval(get_post_meta($product_id, '_calc_length_min', true));
    
    if (!$min_width || $min_width <= 0) $min_width = 40;
    if (!$min_length || $min_length <= 0) $min_length = 0.3;
    
    $min_area = ($min_width / 1000) * $min_length;
    $multiplier = function_exists('get_price_multiplier') ? get_price_multiplier($product_id) : 1.0;
    
    return $base_price_per_m2 * $min_area * $multiplier;
}

/**
 * Рассчитать минимальную цену для погонных метров
 * 
 * @param int $product_id ID товара
 * @param float $base_price_per_m Базовая цена за пог. метр
 * @return float Минимальная цена
 */
function calculate_min_price_running_meter($product_id, $base_price_per_m) {
    $min_length = floatval(get_post_meta($product_id, '_calc_length_min', true));
    
    if (!$min_length || $min_length <= 0) $min_length = 1.0;
    
    return $base_price_per_m * $min_length;
}

/**
 * Рассчитать минимальную цену для реечных перегородок
 * 
 * @param int $product_id ID товара
 * @param float $base_price_per_m2 Базовая цена за м²
 * @return float Минимальная цена
 */
function calculate_min_price_partition_slat($product_id, $base_price_per_m2) {
    $min_width = floatval(get_post_meta($product_id, '_calc_width_min', true));
    $min_length = floatval(get_post_meta($product_id, '_calc_length_min', true));
    $min_thickness = floatval(get_post_meta($product_id, '_calc_thickness_min', true));
    
    if (!$min_width || $min_width <= 0) $min_width = 70;
    if (!$min_length || $min_length <= 0) $min_length = 0.4;
    if (!$min_thickness || $min_thickness <= 0) $min_thickness = 40;
    
    // Объем = ширина × длина × толщина (все в метрах)
    $min_volume = ($min_width / 1000) * $min_length * ($min_thickness / 1000);
    
    // Для реечных перегородок может быть своя формула
    // Пока используем через площадь
    $min_area = ($min_width / 1000) * $min_length;
    
    return $base_price_per_m2 * $min_area;
}

// ============================================================================
// КОНЕЦ ФАЙЛА
// ============================================================================