<?php

/*
Plugin Name: WooCommerce Suppliers
Plugin URI: https://alexdraghici.dev/
Description: Adds a new "Suppliers" taxonomy to WooCommerce products
Version: 1.0.0
Author: Alexandru Draghici
Author URI: https://alexdraghici.dev/
*/

if (!defined('ABSPATH')) {
    exit;
}

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    exit;
}

const SUPPLIERS_TAX_SLUG = 'supplier';
const SUPPLIER_LEAD_TIME_SLUG = 'supplier_lead_time';

/**
 * @return void
 */
function add_suppliers_taxonomy()
{
    $labels = [
        'name' => __('Suppliers', 'woocommerce-suppliers'),
        'singular_name' => __('Supplier', 'woocommerce-suppliers'),
        'search_items' => __('Search Suppliers', 'woocommerce-suppliers'),
        'all_items' => __('All Suppliers', 'woocommerce-suppliers'),
        'parent_item' => __('Parent Supplier', 'woocommerce-suppliers'),
        'parent_item_colon' => __('Parent Supplier:', 'woocommerce-suppliers'),
        'edit_item' => __('Edit Supplier', 'woocommerce-suppliers'),
        'update_item' => __('Update Supplier', 'woocommerce-suppliers'),
        'add_new_item' => __('Add New Supplier', 'woocommerce-suppliers'),
        'new_item_name' => __('New Supplier Name', 'woocommerce-suppliers'),
        'menu_name' => __('Suppliers', 'woocommerce-suppliers'),
    ];

    $args = [
        'labels' => $labels,
        'hierarchical' => false,
        'public' => false,
        'show_ui' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => ['slug' => SUPPLIERS_TAX_SLUG],
        'meta_box_cb' => 'supplier_dropdown_meta_box'
    ];

    register_taxonomy(SUPPLIERS_TAX_SLUG, ['product'], $args);
}

add_action('init', 'add_suppliers_taxonomy');

/**
 * @return void
 */
function add_supplier_lead_time_field($term)
{
    $term_id = is_object($term) ? $term->term_id : '';
    $lead_time = get_term_meta($term_id, SUPPLIER_LEAD_TIME_SLUG, true);
    ?>
    <tr class="form-field">
        <th scope="row" valign="top">
            <label for="<?= SUPPLIER_LEAD_TIME_SLUG ?>"><?php _e('Supplier Lead Time', 'woocommerce-suppliers'); ?></label>
        </th>
        <td>
            <input type="text" name="<?= SUPPLIER_LEAD_TIME_SLUG ?>" id="<?= SUPPLIER_LEAD_TIME_SLUG ?>"
                   value="<?php echo esc_attr($lead_time); ?>">
            <p class="description"><?php _e('Enter the lead time of the supplier', 'woocommerce-suppliers'); ?></p>
        </td>
    </tr>
    <?php
}

add_action('supplier_edit_form_fields', 'add_supplier_lead_time_field', 10, 2);
add_action('supplier_add_form_fields', 'add_supplier_lead_time_field', 10, 2);

/**
 * @param $term_id
 * @return void
 */
function save_supplier_lead_time_field($term_id)
{
    if (isset($_POST[SUPPLIER_LEAD_TIME_SLUG])) {
        $lead_time = sanitize_text_field($_POST[SUPPLIER_LEAD_TIME_SLUG]);
        update_term_meta($term_id, SUPPLIER_LEAD_TIME_SLUG, $lead_time);
    }
}

add_action('edited_supplier', 'save_supplier_lead_time_field');
add_action('create_supplier', 'save_supplier_lead_time_field');

/**
 * @param $columns
 * @return mixed
 */
function display_supplier_lead_time_column($columns)
{
    $columns[SUPPLIER_LEAD_TIME_SLUG] = __('Lead Time', 'woocommerce-suppliers');
    return $columns;
}

add_filter('manage_edit-supplier_columns', 'display_supplier_lead_time_column');

/**
 * @param $content
 * @param $column_name
 * @param $term_id
 * @return mixed|string
 */
function populate_supplier_lead_time_column($content, $column_name, $term_id)
{
    if (SUPPLIER_LEAD_TIME_SLUG === $column_name) {
        $lead_time = get_term_meta($term_id, SUPPLIER_LEAD_TIME_SLUG, true);
        $content .= !empty($lead_time) ? esc_html($lead_time) : '-';
    }
    return $content;
}

add_filter('manage_supplier_custom_column', 'populate_supplier_lead_time_column', 10, 3);

/**
 * @param $post
 * @param $box
 * @return void
 */
function supplier_dropdown_meta_box($post, $box)
{
    $tax_name = SUPPLIERS_TAX_SLUG;

    $selected = wp_get_object_terms($post->ID, $tax_name, ['fields' => 'ids']);
    $selected_str = \implode(',', $selected);

    wp_dropdown_categories([
        'taxonomy' => $tax_name,
        'name' => $tax_name . '[]',
        'orderby' => 'name',
        'hierarchical' => true,
        'show_option_all' => __('All suppliers', 'woocommerce-suppliers'),
        'hide_empty' => false,
        'value_field' => 'term_id',
        'selected' => $selected_str,
    ]);
}

/**
 * @param $product_id
 * @return void
 */
function save_supplier_data($product_id)
{
    $suppliers = $_POST[SUPPLIERS_TAX_SLUG] ?? '';

    $term = get_term_by('term_id', $suppliers[0], SUPPLIERS_TAX_SLUG);

    wp_set_object_terms($product_id, $term->term_id, SUPPLIERS_TAX_SLUG);
}

add_action('woocommerce_process_product_meta', 'save_supplier_data');

/**
 * @return void
 */
function supplier_dropdown_product_filter()
{
    $tax_name = SUPPLIERS_TAX_SLUG;
    $taxonomy = get_taxonomy($tax_name);

    $selected = isset($_GET[$taxonomy->query_var]) ? $_GET[$taxonomy->query_var] : '';
    $args = [
        'orderby' => 'name',
        'hide_empty' => false,
        'selected' => $selected,
        'option_none_value' => '',
        'show_option_none' => __('All suppliers', 'woocommerce-suppliers'),
        'taxonomy' => $tax_name,
        'name' => $tax_name,
        'id' => $tax_name,
        'class' => 'form-select',
        'depth' => 1,
        'hierarchical' => true,
        'value_field' => 'slug',
    ];

    wp_dropdown_categories($args);
}

add_action('woocommerce_product_filters', 'supplier_dropdown_product_filter');

/**
 * @param $availability
 * @param $product
 * @return mixed|string
 */
function modify_product_availability($availability, $product)
{
    $product_id = $product->get_id();
    $is_variation = false;

    if ($product->get_parent_id()) {
        $product_id = $product->get_parent_id();
        $is_variation = true;
    }

    if ($product->backorders_allowed()) {
        $stock_quantity = $product->get_stock_quantity();
        $selected_supplier = wp_get_post_terms($product_id, SUPPLIERS_TAX_SLUG);

        if ($stock_quantity == 0 && \is_array($selected_supplier) && !empty($selected_supplier)) {
            $selected_supplier = $selected_supplier[0]->term_id;
            $supplier_lead_time = get_term_meta($selected_supplier, SUPPLIER_LEAD_TIME_SLUG, true);

            if (!empty($supplier_lead_time)) {
                $availability = '<strong>' . __('Supplier availability: ', 'woocommerce-suppliers') . '</strong>' . $supplier_lead_time;
            }
        }
    }

    return $availability;
}

add_filter('woocommerce_get_availability_text', 'modify_product_availability', 10, 2);

/**
 * Add supplier lead hidden input for cart data
 */
add_action('woocommerce_before_add_to_cart_button', function () {
    global $product;

    if ($product->is_type('variable')) {
        $backorder_data = [];
        $variations = $product->get_children();

        foreach ($variations as $variation_id) {
            $variation = wc_get_product($variation_id);
            $backorder_data[$variation_id] = $variation->backorders_allowed();
        }

        $selected_supplier = wp_get_post_terms($product->get_id(), SUPPLIERS_TAX_SLUG);
        if (\is_array($selected_supplier) && !empty($selected_supplier)) {
            $selected_supplier = $selected_supplier[0]->term_id;
            $supplier_lead_time = get_term_meta($selected_supplier, SUPPLIER_LEAD_TIME_SLUG, true);

            echo '<script>';
            echo 'var backorderData = ' . \json_encode($backorder_data) . ';';
            echo 'var supplierLeadTime = "' . $supplier_lead_time . '";';
            echo '</script>';
            echo '<input type="hidden" id="supplier_lead" name="supplier_lead" value="" style="display:none;"/>';
        }
    } else if ($product->is_type('simple')) {
        $backorder_status = $product->backorders_allowed();

        if ($backorder_status) {
            $selected_supplier = wp_get_post_terms($product->get_id(), SUPPLIERS_TAX_SLUG);
            if (\is_array($selected_supplier) && !empty($selected_supplier)) {
                $selected_supplier = $selected_supplier[0]->term_id;
                $supplier_lead_time = get_term_meta($selected_supplier, SUPPLIER_LEAD_TIME_SLUG, true);

                if (!empty($supplier_lead_time)) {
                    echo '<input type="hidden" id="supplier_lead" name="supplier_lead" value="' . $supplier_lead_time . '"/>';
                }
            }
        }
    }
});

/**
 *  Use JavaScript to add/remove data from supplier_lead input
 */
add_action('wp_footer', function () {
    if (is_product()) {
        ?>
        <script>
            jQuery(document).ready(function ($) {
                if (typeof backorderData !== 'undefined') {
                    $('body').on('found_variation', '.variations_form', function (event, variation) {
                        if (backorderData[variation.variation_id]) {
                            $('#supplier_lead').val(supplierLeadTime);
                        } else {
                            $('#supplier_lead').val('');
                        }
                    });
                }
            });
        </script>
        <?php
    }
}, 100);

/**
 * Add supplier lead to cart item data
 */
add_filter('woocommerce_add_cart_item_data', function ($cart_item_data, $product_id) {
    if (isset($_POST['supplier_lead']) && $_POST['supplier_lead'] !== '') {
        $cart_item_data['supplier_lead'] = sanitize_text_field($_POST['supplier_lead']);
    }
    return $cart_item_data;
}, 10, 2);

/**
 * Display supplier lead in cart and checkout
 */
add_filter('woocommerce_get_item_data', function ($data, $cart_item) {
    if (isset($cart_item['supplier_lead'])) {
        $data[] = [
            'name' => __('Supplier availability', 'woocommerce-suppliers'),
            'value' => wc_clean($cart_item['supplier_lead']),
        ];
    }
    return $data;
}, 10, 2);