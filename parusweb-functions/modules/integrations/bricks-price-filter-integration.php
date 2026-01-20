<?php
/**
 * ============================================================================
 * ИНТЕГРАЦИЯ С BRICKS RANGE FILTER: Цены за м² для пиломатериалов И листовых
 * ============================================================================
 * 
 * Перехватывает запросы фильтра цен в категориях пиломатериалов и листовых
 * и подменяет цены из JSON файла (₽/м² вместо ₽/м³)
 * 
 * ВАЖНО: Теперь поддерживает:
 * - Пиломатериалы: 87, 310, 88-93
 * - Листовые материалы: 190, 191, 301, 94
 */

if (!defined('ABSPATH')) exit;

/**
 * ============================================================================
 * ЗАГРУЗКА JSON С ЦЕНАМИ
 * ============================================================================
 */

function parusweb_get_timber_prices() {
    static $prices = null;
    
    if ($prices !== null) {
        return $prices;
    }
    
    $upload_dir = wp_upload_dir();
    $json_file = $upload_dir['basedir'] . '/timber-prices.json';
    
    if (!file_exists($json_file)) {
        error_log("Timber prices JSON not found: $json_file");
        return [];
    }
    
    $json_data = file_get_contents($json_file);
    $data = json_decode($json_data, true);
    
    if (!$data || !isset($data['prices'])) {
        error_log("Invalid timber prices JSON format");
        return [];
    }
    
    $prices = $data['prices'];
    return $prices;
}

/**
 * ============================================================================
 * ХУК 1: Изменение meta_query для фильтра цен
 * ============================================================================
 */

add_action('pre_get_posts', 'parusweb_filter_timber_prices', 20);

function parusweb_filter_timber_prices($query) {
    
    // Только для основного запроса на фронтенде
    if (is_admin() || !$query->is_main_query()) {
        return;
    }
    
    // Только для категорий товаров
    if (!is_product_category() && !is_shop()) {
        return;
    }
    
    // РАСШИРЕННАЯ ПРОВЕРКА: Пиломатериалы + Листовые
    $target_categories = array_merge(
        [87, 310],           // Основные ПМ
        range(88, 93),       // Подкатегории ПМ
        [190, 191, 301, 94]  // Листовые материалы
    );
    
    $queried_object = get_queried_object();
    $is_target_category = false;
    
    if ($queried_object && isset($queried_object->term_id)) {
        if (in_array($queried_object->term_id, $target_categories)) {
            $is_target_category = true;
        }
    }
    
    if (!$is_target_category) {
        return;
    }
    
    // Проверяем есть ли фильтр по цене в запросе
    $meta_query = $query->get('meta_query');
    
    if (empty($meta_query)) {
        return;
    }
    
    $price_filter = null;
    $min_price = null;
    $max_price = null;
    
    // Ищем фильтр по цене
    foreach ($meta_query as $key => $meta) {
        if (isset($meta['key']) && $meta['key'] === '_price') {
            $price_filter = $meta;
            
            if (isset($meta['value']) && is_array($meta['value'])) {
                $min_price = floatval($meta['value'][0] ?? 0);
                $max_price = floatval($meta['value'][1] ?? PHP_INT_MAX);
            }
            
            break;
        }
    }
    
    if (!$price_filter || $min_price === null || $max_price === null) {
        return;
    }
    
    // Загружаем цены из JSON
    $timber_prices = parusweb_get_timber_prices();
    
    if (empty($timber_prices)) {
        return;
    }
    
    // Фильтруем товары по цене за м²
    $matching_ids = [];
    
    foreach ($timber_prices as $product_id => $data) {
        $price_m2 = floatval($data['price_m2']);
        
        if ($price_m2 >= $min_price && $price_m2 <= $max_price) {
            $matching_ids[] = intval($product_id);
        }
    }
    
    if (empty($matching_ids)) {
        // Нет товаров в этом диапазоне - показываем пустой результат
        $query->set('post__in', [0]);
    } else {
        // Ограничиваем результаты только этими товарами
        $existing_post_in = $query->get('post__in');
        
        if (!empty($existing_post_in)) {
            // Пересечение с существующими ограничениями
            $matching_ids = array_intersect($matching_ids, $existing_post_in);
        }
        
        $query->set('post__in', $matching_ids);
    }
    
    // Удаляем оригинальный мета-запрос по цене (он уже не нужен)
    $new_meta_query = [];
    foreach ($meta_query as $key => $meta) {
        if (!isset($meta['key']) || $meta['key'] !== '_price') {
            $new_meta_query[] = $meta;
        }
    }
    
    $query->set('meta_query', $new_meta_query);
    
    error_log(sprintf(
        "Timber+Листовые price filter: min=%.2f, max=%.2f, found %d products",
        $min_price,
        $max_price,
        count($matching_ids)
    ));
}

/**
 * ============================================================================
 * ХУК 2: Изменение диапазона цен для слайдера
 * ============================================================================
 */

add_filter('bricks/query/min_max_prices', 'parusweb_modify_timber_price_range', 10, 2);

function parusweb_modify_timber_price_range($prices, $query_vars) {
    
    // РАСШИРЕННАЯ ПРОВЕРКА: Пиломатериалы + Листовые
    $target_categories = array_merge(
        [87, 310],           // Основные ПМ
        range(88, 93),       // Подкатегории ПМ
        [190, 191, 301, 94]  // Листовые материалы
    );
    
    if (isset($query_vars['tax_query'])) {
        $is_target = false;
        
        foreach ($query_vars['tax_query'] as $tax) {
            if (isset($tax['taxonomy']) && $tax['taxonomy'] === 'product_cat') {
                if (isset($tax['terms'])) {
                    $terms = is_array($tax['terms']) ? $tax['terms'] : [$tax['terms']];
                    
                    foreach ($terms as $term) {
                        if (in_array($term, $target_categories)) {
                            $is_target = true;
                            break 2;
                        }
                    }
                }
            }
        }
        
        if (!$is_target) {
            return $prices;
        }
    }
    
    // Загружаем цены из JSON
    $timber_prices = parusweb_get_timber_prices();
    
    if (empty($timber_prices)) {
        return $prices;
    }
    
    // Находим min/max цены за м²
    $min_price = 0;
    $max_price = PHP_INT_MAX;
    
    foreach ($timber_prices as $data) {
        $price_m2 = floatval($data['price_m2']);
        
        if ($price_m2 < $min_price) {
            $min_price = $price_m2;
        }
        
        if ($price_m2 > $max_price) {
            $max_price = $price_m2;
        }
    }
    
    return [
        'min' => floor($min_price),
        'max' => ceil($max_price)
    ];
}

/**
 * ============================================================================
 * ХУК 3: Сортировка по цене
 * ============================================================================
 */

add_filter('posts_clauses', 'parusweb_sort_timber_by_price_m2', 10, 2);

function parusweb_sort_timber_by_price_m2($clauses, $query) {
    global $wpdb;
    
    if (is_admin() || !$query->is_main_query()) {
        return $clauses;
    }
    
    if (!is_product_category() && !is_shop()) {
        return $clauses;
    }
    
    $orderby = $query->get('orderby');
    
    if ($orderby !== 'price' && $orderby !== 'price-desc') {
        return $clauses;
    }
    
    // РАСШИРЕННАЯ ПРОВЕРКА: Пиломатериалы + Листовые
    $target_categories = array_merge(
        [87, 310],           // Основные ПМ
        range(88, 93),       // Подкатегории ПМ
        [190, 191, 301, 94]  // Листовые материалы
    );
    
    $queried_object = get_queried_object();
    
    if (!$queried_object || !isset($queried_object->term_id)) {
        return $clauses;
    }
    
    if (!in_array($queried_object->term_id, $target_categories)) {
        return $clauses;
    }
    
    // Загружаем цены из JSON
    $timber_prices = parusweb_get_timber_prices();
    
    if (empty($timber_prices)) {
        return $clauses;
    }
    
    // Создаем временную таблицу с ценами
    $order = $query->get('order') ?: 'ASC';
    
    $cases = [];
    foreach ($timber_prices as $product_id => $data) {
        $price_m2 = floatval($data['price_m2']);
        $cases[] = "WHEN {$wpdb->posts}.ID = {$product_id} THEN {$price_m2}";
    }
    
    if (!empty($cases)) {
        $case_sql = "CASE " . implode(' ', $cases) . " ELSE 999999 END";
        $clauses['orderby'] = "{$case_sql} {$order}";
    }
    
    return $clauses;
}

/**
 * ============================================================================
 * ДИАГНОСТИКА
 * ============================================================================
 */

// Вывод в консоль браузера (для отладки)
add_action('wp_footer', function() {
    if (!is_product_category() && !is_shop()) return;
    
    $prices = parusweb_get_timber_prices();
    $count = count($prices);
    
    // Проверяем наличие листовых в JSON
    $has_leaves = false;
    $leaf_categories = [190, 191, 301, 94];
    
    foreach ($prices as $product_id => $data) {
        if (has_term($leaf_categories, 'product_cat', $product_id)) {
            $has_leaves = true;
            break;
        }
    }
    
    echo '<script>console.log("🌲 Timber+Листовые prices loaded: ' . $count . '");</script>';
    echo '<script>console.log("📄 Листовые в JSON: ' . ($has_leaves ? 'ДА' : 'НЕТ') . $max_price.'");</script>';
}, 999);