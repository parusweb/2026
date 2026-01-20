<?php
/**
 * ============================================================================
 * ЭКСПОРТ ТОВАРОВ ДЛЯ FINE-TUNING GPT (SEO + ПО 1 ИЗ КАТЕГОРИИ)
 * ============================================================================
 * 
 * Экспортирует по 1 товару из каждой категории в формат JSONL
 * 
 * ИСПОЛЬЗОВАНИЕ:
 * 1. Загрузите в /wp-content/plugins/parusweb-functions/
 * 2. Откройте: https://your-site.ru/wp-content/plugins/parusweb-functions/export-for-finetuning.php
 * 3. Скачайте файл training_data.jsonl
 * 4. УДАЛИТЕ скрипт после использования!
 */

// Загрузка WordPress
$wp_load_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
if (file_exists($wp_load_path)) {
    require_once($wp_load_path);
} else {
    die('WordPress not found');
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== ЭКСПОРТ ПО 1 ТОВАРУ ИЗ КАЖДОЙ КАТЕГОРИИ ===\n";
echo "Дата: " . date('Y-m-d H:i:s') . "\n\n";

// ============================================================================
// КОНФИГУРАЦИЯ
// ============================================================================

$config = [
    // Только товары с хорошими описаниями
    'min_description_length' => 200, // минимум символов
    'min_excerpt_length' => 50,
    
    // Включить SEO поля
    'include_seo' => true,
];

// ============================================================================
// СИСТЕМНЫЙ ПРОМПТ С SEO ТРЕБОВАНИЯМИ
// ============================================================================

$system_prompt = "Ты опытный SEO-копирайтер интернет-магазина строительных материалов в Санкт-Петербурге. "
    . "Специализация: пиломатериалы (вагонка, имитация бруса, доска), ДПК/МПК композит, листовые материалы, ЛКМ, столярные изделия, крепеж, услуги покраски. "
    . "\n\n"
    . "ОБЯЗАТЕЛЬНЫЕ SEO ТРЕБОВАНИЯ:\n"
    . "1. Короткие предложения - максимум 15 слов в предложении\n"
    . "2. Используй переходные слова: кроме того, также, более того, например, в частности, благодаря этому, в результате, помимо этого\n"
    . "3. Ключевую фразу (название товара) упоминай 2-3 раза в тексте естественным образом\n"
    . "4. ПЕРВОЕ предложение ОБЯЗАТЕЛЬНО должно содержать ключевую фразу\n"
    . "5. В подзаголовках H2 используй ключевую фразу или её синонимы\n"
    . "6. Плотность ключевой фразы: 1-2% от текста\n"
    . "\n"
    . "СТИЛЬ НАПИСАНИЯ:\n"
    . "- Профессиональный, но понятный\n"
    . "- Информативный, без рекламных штампов\n"
    . "- Конкретные факты вместо общих фраз\n"
    . "- НЕ используй: 'высокое качество', 'доступные цены', 'широкий ассортимент'\n"
    . "\n"
    . "СТРУКТУРА ОПИСАНИЙ:\n"
    . "1. Введение с ключевой фразой (1-2 предложения)\n"
    . "2. H2: Характеристики (список UL/LI)\n"
    . "3. H2: Преимущества (с переходными словами)\n"
    . "4. H2: Область применения\n"
    . "5. Заключение с практическими рекомендациями\n"
    . "\n"
    . "HTML-форматирование: <p>, <h2>, <ul>, <li>, <strong>.";

// ============================================================================
// ПОЛУЧЕНИЕ ПО 1 ТОВАРУ ИЗ КАЖДОЙ КАТЕГОРИИ
// ============================================================================

// Получаем все категории товаров
$categories = get_terms([
    'taxonomy' => 'product_cat',
    'hide_empty' => true,
]);

echo "Найдено категорий: " . count($categories) . "\n\n";

$all_products = [];

foreach ($categories as $category) {
    echo "Обработка категории: {$category->name}... ";
    
    // Берём 1 товар с самым длинным описанием из каждой категории
    $product_in_cat = $wpdb->get_row($wpdb->prepare("
        SELECT 
            p.ID,
            p.post_title,
            p.post_content,
            p.post_excerpt,
            LENGTH(p.post_content) as description_length
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
        INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
        WHERE p.post_type = 'product'
        AND p.post_status = 'publish'
        AND tt.term_id = %d
        AND LENGTH(p.post_content) >= %d
        ORDER BY description_length DESC
        LIMIT 1
    ", $category->term_id, $config['min_description_length']));
    
    if ($product_in_cat) {
        $all_products[] = $product_in_cat;
        echo "✓ {$product_in_cat->post_title} (" . $product_in_cat->description_length . " символов)\n";
    } else {
        echo "✗ Нет товаров с подходящими описаниями\n";
    }
}

echo "\n";
echo "Всего товаров отобрано: " . count($all_products) . "\n";
echo "Начинаем создание примеров для обучения...\n\n";

// ============================================================================
// ПОДГОТОВКА ДАТАСЕТА
// ============================================================================

$training_examples = [];
$stats = [
    'total' => 0,
    'with_description' => 0,
    'with_excerpt' => 0,
    'with_seo' => 0,
    'skipped' => 0,
];

foreach ($all_products as $product_data) {
    
    $stats['total']++;
    
    $product = wc_get_product($product_data->ID);
    
    if (!$product) {
        $stats['skipped']++;
        continue;
    }
    
    $title = $product->get_name();
    $excerpt = $product->get_short_description();
    $description = $product->get_description();
    
    // Получаем категории
    $categories = wp_get_post_terms($product_data->ID, 'product_cat', ['fields' => 'names']);
    $category_text = !empty($categories) ? implode(', ', $categories) : '';
    
    // Получаем атрибуты
    $attributes = [];
    if ($product->get_attributes()) {
        foreach ($product->get_attributes() as $attribute) {
            if ($attribute->is_taxonomy()) {
                $terms = wp_get_post_terms($product_data->ID, $attribute->get_name(), ['fields' => 'names']);
                if (!empty($terms)) {
                    $attr_name = wc_attribute_label($attribute->get_name());
                    $attributes[] = $attr_name . ': ' . implode(', ', $terms);
                }
            }
        }
    }
    $attributes_text = !empty($attributes) ? implode('. ', $attributes) : '';
    
    // ========================================================================
    // ПРИМЕР 1: ПОЛНОЕ ОПИСАНИЕ С SEO
    // ========================================================================
    
    if (!empty($description) && strlen($description) >= $config['min_description_length']) {
        
        $user_prompt = "Напиши SEO-оптимизированное описание товара для интернет-магазина:\n\n"
            . "Товар (ключевая фраза): {$title}\n"
            . "Категория: {$category_text}\n";
        
        if ($attributes_text) {
            $user_prompt .= "Характеристики: {$attributes_text}\n";
        }
        
        $user_prompt .= "\n"
            . "ОБЯЗАТЕЛЬНЫЕ ТРЕБОВАНИЯ:\n"
            . "✓ Первое предложение содержит ключевую фразу '{$title}'\n"
            . "✓ Предложения не длиннее 15 слов\n"
            . "✓ Используй переходные слова (кроме того, также, например)\n"
            . "✓ Ключевая фраза встречается 2-3 раза в тексте\n"
            . "✓ В H2 заголовках используй ключевую фразу или синонимы\n"
            . "✓ HTML-форматирование: <p>, <h2>, <ul>, <li>\n"
            . "\nСтруктура: введение, характеристики, преимущества, применение.";
        
        $training_examples[] = [
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => $user_prompt],
                ['role' => 'assistant', 'content' => strip_tags($description, '<p><h2><h3><ul><li><strong><br>')]
            ]
        ];
        
        $stats['with_description']++;
    }
    
    // ========================================================================
    // ПРИМЕР 2: КРАТКОЕ ОПИСАНИЕ (META DESCRIPTION)
    // ========================================================================
    
    if (!empty($excerpt) && strlen($excerpt) >= $config['min_excerpt_length']) {
        
        $user_prompt = "Напиши краткое SEO-описание (мета-описание) для товара:\n\n"
            . "Товар (ключевая фраза): {$title}\n"
            . "Категория: {$category_text}\n";
        
        if ($attributes_text) {
            $user_prompt .= "Характеристики: {$attributes_text}\n";
        }
        
        $user_prompt .= "\n"
            . "ТРЕБОВАНИЯ:\n"
            . "✓ Длина 120-160 символов\n"
            . "✓ Ключевая фраза в начале\n"
            . "✓ Призыв к действию или выгода\n"
            . "✓ Без HTML тегов\n"
            . "✓ 1-2 предложения максимум";
        
        $training_examples[] = [
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => $user_prompt],
                ['role' => 'assistant', 'content' => strip_tags($excerpt)]
            ]
        ];
        
        $stats['with_excerpt']++;
    }
    
    // ========================================================================
    // ПРИМЕР 3: SEO TITLE
    // ========================================================================
    
    if ($config['include_seo']) {
        $seo_title = get_post_meta($product_data->ID, '_yoast_wpseo_title', true);
        
        if (!empty($seo_title)) {
            
            $user_prompt = "Создай SEO-заголовок (Title) для товара:\n\n"
                . "Товар: {$title}\n"
                . "Категория: {$category_text}\n\n"
                . "ТРЕБОВАНИЯ:\n"
                . "✓ Длина до 60 символов\n"
                . "✓ Ключевая фраза в начале\n"
                . "✓ Город 'СПб' в конце\n"
                . "✓ Формат: Товар характеристики - действие СПб\n"
                . "✓ Верни ТОЛЬКО заголовок без кавычек";
            
            $training_examples[] = [
                'messages' => [
                    ['role' => 'system', 'content' => $system_prompt],
                    ['role' => 'user', 'content' => $user_prompt],
                    ['role' => 'assistant', 'content' => $seo_title]
                ]
            ];
            
            $stats['with_seo']++;
        }
    }
    
    // Прогресс
    echo ".";
}

echo "\n\n";

// ============================================================================
// СОХРАНЕНИЕ В JSONL
// ============================================================================

$filename = 'training_data_categories_' . date('Y-m-d') . '.jsonl';
$filepath = ABSPATH . $filename;

$fp = fopen($filepath, 'w');

foreach ($training_examples as $example) {
    fwrite($fp, json_encode($example, JSON_UNESCAPED_UNICODE) . "\n");
}

fclose($fp);

// ============================================================================
// СТАТИСТИКА
// ============================================================================

echo "=== СТАТИСТИКА ===\n\n";
echo "Категорий обработано: " . count($all_products) . "\n";
echo "Товаров обработано: {$stats['total']}\n";
echo "Примеров с полным описанием: {$stats['with_description']}\n";
echo "Примеров с кратким описанием: {$stats['with_excerpt']}\n";
echo "Примеров с SEO Title: {$stats['with_seo']}\n";
echo "Пропущено (ошибки): {$stats['skipped']}\n";
echo "\n";
echo "Всего примеров в датасете: " . count($training_examples) . "\n\n";

// ============================================================================
// ОЦЕНКА СТОИМОСТИ
// ============================================================================

$total_tokens = 0;
foreach ($training_examples as $example) {
    foreach ($example['messages'] as $msg) {
        // Примерная оценка: 1 токен ≈ 4 символа для русского
        $total_tokens += strlen($msg['content']) / 4;
    }
}

$training_cost_mini = ($total_tokens / 1000000) * 3; // $3 за 1M токенов (gpt-4o-mini)
$training_cost_4o = ($total_tokens / 1000000) * 25; // $25 за 1M токенов (gpt-4o)
$monthly_usage_cost_mini = (1000 * 1000 / 1000000) * 1.5; // 1000 генераций × 1000 токенов × $1.5
$monthly_usage_cost_4o = (1000 * 1000 / 1000000) * 15; // 1000 генераций × 1000 токенов × $15

echo "=== ОЦЕНКА СТОИМОСТИ ===\n\n";
echo "Токенов в датасете: ~" . number_format($total_tokens, 0, ',', ' ') . "\n\n";
echo "ОБУЧЕНИЕ:\n";
echo "  GPT-4o-mini: ~$" . number_format($training_cost_mini, 2) . " ⭐ РЕКОМЕНДУЕТСЯ\n";
echo "  GPT-4o: ~$" . number_format($training_cost_4o, 2) . "\n\n";
echo "ИСПОЛЬЗОВАНИЕ (1000 товаров/месяц):\n";
echo "  GPT-4o-mini: ~$" . number_format($monthly_usage_cost_mini, 2) . " ⭐ РЕКОМЕНДУЕТСЯ\n";
echo "  GPT-4o: ~$" . number_format($monthly_usage_cost_4o, 2) . "\n\n";

// ============================================================================
// ИНСТРУКЦИЯ
// ============================================================================

echo "=== СЛЕДУЮЩИЕ ШАГИ ===\n\n";
echo "1. Скачай файл: " . home_url($filename) . "\n\n";
echo "2. Загрузи на OpenAI Platform:\n";
echo "   https://platform.openai.com/storage/files\n";
echo "   Purpose: Fine-tune\n\n";
echo "3. Создай Fine-tuning задачу:\n";
echo "   https://platform.openai.com/finetune/create\n";
echo "   Model: gpt-4o-mini-2024-07-18 ⭐ РЕКОМЕНДУЕТСЯ\n";
echo "   Suffix: stroymaterials-seo-cat\n\n";
echo "4. Дождись завершения (30-60 минут)\n\n";
echo "5. Получи ID модели:\n";
echo "   ft:gpt-4o-mini:...:stroymaterials-seo-cat:...\n\n";
echo "6. Используй в WordPress:\n";
echo "   WooCommerce → AI Генерация → Модель → вставь ID\n\n";

echo "=== ПРЕИМУЩЕСТВА ЭТОГО ДАТАСЕТА ===\n\n";
echo "✓ Покрытие всех категорий магазина\n";
echo "✓ Оптимальный размер для обучения\n";
echo "✓ Низкая стоимость ($2-3)\n";
echo "✓ SEO-оптимизация встроена\n";
echo "✓ Быстрое обучение (30-60 мин)\n\n";

echo "=== SEO УЛУЧШЕНИЯ ===\n\n";
echo "Модель будет генерировать тексты которые:\n";
echo "✓ Проходят Yoast SEO проверки\n";
echo "✓ Короткие предложения (до 15 слов)\n";
echo "✓ Много переходных слов (>30%)\n";
echo "✓ Ключевая фраза в первом абзаце\n";
echo "✓ Ключевая фраза в H2 заголовках\n";
echo "✓ Правильная плотность ключевых слов\n\n";

echo "=== ГОТОВО ===\n\n";
echo "⚠️ УДАЛИ ЭТОТ СКРИПТ ПОСЛЕ ИСПОЛЬЗОВАНИЯ!\n";