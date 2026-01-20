<?php
/**
 * ============================================================================
 * МОДУЛЬ КОЛЕРОВКИ ЛКМ
 * ============================================================================
 * 
 * Интеграция с существующей системой схем покраски.
 * Использует папки с изображениями цветов для визуализации.
 * 
 * @package ParusWeb_Functions
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

// ============================================================================
// КОНСТАНТЫ
// ============================================================================

// ID категорий ЛКМ
define('LKM_CATEGORY_IDS', [81, 82, 83, 84, 85, 86]);

// Соответствие типов ЛКМ и названий папок RML
define('LKM_TYPE_TO_RML_FOLDER', [
    'Укрывной ЛКМ' => 'ukryvnaya',
    'Лак' => 'lak',
    'Гидромасло' => 'gidromaslo',
    'Пропитка' => 'propitka',
    'Воск' => 'vosk',
    'Грунт' => 'grunt',
    'Герметик для торцов' => 'germetik',
    'Лазурь' => 'lazur',
    'Винтаж' => 'vintaj',
]);

// ============================================================================
// БЛОК 1: ACF ПОЛЯ ДЛЯ СХЕМ КОЛЕРОВКИ
// ============================================================================

/**
 * Регистрация ACF полей для схем колеровки ЛКМ
 */
function lkm_tinting_register_acf_fields() {
    if (!function_exists('acf_add_local_field_group')) {
        return;
    }
    
    // Настройки для категории ЛКМ (определение схем покраски)
    acf_add_local_field_group([
        'key' => 'group_lkm_tinting_category',
        'title' => 'Схемы колеровки ЛКМ',
        'fields' => [
            [
                'key' => 'field_lkm_tinting_schemes',
                'label' => 'Доступные схемы колеровки',
                'name' => 'lkm_tinting_schemes',
                'type' => 'repeater',
                'instructions' => 'Настройте схемы колеровки с папками цветов. Схемы автоматически применяются к товарам по атрибуту "Тип".',
                'layout' => 'table',
                'button_label' => 'Добавить схему',
                'sub_fields' => [
                    [
                        'key' => 'field_scheme_name',
                        'label' => 'Название схемы',
                        'name' => 'scheme_name',
                        'type' => 'text',
                        'required' => 1,
                        'wrapper' => ['width' => '30'],
                        'placeholder' => '!Укрывная',
                        'instructions' => 'Должно совпадать с именем папки в pm-colors/',
                    ],
                    [
                        'key' => 'field_scheme_type',
                        'label' => 'Тип ЛКМ',
                        'name' => 'scheme_type',
                        'type' => 'select',
                        'required' => 1,
                        'wrapper' => ['width' => '30'],
                        'choices' => [
                            'Укрывной ЛКМ' => 'Укрывной ЛКМ',
                            'Лак' => 'Лак',
                            'Гидромасло' => 'Гидромасло',
                            'Пропитка' => 'Пропитка',
                            'Воск' => 'Воск',
                            'Грунт' => 'Грунт',
                            'Герметик для торцов' => 'Герметик для торцов',
                            'Лазурь' => 'Лазурь',
                        ],
                        'default_value' => 'Укрывной ЛКМ',
                    ],
                    [
                        'key' => 'field_scheme_cost',
                        'label' => 'Стоимость колеровки',
                        'name' => 'scheme_cost',
                        'type' => 'number',
                        'required' => 1,
                        'wrapper' => ['width' => '20'],
                        'default_value' => 500,
                        'min' => 0,
                        'step' => 50,
                        'append' => '₽',
                    ],
                    [
                        'key' => 'field_scheme_folder',
                        'label' => 'Папка RML с цветами',
                        'name' => 'scheme_folder',
                        'type' => 'text',
                        'required' => 1,
                        'wrapper' => ['width' => '20'],
                        'placeholder' => 'ukryvnaya',
                        'instructions' => 'Slug папки RML (propitka, ukryvnaya, lak и т.п.)',
                    ],
                ],
            ],
        ],
        'location' => [
            [
                [
                    'param' => 'taxonomy',
                    'operator' => '==',
                    'value' => 'product_cat',
                ],
            ],
        ],
        'menu_order' => 25,
    ]);
    
    // Настройки для товаров ЛКМ (переопределение схем)
    acf_add_local_field_group([
        'key' => 'group_lkm_tinting_product',
        'title' => 'Индивидуальные схемы колеровки',
        'fields' => [
            [
                'key' => 'field_lkm_use_custom_schemes',
                'label' => 'Использовать индивидуальные схемы',
                'name' => 'lkm_use_custom_schemes',
                'type' => 'true_false',
                'instructions' => 'Включите для переопределения схем колеровки для этого товара',
                'ui' => 1,
                'default_value' => 0,
            ],
            [
                'key' => 'field_lkm_custom_schemes',
                'label' => 'Схемы колеровки',
                'name' => 'lkm_custom_schemes',
                'type' => 'repeater',
                'instructions' => 'Индивидуальные схемы для этого товара',
                'conditional_logic' => [
                    [
                        [
                            'field' => 'field_lkm_use_custom_schemes',
                            'operator' => '==',
                            'value' => '1',
                        ],
                    ],
                ],
                'layout' => 'table',
                'button_label' => 'Добавить схему',
                'sub_fields' => [
                    [
                        'key' => 'field_custom_scheme_name',
                        'label' => 'Название схемы',
                        'name' => 'scheme_name',
                        'type' => 'text',
                        'required' => 1,
                        'wrapper' => ['width' => '40'],
                    ],
                    [
                        'key' => 'field_custom_scheme_cost',
                        'label' => 'Стоимость',
                        'name' => 'scheme_cost',
                        'type' => 'number',
                        'required' => 1,
                        'wrapper' => ['width' => '30'],
                        'min' => 0,
                        'step' => 50,
                        'append' => '₽',
                    ],
                    [
                        'key' => 'field_custom_scheme_folder',
                        'label' => 'Папка',
                        'name' => 'scheme_folder',
                        'type' => 'text',
                        'required' => 1,
                        'wrapper' => ['width' => '30'],
                    ],
                ],
            ],
        ],
        'location' => [
            [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'product',
                ],
                [
                    'param' => 'product_cat',
                    'operator' => '==',
                    'value' => '81',
                ],
            ],
            [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'product',
                ],
                [
                    'param' => 'product_cat',
                    'operator' => '==',
                    'value' => '82',
                ],
            ],
            [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'product',
                ],
                [
                    'param' => 'product_cat',
                    'operator' => '==',
                    'value' => '83',
                ],
            ],
            [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'product',
                ],
                [
                    'param' => 'product_cat',
                    'operator' => '==',
                    'value' => '84',
                ],
            ],
            [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'product',
                ],
                [
                    'param' => 'product_cat',
                    'operator' => '==',
                    'value' => '85',
                ],
            ],
            [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'product',
                ],
                [
                    'param' => 'product_cat',
                    'operator' => '==',
                    'value' => '86',
                ],
            ],
        ],
        'menu_order' => 25,
    ]);
}
add_action('acf/init', 'lkm_tinting_register_acf_fields');

// ============================================================================
// БЛОК 2: ПОЛУЧЕНИЕ СХЕМ КОЛЕРОВКИ
// ============================================================================

/**
 * Проверка, доступна ли колеровка для товара
 * 
 * @param int $product_id ID товара
 * @return bool
 */
function lkm_tinting_is_available($product_id) {
    // Проверяем, что товар в категории ЛКМ
    if (!has_term(LKM_CATEGORY_IDS, 'product_cat', $product_id)) {
        return false;
    }
    
    // Проверяем, есть ли схема для этого товара
    $scheme = lkm_tinting_get_schemes($product_id);
    return $scheme !== null;
}

/**
 * Получение схем колеровки для товара
 * Выбирает схему автоматически по атрибуту "Тип"
 * 
 * @param int $product_id ID товара
 * @return array|null Схема или null
 */
function lkm_tinting_get_schemes($product_id) {
    // Уровень 1: Индивидуальные схемы товара
    $use_custom = get_field('lkm_use_custom_schemes', $product_id);
    if ($use_custom) {
        $custom_schemes = get_field('lkm_custom_schemes', $product_id);
        if (!empty($custom_schemes)) {
            // Если есть кастомные схемы, пытаемся найти по атрибуту Тип
            $product = wc_get_product($product_id);
            if ($product) {
                $type_attribute = $product->get_attribute('Тип');
                if ($type_attribute) {
                    foreach ($custom_schemes as $scheme) {
                        // Пытаемся найти схему, название которой содержит тип или совпадает
                        if (stripos($scheme['scheme_name'], $type_attribute) !== false) {
                            return $scheme;
                        }
                    }
                }
            }
            // Если не нашли по типу, возвращаем первую схему
            return !empty($custom_schemes[0]) ? $custom_schemes[0] : null;
        }
    }
    
    // Уровень 2: Автоматический выбор по атрибуту "Тип" из категории
    $product = wc_get_product($product_id);
    if (!$product) {
        return null;
    }
    
    $type_attribute = $product->get_attribute('Тип');
    if (!$type_attribute) {
        return null;
    }
    
    $all_schemes = lkm_tinting_get_category_schemes($product_id);
    
    // Ищем схему, где scheme_type совпадает с атрибутом Тип
    foreach ($all_schemes as $scheme) {
        if (isset($scheme['scheme_type']) && $scheme['scheme_type'] === $type_attribute) {
            return $scheme;
        }
    }
    
    return null;
}

/**
 * Получение схем из категории
 * 
 * @param int $product_id ID товара
 * @return array
 */
function lkm_tinting_get_category_schemes($product_id) {
    $product_categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
    
    if (is_wp_error($product_categories) || empty($product_categories)) {
        return [];
    }
    
    // Сортируем по глубине (более конкретные приоритетнее)
    usort($product_categories, function($a, $b) {
        $depth_a = count(get_ancestors($a, 'product_cat'));
        $depth_b = count(get_ancestors($b, 'product_cat'));
        return $depth_b - $depth_a;
    });
    
    foreach ($product_categories as $cat_id) {
        $schemes = get_field('lkm_tinting_schemes', 'product_cat_' . $cat_id);
        if (!empty($schemes)) {
            return $schemes;
        }
    }
    
    return [];
}

/**
 * Получение цветов из папки RML медиабиблиотеки
 * 
 * @param string $rml_folder_slug Slug папки RML (propitka, ukryvnaya, lak и т.п.)
 * @return array Массив цветов ['filename' => ['url' => ..., 'id' => ...]]
 */
function lkm_tinting_get_scheme_colors($rml_folder_slug) {
    global $wpdb;
    
    // Получаем ID папки RML по slug
    $folder_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}realmedialibrary 
         WHERE slug = %s AND type IN (0, 1) 
         LIMIT 1",
        $rml_folder_slug
    ));
    
    if (!$folder_id) {
        return [];
    }
    
    // Получаем все attachment ID из этой папки
    $attachment_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT attachment 
         FROM {$wpdb->prefix}realmedialibrary_posts 
         WHERE fid = %d
         ORDER BY attachment ASC",
        $folder_id
    ));
    
    if (empty($attachment_ids)) {
        return [];
    }
    
    $colors = [];
    
    foreach ($attachment_ids as $attachment_id) {
        // Проверяем что это изображение
        if (!wp_attachment_is_image($attachment_id)) {
            continue;
        }
        
        $image_url = wp_get_attachment_url($attachment_id);
        if (!$image_url) {
            continue;
        }
        
        // Получаем имя файла без расширения
        $filename = pathinfo(get_attached_file($attachment_id), PATHINFO_FILENAME);
        
        // Можно получить alt или title из attachment
        $alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        $title = get_the_title($attachment_id);
        
        $display_name = $alt ?: ($title ?: $filename);
        
        $colors[$filename] = [
            'id' => $attachment_id,
            'url' => $image_url,
            'name' => $display_name,
            'filename' => $filename,
        ];
    }
    
    // Сортируем по имени файла
    ksort($colors);
    
    return $colors;
}

/**
 * Получение ID папки RML по slug
 * 
 * @param string $slug Slug папки
 * @return int|false ID папки или false
 */
function lkm_get_rml_folder_id($slug) {
    global $wpdb;
    
    $folder_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}realmedialibrary 
         WHERE slug = %s AND type IN (0, 1) 
         LIMIT 1",
        $slug
    ));
    
    return $folder_id ? intval($folder_id) : false;
}

// ============================================================================
// БЛОК 3: ВЫВОД СЕЛЕКТОРА КОЛЕРОВКИ
// ============================================================================

/**
 * Отображение селектора колеровки на странице товара
 */
function lkm_tinting_display_selector() {
    global $product;
    
    if (!$product) {
        return;
    }
    
    $product_id = $product->get_id();
    
    if (!lkm_tinting_is_available($product_id)) {
        return;
    }
    
    $scheme = lkm_tinting_get_schemes($product_id);
    
    if (!$scheme) {
        return;
    }
    
    // Получаем цвета сразу
    $colors = lkm_tinting_get_scheme_colors($scheme['scheme_folder']);
    
    if (empty($colors)) {
        return;
    }
    
    ?>
    <div class="lkm-tinting-wrapper">
        <h3 class="tinting-title">Колеровка ЛКМ - <?php echo esc_html($scheme['scheme_name']); ?></h3>
        
        <!-- Плитка цветов -->
        <div class="tinting-colors-block">
            <h4>Выберите цвет:</h4>
            <div class="tinting-colors-grid">
                <?php foreach ($colors as $filename => $color_data): ?>
                    <div class="color-tile-mini" 
                         data-filename="<?php echo esc_attr($color_data['filename']); ?>"
                         data-url="<?php echo esc_attr($color_data['url']); ?>"
                         data-id="<?php echo esc_attr($color_data['id']); ?>"
                         data-name="<?php echo esc_attr($color_data['name']); ?>"
                         title="<?php echo esc_attr($color_data['name']); ?>"
                         style="background-image: url('<?php echo esc_url($color_data['url']); ?>');">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Превью выбранного цвета -->
        <div class="tinting-preview-block" style="display: none;">
            <div class="preview-header">
                <div class="selected-color-info">
                    <span class="selected-color-name"></span>
                    <span class="tinting-cost-badge"></span>
                </div>
                <button type="button" class="reset-color-btn">Сбросить выбор</button>
            </div>
            <div class="tinting-color-image-wrapper">
                <img src="" alt="Цвет" class="tinting-color-image">
            </div>
        </div>
        
        <!-- Скрытые поля -->
        <input type="hidden" name="lkm_tinting_enabled" value="0" />
        <input type="hidden" name="lkm_scheme_name" value="<?php echo esc_attr($scheme['scheme_name']); ?>" />
        <input type="hidden" name="lkm_scheme_folder" value="<?php echo esc_attr($scheme['scheme_folder']); ?>" />
        <input type="hidden" name="lkm_color_filename" value="" />
        <input type="hidden" name="lkm_color_name" value="" />
        <input type="hidden" name="lkm_color_image_url" value="" />
        <input type="hidden" name="lkm_color_image_id" value="" />
        <input type="hidden" name="lkm_tinting_cost" value="<?php echo esc_attr($scheme['scheme_cost']); ?>" />
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        const $wrapper = $('.lkm-tinting-wrapper');
        const $previewBlock = $('.tinting-preview-block');
        const $selectedColorName = $('.selected-color-name');
        const $colorImage = $('.tinting-color-image');
        const $resetBtn = $('.reset-color-btn');
        
        let isColorSelected = false;
        
        const schemeName = '<?php echo esc_js($scheme['scheme_name']); ?>';
        const schemeCost = '<?php echo esc_js($scheme['scheme_cost']); ?>';
        
        // ====================================================================
        // ФУНКЦИЯ ОБНОВЛЕНИЯ ЦЕНЫ С УЧЕТОМ ТАРЫ И КОЛЕРОВКИ
        // ====================================================================
        
        function updatePriceWithTinting() {
            const $taraSelect = $('#tara');
            if (!$taraSelect.length || !$taraSelect.val()) {
                console.log('LKM Tinting: Тара не выбрана');
                return;
            }
            
            const selectedVolume = parseFloat($taraSelect.val());
            const basePricePerLiter = parseFloat($taraSelect.data('basePrice')) || 0;
            
            if (!selectedVolume || !basePricePerLiter) {
                console.log('LKM Tinting: Нет данных о таре');
                return;
            }
            
            // Цена = цена за литр × объем
            let basePrice = basePricePerLiter * selectedVolume;
            
            // Скидка 10% при объеме >= 9л
            if (selectedVolume >= 9) {
                basePrice *= 0.9;
            }
            
            console.log('LKM Tinting: Базовая цена (тара) =', basePrice);
            
            // Добавляем колеровку если выбрана (за литр!)
            let finalPrice = basePrice;
            let tintingCost = 0;
            
            if (isColorSelected && schemeCost) {
                // ВАЖНО: Колеровка = стоимость_за_литр × объем
                tintingCost = parseFloat(schemeCost) * selectedVolume;
                finalPrice = basePrice + tintingCost;
                console.log('LKM Tinting: Добавлена колеровка', schemeCost, '₽/л × ', selectedVolume, 'л =', tintingCost, ', итого =', finalPrice);
            }
            
            // Обновляем карточки оплаты
            // Модуль non-cash-price автоматически обновит отображаемую цену товара
            if (typeof window.updatePaymentCardsFromCalculator === 'function') {
                console.log('LKM Tinting: Обновляем карточки оплаты с ценой =', finalPrice);
                window.updatePaymentCardsFromCalculator(finalPrice);
            }
        }
        
        // Добавление текста о колеровке к отображаемой цене
        function addTintingTextToPrice() {
            if (!isColorSelected || !schemeCost) {
                return;
            }
            
            setTimeout(function() {
                const $taraSelect = $('#tara');
                if (!$taraSelect.length || !$taraSelect.val()) {
                    console.log('LKM Tinting: Тара не выбрана, текст не добавлен');
                    return;
                }
                
                const selectedVolume = parseFloat($taraSelect.val());
                const tintingPerLiter = parseFloat(schemeCost);
                
                // Получаем текущую наценку от типа оплаты
                const currentMarkup = typeof window.getCurrentPaymentMarkup === 'function' 
                    ? window.getCurrentPaymentMarkup() 
                    : 0;
                
                // Считаем итоговую стоимость колеровки
                let tintingTotal = tintingPerLiter * selectedVolume;
                
                // Применяем наценку если есть
                if (currentMarkup > 0) {
                    tintingTotal = tintingTotal * (1 + currentMarkup / 100);
                }
                
                // Обновляем бейдж с ценой в превью
                updateTintingBadge(tintingTotal);
                
                const $priceSpan = $('.woocommerce-Price-amount').first();
                if ($priceSpan.length) {
                    let currentHTML = $priceSpan.html();
                    
                    // Убираем "/литр" если есть (тара уже выбрана)
                    currentHTML = currentHTML.replace('</bdi>/литр', '</bdi>');
                    currentHTML = currentHTML.replace('/литр', '');
                    currentHTML = currentHTML.replace('за литр', '');
                    
                    // Убираем старый текст о колеровке если есть
                    currentHTML = currentHTML.replace(/<br><small[^>]*>.*?колеровку.*?<\/small>/gi, '');
                    
                    // Формируем новый текст
                    const tintingText = '<br><small style="font-size: 12px; color: #4CAF50; font-weight: 600;">' + 
                        '(включая +' + Math.round(tintingTotal) + ' ₽ за колеровку)</small>';
                    
                    $priceSpan.html(currentHTML + tintingText);
                    console.log('LKM Tinting: Добавлен текст о колеровке:', tintingTotal + '₽');
                }
            }, 100);
        }
        
        // Обновление бейджа с ценой колеровки
        function updateTintingBadge(tintingTotal) {
            const $badge = $('.tinting-cost-badge');
            if ($badge.length && tintingTotal > 0) {
                $badge.text('+' + Math.round(tintingTotal) + ' ₽').show();
            } else {
                $badge.hide();
            }
        }
        
        // ====================================================================
        // ОБРАБОТЧИКИ СОБЫТИЙ
        // ====================================================================
        
        // Наведение на плитку цвета
        $('.color-tile-mini').on('mouseenter', function() {
            if (!isColorSelected) {
                const $tile = $(this);
                showPreview($tile.data('name'), $tile.data('url'));
            }
        });
        
        // Уход мыши с плитки
        $('.color-tile-mini').on('mouseleave', function() {
            if (!isColorSelected) {
                $previewBlock.hide();
            }
        });
        
        // Клик по плитке цвета
        $('.color-tile-mini').on('click', function() {
            const $tile = $(this);
            selectColor($tile);
        });
        
        // Показать превью
        function showPreview(colorName, imageUrl) {
            $selectedColorName.text(schemeName + ' - ' + colorName);
            $colorImage.attr('src', imageUrl);
            $previewBlock.show();
        }
        
        // Выбрать цвет
        function selectColor($tile) {
            $('.color-tile-mini').removeClass('selected');
            $tile.addClass('selected');
            
            isColorSelected = true;
            
            const colorName = $tile.data('name');
            const imageUrl = $tile.data('url');
            
            showPreview(colorName, imageUrl);
            
            // Обновляем скрытые поля
            $wrapper.find('input[name="lkm_tinting_enabled"]').val('1');
            $wrapper.find('input[name="lkm_color_filename"]').val($tile.data('filename'));
            $wrapper.find('input[name="lkm_color_name"]').val(colorName);
            $wrapper.find('input[name="lkm_color_image_url"]').val(imageUrl);
            $wrapper.find('input[name="lkm_color_image_id"]').val($tile.data('id'));
            
            console.log('LKM Tinting: Выбран цвет, скрытые поля:');
            console.log('  lkm_tinting_enabled:', $wrapper.find('input[name="lkm_tinting_enabled"]').val());
            console.log('  lkm_tinting_cost:', $wrapper.find('input[name="lkm_tinting_cost"]').val());
            console.log('  lkm_color_name:', colorName);
            
            // Обновляем цену с учетом колеровки
            updatePriceWithTinting();
            
            // Добавляем текст о колеровке
            addTintingTextToPrice();
            
            // Прокрутка к превью
            $('html, body').animate({
                scrollTop: $previewBlock.offset().top - 100
            }, 500);
        }
        
        // Сброс выбора
        $resetBtn.on('click', function() {
            resetSelection();
        });
        
        function resetSelection() {
            $('.color-tile-mini').removeClass('selected');
            $previewBlock.hide();
            isColorSelected = false;
            
            $wrapper.find('input[name="lkm_tinting_enabled"]').val('0');
            $wrapper.find('input[name="lkm_color_filename"]').val('');
            $wrapper.find('input[name="lkm_color_name"]').val('');
            $wrapper.find('input[name="lkm_color_image_url"]').val('');
            $wrapper.find('input[name="lkm_color_image_id"]').val('');
            
            // Скрываем бейдж
            updateTintingBadge(0);
            
            // После сброса обновляем цену
            updatePriceWithTinting();
        }
        
        // ====================================================================
        // СЛУШАТЕЛИ СОБЫТИЙ ДЛЯ ИНТЕГРАЦИИ
        // ====================================================================
        
        // Слушаем изменение тары
        $(document).on('change', '#tara', function() {
            console.log('LKM Tinting: Изменена тара');
            updatePriceWithTinting();
            addTintingTextToPrice();
        });
        
        // Слушаем изменение типа оплаты
        $(document).on('click', '.payment-option', function() {
            console.log('LKM Tinting: Изменен тип оплаты');
            setTimeout(function() {
                updatePriceWithTinting();
                addTintingTextToPrice();
            }, 150);
        });
        
        // Инициализация при загрузке если тара уже выбрана
        setTimeout(function() {
            const $taraSelect = $('#tara');
            if ($taraSelect.length && $taraSelect.val()) {
                console.log('LKM Tinting: Инициализация - тара выбрана');
                updatePriceWithTinting();
                addTintingTextToPrice();
            }
        }, 500);
        
        // Логирование при отправке формы в корзину (capture phase)
        const cartForm = document.querySelector('form.cart');
        if (cartForm) {
            cartForm.addEventListener('submit', function(e) {
                console.log('=== FORM SUBMIT - LKM TINTING ===');
                const formData = new FormData(cartForm);
                console.log('lkm_tinting_enabled:', formData.get('lkm_tinting_enabled'));
                console.log('lkm_tinting_cost:', formData.get('lkm_tinting_cost'));
                console.log('lkm_color_name:', formData.get('lkm_color_name'));
                console.log('payment_type:', formData.get('payment_type'));
                console.log('payment_markup:', formData.get('payment_markup'));
                console.log('tara:', formData.get('tara'));
                console.log('================================');
            }, true);
        }
        
        // ====================================================================
        // ИНТЕГРАЦИЯ С МОДУЛЕМ NON-CASH-PRICE
        // ====================================================================
        
        // Перехватываем функцию updateDisplayPrice из non-cash-price.php
        setTimeout(function() {
            const originalUpdateDisplayPrice = window.updateDisplayedProductPrice;
            
            if (originalUpdateDisplayPrice) {
                window.updateDisplayedProductPrice = function(price, isCalculated) {
                    console.log('LKM Tinting: Перехвачено updateDisplayedProductPrice');
                    
                    // Вызываем оригинальную функцию
                    const result = originalUpdateDisplayPrice.call(window, price, isCalculated);
                    
                    // Добавляем текст о колеровке после обновления цены
                    addTintingTextToPrice();
                    
                    return result;
                };
                
                console.log('LKM Tinting: Успешно перехвачена updateDisplayedProductPrice');
            }
        }, 600);
    });
    </script>
    <?php
}
add_action('woocommerce_before_add_to_cart_button', 'lkm_tinting_display_selector', 25);

// ============================================================================
// БЛОК 4: СОХРАНЕНИЕ В КОРЗИНУ И ЗАКАЗ
// ============================================================================

/**
 * Сохранение данных колеровки в корзину
 */
function lkm_tinting_add_to_cart($cart_item_data, $product_id) {
    if (!empty($_POST['lkm_tinting_enabled']) && $_POST['lkm_tinting_enabled'] === '1') {
        $tinting_data = [
            'scheme_name' => sanitize_text_field($_POST['lkm_scheme_name'] ?? ''),
            'scheme_folder' => sanitize_text_field($_POST['lkm_scheme_folder'] ?? ''),
            'color_filename' => sanitize_text_field($_POST['lkm_color_filename'] ?? ''),
            'color_name' => sanitize_text_field($_POST['lkm_color_name'] ?? ''),
            'color_image_url' => esc_url_raw($_POST['lkm_color_image_url'] ?? ''),
            'color_image_id' => intval($_POST['lkm_color_image_id'] ?? 0),
            'cost' => floatval($_POST['lkm_tinting_cost'] ?? 0),
        ];
        
        $cart_item_data['lkm_tinting'] = $tinting_data;
        
        error_log("=== LKM TINTING ADD TO CART ===");
        error_log("Product ID: {$product_id}");
        error_log("Tinting data: " . json_encode($tinting_data));
        error_log("Tinting cost: {$tinting_data['cost']}₽");
        
        // Проверяем payment_markup
        if (isset($_POST['payment_markup'])) {
            error_log("Payment markup from POST: {$_POST['payment_markup']}%");
        }
        if (isset($_POST['payment_type'])) {
            error_log("Payment type from POST: {$_POST['payment_type']}");
        }
    }
    
    return $cart_item_data;
}
add_filter('woocommerce_add_cart_item_data', 'lkm_tinting_add_to_cart', 10, 2);

/**
 * Отображение данных колеровки в корзине
 */
function lkm_tinting_cart_item_data($item_data, $cart_item) {
    if (isset($cart_item['lkm_tinting'])) {
        $tinting = $cart_item['lkm_tinting'];
        
        $display_value = '<img src="' . esc_url($tinting['color_image_url']) . '" 
                               style="width:40px;height:40px;object-fit:cover;border:2px solid #333;border-radius:4px;vertical-align:middle;margin-right:8px;">';
        $display_value .= esc_html($tinting['scheme_name']) . ' - ' . esc_html($tinting['color_name']);
        
        $item_data[] = [
            'key' => 'Колеровка',
            'value' => $display_value,
            'display' => $display_value,
        ];
    }
    
    return $item_data;
}
add_filter('woocommerce_get_item_data', 'lkm_tinting_cart_item_data', 10, 2);

/**
 * Добавление стоимости колеровки напрямую к цене товара в корзине
 * ВАЖНО: Выполняется ДО применения наценки от типа оплаты
 */
function lkm_tinting_add_to_product_price($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }
    
    error_log("=== LKM TINTING ADD TO PRICE ===");
    
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        if (isset($cart_item['lkm_tinting']) && !empty($cart_item['lkm_tinting']['cost'])) {
            $product = $cart_item['data'];
            $current_price = floatval($product->get_price());
            $tinting_cost_per_liter = floatval($cart_item['lkm_tinting']['cost']);
            
            // ВАЖНО: Получаем объем (литры) из tara
            $volume = isset($cart_item['tara']) ? floatval($cart_item['tara']) : 1;
            
            // Колеровка = цена_за_литр × объем
            $tinting_cost_total = $tinting_cost_per_liter * $volume;
            
            // Добавляем колеровку к цене товара
            $new_price = $current_price + $tinting_cost_total;
            $product->set_price($new_price);
            
            error_log("Product {$cart_item['product_id']}: {$current_price}₽ + ({$tinting_cost_per_liter}₽/л × {$volume}л = {$tinting_cost_total}₽) = {$new_price}₽");
        }
    }
    
    error_log("=== END LKM TINTING ===");
}
// Приоритет 50 - ДО non-cash-price (100), чтобы наценка применилась к (товар + колеровка)
add_action('woocommerce_before_calculate_totals', 'lkm_tinting_add_to_product_price', 50, 1);

/**
 * Сохранение данных колеровки в заказ
 */
function lkm_tinting_save_to_order($item, $cart_item_key, $values, $order) {
    if (isset($values['lkm_tinting'])) {
        $tinting = $values['lkm_tinting'];
        
        $display = '<img src="' . esc_url($tinting['color_image_url']) . '" 
                         style="width:40px;height:40px;object-fit:cover;border:2px solid #333;border-radius:4px;vertical-align:middle;margin-right:8px;">';
        $display .= esc_html($tinting['scheme_name']) . ' - ' . esc_html($tinting['color_name']);
        
        $item->add_meta_data('Колеровка', $display, true);
        $item->add_meta_data('_lkm_tinting_data', json_encode($tinting), true);
        $item->add_meta_data('_lkm_color_image_url', $tinting['color_image_url'], true);
        $item->add_meta_data('Код цвета', $tinting['color_filename'], true);
    }
}
add_action('woocommerce_checkout_create_order_line_item', 'lkm_tinting_save_to_order', 10, 4);

// ============================================================================
// БЛОК 5: СТИЛИ
// ============================================================================

/**
 * Подключение стилей
 */
function lkm_tinting_enqueue_styles() {
    if (!is_product()) {
        return;
    }
    
    $product_id = get_the_ID();
    
    if (!lkm_tinting_is_available($product_id)) {
        return;
    }
    
    add_action('wp_head', 'lkm_tinting_output_styles', 100);
}
add_action('wp_enqueue_scripts', 'lkm_tinting_enqueue_styles');

/**
 * Вывод стилей
 */
function lkm_tinting_output_styles() {
    ?>
    <style>
    .lkm-tinting-wrapper {
        margin: 10px 0;
    }
    
    .tinting-title {
        margin: 0 0 15px 0;
        font-size: 20px;
        font-weight: 700;
        border-bottom: 1px solid #ddd;
        padding-bottom: 8px;
    }
    
    .tinting-colors-block h4 {
        margin: 0 0 10px 0;
        font-size: 15px;
        font-weight: 600;
        color: #555;
    }
    
    .tinting-colors-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 3px;
        justify-content:center;
    }
    
    .color-tile-mini {
        width: 35px;
        height: 25px;
        cursor: pointer;
        border: 1px solid rgba(0,0,0,0.2);
        background-size: cover;
        background-position: center;
        transition: all 0.15s ease;
    }
    
    .color-tile-mini:hover {
        transform: scale(1.3);
        z-index: 10;
        box-shadow: 0 3px 10px rgba(0,0,0,0.4);
        border: 2px solid #333;
    }
    
    .color-tile-mini.selected {
        border: 2px solid #4CAF50;
        box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.3);
        transform: scale(1.15);
    }
    
    .tinting-preview-block {
        margin-top: 15px;
        padding: 12px;
        background: #f0f8f0;
        border: 1px solid #4CAF50;
    }
    
    .preview-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        padding-bottom: 8px;
        border-bottom: 1px solid #ddd;
    }
    
    .selected-color-info {
        font-size: 16px;
        font-weight: 600;
        color: #333;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .tinting-cost-badge {
        display: inline-block;
        padding: 3px 10px;
        background: #4CAF50;
        color: white;
        font-size: 13px;
        font-weight: 700;
    }
    
    .reset-color-btn {
        padding: 6px 12px;
        background: #fff;
        color: #333;
        border: 1px solid #ddd;
        cursor: pointer;
        font-size: 13px;
        transition: all 0.2s;
    }
    
    .reset-color-btn:hover {
        background: #f5f5f5;
        border-color: #999;
    }
    
    .tinting-color-image-wrapper {
        text-align: center;
    }
    
    .tinting-color-image {
        width: 100%;
        height: auto;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    @media (max-width: 768px) {
        .color-tile-mini {
            width: 20px;
            height: 20px;
        }
        
        .preview-header {
            flex-direction: column;
            gap: 10px;
            align-items: flex-start;
        }
        
        .reset-color-btn {
            width: 100%;
        }
    }
    </style>
    <?php
}