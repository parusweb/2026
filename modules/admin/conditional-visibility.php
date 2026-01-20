<?php
/**
 * ============================================================================
 * МОДУЛЬ: УСЛОВНОЕ ОТОБРАЖЕНИЕ АДМИН-БЛОКОВ ПО КАТЕГОРИЯМ
 * ============================================================================
 * 
 * Показывает только релевантные блоки настроек в зависимости от категории товара
 * 
 * @package ParusWeb_Functions
 * @subpackage Admin
 */

if (!defined('ABSPATH')) exit;

// ============================================================================
// ОПРЕДЕЛЕНИЕ КАТЕГОРИЙ
// ============================================================================

/**
 * Получить группу для категории по ID
 */
function parusweb_get_category_group($term_id) {
    // ЛКМ (Лаки и краски)
    $lkm_cats = [81, 82, 83, 84, 85, 86];
    if (in_array($term_id, $lkm_cats)) {
        return 'lkm';
    }
    
    // Крепеж
    $fastener_cats = [77, 80, 123, 299, 300];
    if (in_array($term_id, $fastener_cats)) {
        return 'fastener';
    }
    
    // Пиломатериалы
    $timber_cats = [87, 88, 90, 91, 92, 93, 310];
    if (in_array($term_id, $timber_cats)) {
        return 'timber';
    }
    
    // Столярные изделия (с множителем и размерами)
    $stolyarka_cats = [265, 266, 267, 268, 270, 271, 273];
    if (in_array($term_id, $stolyarka_cats)) {
        return 'stolyarka';
    }
    
    // Листовые материалы (мебельные щиты)
    $sheet_cats = [190, 94, 191, 301];
    if (in_array($term_id, $sheet_cats)) {
        return 'sheet';
    }
    
    // ДПК и МПК (декинг)
    $dpk_cats = [197, 193, 194, 195, 196];
    if (in_array($term_id, $dpk_cats)) {
        return 'dpk';
    }
    
    // Фальшбалки (ID 269 из ЛКМ переместили сюда?)
    // Если 269 это действительно фальшбалки, раскомментируйте:
    // $falsebalk_cats = [269];
    // if (in_array($term_id, $falsebalk_cats)) {
    //     return 'falsebalk';
    // }
    
    return 'other';
}

/**
 * Получить группу категорий для товара
 */
function parusweb_get_product_category_group($product_id) {
    $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
    
    if (empty($categories)) {
        return 'other';
    }
    
    // Проверяем каждую категорию
    foreach ($categories as $cat_id) {
        $group = parusweb_get_category_group($cat_id);
        if ($group !== 'other') {
            return $group;
        }
    }
    
    return 'other';
}

// ============================================================================
// МАТРИЦА ВИДИМОСТИ БЛОКОВ
// ============================================================================

/**
 * Определить какие блоки показывать для группы
 * 
 * @return array Массив с ключами блоков и значениями true/false
 */
function parusweb_get_visible_blocks($group) {
    $matrix = [
        'lkm' => [
            'calculator_dimensions' => false,  // Размеры
            'calculator_fastener' => false,    // Крепеж
            'multiplier' => false,             // Множитель
            'tinting' => true,                 // Колеровка (схемы)
            'painting' => false,               // Услуги покраски
            'shtaketnik' => false,             // Штакетник
            'falsebalk' => false,              // Фальшбалки
            'thickness' => false,              // Толщина
        ],
        'fastener' => [
            'calculator_dimensions' => false,
            'calculator_fastener' => false,
            'multiplier' => false,
            'tinting' => false,
            'painting' => false,
            'shtaketnik' => false,
            'falsebalk' => false,
            'thickness' => false,
        ],
        'timber' => [
            'calculator_dimensions' => false,
            'calculator_fastener' => true,     // Крепеж для пиломатериалов
            'multiplier' => false,
            'tinting' => false,
            'painting' => true,                // Услуги покраски
            'shtaketnik' => false,
            'falsebalk' => false,
            'thickness' => false,
        ],
        'stolyarka' => [
            'calculator_dimensions' => true,   // Размеры для столярки
            'calculator_fastener' => false,
            'multiplier' => true,              // Множитель
            'tinting' => false,
            'painting' => true,                // Услуги покраски
            'shtaketnik' => false,
            'falsebalk' => false,
            'thickness' => true,               // Толщина для некоторых
        ],
        'falsebalk' => [
            'calculator_dimensions' => false,
            'calculator_fastener' => false,
            'multiplier' => false,
            'tinting' => false,
            'painting' => false,
            'shtaketnik' => false,
            'falsebalk' => true,               // Настройки фальшбалок
            'thickness' => false,
        ],
        'other' => [
            'calculator_dimensions' => true,
            'calculator_fastener' => false,
            'multiplier' => true,
            'tinting' => false,
            'painting' => true,
            'shtaketnik' => false,
            'falsebalk' => false,
            'thickness' => false,
        ],
    ];
    
    return $matrix[$group] ?? $matrix['other'];
}

// ============================================================================
// УСЛОВНОЕ ОТОБРАЖЕНИЕ В PRODUCT-META.PHP
// ============================================================================

/**
 * Проверить нужно ли показывать блок
 */
function parusweb_should_show_block($block_name, $product_id = null) {
    if (!$product_id) {
        global $post;
        $product_id = $post->ID ?? 0;
    }
    
    if (!$product_id) {
        return true; // На странице создания показываем всё
    }
    
    $group = parusweb_get_product_category_group($product_id);
    $visible_blocks = parusweb_get_visible_blocks($group);
    
    return $visible_blocks[$block_name] ?? true;
}

// ============================================================================
// ОБЁРТКИ ДЛЯ СУЩЕСТВУЮЩИХ ФУНКЦИЙ
// ============================================================================

/**
 * Калькулятор размеров - с проверкой
 */
add_action('woocommerce_product_options_general_product_data', 'parusweb_conditional_calculator_settings', 5);

function parusweb_conditional_calculator_settings() {
    global $post;
    
    if (!parusweb_should_show_block('calculator_dimensions', $post->ID)) {
        return;
    }
    
    // Вызов оригинальной функции
    if (function_exists('parusweb_add_calculator_settings')) {
        remove_action('woocommerce_product_options_general_product_data', 'parusweb_add_calculator_settings');
        parusweb_add_calculator_settings();
    }
}

/**
 * Калькулятор крепежа - с проверкой
 */
add_action('woocommerce_product_options_general_product_data', 'parusweb_conditional_fastener_settings', 5);

function parusweb_conditional_fastener_settings() {
    global $post;
    
    if (!parusweb_should_show_block('calculator_fastener', $post->ID)) {
        return;
    }
    
    // Вызов оригинальной функции
    if (function_exists('parusweb_add_fastener_calculator_settings')) {
        remove_action('woocommerce_product_options_general_product_data', 'parusweb_add_fastener_calculator_settings');
        parusweb_add_fastener_calculator_settings();
    }
}

/**
 * Множитель цены - с проверкой
 */
add_action('woocommerce_product_options_pricing', 'parusweb_conditional_multiplier_field', 5);

function parusweb_conditional_multiplier_field() {
    global $post;
    
    if (!parusweb_should_show_block('multiplier', $post->ID)) {
        return;
    }
    
    // Вызов оригинальной функции
    if (function_exists('parusweb_add_price_multiplier_field')) {
        remove_action('woocommerce_product_options_pricing', 'parusweb_add_price_multiplier_field');
        parusweb_add_price_multiplier_field();
    }
}

// ============================================================================
// ДИНАМИЧЕСКОЕ ДОБАВЛЕНИЕ/УДАЛЕНИЕ ХУКОВ
// ============================================================================

/**
 * Убрать хуки которые не нужны для текущей категории
 */
add_action('admin_init', 'parusweb_filter_admin_hooks', 1);

function parusweb_filter_admin_hooks() {
    if (!is_admin()) return;
    
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'product') return;
    
    global $post;
    if (!$post) return;
    
    $group = parusweb_get_product_category_group($post->ID);
    $visible_blocks = parusweb_get_visible_blocks($group);
    
    // Убираем ненужные хуки
    if (!$visible_blocks['calculator_dimensions']) {
        remove_action('woocommerce_product_options_general_product_data', 'parusweb_add_calculator_settings');
    }
    
    if (!$visible_blocks['calculator_fastener']) {
        remove_action('woocommerce_product_options_general_product_data', 'parusweb_add_fastener_calculator_settings');
    }
    
    if (!$visible_blocks['multiplier']) {
        remove_action('woocommerce_product_options_pricing', 'parusweb_add_price_multiplier_field');
    }
    
    if (!$visible_blocks['shtaketnik']) {
        remove_action('woocommerce_product_options_general_product_data', 'parusweb_add_shtaketnik_form_prices');
    }
}

// ============================================================================
// ВИЗУАЛЬНАЯ ПОДСКАЗКА О КАТЕГОРИИ
// ============================================================================

/**
 * Показать индикатор группы товара
 */
add_action('edit_form_after_title', 'parusweb_show_category_group_indicator');

function parusweb_show_category_group_indicator() {
    global $post;
    
    if (!$post || get_post_type($post) !== 'product') {
        return;
    }
    
    $group = parusweb_get_product_category_group($post->ID);
    
    $labels = [
        'lkm' => ['🎨 ЛКМ', '#4caf50'],
        'fastener' => ['🔩 Крепеж', '#ff9800'],
        'timber' => ['🌲 Пиломатериалы', '#8bc34a'],
        'stolyarka' => ['🪚 Столярка', '#2196f3'],
        'sheet' => ['📄 Листовые материалы', '#9c27b0'],
        'dpk' => ['🏗️ ДПК/МПК', '#00bcd4'],
        'falsebalk' => ['🏛️ Фальшбалки', '#795548'],
        'other' => ['📦 Прочее', '#607d8b'],
    ];
    
    [$label, $color] = $labels[$group];
    
    ?>
    <div style="background: <?php echo $color; ?>; color: white; padding: 8px 15px; border-radius: 4px; display: inline-block; margin: 10px 0; font-weight: 600;">
        <?php echo $label; ?>
    </div>
    <?php
}

// ============================================================================
// ФИЛЬТРАЦИЯ ACF ПОЛЕЙ ДЛЯ КАТЕГОРИЙ
// ============================================================================

/**
 * Скрыть ненужные ACF поля при редактировании категории
 */
add_filter('acf/prepare_field', 'parusweb_filter_category_acf_fields');

function parusweb_filter_category_acf_fields($field) {
    // Работает только в админке
    if (!is_admin()) {
        return $field;
    }
    
    // Только для страницы редактирования категории
    $screen = get_current_screen();
    if (!$screen || $screen->taxonomy !== 'product_cat') {
        return $field;
    }
    
    // Получаем ID категории
    $term_id = 0;
    if (isset($_GET['tag_ID'])) {
        $term_id = intval($_GET['tag_ID']);
    }
    
    if ($term_id === 0) {
        return $field; // На странице создания показываем всё
    }
    
    // Определяем группу категории
    $group = parusweb_get_category_group($term_id);
    
    // Матрица видимости ACF полей для категорий
    $field_visibility = [
        'lkm' => [
            // Схемы колеровки - ТОЛЬКО для ЛКМ
            'field_lkm_tinting_schemes' => true,
            'group_lkm_tinting_category' => true,
            
            // Услуги покраски - НЕТ для ЛКМ
            'field_dop_uslugi_category' => false,
            'group_painting_services_category' => false,
            
            // Крепеж - НЕТ для ЛКМ
            'field_enable_fasteners_calc' => false,
            'field_fasteners_products' => false,
            'group_fasteners_calculator' => false,
            
            // Калькулятор размеров - НЕТ для ЛКМ
            'field_calc_cat_width_min' => false,
            'field_calc_cat_width_max' => false,
            'field_calc_cat_width_step' => false,
            'field_calc_cat_length_min' => false,
            'field_calc_cat_length_max' => false,
            'field_calc_cat_thickness_min' => false,
            'group_calc_cat_stolyarka' => false,
            
            // Множитель - НЕТ для ЛКМ
            'category_price_multiplier' => false,
            
            // Фаски - НЕТ для ЛКМ
            'faska_types' => false,
        ],
        'timber' => [
            // Колеровка - НЕТ для пиломатериалов
            'field_lkm_tinting_schemes' => false,
            'group_lkm_tinting_category' => false,
            
            // Услуги покраски - ДА для пиломатериалов
            'field_dop_uslugi_category' => true,
            'group_painting_services_category' => true,
            
            // Крепеж - ДА для пиломатериалов
            'field_enable_fasteners_calc' => true,
            'field_fasteners_products' => true,
            'group_fasteners_calculator' => true,
            
            // Калькулятор размеров - НЕТ для пиломатериалов
            'field_calc_cat_width_min' => false,
            'field_calc_cat_width_max' => false,
            'group_calc_cat_stolyarka' => false,
            
            // Множитель - НЕТ
            'category_price_multiplier' => false,
            
            // Фаски - НЕТ
            'faska_types' => false,
        ],
        'stolyarka' => [
            // Колеровка - НЕТ
            'field_lkm_tinting_schemes' => false,
            'group_lkm_tinting_category' => false,
            
            // Услуги покраски - ДА
            'field_dop_uslugi_category' => true,
            'group_painting_services_category' => true,
            
            // Крепеж - НЕТ
            'field_enable_fasteners_calc' => false,
            'field_fasteners_products' => false,
            'group_fasteners_calculator' => false,
            
            // Калькулятор размеров - ДА
            'field_calc_cat_width_min' => true,
            'field_calc_cat_width_max' => true,
            'field_calc_cat_width_step' => true,
            'field_calc_cat_length_min' => true,
            'field_calc_cat_length_max' => true,
            'field_calc_cat_thickness_min' => true,
            'group_calc_cat_stolyarka' => true,
            
            // Множитель - ДА
            'category_price_multiplier' => true,
            
            // Фаски - СПЕЦИАЛЬНАЯ ЛОГИКА (см. ниже)
            'faska_types' => null, // null = проверяем дополнительно
        ],
        'fastener' => [
            // Всё скрываем для крепежа
            'field_lkm_tinting_schemes' => false,
            'group_lkm_tinting_category' => false,
            'field_dop_uslugi_category' => false,
            'group_painting_services_category' => false,
            'field_enable_fasteners_calc' => false,
            'field_fasteners_products' => false,
            'group_fasteners_calculator' => false,
            'field_calc_cat_width_min' => false,
            'group_calc_cat_stolyarka' => false,
            'category_price_multiplier' => false,
            'faska_types' => false,
        ],
        'sheet' => [
            // Листовые материалы - покраска ДА, остальное НЕТ
            'field_lkm_tinting_schemes' => false,
            'group_lkm_tinting_category' => false,
            'field_dop_uslugi_category' => true,  // Покраска ДА
            'group_painting_services_category' => true,
            'field_enable_fasteners_calc' => false,
            'field_fasteners_products' => false,
            'group_fasteners_calculator' => false,
            'field_calc_cat_width_min' => false,
            'group_calc_cat_stolyarka' => false,
            'category_price_multiplier' => false,
            'faska_types' => false,
        ],
        'dpk' => [
            // ДПК и МПК - как пиломатериалы
            'field_lkm_tinting_schemes' => false,
            'group_lkm_tinting_category' => false,
            'field_dop_uslugi_category' => true,  // Покраска ДА
            'group_painting_services_category' => true,
            'field_enable_fasteners_calc' => true, // Крепеж ДА
            'field_fasteners_products' => true,
            'group_fasteners_calculator' => true,
            'field_calc_cat_width_min' => false,
            'group_calc_cat_stolyarka' => false,
            'category_price_multiplier' => false,
            'faska_types' => false,
        ],
    ];
    
    // Если нет правил для группы - показываем всё
    if (!isset($field_visibility[$group])) {
        return $field;
    }
    
    $rules = $field_visibility[$group];
    
    // Проверяем ключ поля
    $field_key = $field['key'] ?? '';
    
    // СПЕЦИАЛЬНАЯ ЛОГИКА ДЛЯ ФАСОК
    // Фаски показываем ТОЛЬКО для подоконников (271) и столешниц (273)
    if ($field_key === 'faska_types' || strpos($field_key, 'faska') !== false) {
        $faska_allowed_cats = [271, 273]; // Подоконники и столешницы
        
        // Если категория НЕ в списке разрешённых - скрываем фаски
        if (!in_array($term_id, $faska_allowed_cats)) {
            return false;
        }
    }
    
    if (isset($rules[$field_key])) {
        if ($rules[$field_key] === false) {
            return false; // Скрываем поле
        } elseif ($rules[$field_key] === null) {
            // null означает что проверка выполнена выше (для фасок)
            return $field;
        }
    }
    
    return $field;
}

// ============================================================================
// СКРЫТИЕ СТАНДАРТНЫХ ПОЛЕЙ КАТЕГОРИЙ (НЕ ACF)
// ============================================================================

/**
 * Добавить стили для скрытия стандартных полей категорий
 */
add_action('admin_head-term.php', 'parusweb_hide_category_fields_css');

function parusweb_hide_category_fields_css() {
    $screen = get_current_screen();
    if (!$screen || $screen->taxonomy !== 'product_cat') {
        return;
    }
    
    // Получаем ID категории
    $term_id = isset($_GET['tag_ID']) ? intval($_GET['tag_ID']) : 0;
    if ($term_id === 0) {
        return;
    }
    
    $group = parusweb_get_category_group($term_id);
    
    // Фаски разрешены ТОЛЬКО для подоконников (271) и столешниц (273)
    $faska_allowed_cats = [271, 273];
    $hide_faska = !in_array($term_id, $faska_allowed_cats);
    
    // Множитель разрешён ТОЛЬКО для столярки
    $hide_multiplier = ($group !== 'stolyarka');
    
    ?>
    <style>
        <?php if ($hide_faska): ?>
        /* Скрываем блок фасок */
        tr.form-field:has(#faska_types_container) {
            display: none !important;
        }
        <?php endif; ?>
        
        <?php if ($hide_multiplier): ?>
        /* Скрываем множитель */
        tr.form-field:has(#category_price_multiplier) {
            display: none !important;
        }
        <?php endif; ?>
    </style>
    <script>
    jQuery(document).ready(function($) {
        <?php if ($hide_faska): ?>
        // Скрываем блок фасок по тексту заголовка
        $('tr.form-field').each(function() {
            var labelText = $(this).find('th label').text().trim();
            if (labelText === 'Типы фасок' || labelText.indexOf('фаск') !== -1 || labelText.toLowerCase().indexOf('faska') !== -1) {
                $(this).hide();
            }
        });
        <?php endif; ?>
        
        <?php if ($hide_multiplier): ?>
        // Скрываем множитель по тексту
        $('tr.form-field').each(function() {
            var labelText = $(this).find('th label').text().trim();
            if (labelText.indexOf('Множитель') !== -1 || labelText.indexOf('множител') !== -1) {
                $(this).hide();
            }
        });
        <?php endif; ?>
    });
    </script>
    <?php
    
    // Скрытие заголовков ACF блоков в зависимости от группы
    ?>
    <script>
    jQuery(document).ready(function($) {
        // Скрываем заголовки в зависимости от группы
        <?php if ($group === 'lkm'): ?>
        $('h2, h3').each(function() {
            var text = $(this).text().trim();
            if (text === 'Калькулятор крепежа' || 
                text === 'Настройки размеров' || 
                text === 'Схемы покраски (категории)' ||
                text === 'Услуги покраски для категории' ||
                text === 'Схемы') {
                $(this).hide();
            }
        });
        
        $('.acf-field-group[data-key="group_fasteners_calculator"]').hide();
        $('.acf-field-group[data-key="group_calc_cat_stolyarka"]').hide();
        $('.acf-field-group[data-key="group_painting_services_category"]').hide();
        
        <?php elseif ($group === 'fastener'): ?>
        $('h2, h3').each(function() {
            var text = $(this).text().trim();
            if (text === 'Калькулятор крепежа' || 
                text === 'Настройки размеров' || 
                text === 'Схемы покраски (категории)' ||
                text === 'Услуги покраски для категории' ||
                text === 'Схемы колеровки ЛКМ' ||
                text === 'Схемы') {
                $(this).hide();
            }
        });
        
        $('.acf-field-group[data-key="group_fasteners_calculator"]').hide();
        $('.acf-field-group[data-key="group_calc_cat_stolyarka"]').hide();
        $('.acf-field-group[data-key="group_painting_services_category"]').hide();
        $('.acf-field-group[data-key="group_lkm_tinting_category"]').hide();
        
        <?php elseif ($group === 'timber'): ?>
        $('h2, h3').each(function() {
            var text = $(this).text().trim();
            if (text === 'Настройки размеров' || 
                text === 'Схемы колеровки ЛКМ') {
                $(this).hide();
            }
        });
        
        $('.acf-field-group[data-key="group_calc_cat_stolyarka"]').hide();
        $('.acf-field-group[data-key="group_lkm_tinting_category"]').hide();
        
        <?php elseif ($group === 'sheet'): ?>
        $('h2, h3').each(function() {
            var text = $(this).text().trim();
            if (text === 'Калькулятор крепежа' || 
                text === 'Настройки размеров' || 
                text === 'Схемы колеровки ЛКМ' ||
                text === 'Схемы') {
                $(this).hide();
            }
        });
        
        $('.acf-field-group[data-key="group_fasteners_calculator"]').hide();
        $('.acf-field-group[data-key="group_calc_cat_stolyarka"]').hide();
        $('.acf-field-group[data-key="group_lkm_tinting_category"]').hide();
        
        <?php elseif ($group === 'dpk'): ?>
        $('h2, h3').each(function() {
            var text = $(this).text().trim();
            if (text === 'Настройки размеров' || 
                text === 'Схемы колеровки ЛКМ') {
                $(this).hide();
            }
        });
        
        $('.acf-field-group[data-key="group_calc_cat_stolyarka"]').hide();
        $('.acf-field-group[data-key="group_lkm_tinting_category"]').hide();
        <?php endif; ?>
    });
    </script>
    <?php
}

// ============================================================================
// ОЧИСТКА ФОРМЫ СОЗДАНИЯ НОВОЙ КАТЕГОРИИ
// ============================================================================

/**
 * Скрыть все кастомные поля при создании новой категории
 */
add_action('admin_head-edit-tags.php', 'parusweb_hide_fields_on_new_category');

function parusweb_hide_fields_on_new_category() {
    $screen = get_current_screen();
    if (!$screen || $screen->taxonomy !== 'product_cat') {
        return;
    }
    
    ?>
    <style>
        /* Скрываем все ACF поля в форме создания новой категории */
        #addtag .acf-field-group,
        #addtag .acf-field {
            display: none !important;
        }
        
        /* Скрываем стандартные кастомные поля */
        #addtag tr.form-field:has(#category_price_multiplier),
        #addtag tr.form-field:has(#faska_types_container) {
            display: none !important;
        }
    </style>
    <script>
    jQuery(document).ready(function($) {
        // Скрываем все кастомные заголовки в форме создания
        $('#addtag h2, #addtag h3').each(function() {
            var text = $(this).text().trim();
            if (text !== 'Добавить новую категорию товара' && 
                text !== 'Name' && 
                text !== 'Название') {
                $(this).hide();
            }
        });
    });
    </script>
    <?php
}