<?php
/**
 * ============================================================================
 * МОДУЛЬ: ВЫБОР ТИПА ОПЛАТЫ (БЕЗНАЛ БЕЗ НДС / С НДС)
 * ============================================================================
 * 
 * ВЕРСИЯ 2.3.0 - ИСПРАВЛЕНО ДВОЙНОЕ ПРИМЕНЕНИЕ НАЦЕНКИ
 * 
 * КРИТИЧНО: Наценка применяется ТОЛЬКО РАЗ - в корзине!
 * На фронте показывается цена с наценкой, но в корзину идет БАЗОВАЯ цена.
 * 
 * @package ParusWeb_Functions
 * @subpackage Features
 * @version 2.3.0
 */

if (!defined('ABSPATH')) exit;

// ============================================================================
// НАСТРОЙКИ В АДМИНКЕ
// ============================================================================

function parusweb_payment_settings_menu() {
    add_submenu_page(
        'woocommerce',
        'Настройки безналичной оплаты',
        'Безналичная оплата',
        'manage_woocommerce',
        'parusweb-payment-settings',
        'parusweb_payment_settings_page'
    );
}
add_action('admin_menu', 'parusweb_payment_settings_menu');

function parusweb_payment_register_settings() {
    register_setting('parusweb_payment_settings', 'parusweb_payment_without_vat_percent');
    register_setting('parusweb_payment_settings', 'parusweb_payment_with_vat_percent');
}
add_action('admin_init', 'parusweb_payment_register_settings');

function parusweb_payment_settings_page() {
    ?>
    <div class="wrap">
        <h1>Настройки безналичной оплаты</h1>
        <form method="post" action="options.php">
            <?php settings_fields('parusweb_payment_settings'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="parusweb_payment_without_vat_percent">Процент наценки без НДС</label></th>
                    <td>
                        <input type="number" name="parusweb_payment_without_vat_percent" id="parusweb_payment_without_vat_percent"
                               value="<?php echo esc_attr(get_option('parusweb_payment_without_vat_percent', 10)); ?>" 
                               step="0.1" min="0" max="100" style="width: 100px;" /> %
                        <p class="description">По умолчанию: 10%</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="parusweb_payment_with_vat_percent">Процент наценки с НДС</label></th>
                    <td>
                        <input type="number" name="parusweb_payment_with_vat_percent" id="parusweb_payment_with_vat_percent"
                               value="<?php echo esc_attr(get_option('parusweb_payment_with_vat_percent', 22)); ?>" 
                               step="0.1" min="0" max="100" style="width: 100px;" /> %
                        <p class="description">По умолчанию: 22%</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function parusweb_get_payment_percent($type) {
    if ($type === 'without_vat') {
        return floatval(get_option('parusweb_payment_without_vat_percent', 10));
    } elseif ($type === 'with_vat') {
        return floatval(get_option('parusweb_payment_with_vat_percent', 22));
    }
    return 0;
}

// ============================================================================
// ВЫВОД БЛОКА ВЫБОРА
// ============================================================================

function parusweb_payment_type_selector_block() {
    static $already_rendered = false;
    if ($already_rendered) return;
    if (!is_product()) return;
    
    global $product;
    if (!$product) return;
    
    $already_rendered = true;
    
    $percent_without_vat = parusweb_get_payment_percent('without_vat');
    $percent_with_vat = parusweb_get_payment_percent('with_vat');
    
    $base_price = floatval($product->get_regular_price() ?: $product->get_price());
    
    ?>
    <div class="payment-type-selector" style="margin: 15px 0;">
        <h4 style="margin: 0 0 10px 0; font-size: 15px; font-weight: 600;">Выберите тип оплаты:</h4>
        
        <label class="payment-option active" data-type="cash" data-markup="0" style="position: relative; display: flex; align-items: center; padding: 12px 16px; border: 2px solid #8bc34a; border-radius: 8px; cursor: pointer; transition: all 0.2s; background: #f1f8e9; margin-bottom: 10px;">
            <input type="radio" name="payment_type_radio" value="cash" checked style="position: absolute; opacity: 0;">
            <span class="payment-checkmark" style="width: 24px; height: 24px; border: 2px solid #8bc34a; border-radius: 50%; background: #8bc34a; display: flex; align-items: center; justify-content: center; margin-right: 12px; flex-shrink: 0;">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 7L5.5 10.5L12 3.5" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            <div style="flex: 1;">
                <div style="font-weight: 600; font-size: 14px;">Наличными</div>
                <div style="font-size: 12px; color: #666;">Без наценки</div>
            </div>
            <div class="payment-price" style="font-size: 18px; font-weight: 700; color: #558b2f;" data-base-price="<?php echo $base_price; ?>">
                —
            </div>
        </label>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
            
            <label class="payment-option" data-type="without_vat" data-markup="<?php echo $percent_without_vat; ?>" style="position: relative; display: block; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; cursor: pointer; transition: all 0.2s;">
                <input type="radio" name="payment_type_radio" value="without_vat" style="position: absolute; opacity: 0;">
                <span class="payment-checkmark" style="position: absolute; top: 10px; right: 10px; width: 20px; height: 20px; border: 2px solid #e0e0e0; border-radius: 50%; background: #fff; display: none; align-items: center; justify-content: center;">
                    <svg width="12" height="12" viewBox="0 0 14 14" fill="none"><path d="M2 7L5.5 10.5L12 3.5" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <div style="margin-bottom: 6px;">
                    <div style="font-weight: 600; font-size: 13px; margin-bottom: 2px;">Безналичный расчёт без НДС</div>
                    <div style="font-size: 12px; color: #666;">+<?php echo round($percent_without_vat); ?>%</div>
                </div>
                <div class="payment-price" style="font-size: 17px; font-weight: 700;">
                    —
                </div>
            </label>
            
            <label class="payment-option" data-type="with_vat" data-markup="<?php echo $percent_with_vat; ?>" style="position: relative; display: block; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; cursor: pointer; transition: all 0.2s;">
                <input type="radio" name="payment_type_radio" value="with_vat" style="position: absolute; opacity: 0;">
                <span class="payment-checkmark" style="position: absolute; top: 10px; right: 10px; width: 20px; height: 20px; border: 2px solid #e0e0e0; border-radius: 50%; background: #fff; display: none; align-items: center; justify-content: center;">
                    <svg width="12" height="12" viewBox="0 0 14 14" fill="none"><path d="M2 7L5.5 10.5L12 3.5" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <div style="margin-bottom: 6px;">
                    <div style="font-weight: 600; font-size: 13px; margin-bottom: 2px;">Безналичный расчёт с НДС</div>
                    <div style="font-size: 12px; color: #666;">+<?php echo round($percent_with_vat); ?>%</div>
                </div>
                <div class="payment-price" style="font-size: 17px; font-weight: 700;">
                    —
                </div>
            </label>
            
        </div>
        
        <div class="payment-selected-info" style="display: none; margin-top: 10px; padding: 8px 12px; background: #e3f2fd; border-left: 4px solid #2196f3; border-radius: 4px; font-size: 13px;"></div>
    </div>
    
    <style>
        @media (max-width: 768px) {
            .payment-type-selector > div:last-of-type {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        
        const form = $('form.cart').first();
        if (!form.length) {
            return;
        }
        
        form.find('#payment_type').remove();
        form.find('#payment_markup').remove();
        form.find('#base_price_before_markup').remove();
        
        form.prepend('<input type="hidden" id="payment_type" name="payment_type" value="cash">');
        form.prepend('<input type="hidden" id="payment_markup" name="payment_markup" value="0">');
        form.prepend('<input type="hidden" id="base_price_before_markup" name="base_price_before_markup" value="0">');
        
        let currentMarkup = 0;
        let currentBasePrice = 0;
        
        window.updatePaymentCardsFromCalculator = function(basePrice) {
            if (!basePrice || basePrice <= 0) {
                return;
            }
            
            currentBasePrice = basePrice;
            $('#base_price_before_markup').val(basePrice);
            updateCardPrices(basePrice);
            updateDisplayPrice(basePrice);
            updateInfoBlock(basePrice);
        };
        
        window.getCurrentPaymentMarkup = function() {
            return currentMarkup;
        };
        
        function updateCardPrices(basePrice) {
            $('.payment-option').each(function() {
                const $option = $(this);
                const markup = parseFloat($option.data('markup'));
                const price = Math.round(basePrice * (1 + markup / 100));
                const formatted = price.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' ₽';
                $option.find('.payment-price').html(formatted);
            });
        }
        
        function updateDisplayPrice(basePrice) {
            const finalPrice = Math.round(basePrice * (1 + currentMarkup / 100));
            const $priceEl = $('p.price .woocommerce-Price-amount bdi').first();
            if ($priceEl.length > 0) {
                $priceEl.html(finalPrice + '&nbsp;<span class="woocommerce-Price-currencySymbol">₽</span>');
            }
        }
        
        function updateInfoBlock(basePrice) {
            const $info = $('.payment-selected-info');
            
            if (currentMarkup === 0) {
                $info.hide();
                return;
            }
            
            if (!basePrice || basePrice <= 0) {
                $info.hide();
                return;
            }
            
            const finalPrice = Math.round(basePrice * (1 + currentMarkup / 100));
            const type = $('#payment_type').val();
            
            let text = '<strong>Применена наценка +' + Math.round(currentMarkup) + '%</strong> ';
            text += '(' + (type === 'without_vat' ? 'безналичный расчёт без НДС' : 'безналичный расчёт с НДС') + ').<br>';
            text += 'Итоговая цена: <strong>' + finalPrice.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' ₽</strong>';
            
            $info.html(text).show();
        }
        
        const originalUpdatePrice = window.updateDisplayedProductPrice;
        if (originalUpdatePrice) {
            window.updateDisplayedProductPrice = function(basePrice, isCalculated) {
                currentBasePrice = basePrice;
                $('#base_price_before_markup').val(basePrice);
                const finalPrice = basePrice * (1 + currentMarkup / 100);
                const result = originalUpdatePrice.call(window, finalPrice, isCalculated);
                updateCardPrices(basePrice);
                updateInfoBlock(basePrice);
                return result;
            };
        }
        
        $('.payment-option').on('click', function() {
            const $this = $(this);
            const type = $this.data('type');
            const markup = parseFloat($this.data('markup'));
            
            $('.payment-option').removeClass('active').css({
                'border-color': '#e0e0e0',
                'background': '#fff'
            });
            $('.payment-option .payment-checkmark').css({
                'display': 'none',
                'background': '#fff',
                'border-color': '#e0e0e0'
            });
            
            $this.addClass('active').css({
                'border-color': '#8bc34a',
                'background': '#f1f8e9'
            });
            $this.find('.payment-checkmark').css({
                'display': 'flex',
                'background': '#8bc34a',
                'border-color': '#8bc34a'
            });
            
            $('#payment_type').val(type);
            $('#payment_markup').val(markup);
            currentMarkup = markup;
            
            if (currentBasePrice > 0) {
                updateDisplayPrice(currentBasePrice);
                updateInfoBlock(currentBasePrice);
            }
        });
        
        setTimeout(function() {
            const widthEl = document.getElementById('custom_width');
            const lengthEl = document.getElementById('custom_length');
            if (widthEl && lengthEl && widthEl.value && lengthEl.value) {
                if (typeof window.updateDimCalc === 'function') {
                    window.updateDimCalc(false);
                }
            }
            
            if (currentBasePrice === 0) {
                const $priceEl = $('p.price .woocommerce-Price-amount bdi').first();
                if ($priceEl.length > 0) {
                    const text = $priceEl.text().replace(/[^\d]/g, '');
                    const price = parseFloat(text);
                    if (!isNaN(price) && price > 0) {
                        window.updatePaymentCardsFromCalculator(price);
                    }
                }
            }
        }, 100);
    });
    </script>
    <?php
}

add_action('woocommerce_before_add_to_cart_form', 'parusweb_payment_type_selector_block', 16);

// ============================================================================
// КОРЗИНА И ЗАКАЗЫ - ИСПРАВЛЕНО!
// ============================================================================

add_filter('woocommerce_add_cart_item_data', 'parusweb_add_payment_type_to_cart', 10, 3);

function parusweb_add_payment_type_to_cart($cart_item_data, $product_id, $variation_id) {
    if (isset($_POST['payment_type']) && $_POST['payment_type'] !== 'cash') {
        $payment_type = sanitize_text_field($_POST['payment_type']);
        
        if (in_array($payment_type, ['without_vat', 'with_vat'])) {
            $markup = isset($_POST['payment_markup']) ? floatval($_POST['payment_markup']) : parusweb_get_payment_percent($payment_type);
            
            // ============================================================
            // КРИТИЧНО: Сохраняем БАЗОВУЮ цену без наценки!
            // ============================================================
            $base_price = isset($_POST['base_price_before_markup']) ? floatval($_POST['base_price_before_markup']) : 0;
            
            $cart_item_data['payment_type'] = $payment_type;
            $cart_item_data['payment_markup'] = $markup;
            $cart_item_data['base_price_before_markup'] = $base_price;
            
            error_log("=== ДОБАВЛЕНИЕ В КОРЗИНУ ===");
            error_log("Product ID: {$product_id}");
            error_log("Payment type: {$payment_type}");
            error_log("Markup: {$markup}%");
            error_log("Base price before markup: {$base_price} ₽");
        }
    }
    
    return $cart_item_data;
}

// ============================================================
// КРИТИЧНО: Применяем наценку ТОЛЬКО РАЗ в корзине!
// ============================================================
add_action('woocommerce_before_calculate_totals', 'parusweb_apply_payment_markup_to_cart', 100);

function parusweb_apply_payment_markup_to_cart($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    
    foreach ($cart->get_cart() as $cart_item) {
        if (isset($cart_item['payment_markup']) && $cart_item['payment_markup'] > 0) {
            
            // ============================================================
            // ИСПРАВЛЕНО: Берем БАЗОВУЮ цену, сохраненную при добавлении
            // ============================================================
            $base_price = 0;
            
            if (isset($cart_item['base_price_before_markup']) && $cart_item['base_price_before_markup'] > 0) {
                // Используем сохраненную базовую цену
                $base_price = floatval($cart_item['base_price_before_markup']);
            } else {
                // Fallback: берем текущую цену товара (без наценки)
                $base_price = floatval($cart_item['data']->get_price());
            }
            
            $markup = floatval($cart_item['payment_markup']);
            $new_price = $base_price * (1 + $markup / 100);
            
            error_log("=== ПРИМЕНЕНИЕ НАЦЕНКИ В КОРЗИНЕ ===");
            error_log("Base price: {$base_price} ₽");
            error_log("Markup: {$markup}%");
            error_log("New price: {$new_price} ₽");
            
            $cart_item['data']->set_price(round($new_price, 2));
        }
    }
}

add_filter('woocommerce_get_item_data', 'parusweb_display_payment_type_in_cart', 100, 2);

function parusweb_display_payment_type_in_cart($item_data, $cart_item) {
    if (isset($cart_item['payment_type'])) {
        $payment_type = $cart_item['payment_type'];
        $markup = isset($cart_item['payment_markup']) ? $cart_item['payment_markup'] : 0;
        
        $label = ($payment_type === 'without_vat') 
            ? sprintf('Безналичный расчёт без НДС (+%s%%)', round($markup))
            : sprintf('Безналичный расчёт с НДС (+%s%%)', round($markup));
        
        $item_data[] = [
            'key' => 'Тип оплаты',
            'value' => $label,
            'display' => $label
        ];
    }
    
    return $item_data;
}

add_filter('woocommerce_cart_item_price', 'parusweb_fix_cart_item_price_display', 10, 3);

function parusweb_fix_cart_item_price_display($price_html, $cart_item, $cart_item_key) {
    $product_id = $cart_item['product_id'];
    $product = $cart_item['data'];
    
    $timber_cats = array_merge([87, 310], range(88, 93));
    $leaf_cats = [190, 191, 301, 94];
    
    if (!has_term(array_merge($timber_cats, $leaf_cats), 'product_cat', $product_id)) {
        return $price_html;
    }
    
    if (function_exists('calculate_cubic_package_price')) {
        $base_price = floatval($product->get_price());
        $calc_result = calculate_cubic_package_price($product_id, $base_price);
        
        if ($calc_result && isset($calc_result['price_per_m2'])) {
            $display_price = $calc_result['price_per_m2'];
            
            if (isset($cart_item['payment_markup']) && $cart_item['payment_markup'] > 0) {
                $markup = floatval($cart_item['payment_markup']);
                $display_price = $display_price * (1 + $markup / 100);
            }
            
            return wc_price($display_price) . '<small class="woocommerce-price-suffix">за м²</small>';
        }
    }
    
    return $price_html;
}

add_action('woocommerce_checkout_create_order_line_item', 'parusweb_save_payment_type_to_order', 10, 4);

function parusweb_save_payment_type_to_order($item, $cart_item_key, $values, $order) {
    if (isset($values['payment_type'])) {
        $payment_type = $values['payment_type'];
        $markup = isset($values['payment_markup']) ? $values['payment_markup'] : 0;
        
        $label = ($payment_type === 'without_vat') 
            ? sprintf('Безналичный расчёт без НДС (+%s%%)', number_format($markup, 1))
            : sprintf('Безналичный расчёт с НДС (+%s%%)', number_format($markup, 1));
        
        $item->add_meta_data('Тип оплаты', $label, true);
        $item->add_meta_data('_payment_type', $payment_type, true);
        $item->add_meta_data('_payment_markup', $markup, true);
    }
}

add_action('woocommerce_admin_order_data_after_billing_address', 'parusweb_display_payment_summary_in_admin');

function parusweb_display_payment_summary_in_admin($order) {
    $payment_types = [];
    
    foreach ($order->get_items() as $item) {
        $payment_type = $item->get_meta('_payment_type');
        if ($payment_type) {
            $payment_types[] = $payment_type;
        }
    }
    
    if (!empty($payment_types)) {
        $unique_types = array_unique($payment_types);
        ?>
        <div class="order_data_column" style="clear:both; margin-top: 20px;">
            <h3>Тип оплаты</h3>
            <div class="address">
                <?php foreach ($unique_types as $type): 
                    $percent = parusweb_get_payment_percent($type);
                ?>
                    <p><strong><?php echo ($type === 'without_vat' ? 'Безналичный расчёт без НДС' : 'Безналичный расчёт с НДС'); ?> (+<?php echo number_format($percent, 1); ?>%)</strong></p>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
}