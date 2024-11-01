<?php

/*
  Plugin Name: WooCommerce Discounts and Dynamic Pricing
  Description: WooCommerce Discounts and Dynamic Pricing
  Author: azexo
  Author URI: http://azexo.com
  Version: 1.27.15
  Text Domain: azm
 */

add_action('plugins_loaded', 'azm_wpd_plugins_loaded');

function azm_wpd_plugins_loaded() {
    load_plugin_textdomain('azm', FALSE, basename(dirname(__FILE__)) . '/languages/');
}

add_action('admin_notices', 'azm_wpd_admin_notices');

function azm_wpd_admin_notices() {
    if (!defined('AZM_VERSION')) {
        $plugin_data = get_plugin_data(__FILE__);
        print '<div class="updated notice error is-dismissible"><p>' . $plugin_data['Name'] . ': ' . __('please install <a href="https://codecanyon.net/item/marketing-automation-by-azexo/21402648">Marketing Automation by AZEXO</a> plugin.', 'azm') . '</p><button class="notice-dismiss" type="button"><span class="screen-reader-text">' . esc_html__('Dismiss this notice.', 'azm') . '</span></button></div>';
    }
}

add_filter('azr_settings', 'azm_wpd_settings', 12);

function azm_wpd_settings($azr) {
    global $wpdb;
    $gmt_offset = get_option('gmt_offset') * HOUR_IN_SECONDS;

    $categories_options = array();
    $categories = get_terms(array(
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
    ));
    if ($categories && !is_wp_error($categories)) {
        foreach ($categories as $category) {
            $categories_options[$category->term_id] = $category->name;
        }
    }
    $tags_options = array();
    $tags = get_terms(array(
        'taxonomy' => 'product_tag',
        'hide_empty' => false,
    ));
    if ($tags && !is_wp_error($tags)) {
        foreach ($tags as $tag) {
            $tags_options[$tag->term_id] = $tag->name;
        }
    }
    $attributes_options = array();
    if (function_exists('wc_get_attribute_taxonomies')) {
        $attribute_taxonomies = wc_get_attribute_taxonomies();
        if ($attribute_taxonomies) {
            foreach ($attribute_taxonomies as $attribute_taxonomy) {
                $attributes = get_terms(array(
                    'taxonomy' => 'pa_' . $attribute_taxonomy->attribute_name,
                    'hide_empty' => false,
                ));
                if ($attributes && !is_wp_error($attributes)) {
                    foreach ($attributes as $attribute) {
                        $attributes_options[$attribute->term_id] = $attribute_taxonomy->attribute_label . ': ' . $attribute->name;
                    }
                }
            }
        }
    }
    $coupons = get_posts(array(
        'post_type' => 'shop_coupon',
        'post_status' => 'publish',
        'ignore_sticky_posts' => 1,
        'no_found_rows' => 1,
        'posts_per_page' => -1,
        'numberposts' => -1,
    ));
    $coupons_options = array();
    if (!empty($coupons)) {
        foreach ($coupons as $coupon) {
            $coupons_options[$coupon->post_title] = $coupon->post_title;
        }
    }


    $azr['events']['cart_calculate_discounts'] = array(
        'name' => __('Cart calculate discounts', 'azm'),
        'description' => __('All conditions will be linked with current site visitor', 'azm'),
        'set_context' => array('visitors' => true),
    );
    $azr['events']['product_price'] = array(
        'name' => __('Product price', 'azm'),
        'description' => __('All conditions will be linked with current site visitor', 'azm'),
        'set_context' => array('visitors' => true),
        'required_context' => array('visitors'),
    );
    $azr['actions']['cart_fixed_discount'] = array(
        'name' => __('Apply fixed discount to cart', 'azm'),
        'description' => __('Use "Products filter" conditions for specify discounted products', 'azm'),
        'group' => __('Cart discounts', 'azm'),
        'event_dependency' => array('cart_calculate_discounts'),
        'required_context' => array('visitors'),
        'parameters' => array(
            'name' => array(
                'type' => 'text',
                'label' => __('Name', 'azm'),
                'required' => true,
                'default' => 'Discount',
            ),
            'discount' => array(
                'type' => 'number',
                'label' => __('Discount', 'azm'),
                'required' => true,
                'default' => '10',
            ),
            'exclusivity' => array(
                'type' => 'dropdown',
                'label' => __('Exclusivity (per product or for all cart)', 'azm'),
                'required' => true,
                'options' => array(
                    'non_exclusive' => __('Apply with other applicable rules', 'azm'),
                    'exclusive_hard' => __('Apply this rule and disregard other rules', 'azm'),
                    'exclusive_soft' => __('Apply if other rules not applicable', 'azm'),
                ),
                'default' => 'non_exclusive',
            ),
        ),
    );
    $azr['actions']['cart_percentage_discount'] = array(
        'name' => __('Apply percentage discount to cart', 'azm'),
        'description' => __('Use "Products filter" conditions for specify discounted products', 'azm'),
        'group' => __('Cart discounts', 'azm'),
        'event_dependency' => array('cart_calculate_discounts'),
        'required_context' => array('visitors'),
        'parameters' => array(
            'name' => array(
                'type' => 'text',
                'label' => __('Name', 'azm'),
                'required' => true,
                'default' => 'Discount',
            ),
            'discount' => array(
                'type' => 'number',
                'label' => __('Discount (%)', 'azm'),
                'required' => true,
                'default' => '10',
            ),
            'exclusivity' => array(
                'type' => 'dropdown',
                'label' => __('Exclusivity (per product or for all cart)', 'azm'),
                'required' => true,
                'options' => array(
                    'non_exclusive' => __('Apply with other applicable rules', 'azm'),
                    'exclusive_hard' => __('Apply this rule and disregard other rules', 'azm'),
                    'exclusive_soft' => __('Apply if other rules not applicable', 'azm'),
                ),
                'default' => 'non_exclusive',
            ),
        ),
    );
    $azr['actions']['cart_points_discount'] = array(
        'name' => __('Apply points based discount to cart', 'azm'),
        'group' => __('Cart discounts', 'azm'),
        'event_dependency' => array('cart_calculate_discounts'),
        'required_context' => array('visitors'),
        'parameters' => array(
            'name' => array(
                'type' => 'text',
                'label' => __('Name', 'azm'),
                'required' => true,
                'default' => 'Discount',
            ),
            'rate' => array(
                'type' => 'number',
                'step' => '0.01',
                'label' => __('Conversion rate', 'azm'),
                'required' => true,
                'default' => '1',
            ),
            'exclusivity' => array(
                'type' => 'dropdown',
                'label' => __('Exclusivity (per product or for all cart)', 'azm'),
                'required' => true,
                'options' => array(
                    'non_exclusive' => __('Apply with other applicable rules', 'azm'),
                    'exclusive_hard' => __('Apply this rule and disregard other rules', 'azm'),
                    'exclusive_soft' => __('Apply if other rules not applicable', 'azm'),
                ),
                'default' => 'non_exclusive',
            ),
        ),
    );

    $azr['actions']['pricing_simple_adjustment'] = array(
        'name' => __('Apply simple adjustment to product price', 'azm'),
        'description' => __('Use "Products filter" conditions for specify discounted products', 'azm'),
        'group' => __('Product pricing', 'azm'),
        'event_dependency' => array('product_price'),
        'required_context' => array('visitors'),
        'parameters' => array(
            'name' => array(
                'type' => 'text',
                'label' => __('Name', 'azm'),
            ),
            'adjustment' => array(
                'type' => 'dropdown',
                'label' => __('Adjustment', 'azm'),
                'required' => true,
                'options' => array(
                    'fixed_discount' => __('Fixed discount', 'azm'),
                    'percentage_discount' => __('Percentage discount', 'azm'),
                    'fixed_price' => __('Fixed price', 'azm'),
                ),
                'default' => 'fixed_discount',
            ),
            'value' => array(
                'type' => 'number',
                'label' => __('Value', 'azm'),
                'required' => true,
                'step' => '0.01',
                'default' => '10',
            ),
            'exclusivity' => array(
                'type' => 'dropdown',
                'label' => __('Exclusivity', 'azm'),
                'required' => true,
                'options' => array(
                    'non_exclusive' => __('Apply with other applicable rules', 'azm'),
                    'exclusive_hard' => __('Apply this rule and disregard other rules', 'azm'),
                    'exclusive_soft' => __('Apply if other rules not applicable', 'azm'),
                ),
                'default' => 'non_exclusive',
            ),
        ),
    );

    $azr['actions']['bulk_pricing'] = array(
        'name' => __('Apply bulk pricing to product', 'azm'),
        'description' => __('Use "Products filter" conditions for specify discounted products', 'azm'),
        'group' => __('Product pricing', 'azm'),
        'event_dependency' => array('product_price'),
        'required_context' => array('visitors'),
        'parameters' => array(
            'name' => array(
                'type' => 'text',
                'label' => __('Name', 'azm'),
            ),
//            'quantities_based_on' => array(
//                'type' => 'dropdown',
//                'label' => __('Quantities Based On', 'azm'),
//                'required' => true,
//                'options' => array(
//                    'individual_product' => __('Each individual product', 'azm'),
//                    'individual_variation' => __('Each individual variation', 'azm'),
//                    'individual_configuration' => __('Each individual cart line item', 'azm'),
//                    'cumulative_categories' => __('Quantities added up by category', 'azm'),
//                    'cumulative_all' => __('All quantities added up', 'azm'),
//                ),
//                'default' => 'individual_product',
//            ),
            'quantity_ranges' => array(
                'type' => 'group',
                'label' => __('Quantity Ranges', 'azm'),
                'add_label' => __('Add Range', 'azm'),
                'required' => true,
                'fields' => array(
                    'from' => array(
                        'type' => 'number',
                        'label' => __('From', 'azm'),
                    ),
                    'to' => array(
                        'type' => 'number',
                        'label' => __('To', 'azm'),
                    ),
                    'adjustment' => array(
                        'type' => 'dropdown',
                        'label' => __('Adjustment', 'azm'),
                        'required' => true,
                        'options' => array(
                            'fixed_discount' => __('Fixed discount', 'azm'),
                            'percentage_discount' => __('Percentage discount', 'azm'),
                            'fixed_price' => __('Fixed price', 'azm'),
                        ),
                        'default' => 'fixed_discount',
                    ),
                    'value' => array(
                        'type' => 'number',
                        'label' => __('Value', 'azm'),
                        'required' => true,
                        'step' => '0.01',
                        'default' => '10',
                    ),
                ),
            ),
            'exclusivity' => array(
                'type' => 'dropdown',
                'label' => __('Exclusivity', 'azm'),
                'required' => true,
                'options' => array(
                    'non_exclusive' => __('Apply with other applicable rules', 'azm'),
                    'exclusive_hard' => __('Apply this rule and disregard other rules', 'azm'),
                    'exclusive_soft' => __('Apply if other rules not applicable', 'azm'),
                ),
                'default' => 'non_exclusive',
            ),
        ),
    );
    $azr['actions']['tiered_pricing'] = array(
        'name' => __('Apply tiered pricing to product', 'azm'),
        'description' => __('Use "Products filter" conditions for specify discounted products', 'azm'),
        'group' => __('Product pricing', 'azm'),
        'event_dependency' => array('product_price'),
        'required_context' => array('visitors'),
        'parameters' => array(
            'name' => array(
                'type' => 'text',
                'label' => __('Name', 'azm'),
            ),
            'quantity_ranges' => array(
                'type' => 'group',
                'label' => __('Quantity Tiers', 'azm'),
                'add_label' => __('Add Tier', 'azm'),
                'required' => true,
                'fields' => array(
                    'to' => array(
                        'type' => 'number',
                        'label' => __('Up to', 'azm'),
                    ),
                    'adjustment' => array(
                        'type' => 'dropdown',
                        'label' => __('Adjustment', 'azm'),
                        'required' => true,
                        'options' => array(
                            'no_discount' => __('No discount', 'azm'),
                            'fixed_discount' => __('Fixed discount', 'azm'),
                            'percentage_discount' => __('Percentage discount', 'azm'),
                            'fixed_price' => __('Fixed price', 'azm'),
                        ),
                        'default' => 'fixed_discount',
                    ),
                    'value' => array(
                        'type' => 'number',
                        'label' => __('Value', 'azm'),
                        'required' => true,
                        'step' => '0.01',
                        'default' => '10',
                        'dependencies' => array(
                            'adjustment' => array('fixed_discount', 'percentage_discount', 'fixed_price'),
                        ),
                    ),
                ),
            ),
            'exclusivity' => array(
                'type' => 'dropdown',
                'label' => __('Exclusivity', 'azm'),
                'required' => true,
                'options' => array(
                    'non_exclusive' => __('Apply with other applicable rules', 'azm'),
                    'exclusive_hard' => __('Apply this rule and disregard other rules', 'azm'),
                    'exclusive_soft' => __('Apply if other rules not applicable', 'azm'),
                ),
                'default' => 'non_exclusive',
            ),
        ),
    );

    $azr['actions']['buy_x_get_x'] = array(
        'name' => __('Apply "buy x and get x" pricing to product', 'azm'),
        'description' => __('Use "Products filter" conditions for specify discounted products', 'azm'),
        'group' => __('Product pricing', 'azm'),
        'event_dependency' => array('product_price'),
        'required_context' => array('visitors'),
        'parameters' => array(
            'name' => array(
                'type' => 'text',
                'label' => __('Name', 'azm'),
            ),
            'buy' => array(
                'type' => 'number',
                'label' => __('Buy (at full price)', 'azm'),
                'required' => true,
                'default' => '1',
            ),
            'get' => array(
                'type' => 'number',
                'label' => __('Get (Discounted)', 'azm'),
                'required' => true,
                'default' => '1',
            ),
            'adjustment' => array(
                'type' => 'dropdown',
                'label' => __('Adjustment', 'azm'),
                'required' => true,
                'options' => array(
                    'fixed_discount' => __('Fixed discount', 'azm'),
                    'percentage_discount' => __('Percentage discount', 'azm'),
                    'fixed_price' => __('Fixed price', 'azm'),
                ),
                'default' => 'fixed_discount',
            ),
            'value' => array(
                'type' => 'number',
                'label' => __('Value', 'azm'),
                'required' => true,
                'step' => '0.01',
                'default' => '10',
            ),
            'exclusivity' => array(
                'type' => 'dropdown',
                'label' => __('Exclusivity', 'azm'),
                'required' => true,
                'options' => array(
                    'non_exclusive' => __('Apply with other applicable rules', 'azm'),
                    'exclusive_hard' => __('Apply this rule and disregard other rules', 'azm'),
                    'exclusive_soft' => __('Apply if other rules not applicable', 'azm'),
                ),
                'default' => 'non_exclusive',
            ),
        ),
    );

    $azr['conditions']['cart_subtotal']['event_dependency'][] = 'cart_calculate_discounts';
    $azr['conditions']['cart_items_count']['event_dependency'][] = 'cart_calculate_discounts';
    $azr['conditions']['cart_total_weight']['event_dependency'][] = 'cart_calculate_discounts';
    $azr['conditions']['cart_total_quantity']['event_dependency'][] = 'cart_calculate_discounts';
    $azr['conditions']['coupons_applied']['event_dependency'][] = 'cart_calculate_discounts';
    $azr['conditions']['cart_contain_product']['event_dependency'][] = 'cart_calculate_discounts';
    $azr['conditions']['cart_contain_product_variation']['event_dependency'][] = 'cart_calculate_discounts';
    $azr['conditions']['cart_contain_product_tag']['event_dependency'][] = 'cart_calculate_discounts';
    $azr['conditions']['cart_contain_product_category']['event_dependency'][] = 'cart_calculate_discounts';
    $azr['conditions']['cart_contain_product_attribute']['event_dependency'][] = 'cart_calculate_discounts';
    $azr['conditions']['cart_contain_product_quantity']['event_dependency'][] = 'cart_calculate_discounts';
    $azr['conditions']['cart_contain_product_variation_quantity']['event_dependency'][] = 'cart_calculate_discounts';
    $azr['conditions']['cart_contain_product_tag_quantity']['event_dependency'][] = 'cart_calculate_discounts';
    $azr['conditions']['cart_contain_product_category_quantity']['event_dependency'][] = 'cart_calculate_discounts';
    $azr['conditions']['cart_contain_product_attribute_quantity']['event_dependency'][] = 'cart_calculate_discounts';
    $azr['conditions']['cart_contain_product_subtotal']['event_dependency'][] = 'cart_calculate_discounts';
    $azr['conditions']['cart_contain_product_variation_subtotal']['event_dependency'][] = 'cart_calculate_discounts';
    $azr['conditions']['cart_contain_product_tag_subtotal']['event_dependency'][] = 'cart_calculate_discounts';
    $azr['conditions']['cart_contain_product_category_subtotal']['event_dependency'][] = 'cart_calculate_discounts';
    $azr['conditions']['cart_contain_product_attribute_subtotal']['event_dependency'][] = 'cart_calculate_discounts';

    $azr['actions']['product_text'] = array(
        'name' => __('Show text inside porduct template', 'azm'),
        'group' => __('Product', 'azm'),
        'event_dependency' => array('visit'),
        'required_context' => array('visitors'),
        'parameters' => array(
            'position' => array(
                'type' => 'dropdown',
                'label' => __('Position', 'azm'),
                'required' => true,
                'options' => array(
                    'woocommerce_after_shop_loop_item_title|9' => __('Loop product - Price - Before', 'azm'),
                    'woocommerce_after_shop_loop_item_title|11' => __('Loop product - Price - After', 'azm'),
                    'woocommerce_after_shop_loop_item|9' => __('Loop product - Add to cart - Before', 'azm'),
                    'woocommerce_after_shop_loop_item|11' => __('Loop product - Add to cart - After', 'azm'),
                    'woocommerce_single_product_summary|9' => __('Single product - Price - Before', 'azm'),
                    'woocommerce_single_product_summary|11' => __('Single product - Price - After', 'azm'),
                    'woocommerce_before_add_to_cart_form|10' => __('Single product - Add to cart - Before', 'azm'),
                    'woocommerce_after_add_to_cart_form|10' => __('Single product - Add to cart - After', 'azm'),
                    'woocommerce_product_meta_start|10' => __('Single product - Product meta - Before', 'azm'),
                    'woocommerce_product_meta_end|10' => __('Single product - Product meta - After', 'azm'),
                    'woocommerce_single_product_summary|19' => __('Single product - Product summary - Before', 'azm'),
                    'woocommerce_single_product_summary|21' => __('Single product - Product summary - After', 'azm'),
                ),
                'default' => 'woocommerce_before_add_to_cart_form|10',
            ),
            'text' => array(
                'type' => 'richtext',
                'label' => __('Text', 'azm'),
            ),
        ),
    );
    
    return $azr;
}

$context = apply_filters('azr_rule_init', $context, $rule);
add_filter('azr_rule_init', 'azm_wpd_rule_init', 10, 2);

function azm_wpd_rule_init($context, $rule) {
    switch ($rule['event']['type']) {
        case 'cart_calculate_discounts':
            global $azm_cart_discounts_rules, $azm_cart_products_discounts_rules;
            if (!isset($azm_cart_discounts_rules)) {
                $azm_cart_discounts_rules = array();
            }
            if (!isset($azm_cart_products_discounts_rules)) {
                $azm_cart_products_discounts_rules = array();
            }
            add_action('woocommerce_cart_calculate_fees', function ($cart) use($rule, $context) {
                $context['cart'] = $cart;
                $context['cart_contents'] = $cart->get_cart_for_session();
                $context['product_id'] = array();
                $lines = $cart->get_cart_contents();
                foreach ($lines as $line) {
                    if (isset($line['data'])) {
                        $context['product_id'][] = $line['data']->get_id();
                    }
                }
                $context['visitor_id'] = azr_get_current_visitor();
                azr_process_rule($rule, $context);
            }, 100, 1);
            break;
        case 'product_price':
            global $azm_product_pricing_rules, $azm_bulk_pricing_rules, $azm_tiered_pricing_rules, $azm_buy_x_get_x_pricing_rules;
            if (!isset($azm_product_pricing_rules)) {
                $azm_product_pricing_rules = array();
            }
            if (!isset($azm_bulk_pricing_rules)) {
                $azm_bulk_pricing_rules = array();
            }
            if (!isset($azm_buy_x_get_x_pricing_rules)) {
                $azm_buy_x_get_x_pricing_rules = array();
            }
            if (!isset($azm_tiered_pricing_rules)) {
                $azm_tiered_pricing_rules = array();
            }
            add_action('woocommerce_before_calculate_totals', function ($cart) use($rule, $context) {
                if ((!empty($_POST['apply_coupon']) && !empty($_POST['coupon_code']))) {
                    if (!did_action('woocommerce_applied_coupon')) {
                        return;
                    }
                }
                $context['cart'] = $cart;
                $context['product_id'] = array();
                $lines = $cart->get_cart_contents();
                foreach ($lines as $line) {
                    if (isset($line['data'])) {
                        $context['product_id'][] = $line['data']->get_id();
                    }
                }
                $context['visitor_id'] = azr_get_current_visitor();
                azr_process_rule($rule, $context);
            }, 100);
            add_action('woocommerce_applied_coupon', function ($cart) use($rule, $context) {
                $context['cart'] = $cart;
                $context['product_id'] = array();
                $lines = $cart->get_cart_contents();
                foreach ($lines as $line) {
                    if (isset($line['data'])) {
                        $context['product_id'][] = $line['data']->get_id();
                    }
                }
                $context['visitor_id'] = azr_get_current_visitor();
                azr_process_rule($rule, $context);
            }, 100);
            add_filter('woocommerce_product_get_price', function ($price, $product) use($rule, $context) {
                $context['product'] = $product;
                $context['product_id'] = $product->get_id();
                $context['visitor_id'] = azr_get_current_visitor();
                $context = azr_process_rule($rule, $context);
                return $price;
            }, 100, 2);
            add_filter('woocommerce_product_variation_get_price', function ($price, $product) use($rule, $context) {
                $context['product'] = $product;
                $context['product_id'] = $product->get_id();
                $context['visitor_id'] = azr_get_current_visitor();
                $context = azr_process_rule($rule, $context);
                return $price;
            }, 100, 2);
            add_filter('woocommerce_variation_prices_price', function ($price, $variation, $product) use($rule, $context) {
                $context['product'] = $variation;
                $context['product_id'] = $variation->get_id();
                $context['visitor_id'] = azr_get_current_visitor();
                $context = azr_process_rule($rule, $context);
                return $price;
            }, 100, 3);
            $settings = get_option('azh-woo-settings', array());
            if ($settings['vpt-enabled'] && $settings['vpt-position']) {
                $position = explode('|', $settings['vpt-position']);
                add_action($position[0], function () use($rule, $context) {
                    global $product;
                    $context['volume_pricing_table'] = true;
                    $context['product'] = $product;
                    $context['product_id'] = $product->get_id();
                    $context['visitor_id'] = azr_get_current_visitor();
                    $context = azr_process_rule($rule, $context);
                }, $position[0]);
            }
            break;
    }
    return $context;
}

add_action('woocommerce_cart_calculate_fees', function ($cart) {
    $settings = get_option('azh-woo-settings', array());
    if (!isset($settings['discount-rule-selection-method'])) {
        $settings['discount-rule-selection-method'] = 'all';
    }

    global $azm_cart_discounts_rules, $azm_cart_products_discounts_rules;
    $cart_discounts = array();
    if (is_array($azm_cart_discounts_rules)) {
        switch ($settings['discount-rule-selection-method']) {
            case 'all':
                foreach ($azm_cart_discounts_rules as $rule_id => $discount_rule) {
                    switch ($discount_rule['action']['exclusivity']) {
                        case 'non_exclusive':
                            $cart_discounts[$discount_rule['action']['name']] = $discount_rule['discount'];
                            break 1;
                        case 'exclusive_hard':
                            $cart_discounts = array($discount_rule['action']['name'] => $discount_rule['discount']);
                            break 2;
                        case 'exclusive_soft':
                            if (count($azm_cart_discounts_rules) == 1) {
                                $cart_discounts = array($discount_rule['action']['name'] => $discount_rule['discount']);
                                break 2;
                            }
                            break 1;
                    }
                }
                break;
            case 'smaller_discount':
                $discounts = array();
                foreach ($azm_cart_discounts_rules as $rule_id => $discount_rule) {
                    $discounts[$discount_rule['action']['name']] = $discount_rule['discount'];
                }
                if (!empty($discounts)) {
                    asort($discounts);
                    reset($discounts);
                    $cart_discounts = array(key($discounts) => current($discounts));
                }
                break;
            case 'bigger_discount':
                $discounts = array();
                foreach ($azm_cart_discounts_rules as $rule_id => $discount_rule) {
                    $discounts[$discount_rule['action']['name']] = $discount_rule['discount'];
                }
                if (!empty($discounts)) {
                    asort($discounts);
                    end($discounts);
                    $cart_discounts = array(key($discounts) => current($discounts));
                }
                break;
        }
    }
    foreach ($cart_discounts as $name => $discount) {
        $cart->add_fee($name, -$discount);
    }

    $lines = $cart->get_cart_contents();
    $cart_products_discounts = array();
    foreach ($lines as $line) {
        if (isset($line['data'])) {
            $product_id = $line['data']->get_id();
            if (isset($azm_cart_products_discounts_rules[$product_id])) {
                switch ($settings['discount-rule-selection-method']) {
                    case 'all':
                        foreach ($azm_cart_products_discounts_rules[$product_id] as $rule_id => $discount_rule) {
                            switch ($discount_rule['action']['exclusivity']) {
                                case 'non_exclusive':
                                    $cart_products_discounts[$product_id][$discount_rule['action']['name']] = $discount_rule['discount'];
                                    break 1;
                                case 'exclusive_hard':
                                    $cart_products_discounts[$product_id] = array($discount_rule['action']['name'] => $discount_rule['discount']);
                                    break 2;
                                case 'exclusive_soft':
                                    if (count($azm_cart_discounts_rules) == 1) {
                                        $cart_products_discounts[$product_id] = array($discount_rule['action']['name'] => $discount_rule['discount']);
                                        break 2;
                                    }
                                    break 1;
                            }
                        }
                        break;
                    case 'smaller_discount':
                        $discounts = array();
                        foreach ($azm_cart_products_discounts_rules[$product_id] as $rule_id => $discount_rule) {
                            $discounts[$discount_rule['action']['name']] = $discount_rule['discount'];
                        }
                        if (!empty($discounts)) {
                            asort($discounts);
                            reset($discounts);
                            $cart_products_discounts[$product_id] = array(key($discounts) => current($discounts));
                        }
                        break;
                    case 'bigger_discount':
                        $discounts = array();
                        foreach ($azm_cart_products_discounts_rules[$product_id] as $rule_id => $discount_rule) {
                            $discounts[$discount_rule['action']['name']] = $discount_rule['discount'];
                        }
                        if (!empty($discounts)) {
                            asort($discounts);
                            end($discounts);
                            $cart_products_discounts[$product_id] = array(key($discounts) => current($discounts));
                        }
                        break;
                }
            }
        }
    }
    $cart_discounts = array();
    foreach ($cart_products_discounts as $product_id => $discounts) {
        foreach ($discounts as $name => $discount) {
            if (!isset($cart_discounts[$name])) {
                $cart_discounts[$name] = 0;
            }
            $cart_discounts[$name] = $cart_discounts[$name] + $discount;
        }
    }
    foreach ($cart_discounts as $name => $discount) {
        $cart->add_fee($name, -$discount);
    }
}, 101, 1);

function azm_wpd_apply_price_rules_to_product($product, $base_price = false) {
    $settings = get_option('azh-woo-settings', array());
    if (!isset($settings['pricing-rule-selection-method'])) {
        $settings['pricing-rule-selection-method'] = 'all';
    }

    global $azm_product_pricing_rules, $azm_bulk_pricing_rules, $azm_tiered_pricing_rules, $azm_buy_x_get_x_pricing_rules;

    $price = $base_price;

    $pricing_rules = array();
    if ($azm_product_pricing_rules) {
        $pricing_rules = $azm_product_pricing_rules;
    }
    if ($azm_bulk_pricing_rules) {
        foreach ($azm_bulk_pricing_rules as $product_id => $bulk_pricing_rules) {
            foreach ($bulk_pricing_rules as $rule_id => $price_rule) {
                $pricing_rules[$product_id][$rule_id] = $price_rule;
            }
        }
    }
    if ($azm_tiered_pricing_rules) {
        foreach ($azm_tiered_pricing_rules as $product_id => $tiered_pricing_rules) {
            foreach ($tiered_pricing_rules as $rule_id => $price_rule) {
                $pricing_rules[$product_id][$rule_id] = $price_rule;
            }
        }
    }
    if ($azm_buy_x_get_x_pricing_rules) {
        foreach ($azm_buy_x_get_x_pricing_rules as $product_id => $buy_x_get_x_pricing_rules) {
            foreach ($buy_x_get_x_pricing_rules as $rule_id => $price_rule) {
                $pricing_rules[$product_id][$rule_id] = $price_rule;
            }
        }
    }
    if (isset($pricing_rules[$product->get_id()])) {
        switch ($settings['pricing-rule-selection-method']) {
            case 'all':
                foreach ($pricing_rules[$product->get_id()] as $rule_id => $price_rule) {
                    switch ($price_rule['action']['exclusivity']) {
                        case 'non_exclusive':
                            $price = $price_rule['callback']($price);
                            break 1;
                        case 'exclusive_hard':
                            $price = $price_rule['callback']($base_price);
                            break 2;
                        case 'exclusive_soft':
                            if (count($pricing_rules[$product->get_id()]) == 1) {
                                $price = $price_rule['callback']($price);
                                break 2;
                            }
                            break 1;
                    }
                }
                break;
            case 'smaller_price':
                $prices = array();
                foreach ($pricing_rules[$product->get_id()] as $rule_id => $price_rule) {
                    $prices[] = $price_rule['callback']($base_price);
                }
                sort($prices);
                $price = reset($prices);
                break;
            case 'bigger_price':
                $prices = array();
                foreach ($pricing_rules[$product->get_id()] as $rule_id => $price_rule) {
                    $prices[] = $price_rule['callback']($base_price);
                }
                sort($prices);
                $price = end($prices);
                break;
        }
    }
    return $price;
}

//function azm_wpd_apply_price_rules_to_cart($cart) {
//    $lines = $cart->get_cart_contents();
//    foreach ($lines as $line) {
//        if (isset($line['data'])) {
//            azm_wpd_apply_price_rules_to_product($line['data']);
//        }
//    }
//}
//add_action('woocommerce_before_calculate_totals', function ($cart) {
//    if ((!empty($_POST['apply_coupon']) && !empty($_POST['coupon_code']))) {
//        if (!did_action('woocommerce_applied_coupon')) {
//            return;
//        }
//    }
//    azm_wpd_apply_price_rules_to_cart($cart);
//}, 101);
//add_action('woocommerce_applied_coupon', function ($cart) {
//    azm_wpd_apply_price_rules_to_cart($cart);
//}, 101);

add_filter('woocommerce_product_get_price', function ($price, $product) {
    return azm_wpd_apply_price_rules_to_product($product, $price);
}, 101, 2);
add_filter('woocommerce_product_variation_get_price', function ($price, $product) {
    return azm_wpd_apply_price_rules_to_product($product, $price);
}, 101, 2);
add_filter('woocommerce_variation_prices_price', function ($price, $variation, $product) {
    return azm_wpd_apply_price_rules_to_product($variation, $price);
}, 101, 3);

add_filter('woocommerce_product_get_sale_price', function ($price, $product) {
    return azm_wpd_apply_price_rules_to_product($product, $price);
}, 101, 2);
add_filter('woocommerce_product_variation_get_sale_price', function ($price, $product) {
    return azm_wpd_apply_price_rules_to_product($product, $price);
}, 101, 2);
add_filter('woocommerce_variation_prices_sale_price', function ($price, $variation, $product) {
    return azm_wpd_apply_price_rules_to_product($variation, $price);
}, 101, 3);

add_filter('woocommerce_cart_item_price', function($price_html, $cart_item, $cart_item_key) {
    global $azm_product_pricing_rules, $azm_bulk_pricing_rules, $azm_tiered_pricing_rules, $azm_buy_x_get_x_pricing_rules;
    $product = $cart_item['data'];
    if ($azm_product_pricing_rules && isset($azm_product_pricing_rules[$product->get_id()]) || $azm_bulk_pricing_rules && isset($azm_bulk_pricing_rules[$product->get_id()])) {
        $price_html = wc_format_sale_price(wc_get_price_to_display($product, array('price' => $product->get_regular_price())), wc_get_price_to_display($product)) . $product->get_price_suffix();
        return $price_html;
    }
    if ($azm_tiered_pricing_rules) {
        if (isset($azm_tiered_pricing_rules[$product->get_id()])) {
            foreach ($azm_tiered_pricing_rules[$product->get_id()] as $pricing_rule) {
                if ($pricing_rule['display_callback']) {
                    return $pricing_rule['display_callback']($cart_item);
                }
            }
        }
    }
    if ($azm_buy_x_get_x_pricing_rules) {
        if (isset($azm_buy_x_get_x_pricing_rules[$product->get_id()])) {
            foreach ($azm_buy_x_get_x_pricing_rules[$product->get_id()] as $pricing_rule) {
                if ($pricing_rule['display_callback']) {
                    return $pricing_rule['display_callback']($cart_item);
                }
            }
        }
    }
    return $price_html;
}, 100, 3);

add_filter('azr_process_condition', 'azm_wpd_process_condition', 10, 3);

function azm_wpd_process_condition($result, $context, $condition) {
    return $result;
}

add_filter('azr_process_action', 'azm_wpd_process_action', 10, 2);

function azm_wpd_process_action($context, $action) {
    switch ($action['type']) {
        case 'product_text':
            global $wpdb;
            $db_query = azr_get_db_query($context['visitors']);
            $visitors = $wpdb->get_results($db_query, ARRAY_A);
            $visitors = array_map(function($value) {
                return $value['visitor_id'];
            }, $visitors);
            $visitors = array_filter($visitors);
            $visitors = array_unique($visitors);
            foreach ($visitors as $visitor_id) {
                if ($visitor_id == $context['visitor_id']) {
                    $position = explode('|', $action['position']);
                    add_action($position[0], function () use($action, $context) {
                        global $product;
                        static $products = array();
                        if (isset($context['products']) && !isset($products[(int) $product->get_id()])) {
                            global $wpdb;
                            if (is_array($context['products']['where'])) {
                                foreach ($context['products']['where'] as &$where) {
                                    $where = str_replace('{product_id}', $product->get_id(), $where);
                                }
                            }
                            $db_query = azr_get_db_query($context['products']);
                            $results = $wpdb->get_results($db_query, ARRAY_A);
                            $results = array_map(function($value) {
                                return (int) $value['ID'];
                            }, $results);
                            $results = array_filter($results);
                            $products[(int) $product->get_id()] = array_unique($results);
                        }
                        if (!empty($products) && in_array((int) $product->get_id(), $products[(int) $product->get_id()])) {
                            print base64_decode($action['text'], ENT_QUOTES);
                        }
                    }, $position[1]);
                }
            }
            break;
        case 'cart_fixed_discount':
            if (isset($context['visitors']) && isset($context['cart'])) {
                global $wpdb;
                $db_query = azr_get_db_query($context['visitors']);
                $visitors = $wpdb->get_results($db_query, ARRAY_A);
                $visitors = array_map(function($value) {
                    return $value['visitor_id'];
                }, $visitors);
                $visitors = array_filter($visitors);
                $visitors = array_unique($visitors);
                foreach ($visitors as $visitor_id) {
                    if ($visitor_id == $context['visitor_id']) {
                        global $azm_cart_discounts_rules, $azm_cart_products_discounts_rules;
                        if (isset($context['products'])) {
                            $db_query = azr_get_db_query($context['products']);
                            $products = $wpdb->get_results($db_query, ARRAY_A);
                            $products = array_map(function($value) {
                                return $value['ID'];
                            }, $products);
                            $products = array_filter($products);
                            $products = array_unique($products);
                            if (!empty($products)) {
                                $amount = 0;
                                $lines = $context['cart']->get_cart_contents();
                                foreach ($lines as $line) {
                                    if (isset($line['data'])) {
                                        $product_id = $line['data']->get_id();
                                        if (in_array($product_id, $products)) {
                                            $amount = $amount + $action['discount'];

                                            $azm_cart_products_discounts_rules[$product_id][$context['rule']] = array(
                                                'action' => $action,
                                                'discount' => $action['discount'],
                                            );
                                        }
                                    }
                                }
//                                if ($amount > 0) {
//                                    $context['cart']->add_fee($action['name'], -$amount);
//                                }
                            }
                        } else {
//                            $context['cart']->add_fee($action['name'], -$action['discount']);

                            $azm_cart_discounts_rules[$context['rule']] = array(
                                'action' => $action,
                                'discount' => $action['discount'],
                            );
                        }
                        break;
                    }
                }
                azr_action_executed($context['rule']);
                azr_visitors_prcessed($context['rule'], count($visitors));
            }
            break;
        case 'cart_percentage_discount':
            if (isset($context['visitors']) && isset($context['cart'])) {
                global $wpdb;
                $db_query = azr_get_db_query($context['visitors']);
                $visitors = $wpdb->get_results($db_query, ARRAY_A);
                $visitors = array_map(function($value) {
                    return $value['visitor_id'];
                }, $visitors);
                $visitors = array_filter($visitors);
                $visitors = array_unique($visitors);
                foreach ($visitors as $visitor_id) {
                    if ($visitor_id == $context['visitor_id']) {
                        global $azm_cart_discounts_rules, $azm_cart_products_discounts_rules;
                        if (isset($context['products'])) {
                            $db_query = azr_get_db_query($context['products']);
                            $products = $wpdb->get_results($db_query, ARRAY_A);
                            $products = array_map(function($value) {
                                return $value['ID'];
                            }, $products);
                            $products = array_filter($products);
                            $products = array_unique($products);
                            if (!empty($products)) {
                                $amount = 0;
                                $lines = $context['cart']->get_cart_contents();
                                foreach ($lines as $line) {
                                    if (isset($line['data'])) {
                                        $product_id = $line['data']->get_id();
                                        if (in_array($product_id, $products)) {
                                            $amount = $amount + $line['line_subtotal'] * $action['discount'] / 100;

                                            $azm_cart_products_discounts_rules[$product_id][$context['rule']] = array(
                                                'action' => $action,
                                                'discount' => $line['line_subtotal'] * $action['discount'] / 100,
                                            );
                                        }
                                    }
                                }
//                                if ($amount > 0) {
//                                    $context['cart']->add_fee($action['name'], -$amount);
//                                }
                            }
                        } else {
                            $amount = $context['cart']->get_subtotal() * $action['discount'] / 100;
//                            $context['cart']->add_fee($action['name'], -$amount);

                            $azm_cart_discounts_rules[$context['rule']] = array(
                                'action' => $action,
                                'discount' => $amount,
                            );
                        }
                        break;
                    }
                }
                azr_action_executed($context['rule']);
                azr_visitors_prcessed($context['rule'], count($visitors));
            }
            break;
        case 'cart_points_discount':
            if (isset($context['visitors']) && isset($context['cart'])) {
                global $wpdb;
                $db_query = azr_get_db_query($context['visitors']);
                $visitors = $wpdb->get_results($db_query, ARRAY_A);
                $visitors = array_map(function($value) {
                    return $value['visitor_id'];
                }, $visitors);
                $visitors = array_filter($visitors);
                $visitors = array_unique($visitors);
                foreach ($visitors as $visitor_id) {
                    if ($visitor_id == $context['visitor_id']) {
                        global $azm_cart_discounts_rules;
                        $points = $wpdb->get_var("SELECT points FROM {$wpdb->prefix}azr_visitors WHERE visitor_id = $visitor_id");
                        if ($points && $points > 0) {
                            $amount = $points * $action['ratio'];
//                            $context['cart']->add_fee($action['name'], -$amount);

                            $azm_cart_discounts_rules[$context['rule']] = array(
                                'action' => $action,
                                'discount' => $amount,
                            );
                        }
                    }
                }
                azr_action_executed($context['rule']);
                azr_visitors_prcessed($context['rule'], count($visitors));
            }
            break;
        case 'pricing_simple_adjustment':
            if (isset($context['visitors'])) {
                global $wpdb;
                $db_query = azr_get_db_query($context['visitors']);
                $visitors = $wpdb->get_results($db_query, ARRAY_A);
                $visitors = array_map(function($value) {
                    return $value['visitor_id'];
                }, $visitors);
                $visitors = array_filter($visitors);
                $visitors = array_unique($visitors);
                foreach ($visitors as $visitor_id) {
                    if ($visitor_id == $context['visitor_id']) {
                        $products = false;
                        if (isset($context['products'])) {
                            $db_query = azr_get_db_query($context['products']);
                            $products = $wpdb->get_results($db_query, ARRAY_A);
                            $products = array_map(function($value) {
                                return $value['ID'];
                            }, $products);
                            $products = array_filter($products);
                            $products = array_unique($products);
                        }
                        if (isset($context['cart'])) {
                            $lines = $context['cart']->get_cart_contents();
                            foreach ($lines as $line) {
                                if (isset($line['data'])) {
                                    $product_id = $line['data']->get_id();
                                    if ($products === false || in_array($product_id, $products)) {
                                        $product = $line['data'];

                                        global $azm_product_pricing_rules;
                                        $azm_product_pricing_rules[$product_id][$context['rule']] = array(
                                            'action' => $action,
                                            'callback' => function($base_price) use($action) {
                                                switch ($action['adjustment']) {
                                                    case 'fixed_discount':
                                                        return $base_price - $action['value'];
                                                        break;
                                                    case 'percentage_discount':
                                                        return $base_price - $base_price * $action['value'] / 100;
                                                        break;
                                                    case 'fixed_price':
                                                        return $action['value'];
                                                        break;
                                                }
                                                return $base_price;
                                            }
                                        );
                                    }
                                }
                            }
                        }
                        if (isset($context['product'])) {
                            $product = $context['product'];
                            if ($products === false || in_array($product->get_id(), $products)) {
                                global $azm_product_pricing_rules;
                                $azm_product_pricing_rules[$product->get_id()][$context['rule']] = array(
                                    'action' => $action,
                                    'callback' => function($base_price) use($action) {
                                        switch ($action['adjustment']) {
                                            case 'fixed_discount':
                                                return $base_price - $action['value'];
                                                break;
                                            case 'percentage_discount':
                                                return $base_price - $base_price * $action['value'] / 100;
                                                break;
                                            case 'fixed_price':
                                                return $action['value'];
                                                break;
                                        }
                                        return $base_price;
                                    }
                                );
                            }
                        }
                    }
                }
            }
            break;
        case 'bulk_pricing':
            if (isset($context['visitors'])) {
                global $wpdb;
                $db_query = azr_get_db_query($context['visitors']);
                $visitors = $wpdb->get_results($db_query, ARRAY_A);
                $visitors = array_map(function($value) {
                    return $value['visitor_id'];
                }, $visitors);
                $visitors = array_filter($visitors);
                $visitors = array_unique($visitors);
                foreach ($visitors as $visitor_id) {
                    if ($visitor_id == $context['visitor_id']) {
                        $products = false;
                        if (isset($context['products'])) {
                            $db_query = azr_get_db_query($context['products']);
                            $products = $wpdb->get_results($db_query, ARRAY_A);
                            $products = array_map(function($value) {
                                return $value['ID'];
                            }, $products);
                            $products = array_filter($products);
                            $products = array_unique($products);
                        }
                        if (isset($context['cart'])) {
                            $lines = $context['cart']->get_cart_contents();
                            foreach ($lines as $line) {
                                if (isset($line['data'])) {
                                    $product_id = $line['data']->get_id();
                                    if ($products === false || in_array($product_id, $products)) {
                                        $product = $line['data'];
                                        global $azm_bulk_pricing_rules;
                                        $azm_bulk_pricing_rules[$product_id][$context['rule']] = array(
                                            'action' => $action,
                                            'callback' => function($base_price) use($action, $line) {
                                                $quantity_range = false;
                                                foreach ($action['quantity_ranges'] as $range) {
                                                    if (!empty($range['from']) && empty($range['to'])) {
                                                        if ($range['from'] <= $line['quantity']) {
                                                            $quantity_range = $range;
                                                            break;
                                                        }
                                                    }
                                                    if (empty($range['from']) && !empty($range['to'])) {
                                                        if ($range['to'] >= $line['quantity']) {
                                                            $quantity_range = $range;
                                                            break;
                                                        }
                                                    }
                                                    if (!empty($range['from']) && !empty($range['to'])) {
                                                        if ($range['from'] <= $line['quantity'] && $range['to'] >= $line['quantity']) {
                                                            $quantity_range = $range;
                                                            break;
                                                        }
                                                    }
                                                }
                                                if ($quantity_range) {
                                                    switch ($quantity_range['adjustment']) {
                                                        case 'fixed_discount':
                                                            return $base_price - $quantity_range['value'];
                                                            break;
                                                        case 'percentage_discount':
                                                            return $base_price - $base_price * $quantity_range['value'] / 100;
                                                            break;
                                                        case 'fixed_price':
                                                            return $quantity_range['value'];
                                                            break;
                                                    }
                                                }
                                                return $base_price;
                                            }
                                        );
                                    }
                                }
                            }
                        }
                        if ($context['volume_pricing_table'] && $context['product']) {
                            if ($products === false || in_array($context['product_id'], $products)) {
                                $settings = get_option('azh-woo-settings', array());
                                $base_price = $context['product']->get_regular_price();
                                $headers = array();
                                $prices = array();
                                foreach ($action['quantity_ranges'] as $range) {
                                    if (!empty($range['from']) && empty($range['to'])) {
                                        $headers[] = $range['from'] . '+';
                                    }
                                    if (empty($range['from']) && !empty($range['to'])) {
                                        $headers[] = '1-' . $range['to'];
                                    }
                                    if (!empty($range['from']) && !empty($range['to'])) {
                                        $headers[] = $range['from'] . '-' . $range['to'];
                                    }
                                    switch ($range['adjustment']) {
                                        case 'fixed_discount':
                                            $prices[] = wc_price($base_price - $range['value']);
                                            break;
                                        case 'percentage_discount':
                                            $prices[] = wc_price($base_price - $base_price * $range['value'] / 100);
                                            break;
                                        case 'fixed_price':
                                            $prices[] = wc_price($range['value']);
                                            break;
                                    }
                                }
                                $outupt = '<table>';
                                $outupt .= '<caption>' . $settings['vpt-title'] . '</caption>';
                                $outupt .= '<thead><tr>';
                                $outupt .= '<th>' . implode('</th><th>', $headers) . '</th>';
                                $outupt .= '</tr></thead>';
                                $outupt .= '<tbody><tr>';
                                $outupt .= '<td>' . implode('</td><td>', $prices) . '</td>';
                                $outupt .= '</tr></tbody>';
                                $outupt .= '</table>';
                                print $outupt;
                            }
                        }
                    }
                }
            }
            break;
        case 'tiered_pricing':
            if (isset($context['visitors'])) {
                global $wpdb;
                $db_query = azr_get_db_query($context['visitors']);
                $visitors = $wpdb->get_results($db_query, ARRAY_A);
                $visitors = array_map(function($value) {
                    return $value['visitor_id'];
                }, $visitors);
                $visitors = array_filter($visitors);
                $visitors = array_unique($visitors);
                foreach ($visitors as $visitor_id) {
                    if ($visitor_id == $context['visitor_id']) {
                        $products = false;
                        if (isset($context['products'])) {
                            $db_query = azr_get_db_query($context['products']);
                            $products = $wpdb->get_results($db_query, ARRAY_A);
                            $products = array_map(function($value) {
                                return $value['ID'];
                            }, $products);
                            $products = array_filter($products);
                            $products = array_unique($products);
                        }
                        if (isset($context['cart'])) {
                            $lines = $context['cart']->get_cart_contents();
                            foreach ($lines as $line) {
                                if (isset($line['data'])) {
                                    $product_id = $line['data']->get_id();
                                    if ($products === false || in_array($product_id, $products)) {
                                        $product = $line['data'];
                                        global $azm_tiered_pricing_rules;
                                        $azm_tiered_pricing_rules[$product_id][$context['rule']] = array(
                                            'action' => $action,
                                            'display_callback' => function($cart_item) use($action, $line) {
                                                $price_html = '';
                                                $product = $cart_item['data'];
                                                $quantity = 0;
                                                $base_price = $product->get_regular_price();
                                                $last_price = '';
                                                foreach ($action['quantity_ranges'] as $range) {
                                                    $qty = 0;
                                                    if (empty($range['to'])) {
                                                        if ($quantity < $line['quantity']) {
                                                            $qty = $line['quantity'] - $quantity;
                                                        }
                                                    } else {
                                                        if ($quantity <= $range['to']) {
                                                            if ($range['to'] > $line['quantity']) {
                                                                $qty = $line['quantity'] - $quantity;
                                                            } else {
                                                                $qty = $range['to'] - $quantity;
                                                            }
                                                        }
                                                    }
                                                    switch ($range['adjustment']) {
                                                        case 'no_discount':
                                                            $ph = wc_price(wc_get_price_to_display($product, array('price' => $base_price))) . $product->get_price_suffix();
                                                            $last_price = $ph;
                                                            $price_html .= '<span style="display: block; float: left;">' . $ph . '</span>';
                                                            $price_html .= '<span style="display: block; float: right; padding-left: 1em;">× ' . $qty . '</span>';
                                                            $price_html .= '<span style="display: block; clear: both;"></span>';
                                                            break;
                                                        case 'fixed_discount':
                                                            $ph = wc_format_sale_price(wc_get_price_to_display($product, array('price' => $base_price)), wc_get_price_to_display($product, array('price' => ($base_price - $range['value'])))) . $product->get_price_suffix();
                                                            $last_price = $ph;
                                                            $price_html .= '<span style="display: block; float: left;">' . $ph . '</span>';
                                                            $price_html .= '<span style="display: block; float: right; padding-left: 1em;">× ' . $qty . '</span>';
                                                            $price_html .= '<span style="display: block; clear: both;"></span>';
                                                            break;
                                                        case 'percentage_discount':
                                                            $ph = wc_format_sale_price(wc_get_price_to_display($product, array('price' => $base_price)), wc_get_price_to_display($product, array('price' => ($base_price - $base_price * $range['value'] / 100)))) . $product->get_price_suffix();
                                                            $last_price = $ph;
                                                            $price_html .= '<span style="display: block; float: left;">' . $ph . '</span>';
                                                            $price_html .= '<span style="display: block; float: right; padding-left: 1em;">× ' . $qty . '</span>';
                                                            $price_html .= '<span style="display: block; clear: both;"></span>';
                                                            break;
                                                        case 'fixed_price':
                                                            $ph = wc_format_sale_price(wc_get_price_to_display($product, array('price' => $base_price)), wc_get_price_to_display($product, array('price' => $range['value']))) . $product->get_price_suffix();
                                                            $last_price = $ph;
                                                            $price_html .= '<span style="display: block; float: left;">' . $ph . '</span>';
                                                            $price_html .= '<span style="display: block; float: right; padding-left: 1em;">× ' . $qty . '</span>';
                                                            $price_html .= '<span style="display: block; clear: both;"></span>';
                                                            break;
                                                    }
                                                    $quantity += $qty;
                                                    if ($quantity >= $line['quantity']) {
                                                        break;
                                                    }
                                                }
                                                if ($quantity < $line['quantity']) {
                                                    $price_html .= '<span style="display: block; float: left;">' . $last_price . '</span>';
                                                    $price_html .= '<span style="display: block; float: right; padding-left: 1em;">× ' . ($line['quantity'] - $quantity) . '</span>';
                                                    $price_html .= '<span style="display: block; clear: both;"></span>';
                                                }
                                                return $price_html;
                                            },
                                            'callback' => function($base_price) use($action, $line) {
                                                $subtotal = 0;
                                                $quantity = 0;
                                                $last_price = $base_price;
                                                foreach ($action['quantity_ranges'] as $range) {
                                                    $qty = 0;
                                                    if (empty($range['to'])) {
                                                        if ($quantity < $line['quantity']) {
                                                            $qty = $line['quantity'] - $quantity;
                                                        }
                                                    } else {
                                                        if ($quantity <= $range['to']) {
                                                            if ($range['to'] > $line['quantity']) {
                                                                $qty = $line['quantity'] - $quantity;
                                                            } else {
                                                                $qty = $range['to'] - $quantity;
                                                            }
                                                        }
                                                    }
                                                    switch ($range['adjustment']) {
                                                        case 'no_discount':
                                                            $last_price = $base_price;
                                                            $subtotal += $base_price * $qty;
                                                            break;
                                                        case 'fixed_discount':
                                                            $last_price = $base_price - $range['value'];
                                                            $subtotal += $last_price * $qty;
                                                            break;
                                                        case 'percentage_discount':
                                                            $last_price = $base_price - $base_price * $range['value'] / 100;
                                                            $subtotal += $last_price * $qty;
                                                            break;
                                                        case 'fixed_price':
                                                            $last_price = $range['value'];
                                                            $subtotal += $last_price * $qty;
                                                            break;
                                                    }
                                                    $quantity += $qty;
                                                    if ($quantity >= $line['quantity']) {
                                                        break;
                                                    }
                                                }
                                                if ($quantity < $line['quantity']) {
                                                    $subtotal += ($line['quantity'] - $quantity) * $last_price;
                                                }
                                                return $subtotal / $line['quantity'];
                                            }
                                        );
                                    }
                                }
                            }
                        }
                        if ($context['volume_pricing_table']) {
                            if ($products === false || in_array($context['product_id'], $products)) {
                                $settings = get_option('azh-woo-settings', array());
                                $base_price = $context['product']->get_regular_price();
                                $headers = array();
                                $prices = array();
                                for ($i = 0; $i < count($action['quantity_ranges']); $i++) {
                                    $range = $action['quantity_ranges'][$i];
                                    if (empty($range['to']) && $i == (count($action['quantity_ranges']) - 1)) {
                                        $headers[] = ($action['quantity_ranges'][$i - 1]['to'] + 1) . '+';
                                    } else {
                                        if ($i == 0) {
                                            $headers[] = '1-' . $range['to'];
                                        } else {
                                            $headers[] = ($action['quantity_ranges'][$i - 1]['to'] + 1) . '-' . $range['to'];
                                        }
                                    }
                                    switch ($range['adjustment']) {
                                        case 'no_discount':
                                            $prices[] = wc_price($base_price);
                                            break;
                                        case 'fixed_discount':
                                            $prices[] = wc_price($base_price - $range['value']);
                                            break;
                                        case 'percentage_discount':
                                            $prices[] = wc_price($base_price - $base_price * $range['value'] / 100);
                                            break;
                                        case 'fixed_price':
                                            $prices[] = wc_price($range['value']);
                                            break;
                                    }
                                }
                                if (!empty($action['quantity_ranges'][count($action['quantity_ranges']) - 1]['to'])) {
                                    $headers[] = ($action['quantity_ranges'][count($action['quantity_ranges']) - 1]['to'] + 1) . '+';
                                    $prices[] = $prices[count($action['quantity_ranges']) - 1];
                                }

                                $outupt = '<table>';
                                $outupt .= '<caption>' . $settings['vpt-title'] . '</caption>';
                                $outupt .= '<thead><tr>';
                                $outupt .= '<th>' . implode('</th><th>', $headers) . '</th>';
                                $outupt .= '</tr></thead>';
                                $outupt .= '<tbody><tr>';
                                $outupt .= '<td>' . implode('</td><td>', $prices) . '</td>';
                                $outupt .= '</tr></tbody>';
                                $outupt .= '</table>';
                                print $outupt;
                            }
                        }
                    }
                }
            }
            break;
        case 'buy_x_get_x':
            if (isset($context['visitors'])) {
                global $wpdb;
                $db_query = azr_get_db_query($context['visitors']);
                $visitors = $wpdb->get_results($db_query, ARRAY_A);
                $visitors = array_map(function($value) {
                    return $value['visitor_id'];
                }, $visitors);
                $visitors = array_filter($visitors);
                $visitors = array_unique($visitors);
                foreach ($visitors as $visitor_id) {
                    if ($visitor_id == $context['visitor_id']) {
                        $products = false;
                        if (isset($context['products'])) {
                            $db_query = azr_get_db_query($context['products']);
                            $products = $wpdb->get_results($db_query, ARRAY_A);
                            $products = array_map(function($value) {
                                return $value['ID'];
                            }, $products);
                            $products = array_filter($products);
                            $products = array_unique($products);
                        }
                        if (isset($context['cart'])) {
                            $lines = $context['cart']->get_cart_contents();
                            foreach ($lines as $line) {
                                if (isset($line['data'])) {
                                    $product_id = $line['data']->get_id();
                                    if ($products === false || in_array($product_id, $products)) {
                                        $product = $line['data'];
                                        global $azm_buy_x_get_x_pricing_rules;
                                        $azm_buy_x_get_x_pricing_rules[$product_id][$context['rule']] = array(
                                            'action' => $action,
                                            'display_callback' => function($cart_item) use($action, $line) {
                                                $price_html = '';
                                                $product = $cart_item['data'];
                                                $quantity = 0;
                                                $base_price = $product->get_regular_price();
                                                $k = $line['quantity'] / ($action['buy'] + $action['get']);
                                                $qty_buy = ceil($k * $action['buy']);
                                                $qty_get = $line['quantity'] - $qty_buy;

                                                $ph = wc_price(wc_get_price_to_display($product, array('price' => $base_price))) . $product->get_price_suffix();
                                                $price_html .= '<span style="display: block; float: left;">' . $ph . '</span>';
                                                $price_html .= '<span style="display: block; float: right; padding-left: 1em;">× ' . $qty_buy . '</span>';
                                                $price_html .= '<span style="display: block; clear: both;"></span>';

                                                if ($qty_get) {
                                                    switch ($action['adjustment']) {
                                                        case 'fixed_discount':
                                                            $ph = wc_format_sale_price(wc_get_price_to_display($product, array('price' => $base_price)), wc_get_price_to_display($product, array('price' => ($base_price - $action['value'])))) . $product->get_price_suffix();
                                                            $price_html .= '<span style="display: block; float: left;">' . $ph . '</span>';
                                                            $price_html .= '<span style="display: block; float: right; padding-left: 1em;">× ' . $qty_get . '</span>';
                                                            $price_html .= '<span style="display: block; clear: both;"></span>';
                                                            break;
                                                        case 'percentage_discount':
                                                            $ph = wc_format_sale_price(wc_get_price_to_display($product, array('price' => $base_price)), wc_get_price_to_display($product, array('price' => ($base_price - $base_price * $action['value'] / 100)))) . $product->get_price_suffix();
                                                            $price_html .= '<span style="display: block; float: left;">' . $ph . '</span>';
                                                            $price_html .= '<span style="display: block; float: right; padding-left: 1em;">× ' . $qty_get . '</span>';
                                                            $price_html .= '<span style="display: block; clear: both;"></span>';
                                                            break;
                                                        case 'fixed_price':
                                                            $ph = wc_format_sale_price(wc_get_price_to_display($product, array('price' => $base_price)), wc_get_price_to_display($product, array('price' => $action['value']))) . $product->get_price_suffix();
                                                            $price_html .= '<span style="display: block; float: left;">' . $ph . '</span>';
                                                            $price_html .= '<span style="display: block; float: right; padding-left: 1em;">× ' . $qty_get . '</span>';
                                                            $price_html .= '<span style="display: block; clear: both;"></span>';
                                                            break;
                                                    }
                                                }
                                                return $price_html;
                                            },
                                            'callback' => function($base_price) use($action, $line) {
                                                $k = $line['quantity'] / ($action['buy'] + $action['get']);
                                                $qty_buy = ceil($k * $action['buy']);
                                                $qty_get = $line['quantity'] - $qty_buy;

                                                $subtotal = $base_price * $qty_buy;
                                                if ($qty_get) {
                                                    switch ($action['adjustment']) {
                                                        case 'fixed_discount':
                                                            $subtotal += ($base_price - $action['value']) * $qty_get;
                                                            break;
                                                        case 'percentage_discount':
                                                            $subtotal += ($base_price - $base_price * $action['value'] / 100) * $qty_get;
                                                            break;
                                                        case 'fixed_price':
                                                            $subtotal += $action['value'] * $qty_get;
                                                            break;
                                                    }
                                                }
                                                return $subtotal / $line['quantity'];
                                            }
                                        );
                                    }
                                }
                            }
                        }
                    }
                }
            }
            break;
    }
    return $context;
}

function azm_wpd_options_callback() {
    
}

add_action('admin_init', 'azm_wpd_options');

function azm_wpd_options() {
    add_settings_section(
            'azh_woo_vpt_section', // Section ID
            esc_html__('Volume Pricing Table', 'azm'), // Title above settings section
            'azm_wpd_options_callback', // Name of function that renders a description of the settings section
            'azh-woo-settings'                     // Page to show on
    );
    add_settings_field(
            'vpt-enabled', // Field ID
            esc_html__('Enabled', 'azm'), // Label to the left
            'azh_checkbox', // Name of function that renders options on the page
            'azh-woo-settings', // Page to show on
            'azh_woo_vpt_section', // Associate with which settings section?
            array(
        'id' => 'vpt-enabled',
        'options' => array(
            'yes' => __('Yes', 'azm'),
        ),
            )
    );
    add_settings_field(
            'vpt-title', // Field ID
            esc_html__('Title', 'azm'), // Label to the left
            'azh_textfield', // Name of function that renders options on the page
            'azh-woo-settings', // Page to show on
            'azh_woo_vpt_section', // Associate with which settings section?
            array(
        'id' => 'vpt-title',
        'default' => esc_html__('Quantity discounts', 'azm'),
            )
    );
    add_settings_field(
            'vpt-position', // Field ID
            esc_html__('Position', 'azm'), // Label to the left
            'azh_select', // Name of function that renders options on the page
            'azh-woo-settings', // Page to show on
            'azh_woo_vpt_section', // Associate with which settings section?
            array(
        'id' => 'vpt-position',
        'default' => 'woocommerce_before_add_to_cart_form|10',
        'options' => array(
            'woocommerce_before_add_to_cart_form|10' => __('Single product - Add to cart - Before', 'azm'),
            'woocommerce_after_add_to_cart_form|10' => __('Single product - Add to cart - After', 'azm'),
            'woocommerce_product_meta_start|10' => __('Single product - Product meta - Before', 'azm'),
            'woocommerce_product_meta_end|10' => __('Single product - Product meta - After', 'azm'),
            'woocommerce_single_product_summary|19' => __('Single product - Product summary - Before', 'azm'),
            'woocommerce_single_product_summary|21' => __('Single product - Product summary - After', 'azm'),
        )
            )
    );

    add_settings_section(
            'azh_woo_pricing_section', // Section ID
            esc_html__('Product pricing settings', 'azm'), // Title above settings section
            'azm_wpd_options_callback', // Name of function that renders a description of the settings section
            'azh-woo-settings'                     // Page to show on
    );
    add_settings_field(
            'pricing-rule-selection-method', // Field ID
            esc_html__('Pricing rule selection method', 'azm'), // Label to the left
            'azh_select', // Name of function that renders options on the page
            'azh-woo-settings', // Page to show on
            'azh_woo_pricing_section', // Associate with which settings section?
            array(
        'id' => 'pricing-rule-selection-method',
        'default' => 'all',
        'options' => array(
            'all' => __('Apply all rules', 'azm'),
            'smaller_price' => __('Apply rule for smaller price', 'azm'),
            'bigger_price' => __('Apply rule for bigger price', 'azm'),
        )
            )
    );
    add_settings_section(
            'azh_woo_discounts_section', // Section ID
            esc_html__('Cart discounts settings', 'azm'), // Title above settings section
            'azm_wpd_options_callback', // Name of function that renders a description of the settings section
            'azh-woo-settings'                     // Page to show on
    );
    add_settings_field(
            'discount-rule-selection-method', // Field ID
            esc_html__('Discount rule selection method', 'azm'), // Label to the left
            'azh_select', // Name of function that renders options on the page
            'azh-woo-settings', // Page to show on
            'azh_woo_discounts_section', // Associate with which settings section?
            array(
        'id' => 'discount-rule-selection-method',
        'default' => 'all',
        'options' => array(
            'all' => __('Apply all rules', 'azm'),
            'smaller_discount' => __('Apply smaller discount', 'azm'),
            'bigger_discount' => __('Apply bigger discount', 'azm'),
        )
            )
    );
}
