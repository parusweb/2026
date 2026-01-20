<?php
/**
 * ============================================================================
 * МОДУЛЬ: КАЛЬКУЛЯТОР КУБОМЕТРОВ → УПАКОВКА + М²
 * ============================================================================
 * 
 * Калькулятор для пиломатериалов с базовой ценой в кубометрах (м³).
 * Используется для категорий пиломатериалов (87-93, 310) и листовых (190, 191, 301, 94).
 * 
 * Логика:
 * - В АДМИНКЕ: цена задается за 1 м³
 * - НА ФРОНТЕ: выводится цена за упаковку/лист + за м² (БЕЗ м³!)
 * - Извлекаются размеры из названия товара ИЛИ из атрибутов WooCommerce
 * 
 * @package ParusWeb_Functions
 * @subpackage Display
 * @version 1.4.0
 * @date 2024-12-08
 */

if (!defined('ABSPATH')) exit;

// ============================================================================
// ПРОВЕРКА КАТЕГОРИИ
// ============================================================================

/**
 * Проверка - относится ли товар к категории с расчетом по кубометрам
 * Категории: пиломатериалы и листовые
 */
if (!function_exists('is_cubic_meter_category')) {
    function is_cubic_meter_category($product_id) {
        // Все категории пиломатериалов и листовых для расчета в кубометрах
        $cubic_categories = [87, 88, 89, 90, 91, 92, 93, 310, 190, 191, 301, 94];
        return has_term($cubic_categories, 'product_cat', $product_id);
    }
}

/**
 * Проверка - относится ли товар к листовым материалам
 * Листовые категории: 190 (родитель), 191, 301, 94 (дети)
 */
if (!function_exists('is_leaf_category')) {
    function is_leaf_category($product_id) {
        $leaf_parent_id = 190;
        $leaf_children = [191, 301, 94]; // ← 301 вместо 127!
        $leaf_ids = array_merge([$leaf_parent_id], $leaf_children);
        
        return has_term($leaf_ids, 'product_cat', $product_id);
    }
}

// ============================================================================
// ИЗВЛЕЧЕНИЕ ДАННЫХ ИЗ ТОВАРА
// ============================================================================

/**
 * Извлечь параметры для расчета кубометров из атрибутов WooCommerce
 * 
 * @param int $product_id ID товара
 * @return array|false Массив с параметрами или false
 */
function extract_cubic_params_from_attributes($product_id) {
    $product = wc_get_product($product_id);
    if (!$product) return false;
    
    $result = [
        'width' => 0,
        'thickness' => 0,
        'length' => 0,
        'qty_in_pack' => 1,
    ];
    
    // Получаем атрибуты - пробуем ВСЕ варианты
    $shirina = null;
    $tolshhina = null;
    $dlina = null;
    
    // ВАРИАНТ 1: Через get_attribute (без префикса)
    $shirina = $product->get_attribute('shirina');
    $tolshhina = $product->get_attribute('tolshina');
    $dlina = $product->get_attribute('dlina');
    
    // ВАРИАНТ 2: Если не нашли - пробуем с префиксом pa_
    if (!$shirina) {
        $shirina = $product->get_attribute('pa_shirina');
    }
    if (!$tolshhina) {
        $tolshhina = $product->get_attribute('pa_tolshhina');
    }
    if (!$dlina) {
        $dlina = $product->get_attribute('pa_dlina');
    }
    
    // ВАРИАНТ 3: Через wp_get_post_terms (без префикса)
    if (!$shirina) {
        $terms = wp_get_post_terms($product_id, 'shirina');
        if (!empty($terms) && !is_wp_error($terms)) {
            $shirina = $terms[0]->name;
        }
    }
    
    if (!$tolshhina) {
        $terms = wp_get_post_terms($product_id, 'tolshina');
        if (!empty($terms) && !is_wp_error($terms)) {
            $tolshhina = $terms[0]->name;
        }
    }
    
    if (!$dlina) {
        $terms = wp_get_post_terms($product_id, 'dlina');
        if (!empty($terms) && !is_wp_error($terms)) {
            $dlina = $terms[0]->name;
        }
    }
    
    // ВАРИАНТ 4: Через wp_get_post_terms (с префиксом pa_)
    if (!$shirina) {
        $terms = wp_get_post_terms($product_id, 'pa_shirina');
        if (!empty($terms) && !is_wp_error($terms)) {
            $shirina = $terms[0]->name;
        }
    }
    
    if (!$tolshhina) {
        $terms = wp_get_post_terms($product_id, 'pa_tolshhina');
        if (!empty($terms) && !is_wp_error($terms)) {
            $tolshhina = $terms[0]->name;
        }
    }
    
    if (!$dlina) {
        $terms = wp_get_post_terms($product_id, 'pa_dlina');
        if (!empty($terms) && !is_wp_error($terms)) {
            $dlina = $terms[0]->name;
        }
    }
    
    // ВАРИАНТ 5: Через get_attributes() и прямой доступ
    if (!$shirina || !$tolshhina || !$dlina) {
        $attributes = $product->get_attributes();
        foreach ($attributes as $attr_name => $attribute) {
            $name_clean = str_replace('pa_', '', $attr_name);
            
            if (($name_clean === 'shirina' || $attr_name === 'shirina') && !$shirina) {
                if ($attribute->is_taxonomy()) {
                    $terms = wp_get_post_terms($product_id, $attr_name);
                    if (!empty($terms) && !is_wp_error($terms)) {
                        $shirina = $terms[0]->name;
                    }
                } else {
                    $shirina = $attribute->get_options()[0] ?? null;
                }
            }
            
            if (($name_clean === 'tolshhina' || $attr_name === 'tolshhina') && !$tolshhina) {
                if ($attribute->is_taxonomy()) {
                    $terms = wp_get_post_terms($product_id, $attr_name);
                    if (!empty($terms) && !is_wp_error($terms)) {
                        $tolshhina = $terms[0]->name;
                    }
                } else {
                    $tolshhina = $attribute->get_options()[0] ?? null;
                }
            }
            
            if (($name_clean === 'dlina' || $attr_name === 'dlina') && !$dlina) {
                if ($attribute->is_taxonomy()) {
                    $terms = wp_get_post_terms($product_id, $attr_name);
                    if (!empty($terms) && !is_wp_error($terms)) {
                        $dlina = $terms[0]->name;
                    }
                } else {
                    $dlina = $attribute->get_options()[0] ?? null;
                }
            }
        }
    }
    
    // Извлекаем числовые значения из полученных строк
    if ($shirina) {
        preg_match('/(\d+(?:[.,]\d+)?)/', $shirina, $match);
        if (!empty($match[1])) {
            $result['width'] = floatval(str_replace(',', '.', $match[1]));
        }
    }
    
    if ($tolshhina) {
        preg_match('/(\d+(?:[.,]\d+)?)/', $tolshhina, $match);
        if (!empty($match[1])) {
            $result['thickness'] = floatval(str_replace(',', '.', $match[1]));
        }
    }
    
    if ($dlina) {
        preg_match('/(\d+(?:[.,]\d+)?)/', $dlina, $match);
        if (!empty($match[1])) {
            $length_value = floatval(str_replace(',', '.', $match[1]));
            
            // Определяем единицы измерения
            if (preg_match('/м|m/ui', $dlina) || $length_value < 50) {
                // Длина в метрах - конвертируем в мм
                $result['length'] = $length_value * 1000;
            } else {
                // Длина уже в мм
                $result['length'] = $length_value;
            }
        }
    }
    
    // Извлекаем количество из названия товара
    $title = $product->get_name();
    if (preg_match('/(\d+)\s*шт/ui', $title, $match)) {
        $result['qty_in_pack'] = intval($match[1]);
    }
    
    // Проверяем что все параметры извлечены
    if ($result['width'] > 0 && $result['thickness'] > 0 && $result['length'] > 0) {
        return $result;
    }
    
    return false;
}

/**
 * Извлечь параметры для расчета кубометров из названия товара
 * 
 * Формат названия: "Имитация бруса 140×20×6000 мм, 10 шт/упак"
 * Извлекаемые данные:
 * - width (ширина) в мм
 * - thickness (толщина) в мм
 * - length (длина) в мм
 * - qty_in_pack (количество в упаковке)
 * 
 * @param string $title Название товара
 * @return array|false Массив с параметрами или false
 */
function extract_cubic_params_from_title($title) {
    // Очищаем строку
    $title = mb_strtolower($title, 'UTF-8');
    $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $title = str_replace("\xC2\xA0", ' ', $title); // Неразрывный пробел
    
    $result = [
        'width' => 0,
        'thickness' => 0,
        'length' => 0,
        'qty_in_pack' => 1,
    ];
    
    // ========================================================================
    // ПАТТЕРН 1: "20/120(110)/500-3000" - ПЛАНКЕН С ДИАПАЗОНОМ
    // ========================================================================
    // КРИТИЧНО: Требуем ДВА слэша и диапазон через дефис
    // Формат: \d+/\d+(?:\(\d+\))?/\d+-\d+
    // Примеры:
    //   ✅ "20/120(110)/500-3000"
    //   ✅ "20/120/500-3000"
    //   ❌ "600/3000" (нет второго слэша и диапазона)
    //   ❌ "40 А/В 600/3000" (не начинается с цифр сразу после пробела)
    
    if (preg_match('/\b(\d+)\s*\/\s*(\d+)\s*(?:\(\d+\))?\s*\/\s*(\d+)\s*-\s*(\d+)\b/u', $title, $matches)) {
        $result['thickness'] = intval($matches[1]);  // 20
        $result['width'] = intval($matches[2]);       // 120
        $min_length = intval($matches[3]);            // 500
        $max_length = intval($matches[4]);            // 3000
        
        // Используем МИНИМАЛЬНУЮ длину
        $result['length'] = $min_length;
        
        // Извлекаем количество штук
        if (preg_match('/(\d+)\s*шт/ui', $title, $qty_matches)) {
            $result['qty_in_pack'] = intval($qty_matches[1]);
        }
        
        return $result;
    }
    
    // ========================================================================
    // ПАТТЕРН 2: "145(140)/18" - ИМИТАЦИЯ БРУСА
    // ========================================================================
    // Ширина(рабочая)/толщина БЕЗ третьего числа
    if (preg_match('/(\d+)\s*\(\s*\d+\s*\)\s*\/\s*(\d+)(?!\s*[\/\-])/u', $title, $matches)) {
        $result['width'] = intval($matches[1]);      // 145
        $result['thickness'] = intval($matches[2]);  // 18
    }
    
    // ========================================================================
    // ПАТТЕРН 3: "140×20×6000" - ТРИ РАЗМЕРА ЧЕРЕЗ ×
    // ========================================================================
    elseif (preg_match('/(\d+)\s*[×xх*]\s*(\d+)\s*[×xх*]\s*(\d+)/ui', $title, $matches)) {
        $dim1 = intval($matches[1]);
        $dim2 = intval($matches[2]);
        $dim3 = intval($matches[3]);
        
        // Сортируем размеры
        $sizes = [$dim1, $dim2, $dim3];
        rsort($sizes);
        
        // Определяем размеры по величине
        if ($sizes[0] > 1000) {
            // Самый большой - длина
            $result['length'] = $sizes[0];
            $result['width'] = $sizes[1];
            $result['thickness'] = $sizes[2];
        } else {
            $result['width'] = $sizes[0];
            $result['thickness'] = $sizes[1];
            $result['length'] = $sizes[2];
        }
    }
    
    // ========================================================================
    // ПАТТЕРН 4: "600/3000" - ДВА ЧИСЛА ЧЕРЕЗ СЛЭШ
    // ========================================================================
    // Для мебельных щитов, фанеры и т.д.
    // Формат: ширина/длина (толщина извлекается отдельно)
    elseif (preg_match('/\b(\d+)\s*\/\s*(\d+)(?!\s*-)\b/u', $title, $matches)) {
        $dim1 = intval($matches[1]);
        $dim2 = intval($matches[2]);
        
        // Определяем что есть что
        if ($dim2 > $dim1 && $dim2 > 1000) {
            // Второе число больше и > 1000 → это длина
            $result['width'] = $dim1;
            $result['length'] = $dim2;
        } elseif ($dim1 > $dim2 && $dim1 > 1000) {
            // Первое число больше и > 1000 → это длина
            $result['length'] = $dim1;
            $result['width'] = $dim2;
        } else {
            // Оба примерно одинаковые или непонятно
            $result['width'] = max($dim1, $dim2);
            $result['length'] = min($dim1, $dim2);
        }
    }
    
    // ========================================================================
    // ПАТТЕРН 5: "140×20" - ДВА РАЗМЕРА ЧЕРЕЗ ×
    // ========================================================================
    elseif (preg_match('/(\d+)\s*[×xх*]\s*(\d+)(?!\s*[×xх*])/ui', $title, $matches)) {
        $dim1 = intval($matches[1]);
        $dim2 = intval($matches[2]);
        
        if ($dim1 > $dim2) {
            $result['width'] = $dim1;
            $result['thickness'] = $dim2;
        } else {
            $result['width'] = $dim2;
            $result['thickness'] = $dim1;
        }
    }
    
    // ========================================================================
    // ИЗВЛЕЧЕНИЕ ТОЛЩИНЫ (если не извлечена выше)
    // ========================================================================
    if ($result['thickness'] == 0) {
        // Паттерн: "Дуб 40 А/В" - число до букв
        if (preg_match('/\b(\d+)\s+[а-яa-z]/ui', $title, $matches)) {
            $potential_thickness = intval($matches[1]);
            // Толщина обычно 10-100мм для листовых материалов
            if ($potential_thickness >= 4 && $potential_thickness <= 100) {
                $result['thickness'] = $potential_thickness;
            }
        }
    }
    
    // ========================================================================
    // ИЗВЛЕЧЕНИЕ ДЛИНЫ (если не извлечена выше)
    // ========================================================================
    if ($result['length'] == 0) {
        // Сначала ищем диапазон: "500-3000мм"
        if (preg_match('/(\d{3,5})\s*-\s*(\d{3,5})\s*(?:мм|mm)?/ui', $title, $matches)) {
            $min_len = intval($matches[1]);
            $max_len = intval($matches[2]);
            
            // Берём минимальную длину
            $result['length'] = min($min_len, $max_len);
        }
        // Затем ищем "3 метра", "3 м"
        elseif (preg_match('/(\d+(?:[.,]\d+)?)\s*(?:метра|метров|м\b)/ui', $title, $matches)) {
            $length_m = floatval(str_replace(',', '.', $matches[1]));
            $result['length'] = $length_m * 1000;
        }
        // Затем ищем просто большое число "6000"
        elseif (preg_match('/\b(\d{4,5})(?!\s*[×xх*\/])\b/ui', $title, $matches)) {
            $result['length'] = intval($matches[1]);
        }
    }
    
    // ========================================================================
    // ИЗВЛЕЧЕНИЕ КОЛИЧЕСТВА В УПАКОВКЕ
    // ========================================================================
    if (preg_match('/(\d+)\s*шт/ui', $title, $matches)) {
        $result['qty_in_pack'] = intval($matches[1]);
    }
    
    // ========================================================================
    // ВАЛИДАЦИЯ
    // ========================================================================
    if ($result['width'] <= 0 || $result['thickness'] <= 0 || $result['length'] <= 0) {
        return false;
    }
    
    return $result;
}

// ============================================================================
// ТЕСТОВЫЕ СЛУЧАИ
// ============================================================================

/**
 * Тестирование различных форматов названий
 */
function test_all_title_formats() {
    $test_cases = [
        // ПЛАНКЕН С ДИАПАЗОНОМ
        'Планкен скошенный ТЕРМО Ясень 20/120(110)/500-3000мм' => [
            'thickness' => 20,
            'width' => 120,
            'length' => 500,
            'qty_in_pack' => 1,
        ],
        
        // ИМИТАЦИЯ БРУСА
        'Имитация бруса 145(140)/18/3000, 8 шт' => [
            'thickness' => 18,
            'width' => 145,
            'length' => 3000,
            'qty_in_pack' => 8,
        ],
        
        // ТРИ РАЗМЕРА ЧЕРЕЗ ×
        'Вагонка штиль 140×20×6000, 10 шт' => [
            'thickness' => 20,
            'width' => 140,
            'length' => 6000,
            'qty_in_pack' => 10,
        ],
        
        // МЕБЕЛЬНЫЙ ЩИТ (два числа через /)
        'Мебельный щит Дуб 40 А/В, Сращеный 600/3000' => [
            'thickness' => 40,
            'width' => 600,
            'length' => 3000,
            'qty_in_pack' => 1,
        ],
        
        // ДОСКА С ДИАПАЗОНОМ
        'Доска обрезная 25×150×1000-6000' => [
            'thickness' => 25,
            'width' => 150,
            'length' => 1000,
            'qty_in_pack' => 1,
        ],
    ];
    
    echo '<div style="background:#f0f0f0;padding:20px;margin:20px;font-family:monospace;font-size:12px;">';
    echo '<h3>ТЕСТИРОВАНИЕ ПАРСИНГА НАЗВАНИЙ</h3>';
    
    $passed = 0;
    $failed = 0;
    
    foreach ($test_cases as $title => $expected) {
        $result = extract_cubic_params_from_title($title);
        
        echo '<div style="background:white;padding:10px;margin:10px 0;border:1px solid #ddd;">';
        echo '<strong>Название:</strong><br>' . esc_html($title) . '<br><br>';
        
        if ($result === false) {
            echo '<span style="color:red;font-weight:bold;">❌ НЕ РАСПОЗНАНО</span><br>';
            $failed++;
        } else {
            $is_correct = (
                $result['thickness'] == $expected['thickness'] &&
                $result['width'] == $expected['width'] &&
                $result['length'] == $expected['length'] &&
                $result['qty_in_pack'] == $expected['qty_in_pack']
            );
            
            if ($is_correct) {
                echo '<span style="color:green;font-weight:bold;">✅ ПРАВИЛЬНО</span><br>';
                $passed++;
            } else {
                echo '<span style="color:red;font-weight:bold;">❌ ОШИБКА</span><br>';
                $failed++;
            }
            
            echo '<table style="margin-top:5px;">';
            echo '<tr><td>Толщина:</td><td>' . $result['thickness'] . ' мм</td><td>' . ($result['thickness'] == $expected['thickness'] ? '✓' : '✗ ожидалось ' . $expected['thickness']) . '</td></tr>';
            echo '<tr><td>Ширина:</td><td>' . $result['width'] . ' мм</td><td>' . ($result['width'] == $expected['width'] ? '✓' : '✗ ожидалось ' . $expected['width']) . '</td></tr>';
            echo '<tr><td>Длина:</td><td>' . $result['length'] . ' мм</td><td>' . ($result['length'] == $expected['length'] ? '✓' : '✗ ожидалось ' . $expected['length']) . '</td></tr>';
            echo '<tr><td>Штук:</td><td>' . $result['qty_in_pack'] . '</td><td>' . ($result['qty_in_pack'] == $expected['qty_in_pack'] ? '✓' : '✗ ожидалось ' . $expected['qty_in_pack']) . '</td></tr>';
            echo '</table>';
        }
        
        echo '</div>';
    }
    
    echo '<div style="background:' . ($failed > 0 ? '#ffebee' : '#e8f5e9') . ';padding:15px;margin-top:20px;border-radius:5px;">';
    echo '<strong>ИТОГО:</strong> ✅ Прошло: ' . $passed . ' | ❌ Провалено: ' . $failed;
    echo '</div>';
    
    echo '</div>';
}

// Раскомментировать для теста:
// add_action('woocommerce_before_add_to_cart_form', 'test_all_title_formats');


/**
 * Получить параметры товара для расчета кубометров
 * 
 * ПРИОРИТЕТ:
 * 1. Извлечение из названия товара
 * 2. Извлечение из атрибутов WooCommerce (shirina, tolshina, dlina)
 * 
 * @param int $product_id ID товара
 * @return array|false Параметры или false
 */
function get_cubic_product_params($product_id) {
    $product = wc_get_product($product_id);
    if (!$product) return false;
    
    // ПРИОРИТЕТ 1: Атрибуты WooCommerce (самые надёжные!)
    $params = extract_cubic_params_from_attributes($product_id);
    
    if ($params !== false) {
        return $params;
    }
    
    // ПРИОРИТЕТ 2: Если атрибутов нет - пытаемся из названия
    $title = $product->get_name();
    $params = extract_cubic_params_from_title($title);
    
    if ($params !== false) {
        return $params;
    }
    
    return false;
}

// ============================================================================
// РАСЧЕТНЫЕ ФУНКЦИИ
// ============================================================================

/**
 * Рассчитать объем одной доски в кубометрах
 * 
 * @param array $params Параметры: width, thickness, length (все в мм)
 * @return float Объем в м³
 */
function calculate_board_volume($params) {
    $width_m = $params['width'] / 1000;
    $thickness_m = $params['thickness'] / 1000;
    $length_m = $params['length'] / 1000;
    
    return $width_m * $thickness_m * $length_m;
}

/**
 * Рассчитать площадь одной доски в м²
 * 
 * @param array $params Параметры: width, length (в мм)
 * @return float Площадь в м²
 */
function calculate_board_area($params) {
    $width_m = $params['width'] / 1000;
    $length_m = $params['length'] / 1000;
    
    return $width_m * $length_m;
}

/**
 * Рассчитать цену за м² исходя из цены за м³ и толщины
 * 
 * @param float $price_per_m3 Цена за кубометр
 * @param int $thickness_mm Толщина в мм
 * @return float Цена за м²
 */
function calculate_price_per_m2_from_m3($price_per_m3, $thickness_mm) {
    $thickness_m = $thickness_mm / 1000;
    return $price_per_m3 * $thickness_m;
}

/**
 * Рассчитать полную цену упаковки
 * 
 * @param int $product_id ID товара
 * @param float $base_price_per_m3 Базовая цена за м³
 * @return array|false Массив с расчетами или false
 */
function calculate_cubic_package_price($product_id, $base_price_per_m3) {
    $params = get_cubic_product_params($product_id);
    
    if (!$params) {
        return false;
    }
    
    // Объем одной доски
    $volume_per_piece = calculate_board_volume($params);
    
    // Площадь одной доски
    $area_per_piece = calculate_board_area($params);
    
    // Цена за одну доску (через объем)
    $price_per_piece = $base_price_per_m3 * $volume_per_piece;
    
    // Цена за упаковку
    $price_per_pack = $price_per_piece * $params['qty_in_pack'];
    
    // Цена за м² (для вывода)
    $price_per_m2 = calculate_price_per_m2_from_m3($base_price_per_m3, $params['thickness']);
    
    // Общая площадь упаковки в м²
    $total_area = $area_per_piece * $params['qty_in_pack'];
    
    return [
        'params' => $params,
        'volume_per_piece' => $volume_per_piece,
        'area_per_piece' => $area_per_piece,
        'price_per_piece' => $price_per_piece,
        'price_per_pack' => $price_per_pack,
        'price_per_m2' => $price_per_m2,
        'total_area' => $total_area,
        'base_price_per_m3' => $base_price_per_m3,
    ];
}

// ============================================================================
// ФУНКЦИИ ФОРМАТИРОВАНИЯ ЦЕНЫ
// ============================================================================

/**
 * ВЫВОД: за упаковку/лист + за м² (БЕЗ м³ на фронте!)
 * 
 * @param int $product_id ID товара
 * @param float $base_price_per_m3 Базовая цена за м³ (из админки)
 * @return string HTML с отформатированной ценой
 */
function format_cubic_meter_price($product_id, $base_price_per_m3) {
    $calc = calculate_cubic_package_price($product_id, $base_price_per_m3);
    
    if (!$calc) {
        $price_per_m2 = $base_price_per_m3 * 0.02;
        return '<span style="font-size:1.1em;"><strong>' . wc_price($price_per_m2) . '</strong> за м²</span>';
    }
    
    $qty = $calc['params']['qty_in_pack'];
    
    // ========================================================================
    // ПРОВЕРЯЕМ: есть ли у товара калькулятор размеров (calc_settings)
    // ========================================================================
    $has_calculator = (
        get_post_meta($product_id, '_calc_width_min', true) || 
        get_post_meta($product_id, '_calc_length_min', true)
    );
    
    // ========================================================================
    // ВАРИАНТ 1: ТОВАР С КАЛЬКУЛЯТОРОМ РАЗМЕРОВ
    // Показываем: "462 ₽ за шт. (от 0.060 м²)" + "(7700 ₽ за м²)"
    // ========================================================================
    if ($has_calculator) {
        //Получаем минимальные размеры ТОЛЬКО из calc_settings
        $min_width = floatval(get_post_meta($product_id, '_calc_width_min', true));
        $min_length = floatval(get_post_meta($product_id, '_calc_length_min', true));
        
        // Если calc_settings не заданы - это ошибка!
        // НЕ используем размеры из названия, т.к. там может быть толщина вместо ширины!
        if (!$min_width || !$min_length) {
            // Логируем ошибку для админа
            if (WP_DEBUG || current_user_can('manage_options')) {
                error_log(sprintf(
                    "[CALC ERROR] Product #%d: calc_settings missing! width_min=%s, length_min=%s",
                    $product_id,
                    $min_width ?: 'EMPTY',
                    $min_length ?: 'EMPTY'
                ));
            }
            
            // Используем минимальные разумные значения (НЕ из названия!)
            if (!$min_width) {
                $min_width = 70; // Минимум 70мм для столешниц/подоконников
            }
            if (!$min_length) {
                $min_length = 0.4; // Минимум 0.4м
            }
        }
        
        // Площадь минимального размера в м²
        $min_area = ($min_width / 1000) * $min_length;
        
        // Цена за минимальный размер
        $min_price = $calc['price_per_m2'] * $min_area;
        
        if (is_product()) {
            return sprintf(
                '<span style="font-size:1.3em;"><strong>%s</strong> за шт. (от %s м²)</span><br>' .
                '<span style="font-size:0.9em !important; color:#666;">(%s за м²)</span>',
                wc_price($min_price),
                number_format($min_area, 3),
                wc_price($calc['price_per_m2'])
            );
        } else {
            return sprintf(
                '<span style="font-size:1.1em;"><strong>%s</strong> шт.</span><br>' .
                '<span style="font-size:0.85em !important; color:#666;">(%s за м²)</span>',
                wc_price($min_price),
                wc_price($calc['price_per_m2'])
            );
        }
    }
    
    // ========================================================================
    // ВАРИАНТ 2: ТОВАР-УПАКОВКА БЕЗ КАЛЬКУЛЯТОРА
    // Показываем: "2480 ₽ за упаковку (10 шт)" ИЛИ "2480 ₽ за лист"
    // ========================================================================
    else {
        // Проверяем, листовой ли это материал
        $is_leaf = is_leaf_category($product_id);

        // Подписи в зависимости от типа товара
        $unit_full  = $is_leaf ? 'за лист' : 'за упаковку';
        $unit_short = $is_leaf ? 'за лист' : 'за упак.';

        // Для листовых не выводим "(n шт)", т.к. цена уже за лист
        $qty_html_product  = $is_leaf ? '' : sprintf('<span style="font-size:1em;">(%d шт)</span><br>', $qty);
        $qty_html_archive  = $is_leaf ? '' : sprintf('<span style="font-size:0.9em !important;">(%d шт)</span><br>', $qty);

        if (is_product()) {
            // На странице товара
            return sprintf(
                '<span style="font-size:1.3em;"><strong>%s</strong> %s</span><br>' .
                '%s' .
                '<span style="font-size:0.9em !important; color:#666;">(%s за м²)</span>',
                wc_price($calc['price_per_pack']),
                $unit_full,
                $qty_html_product,
                wc_price($calc['price_per_m2'])
            );
        } else {
            // В каталоге
            return sprintf(
                '<span style="font-size:1.1em;"><strong>%s</strong> %s</span><br>' .
                '%s' .
                '<span style="font-size:0.85em !important; color:#666;">(%s за м²)</span><br>',
                wc_price($calc['price_per_pack']),
                $unit_short,
                $qty_html_archive,
                wc_price($calc['price_per_m2'])
            );
        }
    }
}

// ============================================================================
// МИНИМАЛЬНАЯ ЦЕНА (для сортировки и фильтров)
// ============================================================================

/**
 * Рассчитать минимальную цену для товаров в кубометрах
 * Используется в фильтрах и сортировке WooCommerce
 * 
 * @param int $product_id ID товара
 * @param float $base_price_per_m3 Базовая цена за м³
 * @return float Минимальная цена (цена упаковки)
 */
function calculate_cubic_min_price($product_id, $base_price_per_m3) {
    $calc = calculate_cubic_package_price($product_id, $base_price_per_m3);
    
if (!$calc) {
    // Получаем текущую цену (уже пересчитанную через parusweb_convert_timber_price_simple)
    $product = wc_get_product($product_id);
    $current_price = floatval($product->get_price());
    
    // Пробуем извлечь размеры из названия для расчета цены за м²
    $title = $product->get_name();
    if (preg_match('/(\d+)[*\/×xх](\d+)[*\/×xх](\d+)/', $title, $matches)) {
        $thickness = floatval(min($matches[1], $matches[2], $matches[3]));
        $price_per_m2 = $base_price_per_m3 * ($thickness / 1000);
        
        if (is_product()) {
            return sprintf(
                '<span style="font-size:1.3em;"><strong>%s</strong> за шт.</span><br>' .
                '<span style="font-size:0.9em !important; color:#666;">(%s за м²)</span>',
                wc_price($current_price),
                wc_price($price_per_m2)
            );
        } else {
            return sprintf(
                '<span style="font-size:1.1em;"><strong>%s</strong> шт.</span><br>' .
                '<span style="font-size:0.85em !important; color:#666;">(%s за м²)</span>',
                wc_price($current_price),
                wc_price($price_per_m2)
            );
        }
    }
    
    // Если совсем не удалось - показываем только текущую цену
    return '<span style="font-size:1.1em;"><strong>' . wc_price($current_price) . '</strong> за шт.</span>';
}
    
    // Возвращаем цену упаковки как минимальную
    return $calc['price_per_pack'];
}

// ============================================================================
// ВЫВОД ДИАГНОСТИЧЕСКОЙ ИНФОРМАЦИИ (ДЛЯ ТЕСТОВ)
// ============================================================================

/**
 * Вывести информацию о расчетах для тестирования
 * УПРОЩЕННАЯ ВЕРСИЯ
 * 
 * @param int $product_id ID товара
 */
function display_cubic_calculation_debug($product_id) {
    $product = wc_get_product($product_id);
    if (!$product) return;
    
    $base_price = floatval($product->get_regular_price() ?: $product->get_price());
    $title = $product->get_name();
    
    $params_from_title = extract_cubic_params_from_title($title);
    $params_from_attrs = extract_cubic_params_from_attributes($product_id);
    $calc = calculate_cubic_package_price($product_id, $base_price);
    
    // Проверка типа материала
    $is_leaf = is_leaf_category($product_id);
    $material_type = $is_leaf ? 'ЛИСТОВОЙ' : 'ПИЛОМАТЕРИАЛ';
    
    echo '<div style="background: #f5f5f5; border: 1px solid #ddd; padding: 15px; margin: 15px 0; font-family: monospace; font-size: 12px;">';
    echo '<strong>ДИАГНОСТИКА (Кубометры)</strong><br><br>';
    
    echo '<strong>Тип материала:</strong> <span style="color: ' . ($is_leaf ? 'blue' : 'green') . '; font-weight: bold;">' . $material_type . '</span><br>';
    echo '<strong>Название:</strong> ' . esc_html($title) . '<br><br>';
    
    echo '<strong>Из НАЗВАНИЯ:</strong> ';
    if ($params_from_title) {
        echo 'OK - ' . $params_from_title['width'] . 'x' . $params_from_title['thickness'] . 'x' . $params_from_title['length'] . ' мм, ' . $params_from_title['qty_in_pack'] . ' шт';
    } else {
        echo 'НЕТ';
    }
    echo '<br><br>';
    
    echo '<strong>Из АТРИБУТОВ:</strong> ';
    if ($params_from_attrs) {
        echo 'OK - ' . $params_from_attrs['width'] . 'x' . $params_from_attrs['thickness'] . 'x' . $params_from_attrs['length'] . ' мм, ' . $params_from_attrs['qty_in_pack'] . ' шт';
    } else {
        echo 'НЕТ';
    }
    echo '<br><br>';
    
    if (!$calc) {
        echo '<strong style="color: red;">ОШИБКА:</strong> Не удалось рассчитать. Проверьте название и атрибуты товара.';
        echo '</div>';
        return;
    }
    
    echo '<strong>ИСПОЛЬЗОВАНО:</strong> ' . $calc['params']['width'] . 'x' . $calc['params']['thickness'] . 'x' . $calc['params']['length'] . ' мм, ' . $calc['params']['qty_in_pack'] . ' шт<br><br>';
    
    echo '<strong>Базовая цена:</strong> ' . wc_price($calc['base_price_per_m3']) . ' за м³<br>';
    echo '<strong>Объем 1 доски:</strong> ' . number_format($calc['volume_per_piece'], 6, '.', '') . ' м³<br>';
    echo '<strong>Площадь упаковки:</strong> ' . number_format($calc['total_area'], 2, '.', '') . ' м²<br><br>';
    
    echo '<strong>РЕЗУЛЬТАТ:</strong><br>';
    $unit_type = $is_leaf ? 'лист' : 'упаковку (' . $calc['params']['qty_in_pack'] . ' шт)';
    echo 'Цена за ' . $unit_type . ': ' . wc_price($calc['price_per_pack']) . '<br>';
    echo 'Цена за м²: ' . wc_price($calc['price_per_m2']);
    
    echo '</div>';
}

/**
 * Хук для вывода диагностики на странице товара
 */
function parusweb_display_cubic_debug() {
    if (!is_product()) return;
    
    global $product;
    $product_id = $product->get_id();
    
    if (!is_cubic_meter_category($product_id)) return;
    
    display_cubic_calculation_debug($product_id);
}
// Раскомментировать для показа диагностики:
//add_action('woocommerce_before_add_to_cart_form', 'parusweb_display_cubic_debug', 5);

// ============================================================================
// КОНЕЦ МОДУЛЯ
// ============================================================================