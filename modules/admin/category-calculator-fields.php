<?php

if (!defined('ABSPATH')) exit;

add_action('acf/init', 'parusweb_register_category_calculator_stolyarka_only', 20);

function parusweb_register_category_calculator_stolyarka_only() {
    if (!function_exists('acf_add_local_field_group')) {
        return;
    }
    
    acf_add_local_field_group(array(
        'key' => 'group_calc_cat_stolyarka',
        'title' => 'Настройки размеров',
        'fields' => array(
            array(
                'key' => 'field_calc_cat_width_min',
                'label' => 'Ширина мин (мм)',
                'name' => 'calc_category_width_min',
                'type' => 'number',
                'placeholder' => '',
                'wrapper' => array('width' => '25'),
            ),
            array(
                'key' => 'field_calc_cat_width_max',
                'label' => 'Ширина макс (мм)',
                'name' => 'calc_category_width_max',
                'type' => 'number',
                'placeholder' => '',
                'wrapper' => array('width' => '25'),
            ),
            array(
                'key' => 'field_calc_cat_width_step',
                'label' => 'Ширина шаг (мм)',
                'name' => 'calc_category_width_step',
                'type' => 'number',
                'wrapper' => array('width' => '25'),
            ),
            array(
                'key' => 'field_calc_cat_thickness_min',
                'label' => 'Толщина мин (мм)',
                'name' => 'calc_category_thickness_min',
                'type' => 'number',
                'placeholder' => '',
                'wrapper' => array('width' => '25'),
            ),
            array(
                'key' => 'field_calc_cat_thickness_max',
                'label' => 'Толщина макс (мм)',
                'name' => 'calc_category_thickness_max',
                'type' => 'number',
                'placeholder' => '',
                'wrapper' => array('width' => '25'),
            ),
            array(
                'key' => 'field_calc_cat_thickness_step',
                'label' => 'Толщина шаг (мм)',
                'name' => 'calc_category_thickness_step',
                'type' => 'number',
                'wrapper' => array('width' => '25'),
            ),
            array(
                'key' => 'field_calc_cat_length_min',
                'label' => 'Длина мин (м)',
                'name' => 'calc_category_length_min',
                'type' => 'number',
                'step' => 0.01,
                'placeholder' => '',
                'wrapper' => array('width' => '25'),
            ),
            array(
                'key' => 'field_calc_cat_length_max',
                'label' => 'Длина макс (м)',
                'name' => 'calc_category_length_max',
                'type' => 'number',
                'step' => 0.01,
                'placeholder' => '',
                'wrapper' => array('width' => '25'),
            ),
            array(
                'key' => 'field_calc_cat_length_step',
                'label' => 'Длина шаг (м)',
                'name' => 'calc_category_length_step',
                'type' => 'number',
                'step' => 0.01,
                'wrapper' => array('width' => '25'),
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'taxonomy',
                    'operator' => '==',
                    'value' => 'product_cat',
                ),
            ),
        ),
    ));
}

add_filter('acf/prepare_field', 'parusweb_prepare_calc_field_stolyarka_only');

function parusweb_prepare_calc_field_stolyarka_only($field) {
    // Проверяем только поля калькулятора
    if (strpos($field['key'], 'field_calc_cat_') !== 0) {
        return $field;
    }
    
    // Проверяем что мы в админке на странице категории
    if (!is_admin()) {
        return $field;
    }
    
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
        return false; // Скрываем на странице добавления новой категории
    }
    
    // Столярные категории
    $stolyarka_categories = [265, 266, 267, 268, 270, 271, 273];
    
    // Проверяем категорию и её родителя
    $is_stolyarka = in_array($term_id, $stolyarka_categories);
    
    if (!$is_stolyarka) {
        $term = get_term($term_id, 'product_cat');
        if ($term && !is_wp_error($term) && $term->parent > 0) {
            $is_stolyarka = in_array($term->parent, $stolyarka_categories);
        }
    }
    
    // Если не столярная - не показываем поле
    if (!$is_stolyarka) {
        return false;
    }
    
    return $field;
}