<?php
/**
 * ============================================================================
 * AI PROMPTS HIERARCHY - ИЕРАРХИЯ ПРОМПТОВ
 * ============================================================================
 * 
 * Метабоксы для задания индивидуальных промптов:
 * - Для товара (самый высокий приоритет)
 * - Для категории (средний приоритет)
 * - Глобальные настройки в WooCommerce → AI Генерация
 * 
 * @version 2.0.0
 */

if (!defined('ABSPATH')) exit;

// ============================================================================
// МЕТАБОКС ПРОМПТОВ ДЛЯ ТОВАРА
// ============================================================================

function parusweb_ai_prompts_metabox() {
    add_meta_box(
        'parusweb_ai_prompts',
        '✏️ AI Промпты для товара',
        'parusweb_ai_prompts_metabox_html',
        'product',
        'normal',
        'low'
    );
}
add_action('add_meta_boxes', 'parusweb_ai_prompts_metabox');

function parusweb_ai_prompts_metabox_html($post) {
    
    wp_nonce_field('parusweb_ai_prompts_save', 'parusweb_ai_prompts_nonce');
    
    $prompt_types = [
        'excerpt' => 'Краткое описание',
        'description' => 'Полное описание',
        'seo_title' => 'SEO Title',
        'meta_description' => 'Meta Description',
        'focus_keyword' => 'Focus Keyword',
        'image' => 'Промпт для изображения'
    ];
    
    ?>
    <div style="margin: 15px 0;">
        <p style="color: #666; margin-bottom: 15px;">
            <strong>ℹ️ Иерархия промптов:</strong> Товар → Категория → Глобальные настройки<br>
            Если промпт не задан для товара, будет использован промпт категории, затем глобальный.
        </p>
        
        <p style="margin-bottom: 20px;">
            <label style="display: inline-flex; align-items: center; gap: 8px;">
                <input type="checkbox" id="toggle_ai_prompts" />
                <strong>Задать индивидуальные промпты для этого товара</strong>
            </label>
        </p>
        
        <div id="ai_prompts_container" style="display: none;">
            
            <?php foreach ($prompt_types as $type => $label): ?>
                <?php
                $value = get_post_meta($post->ID, '_ai_prompt_' . $type, true);
                $has_value = !empty($value);
                ?>
                
                <div style="margin-bottom: 20px; padding: 15px; border: 1px solid <?php echo $has_value ? '#46b450' : '#ddd'; ?>; border-radius: 5px; background: <?php echo $has_value ? '#f0f9f0' : '#fff'; ?>;">
                    <h4 style="margin: 0 0 10px 0;">
                        <?php echo esc_html($label); ?>
                        <?php if ($has_value): ?>
                            <span style="color: #46b450; font-size: 12px;">✓ Задан</span>
                        <?php else: ?>
                            <span style="color: #999; font-size: 12px;">Не задан (будет использован из категории или глобальный)</span>
                        <?php endif; ?>
                    </h4>
                    
                    <textarea 
                        name="_ai_prompt_<?php echo $type; ?>" 
                        rows="<?php echo $type === 'description' || $type === 'image' ? '8' : '4'; ?>" 
                        style="width: 100%; font-family: monospace; font-size: 12px;"
                        placeholder="Оставьте пустым для использования промпта из категории или глобальных настроек"
                    ><?php echo esc_textarea($value); ?></textarea>
                    
                    <?php if ($has_value): ?>
                        <p style="margin: 8px 0 0 0;">
                            <button type="button" 
                                    class="button button-small clear-prompt-btn" 
                                    data-target="_ai_prompt_<?php echo $type; ?>"
                                    style="color: #b32d2e;">
                                🗑️ Очистить (использовать промпт категории)
                            </button>
                        </p>
                    <?php endif; ?>
                </div>
                
            <?php endforeach; ?>
            
            <div style="padding: 15px; background: #f9f9f9; border-radius: 5px; margin-top: 15px;">
                <h4 style="margin: 0 0 10px 0;">Доступные плейсхолдеры:</h4>
                <ul style="columns: 2; margin: 0; padding-left: 20px; font-size: 12px; color: #666;">
                    <li><code>{title}</code> - название товара</li>
                    <li><code>{category}</code> - категории</li>
                    <li><code>{attributes}</code> - все атрибуты</li>
                    <li><code>{pa_sort}</code> - Сорт</li>
                    <li><code>{pa_poroda}</code> - Порода</li>
                    <li><code>{pa_shirina}</code> - Ширина</li>
                    <li><code>{pa_tolshhina}</code> - Толщина</li>
                    <li><code>{pa_dlina}</code> - Длина</li>
                    <li><code>{pa_proizvoditel}</code> - Производитель</li>
                    <li><code>{max_length}</code> - макс. длина</li>
                    <li><code>{min_words}</code> - мин. слова</li>
                    <li><code>{max_words}</code> - макс. слова</li>
                </ul>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        
        $('#toggle_ai_prompts').on('change', function() {
            $('#ai_prompts_container').toggle(this.checked);
        });
        
        <?php
        $has_any_prompt = false;
        foreach ($prompt_types as $type => $label) {
            if (!empty(get_post_meta($post->ID, '_ai_prompt_' . $type, true))) {
                $has_any_prompt = true;
                break;
            }
        }
        ?>
        
        <?php if ($has_any_prompt): ?>
        $('#toggle_ai_prompts').prop('checked', true).trigger('change');
        <?php endif; ?>
        
        $('.clear-prompt-btn').on('click', function() {
            const target = $(this).data('target');
            $('textarea[name="' + target + '"]').val('').trigger('change');
            $(this).closest('div[style*="border"]').css({
                'border-color': '#ddd',
                'background': '#fff'
            }).find('span').remove();
        });
    });
    </script>
    <?php
}

function parusweb_ai_prompts_save($post_id) {
    
    if (!isset($_POST['parusweb_ai_prompts_nonce'])) {
        return;
    }
    
    if (!wp_verify_nonce($_POST['parusweb_ai_prompts_nonce'], 'parusweb_ai_prompts_save')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    $prompt_types = ['excerpt', 'description', 'seo_title', 'meta_description', 'focus_keyword', 'image'];
    
    foreach ($prompt_types as $type) {
        $meta_key = '_ai_prompt_' . $type;
        
        if (isset($_POST[$meta_key])) {
            $value = wp_unslash($_POST[$meta_key]);
            
            if (empty($value)) {
                delete_post_meta($post_id, $meta_key);
            } else {
                update_post_meta($post_id, $meta_key, $value);
            }
        }
    }
}
add_action('save_post_product', 'parusweb_ai_prompts_save');

// ============================================================================
// ПРОМПТЫ ДЛЯ КАТЕГОРИЙ
// ============================================================================

function parusweb_ai_category_prompts_fields($term) {
    
    $prompt_types = [
        'excerpt' => 'Краткое описание',
        'description' => 'Полное описание',
        'seo_title' => 'SEO Title',
        'meta_description' => 'Meta Description',
        'focus_keyword' => 'Focus Keyword',
        'image' => 'Промпт для изображения'
    ];
    
    ?>
    <tr class="form-field">
        <th scope="row" colspan="2">
            <h3 style="margin: 20px 0 10px 0;">✏️ AI Промпты для категории</h3>
            <p style="color: #666; margin-bottom: 15px;">
                Эти промпты будут использоваться для всех товаров категории, если у товара не задан индивидуальный промпт.
            </p>
        </th>
    </tr>
    
    <?php foreach ($prompt_types as $type => $label): ?>
        <?php
        $value = get_term_meta($term->term_id, '_ai_prompt_' . $type, true);
        ?>
        
        <tr class="form-field">
            <th scope="row">
                <label for="ai_prompt_<?php echo $type; ?>">
                    <?php echo esc_html($label); ?>
                </label>
            </th>
            <td>
                <textarea 
                    id="ai_prompt_<?php echo $type; ?>" 
                    name="_ai_prompt_<?php echo $type; ?>" 
                    rows="<?php echo $type === 'description' || $type === 'image' ? '8' : '4'; ?>" 
                    style="width: 100%; font-family: monospace; font-size: 12px;"
                    placeholder="Оставьте пустым для использования глобальных настроек"
                ><?php echo esc_textarea($value); ?></textarea>
                
                <?php if (!empty($value)): ?>
                    <p class="description" style="color: #46b450;">✓ Промпт задан для этой категории</p>
                <?php else: ?>
                    <p class="description">Не задан (будет использован глобальный промпт)</p>
                <?php endif; ?>
            </td>
        </tr>
        
    <?php endforeach; ?>
    
    <tr class="form-field">
        <th scope="row" colspan="2">
            <div style="padding: 15px; background: #f9f9f9; border-radius: 5px;">
                <h4 style="margin: 0 0 10px 0;">Доступные плейсхолдеры:</h4>
                <ul style="columns: 2; margin: 0; padding-left: 20px; font-size: 12px; color: #666;">
                    <li><code>{title}</code> - название товара</li>
                    <li><code>{category}</code> - категории</li>
                    <li><code>{attributes}</code> - все атрибуты</li>
                    <li><code>{pa_sort}</code> - Сорт</li>
                    <li><code>{pa_poroda}</code> - Порода</li>
                    <li><code>{pa_shirina}</code> - Ширина</li>
                    <li><code>{pa_tolshhina}</code> - Толщина</li>
                    <li><code>{pa_dlina}</code> - Длина</li>
                    <li><code>{pa_proizvoditel}</code> - Производитель</li>
                </ul>
            </div>
        </th>
    </tr>
    <?php
}
add_action('product_cat_edit_form_fields', 'parusweb_ai_category_prompts_fields');

function parusweb_ai_category_prompts_save($term_id) {
    
    $prompt_types = ['excerpt', 'description', 'seo_title', 'meta_description', 'focus_keyword', 'image'];
    
    foreach ($prompt_types as $type) {
        $meta_key = '_ai_prompt_' . $type;
        
        if (isset($_POST[$meta_key])) {
            $value = wp_unslash($_POST[$meta_key]);
            
            if (empty($value)) {
                delete_term_meta($term_id, $meta_key);
            } else {
                update_term_meta($term_id, $meta_key, $value);
            }
        }
    }
}
add_action('edited_product_cat', 'parusweb_ai_category_prompts_save');
add_action('created_product_cat', 'parusweb_ai_category_prompts_save');

// ============================================================================
// СТРАНИЦА НАСТРОЕК
// ============================================================================

function parusweb_ai_settings_menu() {
    add_submenu_page(
        'woocommerce',
        'AI Генерация - Настройки',
        'AI Генерация',
        'manage_options',
        'parusweb-ai-settings',
        'parusweb_ai_settings_page'
    );
}
add_action('admin_menu', 'parusweb_ai_settings_menu');

function parusweb_ai_settings_page() {
    
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Сохранение настроек
    if (isset($_POST['parusweb_ai_save_settings'])) {
        check_admin_referer('parusweb_ai_settings');
        
        $settings = [];
        
        // Промпты
        $settings['prompt_excerpt'] = wp_unslash($_POST['prompt_excerpt'] ?? '');
        $settings['prompt_description'] = wp_unslash($_POST['prompt_description'] ?? '');
        $settings['prompt_seo_title'] = wp_unslash($_POST['prompt_seo_title'] ?? '');
        $settings['prompt_meta_description'] = wp_unslash($_POST['prompt_meta_description'] ?? '');
        $settings['prompt_focus_keyword'] = wp_unslash($_POST['prompt_focus_keyword'] ?? '');
        
        // Длины
        $settings['excerpt_max_length'] = intval($_POST['excerpt_max_length'] ?? 160);
        $settings['excerpt_max_tokens'] = intval($_POST['excerpt_max_tokens'] ?? 150);
        
        $settings['description_min_words'] = intval($_POST['description_min_words'] ?? 300);
        $settings['description_max_words'] = intval($_POST['description_max_words'] ?? 500);
        $settings['description_max_tokens'] = intval($_POST['description_max_tokens'] ?? 1000);
        
        $settings['seo_title_max_length'] = intval($_POST['seo_title_max_length'] ?? 60);
        $settings['seo_title_max_tokens'] = intval($_POST['seo_title_max_tokens'] ?? 80);
        
        $settings['meta_description_min_length'] = intval($_POST['meta_description_min_length'] ?? 140);
        $settings['meta_description_max_length'] = intval($_POST['meta_description_max_length'] ?? 160);
        $settings['meta_description_max_tokens'] = intval($_POST['meta_description_max_tokens'] ?? 120);
        
        $settings['focus_keyword_max_tokens'] = intval($_POST['focus_keyword_max_tokens'] ?? 50);
        
        // Настройки изображений
        $settings['image_enabled'] = isset($_POST['image_enabled']);
        $settings['image_model'] = sanitize_text_field($_POST['image_model'] ?? 'dall-e-3');
        $settings['image_size'] = sanitize_text_field($_POST['image_size'] ?? '1024x1024');
        $settings['image_quality'] = sanitize_text_field($_POST['image_quality'] ?? 'standard');
        $settings['image_style'] = sanitize_text_field($_POST['image_style'] ?? 'natural');
        $settings['prompt_image'] = wp_unslash($_POST['prompt_image'] ?? '');
        
        // AI параметры
        $settings['ai_temperature'] = floatval($_POST['ai_temperature'] ?? 0.7);
        $settings['ai_model'] = sanitize_text_field($_POST['ai_model'] ?? 'gpt-3.5-turbo');
        $settings['ai_env_id'] = sanitize_text_field($_POST['ai_env_id'] ?? 'vywjfu3m');
        
        update_option('parusweb_ai_settings', $settings);
        
        echo '<div class="notice notice-success"><p>✅ Настройки сохранены!</p></div>';
    }
    
    // Сброс настроек
    if (isset($_POST['parusweb_ai_reset_settings'])) {
        check_admin_referer('parusweb_ai_settings');
        delete_option('parusweb_ai_settings');
        echo '<div class="notice notice-success"><p>✅ Настройки сброшены на значения по умолчанию!</p></div>';
    }
    
    $settings = parusweb_ai_get_settings();
    
    ?>
    <div class="wrap">
        <h1>⚙️ AI Генерация - Глобальные настройки</h1>
        
        <p style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0;">
            <strong>ℹ️ Иерархия промптов:</strong><br>
            1. <strong>Промпты товара</strong> (задаются в редакторе товара) - самый высокий приоритет<br>
            2. <strong>Промпты категории</strong> (Товары → Категории → Редактировать) - средний приоритет<br>
            3. <strong>Глобальные промпты</strong> (эта страница) - используются если не заданы выше
        </p>
        
        <form method="post" action="">
            <?php wp_nonce_field('parusweb_ai_settings'); ?>
            
            <h2>🤖 Параметры AI</h2>
            <table class="form-table">
                <tr>
                    <th>Environment ID</th>
                    <td>
                        <input type="text" name="ai_env_id" value="<?php echo esc_attr($settings['ai_env_id']); ?>" class="regular-text" />
                        <p class="description">ID окружения AI Engine (обычно не нужно менять)</p>
                    </td>
                </tr>
                
                <tr>
                    <th>Модель</th>
                    <td>
                        <input type="text" name="ai_model" value="<?php echo esc_attr($settings['ai_model']); ?>" class="regular-text" />
                        <p class="description">Например: gpt-3.5-turbo, gpt-4</p>
                    </td>
                </tr>
                
                <tr>
                    <th>Temperature</th>
                    <td>
                        <input type="number" name="ai_temperature" value="<?php echo esc_attr($settings['ai_temperature']); ?>" min="0" max="2" step="0.1" style="width: 80px;" />
                        <p class="description">От 0 (строго) до 2 (креативно). Рекомендуется: 0.7</p>
                    </td>
                </tr>
            </table>
            
            <h2>📝 Краткое описание (Excerpt)</h2>
            <table class="form-table">
                <tr>
                    <th>Промпт</th>
                    <td>
                        <textarea name="prompt_excerpt" rows="6" class="large-text code"><?php echo esc_textarea($settings['prompt_excerpt']); ?></textarea>
                        <p class="description">Плейсхолдеры: {title}, {category}, {attributes}, {max_length}</p>
                    </td>
                </tr>
                <tr>
                    <th>Макс. длина</th>
                    <td>
                        <input type="number" name="excerpt_max_length" value="<?php echo esc_attr($settings['excerpt_max_length']); ?>" min="50" max="500" style="width: 100px;" /> символов
                    </td>
                </tr>
                <tr>
                    <th>Макс. токенов</th>
                    <td>
                        <input type="number" name="excerpt_max_tokens" value="<?php echo esc_attr($settings['excerpt_max_tokens']); ?>" min="50" max="1000" style="width: 100px;" />
                    </td>
                </tr>
            </table>
            
            <h2>📄 Полное описание</h2>
            <table class="form-table">
                <tr>
                    <th>Промпт</th>
                    <td>
                        <textarea name="prompt_description" rows="10" class="large-text code"><?php echo esc_textarea($settings['prompt_description']); ?></textarea>
                        <p class="description">Плейсхолдеры: {title}, {category}, {attributes}, {min_words}, {max_words}</p>
                    </td>
                </tr>
                <tr>
                    <th>Длина (слова)</th>
                    <td>
                        От <input type="number" name="description_min_words" value="<?php echo esc_attr($settings['description_min_words']); ?>" min="100" max="5000" style="width: 100px;" />
                        до <input type="number" name="description_max_words" value="<?php echo esc_attr($settings['description_max_words']); ?>" min="100" max="5000" style="width: 100px;" /> слов
                    </td>
                </tr>
                <tr>
                    <th>Макс. токенов</th>
                    <td>
                        <input type="number" name="description_max_tokens" value="<?php echo esc_attr($settings['description_max_tokens']); ?>" min="100" max="4000" style="width: 100px;" />
                    </td>
                </tr>
            </table>
            
            <h2>🔍 SEO Title</h2>
            <table class="form-table">
                <tr>
                    <th>Промпт</th>
                    <td>
                        <textarea name="prompt_seo_title" rows="6" class="large-text code"><?php echo esc_textarea($settings['prompt_seo_title']); ?></textarea>
                        <p class="description">Плейсхолдеры: {title}, {category}, {max_length}</p>
                    </td>
                </tr>
                <tr>
                    <th>Макс. длина</th>
                    <td>
                        <input type="number" name="seo_title_max_length" value="<?php echo esc_attr($settings['seo_title_max_length']); ?>" min="30" max="100" style="width: 100px;" /> символов
                    </td>
                </tr>
                <tr>
                    <th>Макс. токенов</th>
                    <td>
                        <input type="number" name="seo_title_max_tokens" value="<?php echo esc_attr($settings['seo_title_max_tokens']); ?>" min="30" max="200" style="width: 100px;" />
                    </td>
                </tr>
            </table>
            
            <h2>📋 Meta Description</h2>
            <table class="form-table">
                <tr>
                    <th>Промпт</th>
                    <td>
                        <textarea name="prompt_meta_description" rows="6" class="large-text code"><?php echo esc_textarea($settings['prompt_meta_description']); ?></textarea>
                        <p class="description">Плейсхолдеры: {title}, {category}, {attributes}, {min_length}, {max_length}</p>
                    </td>
                </tr>
                <tr>
                    <th>Длина</th>
                    <td>
                        От <input type="number" name="meta_description_min_length" value="<?php echo esc_attr($settings['meta_description_min_length']); ?>" min="100" max="300" style="width: 100px;" />
                        до <input type="number" name="meta_description_max_length" value="<?php echo esc_attr($settings['meta_description_max_length']); ?>" min="100" max="300" style="width: 100px;" /> символов
                    </td>
                </tr>
                <tr>
                    <th>Макс. токенов</th>
                    <td>
                        <input type="number" name="meta_description_max_tokens" value="<?php echo esc_attr($settings['meta_description_max_tokens']); ?>" min="50" max="500" style="width: 100px;" />
                    </td>
                </tr>
            </table>
            
            <h2>🔑 Focus Keyword</h2>
            <table class="form-table">
                <tr>
                    <th>Промпт</th>
                    <td>
                        <textarea name="prompt_focus_keyword" rows="6" class="large-text code"><?php echo esc_textarea($settings['prompt_focus_keyword']); ?></textarea>
                        <p class="description">Плейсхолдеры: {title}, {category}</p>
                    </td>
                </tr>
                <tr>
                    <th>Макс. токенов</th>
                    <td>
                        <input type="number" name="focus_keyword_max_tokens" value="<?php echo esc_attr($settings['focus_keyword_max_tokens']); ?>" min="10" max="100" style="width: 100px;" />
                    </td>
                </tr>
            </table>
            
            <h2>🎨 Генерация изображений (DALL-E 3)</h2>
            <table class="form-table">
                <tr>
                    <th>Включить генерацию</th>
                    <td>
                        <label>
                            <input type="checkbox" name="image_enabled" value="1" <?php checked($settings['image_enabled']); ?> />
                            Разрешить генерацию изображений через DALL-E 3
                        </label>
                        <p class="description" style="color: #d63638; font-weight: 600;">⚠️ DALL-E 3: $0.04 за изображение (1024x1024 standard) или $0.08 (HD качество)</p>
                    </td>
                </tr>
                
                <tr>
                    <th>Модель</th>
                    <td>
                        <select name="image_model">
                            <option value="dall-e-3" <?php selected($settings['image_model'], 'dall-e-3'); ?>>DALL-E 3 (рекомендуется)</option>
                            <option value="dall-e-2" <?php selected($settings['image_model'], 'dall-e-2'); ?>>DALL-E 2 (дешевле)</option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th>Размер</th>
                    <td>
                        <select name="image_size">
                            <option value="1024x1024" <?php selected($settings['image_size'], '1024x1024'); ?>>1024x1024 (квадрат)</option>
                            <option value="1024x1792" <?php selected($settings['image_size'], '1024x1792'); ?>>1024x1792 (вертикаль)</option>
                            <option value="1792x1024" <?php selected($settings['image_size'], '1792x1024'); ?>>1792x1024 (горизонталь)</option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th>Качество</th>
                    <td>
                        <select name="image_quality">
                            <option value="standard" <?php selected($settings['image_quality'], 'standard'); ?>>Standard ($0.04)</option>
                            <option value="hd" <?php selected($settings['image_quality'], 'hd'); ?>>HD ($0.08)</option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th>Стиль</th>
                    <td>
                        <select name="image_style">
                            <option value="natural" <?php selected($settings['image_style'], 'natural'); ?>>Natural (фотореалистичный)</option>
                            <option value="vivid" <?php selected($settings['image_style'], 'vivid'); ?>>Vivid (яркий)</option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th>Промпт для изображения</th>
                    <td>
                        <textarea name="prompt_image" rows="10" class="large-text code"><?php echo esc_textarea($settings['prompt_image']); ?></textarea>
                        <p class="description">Плейсхолдеры: {title}, {category}, {attributes}</p>
                        <p class="description">Промпт лучше писать на английском для DALL-E</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" name="parusweb_ai_save_settings" class="button button-primary button-large">💾 Сохранить настройки</button>
                <button type="submit" name="parusweb_ai_reset_settings" class="button button-secondary" onclick="return confirm('Вы уверены? Все настройки будут сброшены на значения по умолчанию.');">🔄 Сбросить на умолчания</button>
            </p>
        </form>
    </div>
    <?php
}