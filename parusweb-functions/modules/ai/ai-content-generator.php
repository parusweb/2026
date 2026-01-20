<?php
/**
 * ============================================================================
 * AI CONTENT GENERATOR v11 - С ИЕРАРХИЕЙ ПРОМПТОВ
 * ============================================================================
 * 
 * Возможности:
 * - Генерация текстов через GPT-3.5/4
 * - Генерация изображений через DALL-E 3
 * - Иерархия промптов: Товар → Категория → Глобальные
 * - Настройки для каждого типа контента
 * - Все атрибуты товара доступны как плейсхолдеры
 * 
 * @version 11.0.0
 */

if (!defined('ABSPATH')) exit;

// ============================================================================
// НАСТРОЙКИ ПО УМОЛЧАНИЮ
// ============================================================================

function parusweb_ai_get_default_settings() {
    return [
        // Промпты
        'prompt_excerpt' => "Напиши краткое описание товара для интернет-магазина строительных материалов (1-2 предложения, до {max_length} символов):\n\nТовар: {title}\nКатегория: {category}\n{attributes}\n\nОписание должно быть продающим, информативным и содержать ключевые характеристики.",
        
        'prompt_description' => "Напиши подробное описание товара для интернет-магазина строительных материалов ({min_words}-{max_words} слов):\n\nТовар: {title}\nКатегория: {category}\nХарактеристики: {attributes}\n\nСтруктура описания:\n1. Вводный абзац - что это за товар и для чего используется\n2. Основные характеристики и преимущества\n3. Область применения\n4. Советы по использованию или монтажу\n\nИспользуй HTML-форматирование: <p>, <h2>, <ul>, <li>. Пиши естественно, без излишней рекламности.",
        
        'prompt_seo_title' => "Создай SEO-заголовок для товара (до {max_length} символов):\n\nТовар: {title}\nКатегория: {category}\n\nЗаголовок должен быть привлекательным, содержать ключевые слова, побуждать к клику.\nВерни ТОЛЬКО заголовок без кавычек и пояснений.",
        
        'prompt_meta_description' => "Создай мета-описание для товара ({min_length}-{max_length} символов):\n\nТовар: {title}\nКатегория: {category}\nХарактеристики: {attributes}\n\nОписание должно содержать призыв к действию, ключевые характеристики и быть привлекательным для поисковой выдачи.\nВерни ТОЛЬКО описание без кавычек.",
        
        'prompt_focus_keyword' => "Определи основное ключевое слово для SEO (2-4 слова):\n\nТовар: {title}\nКатегория: {category}\n\nВерни ТОЛЬКО ключевое слово без пояснений.",
        
        // Длины текстов
        'excerpt_max_length' => 160,
        'excerpt_max_tokens' => 150,
        
        'description_min_words' => 300,
        'description_max_words' => 500,
        'description_max_tokens' => 1000,
        
        'seo_title_max_length' => 60,
        'seo_title_max_tokens' => 80,
        
        'meta_description_min_length' => 140,
        'meta_description_max_length' => 160,
        'meta_description_max_tokens' => 120,
        
        'focus_keyword_max_tokens' => 50,
        
        // Генерация изображений
        'image_enabled' => true,
        'image_model' => 'dall-e-3',
        'image_size' => '1024x1024',
        'image_quality' => 'standard',
        'image_style' => 'natural',
        
        'prompt_image' => "Create a professional product photograph for a building materials e-commerce store:\n\nProduct: {title}\nCategory: {category}\nSpecifications: {attributes}\n\nRequirements:\n- Professional studio photography\n- White neutral background\n- Product centered in frame\n- Good lighting without shadows\n- High resolution and detail\n- Product shown at convenient angle for customer",
        
        // AI параметры
        'ai_temperature' => 0.7,
        'ai_model' => 'gpt-3.5-turbo',
        'ai_env_id' => 'vywjfu3m',
    ];
}

function parusweb_ai_get_settings() {
    $defaults = parusweb_ai_get_default_settings();
    $saved = get_option('parusweb_ai_settings', []);
    return array_merge($defaults, $saved);
}

// ============================================================================
// ИЕРАРХИЯ ПРОМПТОВ
// ============================================================================

/**
 * Получить промпт с учётом иерархии: Товар → Категория → Глобальные
 */
function parusweb_ai_get_prompt_hierarchical($product_id, $prompt_type) {
    
    // 1. Проверяем промпт товара
    $product_prompt = get_post_meta($product_id, '_ai_prompt_' . $prompt_type, true);
    
    if (!empty($product_prompt)) {
        error_log('[AI v11] Using PRODUCT prompt for ' . $prompt_type);
        return $product_prompt;
    }
    
    // 2. Проверяем промпты категорий (от дочерних к родительским)
    $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'all']);
    
    if (!empty($categories)) {
        // Сортируем: сначала дочерние (без детей), потом родительские
        usort($categories, function($a, $b) {
            $children_a = get_term_children($a->term_id, 'product_cat');
            $children_b = get_term_children($b->term_id, 'product_cat');
            
            $has_children_a = !empty($children_a) && !is_wp_error($children_a);
            $has_children_b = !empty($children_b) && !is_wp_error($children_b);
            
            if (!$has_children_a && $has_children_b) return -1;
            if ($has_children_a && !$has_children_b) return 1;
            
            return $b->parent - $a->parent;
        });
        
        error_log('[AI v11] Categories order: ' . implode(', ', array_map(function($cat) {
            return $cat->name . ' (ID: ' . $cat->term_id . ')';
        }, $categories)));
        
        foreach ($categories as $category) {
            $cat_prompt = get_term_meta($category->term_id, '_ai_prompt_' . $prompt_type, true);
            
            if (!empty($cat_prompt)) {
                error_log('[AI v11] Using CATEGORY prompt (' . $category->name . ') for ' . $prompt_type);
                return $cat_prompt;
            }
        }
    }
    
    // 3. Глобальные настройки
    error_log('[AI v11] Using GLOBAL prompt for ' . $prompt_type);
    
    $settings = parusweb_ai_get_settings();
    return $settings['prompt_' . $prompt_type] ?? '';
}

/**
 * Получить все плейсхолдеры товара
 */
function parusweb_ai_get_product_placeholders($product_id) {
    
    $product = wc_get_product($product_id);
    
    if (!$product) {
        return [];
    }
    
    $title = $product->get_name();
    $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'names']);
    $category_text = !empty($categories) ? implode(', ', $categories) : 'строительные материалы';
    
    $attributes_all = [];
    $placeholders = [
        '{title}' => $title,
        '{category}' => $category_text,
    ];
    
    // Все атрибуты товара
    if ($product->get_attributes()) {
        foreach ($product->get_attributes() as $attribute) {
            
            $attr_name = '';
            $attr_values = [];
            
            if ($attribute->is_taxonomy()) {
                $taxonomy = $attribute->get_name();
                $terms = wp_get_post_terms($product_id, $taxonomy, ['fields' => 'names']);
                
                if (!empty($terms)) {
                    $attr_name = wc_attribute_label($taxonomy);
                    $attr_values = $terms;
                    
                    // Плейсхолдер для конкретного атрибута: {pa_sort}, {pa_poroda} и т.д.
                    $placeholder_key = '{' . $taxonomy . '}';
                    $placeholders[$placeholder_key] = implode(', ', $terms);
                    
                    error_log('[AI v11] Attribute: ' . $attr_name . ' = ' . implode(', ', $terms));
                }
            } else {
                $attr_name = $attribute->get_name();
                $options = $attribute->get_options();
                
                if (!empty($options)) {
                    $attr_values = is_array($options) ? $options : [$options];
                    
                    $placeholder_key = '{attr_' . sanitize_title($attr_name) . '}';
                    $placeholders[$placeholder_key] = implode(', ', $attr_values);
                }
            }
            
            if (!empty($attr_name) && !empty($attr_values)) {
                $attributes_all[$attr_name] = implode(', ', $attr_values);
            }
        }
    }
    
    // Строка со всеми атрибутами
    $attributes_text = '';
    foreach ($attributes_all as $name => $value) {
        $attributes_text .= "{$name}: {$value}. ";
    }
    
    $placeholders['{attributes}'] = $attributes_text;
    
    return $placeholders;
}

// ============================================================================
// ГЕНЕРАЦИЯ ЧЕРЕЗ AI ENGINE
// ============================================================================

function parusweb_ai_generate_v11($prompt, $max_tokens = 500) {
    
    error_log('[AI v11] Starting generation...');
    
    if (!class_exists('Meow_MWAI_Core') || !class_exists('Meow_MWAI_Query_Text')) {
        error_log('[AI v11] Required classes not found');
        return false;
    }
    
    try {
        global $mwai_core;
        
        $settings = parusweb_ai_get_settings();
        
        $query = new Meow_MWAI_Query_Text($prompt);
        $query->set_env_id($settings['ai_env_id']);
        $query->set_model($settings['ai_model']);
        $query->set_max_tokens($max_tokens);
        $query->set_temperature($settings['ai_temperature']);
        
        if ($mwai_core && method_exists($mwai_core, 'run_query')) {
            $reply = $mwai_core->run_query($query);
        } else {
            $core = new Meow_MWAI_Core();
            $reply = $core->run_query($query);
        }
        
        if (!$reply) {
            error_log('[AI v11] Empty reply');
            return false;
        }
        
        $result = '';
        
        if (isset($reply->result)) {
            $result = $reply->result;
        } elseif (method_exists($reply, 'get_reply')) {
            $result = $reply->get_reply();
        } elseif (isset($reply->reply)) {
            $result = $reply->reply;
        } elseif (is_string($reply)) {
            $result = $reply;
        }
        
        if (empty($result)) {
            error_log('[AI v11] Could not extract result');
            return false;
        }
        
        error_log('[AI v11] ✓ Success! Generated ' . strlen($result) . ' chars');
        return trim($result);
        
    } catch (Exception $e) {
        error_log('[AI v11] Exception: ' . $e->getMessage());
        return false;
    }
}

// ============================================================================
// ГЕНЕРАЦИЯ ИЗОБРАЖЕНИЙ DALL-E
// ============================================================================

function parusweb_ai_generate_image($prompt) {
    
    error_log('[AI v11] Generating image with DALL-E...');
    
    $settings = parusweb_ai_get_settings();
    
    $ai_options = get_option('mwai_options', []);
    $api_key = '';
    
    if (isset($ai_options['ai_envs']) && is_array($ai_options['ai_envs'])) {
        foreach ($ai_options['ai_envs'] as $env) {
            if (isset($env['id']) && $env['id'] === $settings['ai_env_id']) {
                $api_key = $env['apikey'] ?? '';
                break;
            }
        }
    }
    
    if (empty($api_key)) {
        error_log('[AI v11] OpenAI API key not found');
        return false;
    }
    
    $response = wp_remote_post('https://api.openai.com/v1/images/generations', [
        'timeout' => 120,
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ],
        'body' => json_encode([
            'model' => $settings['image_model'],
            'prompt' => $prompt,
            'n' => 1,
            'size' => $settings['image_size'],
            'quality' => $settings['image_quality'],
            'style' => $settings['image_style'],
        ])
    ]);
    
    if (is_wp_error($response)) {
        error_log('[AI v11] DALL-E API error: ' . $response->get_error_message());
        return false;
    }
    
    $status_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    if ($status_code !== 200) {
        error_log('[AI v11] DALL-E error response: ' . $body);
        return false;
    }
    
    $data = json_decode($body, true);
    
    if (isset($data['data'][0]['url'])) {
        $image_url = $data['data'][0]['url'];
        error_log('[AI v11] ✓ Image generated: ' . $image_url);
        return $image_url;
    }
    
    error_log('[AI v11] Unexpected DALL-E response');
    return false;
}

function parusweb_ai_upload_image_to_media($image_url, $product_id, $title) {
    
    error_log('[AI v11] Uploading image to media library...');
    
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    
    $tmp_file = download_url($image_url);
    
    if (is_wp_error($tmp_file)) {
        error_log('[AI v11] Failed to download image: ' . $tmp_file->get_error_message());
        return false;
    }
    
    $file_array = [
        'name' => sanitize_file_name($title . '-ai-generated.png'),
        'tmp_name' => $tmp_file
    ];
    
    $attachment_id = media_handle_sideload($file_array, $product_id, $title);
    
    if (file_exists($tmp_file)) {
        @unlink($tmp_file);
    }
    
    if (is_wp_error($attachment_id)) {
        error_log('[AI v11] Failed to upload to media: ' . $attachment_id->get_error_message());
        return false;
    }
    
    error_log('[AI v11] ✓ Image uploaded to media library, ID: ' . $attachment_id);
    
    return $attachment_id;
}

function parusweb_ai_generate_product_image($product_id, $prompt) {
    
    error_log('[AI v11] === Generating product image ===');
    error_log('[AI v11] Image prompt: ' . $prompt);
    
    $settings = parusweb_ai_get_settings();
    
    if (!$settings['image_enabled']) {
        error_log('[AI v11] Image generation disabled');
        return false;
    }
    
    $image_url = parusweb_ai_generate_image($prompt);
    
    if (!$image_url) {
        return false;
    }
    
    $product = wc_get_product($product_id);
    $title = $product ? $product->get_name() : 'Product';
    
    $attachment_id = parusweb_ai_upload_image_to_media($image_url, $product_id, $title);
    
    if (!$attachment_id) {
        return false;
    }
    
    set_post_thumbnail($product_id, $attachment_id);
    
    error_log('[AI v11] ✓ Product image set successfully');
    
    return $attachment_id;
}

// ============================================================================
// ГЕНЕРАЦИЯ КОНТЕНТА ТОВАРА
// ============================================================================

function parusweb_ai_generate_product_v11($product_id, $fields = []) {
    
    error_log('[AI v11] === Generating for product ' . $product_id . ' ===');
    error_log('[AI v11] Fields: ' . implode(', ', $fields));
    
    $product = wc_get_product($product_id);
    if (!$product) {
        return false;
    }
    
    $settings = parusweb_ai_get_settings();
    
    // Получаем ВСЕ плейсхолдеры
    $placeholders = parusweb_ai_get_product_placeholders($product_id);
    
    $placeholders['{max_length}'] = $settings['excerpt_max_length'];
    $placeholders['{min_words}'] = $settings['description_min_words'];
    $placeholders['{max_words}'] = $settings['description_max_words'];
    $placeholders['{min_length}'] = $settings['meta_description_min_length'];
    
    $results = [];
    
    // КРАТКОЕ ОПИСАНИЕ
    if (in_array('excerpt', $fields)) {
        error_log('[AI v11] Generating excerpt...');
        
        $prompt = parusweb_ai_get_prompt_hierarchical($product_id, 'excerpt');
        $prompt = str_replace(array_keys($placeholders), array_values($placeholders), $prompt);
        
        $results['excerpt'] = parusweb_ai_generate_v11($prompt, $settings['excerpt_max_tokens']);
    }
    
    // ПОЛНОЕ ОПИСАНИЕ
    if (in_array('description', $fields)) {
        error_log('[AI v11] Generating description...');
        
        $prompt = parusweb_ai_get_prompt_hierarchical($product_id, 'description');
        $prompt = str_replace(array_keys($placeholders), array_values($placeholders), $prompt);
        
        error_log('[AI v11] Description prompt: ' . substr($prompt, 0, 500) . '...');
        
        $results['description'] = parusweb_ai_generate_v11($prompt, $settings['description_max_tokens']);
    }
    
    // SEO TITLE
    if (in_array('seo_title', $fields)) {
        error_log('[AI v11] Generating SEO title...');
        
        $prompt = parusweb_ai_get_prompt_hierarchical($product_id, 'seo_title');
        $prompt = str_replace(array_keys($placeholders), array_values($placeholders), $prompt);
        
        $results['seo_title'] = parusweb_ai_generate_v11($prompt, $settings['seo_title_max_tokens']);
    }
    
    // META DESCRIPTION
    if (in_array('meta_description', $fields)) {
        error_log('[AI v11] Generating meta description...');
        
        $prompt = parusweb_ai_get_prompt_hierarchical($product_id, 'meta_description');
        $prompt = str_replace(array_keys($placeholders), array_values($placeholders), $prompt);
        
        $results['meta_description'] = parusweb_ai_generate_v11($prompt, $settings['meta_description_max_tokens']);
    }
    
    // FOCUS KEYWORD
    if (in_array('focus_keyword', $fields)) {
        error_log('[AI v11] Generating focus keyword...');
        
        $prompt = parusweb_ai_get_prompt_hierarchical($product_id, 'focus_keyword');
        $prompt = str_replace(array_keys($placeholders), array_values($placeholders), $prompt);
        
        $results['focus_keyword'] = parusweb_ai_generate_v11($prompt, $settings['focus_keyword_max_tokens']);
    }
    
    // ИЗОБРАЖЕНИЕ
    if (in_array('image', $fields)) {
        error_log('[AI v11] Generating product image...');
        
        $prompt = parusweb_ai_get_prompt_hierarchical($product_id, 'image');
        $prompt = str_replace(array_keys($placeholders), array_values($placeholders), $prompt);
        
        $attachment_id = parusweb_ai_generate_product_image($product_id, $prompt);
        
        $results['image'] = $attachment_id ? 'generated' : false;
    }
    
    error_log('[AI v11] === Complete ===');
    
    return $results;
}

// ============================================================================
// ПРИМЕНЕНИЕ КОНТЕНТА
// ============================================================================

function parusweb_ai_apply_content($product_id, $content) {
    
    if (!empty($content['excerpt'])) {
        wp_update_post(['ID' => $product_id, 'post_excerpt' => $content['excerpt']]);
    }
    
    if (!empty($content['description'])) {
        wp_update_post(['ID' => $product_id, 'post_content' => $content['description']]);
    }
    
    if (!empty($content['seo_title'])) {
        update_post_meta($product_id, '_yoast_wpseo_title', $content['seo_title']);
    }
    
    if (!empty($content['meta_description'])) {
        update_post_meta($product_id, '_yoast_wpseo_metadesc', $content['meta_description']);
    }
    
    if (!empty($content['focus_keyword'])) {
        update_post_meta($product_id, '_yoast_wpseo_focuskw', $content['focus_keyword']);
    }
    
    return true;
}

// ============================================================================
// AJAX
// ============================================================================

function parusweb_ajax_ai_v11() {
    
    error_log('[AI v11] === AJAX START ===');
    
    $product_id = intval($_POST['product_id'] ?? 0);
    
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'parusweb_ai_v11_' . $product_id)) {
        wp_send_json_error('Неверный nonce');
    }
    
    if (!current_user_can('edit_post', $product_id)) {
        wp_send_json_error('Недостаточно прав');
    }
    
    $fields = isset($_POST['fields']) ? $_POST['fields'] : ['excerpt', 'description', 'seo_title', 'meta_description', 'focus_keyword'];
    
    error_log('[AI v11] Selected fields: ' . implode(', ', $fields));
    
    $content = parusweb_ai_generate_product_v11($product_id, $fields);
    
    if (!$content || empty(array_filter($content))) {
        wp_send_json_error('Не удалось сгенерировать. Проверьте debug.log');
    }
    
    parusweb_ai_apply_content($product_id, $content);
    
    wp_send_json_success([
        'message' => 'Контент сгенерирован!',
        'content' => $content
    ]);
}
add_action('wp_ajax_parusweb_ai_v11', 'parusweb_ajax_ai_v11');

// ============================================================================
// МЕТАБОКС
// ============================================================================

function parusweb_ai_v11_metabox() {
    add_meta_box(
        'parusweb_ai_v11',
        '✨ AI Генерация контента',
        'parusweb_ai_v11_metabox_html',
        'product',
        'side',
        'high'
    );
}
add_action('add_meta_boxes', 'parusweb_ai_v11_metabox');

function parusweb_ai_v11_metabox_html($post) {
    ?>
    <div id="parusweb-ai-v11" style="padding: 10px 0;">
        <p style="margin-bottom: 10px; font-weight: 600;">Что генерировать:</p>
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px;">
                <input type="checkbox" name="ai_fields[]" value="excerpt" checked /> 
                Краткое описание
            </label>
            
            <label style="display: block; margin-bottom: 5px;">
                <input type="checkbox" name="ai_fields[]" value="description" checked /> 
                Полное описание
            </label>
            
            <label style="display: block; margin-bottom: 5px;">
                <input type="checkbox" name="ai_fields[]" value="seo_title" checked /> 
                SEO Title (Yoast)
            </label>
            
            <label style="display: block; margin-bottom: 5px;">
                <input type="checkbox" name="ai_fields[]" value="meta_description" checked /> 
                Meta Description (Yoast)
            </label>
            
            <label style="display: block; margin-bottom: 5px;">
                <input type="checkbox" name="ai_fields[]" value="focus_keyword" checked /> 
                Focus Keyword (Yoast)
            </label>
            
            <label style="display: block; margin-bottom: 5px;">
                <input type="checkbox" name="ai_fields[]" value="image" /> 
                🎨 Изображение товара (DALL-E 3)
            </label>
        </div>
        
        <button type="button" id="ai_v11_btn" class="button button-primary button-large" style="width: 100%;">
            ✨ Сгенерировать контент
        </button>
        
        <p style="margin-top: 10px; font-size: 11px; color: #666;">
            <a href="<?php echo admin_url('admin.php?page=parusweb-ai-settings'); ?>" target="_blank">⚙️ Настроить промпты и параметры</a>
        </p>
        
        <div id="ai_v11_status" style="margin-top: 10px;"></div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#ai_v11_btn').on('click', function() {
            const $btn = $(this);
            const $status = $('#ai_v11_status');
            
            const fields = [];
            $('input[name="ai_fields[]"]:checked').each(function() {
                fields.push($(this).val());
            });
            
            if (fields.length === 0) {
                alert('Выберите хотя бы одно поле для генерации');
                return;
            }
            
            $btn.prop('disabled', true).text('⏳ Генерация...');
            $status.html('<p style="color: #135e96; font-size: 12px;">⏳ Генерируем ' + fields.length + ' полей...<br>Подождите 30-60 секунд.</p>');
            
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                timeout: 120000,
                data: {
                    action: 'parusweb_ai_v11',
                    product_id: <?php echo $post->ID; ?>,
                    fields: fields,
                    nonce: '<?php echo wp_create_nonce('parusweb_ai_v11_' . $post->ID); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        let html = '<p style="color: #46b450; font-weight: bold;">✅ ' + response.data.message + '</p>';
                        
                        if (response.data.content) {
                            html += '<div style="font-size: 11px; margin-top: 10px; color: #666;">';
                            
                            const c = response.data.content;
                            if (c.excerpt) html += '✓ Краткое описание<br>';
                            if (c.description) html += '✓ Полное описание<br>';
                            if (c.seo_title) html += '✓ SEO Title<br>';
                            if (c.meta_description) html += '✓ Meta Description<br>';
                            if (c.focus_keyword) html += '✓ Keyword: ' + c.focus_keyword + '<br>';
                            if (c.image) html += '✓ 🎨 Изображение товара<br>';
                            
                            html += '</div>';
                        }
                        
                        $status.html(html);
                        
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $status.html('<p style="color: #d63638;">❌ ' + response.data + '</p>');
                        $btn.prop('disabled', false).text('✨ Сгенерировать контент');
                    }
                },
                error: function(xhr) {
                    $status.html('<p style="color: #d63638;">❌ Ошибка</p>');
                    $btn.prop('disabled', false).text('✨ Сгенерировать контент');
                }
            });
        });
    });
    </script>
    <?php
}

// ============================================================================
// BULK ACTION
// ============================================================================

function parusweb_ai_v11_bulk($actions) {
    $actions['parusweb_ai_v11'] = '✨ Сгенерировать AI контент';
    return $actions;
}
add_filter('bulk_actions-edit-product', 'parusweb_ai_v11_bulk');

function parusweb_ai_v11_bulk_handler($redirect, $action, $post_ids) {
    
    if ($action !== 'parusweb_ai_v11') {
        return $redirect;
    }
    
    $processed = 0;
    $fields = ['excerpt', 'description', 'seo_title', 'meta_description', 'focus_keyword'];
    
    foreach ($post_ids as $product_id) {
        $content = parusweb_ai_generate_product_v11($product_id, $fields);
        
        if ($content && !empty(array_filter($content))) {
            parusweb_ai_apply_content($product_id, $content);
            $processed++;
        }
        
        sleep(3);
    }
    
    return add_query_arg('ai_v11_done', $processed, $redirect);
}
add_filter('handle_bulk_actions-edit-product', 'parusweb_ai_v11_bulk_handler', 10, 3);

function parusweb_ai_v11_notice() {
    if (!empty($_GET['ai_v11_done'])) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p>✅ AI контент сгенерирован для ' . intval($_GET['ai_v11_done']) . ' товаров.</p>';
        echo '</div>';
    }
}
add_action('admin_notices', 'parusweb_ai_v11_notice');

// Подключаем модуль настроек (если есть отдельный файл)
if (file_exists(plugin_dir_path(__FILE__) . 'ai-settings.php')) {
    require_once plugin_dir_path(__FILE__) . 'ai-settings.php';
}