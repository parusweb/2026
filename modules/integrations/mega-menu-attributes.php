<?php
/**
 * Mega Menu Attributes Module
 * 
 * Интеграция атрибутов товаров в мега-меню:
 * - Загрузка атрибутов из JSON файла
 * - Динамическое отображение атрибутов для категорий
 * - Подмена атрибутов при наведении на подкатегории
 * - Автоматический подсчёт количества товаров
 * - Поддержка родительских категорий (агрегация из дочерних)
 * 
 * Требования:
 * - JSON файл /menu_attributes.json в корне сайта
 * - Структура: { "category_slug": { "attribute_name": [{name, slug, count}] } }
 * 
 * @package ParusWeb_Functions
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================================================
// БЛОК 1: ЗАГРУЗКА И ОТОБРАЖЕНИЕ АТРИБУТОВ В МЕГА-МЕНЮ
// ============================================================================

/**
 * JavaScript для работы с атрибутами в мега-меню
 */
function parusweb_mega_menu_attributes_script() {
    ?>
    <script>
    jQuery(function($) {
        'use strict';
        
        let cache = null;
        
        /**
         * Загрузка JSON с атрибутами один раз при загрузке страницы
         */
        $.getJSON('<?php echo home_url("/menu_attributes.json"); ?>', function(data) {
            cache = data;
            //console.log('Menu attributes loaded:', cache);
            
            // Рендерим атрибуты для родительских категорий (если есть виджеты)
            $('.widget_layered_nav').each(function() {
                renderAttributes($(this));
            });
        }).fail(function(jqXHR, textStatus, errorThrown) {
            console.warn('ParusWeb: Не удалось загрузить menu_attributes.json', textStatus);
        });
        
        /**
         * Подмена атрибутов при наведении на подкатегории в мега-меню
         */
        $(document).on('mouseenter', '.mega-menu-item-type-taxonomy', function() {
            let href = $(this).find('a').attr('href');
            if (!href) return;
            
            // Извлекаем slug категории из URL
            let parts = href.split('/');
            let catSlug = parts.filter(Boolean).pop();
            
            //console.log('Hover on category:', catSlug);
            
            // Обновляем все виджеты атрибутов
            $('.widget_layered_nav').each(function() {
                renderAttributes($(this), catSlug);
            });
        });
        
        /**
         * Рендеринг списка атрибутов в виджете
         * 
         * @param {jQuery} $widget Элемент виджета
         * @param {string} overrideCat Slug категории для переопределения (опционально)
         */
        function renderAttributes($widget, overrideCat) {
            if (!cache) return;
            
            // Получаем атрибут и категорию из data-атрибутов виджета
            let attr = $widget.data('attribute');
            let cat = overrideCat || $widget.data('category');
            
            //console.log('Render attributes for:', cat, attr);
            
            // Сначала пробуем найти данные для конкретной категории
            if (cat && attr && cache[cat] && cache[cat][attr]) {
                renderAttributeList($widget, cache[cat][attr], cat, attr);
            } 
            // НОВОЕ: Если не нашли для родительской категории, ищем в дочерних
            else if (cat && attr) {
                let aggregatedTerms = aggregateChildrenAttributes(cat, attr);
                
                if (aggregatedTerms.length > 0) {
                    //console.log('Found in children:', aggregatedTerms.length, 'terms');
                    renderAttributeList($widget, aggregatedTerms, cat, attr);
                } else {
                    //console.log('No attributes found for:', cat, attr);
                    $widget.html('<div class="no-attributes">Нет атрибутов</div>');
                }
            } else {
                $widget.html('<div class="no-attributes">Нет атрибутов</div>');
            }
        }
        
        /**
         * НОВАЯ ФУНКЦИЯ: Агрегация атрибутов из дочерних категорий
         * 
         * @param {string} parentCat Slug родительской категории
         * @param {string} attr Название атрибута
         * @return {Array} Массив объединённых терминов
         */
        function aggregateChildrenAttributes(parentCat, attr) {
            let aggregated = {};
            
            // Проходим по всем категориям в cache
            for (let catSlug in cache) {
                // Проверяем, содержится ли родительская категория в пути дочерней
                // Например: pilomaterial содержится в pilomaterial-doska
                if (catSlug.indexOf(parentCat) === 0 && catSlug !== parentCat) {
                    if (cache[catSlug] && cache[catSlug][attr]) {
                        // Агрегируем термины
                        cache[catSlug][attr].forEach(function(term) {
                            if (!aggregated[term.slug]) {
                                aggregated[term.slug] = {
                                    name: term.name,
                                    slug: term.slug,
                                    count: 0
                                };
                            }
                            aggregated[term.slug].count += term.count;
                        });
                    }
                }
            }
            
            // Преобразуем объект в массив и сортируем по имени
            return Object.values(aggregated).sort(function(a, b) {
                return a.name.localeCompare(b.name, 'ru');
            });
        }
        
        /**
         * НОВАЯ ФУНКЦИЯ: Отрисовка списка атрибутов
         * 
         * @param {jQuery} $widget Элемент виджета
         * @param {Array} terms Массив терминов
         * @param {string} cat Slug категории
         * @param {string} attr Название атрибута
         */
        function renderAttributeList($widget, terms, cat, attr) {
            let $ul = $('<ul class="attribute-list"/>');
            
            // Формируем список атрибутов
            terms.forEach(function(term) {
                let base = '<?php echo home_url("/product-category/"); ?>' + cat + '/';
                let attrSlug = attr.replace('pa_', '');
                    if (attrSlug === 'sortporody') {
                    attrSlug = 'sort_';
                    }
                let url = base + '?_' + attrSlug + '=' + term.slug;
                
                $ul.append(
                    '<li>' +
                        '<a href="' + url + '">' +
                            term.name + 
                            ' <span class="count">(' + term.count + ')</span>' +
                        '</a>' +
                    '</li>'
                );
            });
            
            $widget.html($ul);
        }
    });
    </script>
    
    <style>
    /* Стили для списка атрибутов в мега-меню */
    .widget_layered_nav .attribute-list {
        list-style: none;
        margin: 0;
        padding: 0;
    }
    
    .widget_layered_nav .attribute-list li {
        padding: 2px 0;
    }
    
    .widget_layered_nav .attribute-list a {
        text-decoration: none;
        color: #333;
        transition: color 0.3s ease;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .widget_layered_nav .attribute-list a:hover {
        color: #8bc34a;
    }
    
    .widget_layered_nav .attribute-list .count {
        font-size: 0.85em;
        color: #999;
        margin-left: 5px;
    }
    
    .widget_layered_nav .no-attributes {
        padding: 10px;
        color: #999;
        font-style: italic;
        text-align: center;
    }
    </style>
    <?php
}
add_action('wp_footer', 'parusweb_mega_menu_attributes_script');

// ============================================================================
// БЛОК 2: ГЕНЕРАЦИЯ JSON ФАЙЛА С АТРИБУТАМИ
// ============================================================================

/**
 * Генерация JSON файла с атрибутами товаров для всех категорий
 * Вызывается вручную или по расписанию
 * 
 * @return bool true при успехе, false при ошибке
 */
function parusweb_generate_menu_attributes_json() {
    global $wpdb;
    
    $categories = get_terms(array(
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
    ));
    
    if (is_wp_error($categories)) {
        return false;
    }
    
    $data = array();
    $attributes = wc_get_attribute_taxonomies();
    
    foreach ($categories as $category) {
        $cat_slug = $category->slug;
        $data[$cat_slug] = array();
        
        // НОВОЕ: Получаем ID категории и всех дочерних категорий
        $cat_ids = array($category->term_id);
        
        $children = get_term_children($category->term_id, 'product_cat');
        if (!is_wp_error($children) && !empty($children)) {
            $cat_ids = array_merge($cat_ids, $children);
        }
        
        foreach ($attributes as $attribute) {
            $taxonomy = 'pa_' . $attribute->attribute_name;
            
            // НОВОЕ: Получаем ID товаров из ВСЕХ категорий (текущей + дочерних)
            $placeholders = implode(',', array_fill(0, count($cat_ids), '%d'));
            
            $product_ids = $wpdb->get_col($wpdb->prepare("
                SELECT DISTINCT p.ID
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                WHERE p.post_type = 'product'
                AND p.post_status = 'publish'
                AND tt.taxonomy = 'product_cat'
                AND tt.term_id IN ($placeholders)
            ", $cat_ids));
            
            if (empty($product_ids)) {
                continue;
            }
            
            // Получаем все термины атрибута
            $terms = get_terms(array(
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
                'orderby'    => 'name',
                'order'      => 'ASC',
            ));
            
            if (is_wp_error($terms) || empty($terms)) {
                continue;
            }
            
            $term_data = array();
            
            foreach ($terms as $term) {
                // Считаем товары с этим термином
                $product_ids_placeholders = implode(',', array_fill(0, count($product_ids), '%d'));
                
                $count = $wpdb->get_var($wpdb->prepare("
                    SELECT COUNT(DISTINCT tr.object_id)
                    FROM {$wpdb->term_relationships} tr
                    INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    WHERE tt.taxonomy = %s
                    AND tt.term_id = %d
                    AND tr.object_id IN ($product_ids_placeholders)
                ", array_merge([$taxonomy, $term->term_id], $product_ids)));
                
                // Добавляем только если есть товары
                if ($count > 0) {
                    $term_data[] = array(
                        'name'  => $term->name,
                        'slug'  => $term->slug,
                        'term_id' => $term->term_id,
                        'count' => intval($count)
                    );
                }
            }
            
            if (!empty($term_data)) {
                $data[$cat_slug][$taxonomy] = $term_data;
            }
        }
    }
    
    // Сохраняем JSON файл
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $file_path = ABSPATH . 'menu_attributes.json';
    
    $result = file_put_contents($file_path, $json);
    
    return $result !== false;
}

/**
 * AJAX обработчик для генерации JSON (для админки)
 */
function parusweb_ajax_generate_menu_attributes() {
    // Проверка прав доступа
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Недостаточно прав');
    }
    
    $result = parusweb_generate_menu_attributes_json();
    
    if ($result) {
        wp_send_json_success('JSON файл успешно создан');
    } else {
        wp_send_json_error('Ошибка при создании JSON файла');
    }
}
add_action('wp_ajax_generate_menu_attributes', 'parusweb_ajax_generate_menu_attributes');

// ============================================================================
// БЛОК 3: АВТОМАТИЧЕСКАЯ РЕГЕНЕРАЦИЯ JSON
// ============================================================================

/**
 * Регенерация JSON при обновлении товара
 */
function parusweb_regenerate_on_product_update($post_id) {
    // Проверяем что это товар
    if (get_post_type($post_id) !== 'product') {
        return;
    }
    
    // Используем транзиент чтобы не генерировать слишком часто
    $transient_key = 'parusweb_menu_json_updated';
    
    if (get_transient($transient_key)) {
        return;
    }
    
    // Генерируем JSON
    parusweb_generate_menu_attributes_json();
    
    // Устанавливаем транзиент на 5 минут
    set_transient($transient_key, true, 5 * MINUTE_IN_SECONDS);
}
add_action('save_post_product', 'parusweb_regenerate_on_product_update');

/**
 * Регенерация JSON при изменении терма (категории или атрибута)
 */
function parusweb_regenerate_on_term_update($term_id, $tt_id, $taxonomy) {
    // Проверяем что это категория товара или атрибут
    if ($taxonomy === 'product_cat' || strpos($taxonomy, 'pa_') === 0) {
        $transient_key = 'parusweb_menu_json_updated';
        
        if (get_transient($transient_key)) {
            return;
        }
        
        parusweb_generate_menu_attributes_json();
        set_transient($transient_key, true, 5 * MINUTE_IN_SECONDS);
    }
}
add_action('edited_term', 'parusweb_regenerate_on_term_update', 10, 3);
add_action('created_term', 'parusweb_regenerate_on_term_update', 10, 3);

// ============================================================================
// БЛОК 4: АДМИН ИНТЕРФЕЙС
// ============================================================================

/**
 * Добавление кнопки генерации JSON в админ меню
 */
function parusweb_add_menu_attributes_admin_page() {
    add_submenu_page(
        'tools.php',
        'Атрибуты меню',
        'Атрибуты меню',
        'manage_options',
        'menu-attributes-generator',
        'parusweb_menu_attributes_admin_page'
    );
}
add_action('admin_menu', 'parusweb_add_menu_attributes_admin_page');

/**
 * HTML страницы генератора JSON
 */
function parusweb_menu_attributes_admin_page() {
    ?>
    <div class="wrap">
        <h1>Генератор атрибутов для мега-меню</h1>
        
        <p>Этот инструмент создаёт JSON файл с атрибутами товаров для использования в мега-меню.</p>
        
        <p>
            <button id="generate-menu-json" class="button button-primary">
                Сгенерировать menu_attributes.json
            </button>
        </p>
        
        <div id="generation-result" style="margin-top: 20px;"></div>
        
        <h2>Информация</h2>
        <ul>
            <li><strong>Файл:</strong> /menu_attributes.json</li>
            <li><strong>Автообновление:</strong> Да (при сохранении товаров/категорий)</li>
            <li><strong>Использование:</strong> Виджеты .widget_layered_nav с data-атрибутами</li>
            <li><strong>Поддержка родительских категорий:</strong> Да (агрегация из дочерних)</li>
        </ul>
        
        <h2>Пример использования</h2>
        <pre>&lt;div class="widget_layered_nav" data-attribute="pa_sortporody" data-category="pilomaterial"&gt;&lt;/div&gt;</pre>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#generate-menu-json').on('click', function() {
            const $button = $(this);
            const $result = $('#generation-result');
            
            $button.prop('disabled', true).text('Генерация...');
            $result.html('<p>Генерация JSON файла...</p>');
            
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'generate_menu_attributes'
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
                    } else {
                        $result.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                    }
                },
                error: function() {
                    $result.html('<div class="notice notice-error"><p>Ошибка AJAX запроса</p></div>');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Сгенерировать menu_attributes.json');
                }
            });
        });
    });
    </script>
    <?php
}

// ============================================================================
// БЛОК 5: ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// ============================================================================

/**
 * Проверка существования JSON файла
 * 
 * @return bool true если файл существует
 */
function parusweb_menu_json_exists() {
    return file_exists(ABSPATH . 'menu_attributes.json');
}

/**
 * Получение времени последнего обновления JSON файла
 * 
 * @return int|false Timestamp или false
 */
function parusweb_menu_json_last_modified() {
    $file_path = ABSPATH . 'menu_attributes.json';
    
    if (file_exists($file_path)) {
        return filemtime($file_path);
    }
    
    return false;
}