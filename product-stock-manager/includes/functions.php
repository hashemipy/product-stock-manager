<?php
/**
 * Utility Functions for Product Stock Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

/*-------------------------------------------
   تابع تبدیل اعداد فارسی به لاتین و بالعکس
-------------------------------------------*/
function psm_convert_persian_to_latin($string) {
    $persian = array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹');
    $latin = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
    return str_replace($persian, $latin, $string);
}

function psm_convert_latin_to_persian($string) {
    $persian = array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹');
    $latin = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
    return str_replace($latin, $persian, $string);
}

/*-------------------------------------------
   تابع دیباگ برای بررسی SKUها در دیتابیس
-------------------------------------------*/
function psm_debug_sku() {
    global $wpdb;
    $results = $wpdb->get_results("SELECT post_id, meta_value AS sku FROM $wpdb->postmeta WHERE meta_key='_sku' LIMIT 10");
    error_log('PSM Debug SKU: SKU List from Database:');
    foreach ($results as $result) {
        error_log('Post ID: ' . $result->post_id . ', SKU: ' . $result->sku);
    }
}

/*-------------------------------------------
   تابع دریافت URL تصویر WebP با اولویت و بک‌فال به تصویر اصلی
-------------------------------------------*/
function psm_get_webp_image_url($attachment_id, $size = 'thumbnail') {
    if (!$attachment_id) {
        return '';
    }
    
    // Try to get WebP path from post meta (set by WooCommerce Image Optimizer plugin)
    $webp_path = get_post_meta($attachment_id, '_wio_webp_path', true);
    
    // Check if WebP file exists
    if ($webp_path && file_exists($webp_path)) {
        // Convert file path to URL
        $webp_url = str_replace(WP_CONTENT_DIR, content_url(), $webp_path);
        return $webp_url;
    }
    
    // Check metadata for size-specific WebP paths
    $metadata = wp_get_attachment_metadata($attachment_id);
    if (isset($metadata['sizes'][$size]['webp_path'])) {
        $size_webp_path = $metadata['sizes'][$size]['webp_path'];
        if (file_exists($size_webp_path)) {
            $webp_url = str_replace(WP_CONTENT_DIR, content_url(), $size_webp_path);
            return $webp_url;
        }
    }
    
    // Fallback to original image
    return wp_get_attachment_image_url($attachment_id, $size);
}

/*-------------------------------------------
   تابع بروزرسانی وضعیت موجودی محصول متغیر بر اساس واریاسیون‌ها
-------------------------------------------*/
function psm_update_variable_product_stock_status($parent_id) {
    $parent_product = wc_get_product($parent_id);

    if (!$parent_product || !$parent_product->is_type('variable')) {
        return;
    }

    $variations = $parent_product->get_children();
    $all_out_of_stock = true;
    $has_in_stock = false;
    $low_stock_threshold = apply_filters('woocommerce_get_low_stock_amount', 10); // Get WooCommerce low stock threshold

    // Check stock status for all variations
    foreach ($variations as $variation_id) {
        $variation = wc_get_product($variation_id);
        if ($variation) {
            // Force disable backorders on variation to prevent "backorder" status
            $variation->set_backorders('no');
            
            if ($variation->managing_stock()) {
                $stock_quantity = $variation->get_stock_quantity();
                if ($stock_quantity === null) $stock_quantity = 0; // Treat null as 0 stock for calculation

                if ($stock_quantity > $low_stock_threshold) {
                    $all_out_of_stock = false;
                    $has_in_stock = true;
                } elseif ($stock_quantity > 0) { // Has some stock, but not enough to be considered 'in_stock' by threshold
                    $all_out_of_stock = false; // Not all out of stock
                    $has_in_stock = true; // Consider it available
                }
                // If stock_quantity is 0 or less, it's out of stock
            } else {
                // If variation doesn't manage stock, assume it's available (but force manage stock if needed)
                $all_out_of_stock = false;
                $has_in_stock = true;
            }
            
            $variation->save(); // Save backorders change
            wc_delete_product_transients($variation_id); // Clear variation cache
        }
    }

    // Update parent product status based on findings
    if ($all_out_of_stock) {
        $parent_product->set_manage_stock(false); // Disable stock management for parent if all variations are out
        $parent_product->set_stock_status('outofstock');
        $parent_product->set_backorders('no'); // Ensure no backorders
    } elseif ($has_in_stock) {
        $parent_product->set_stock_status('instock'); // At least one variation is in stock
        $parent_product->set_backorders('no'); // Ensure no backorders
    }

    $parent_product->save();

    // Clear WC transients for the parent product to ensure cache is updated
    wc_delete_product_transients($parent_id);

    // Update WooCommerce lookup table if it exists and method is available
    if (class_exists('WC_Product_Data_Store_CPT')) {
        $data_store = WC_Data_Store::load('product');
        if (method_exists($data_store, 'update_lookup_table')) {
            $data_store->update_lookup_table($parent_id, 'wc_product_meta_lookup');
        }
    }
}
?>
