<?php
/**
 * AJAX Handlers for Product Stock Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

/*-------------------------------------------
   AJAX: دریافت لیست محصولات با صفحه‌بندی
-------------------------------------------*/
function psm_get_all_products() {
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $per_page = 50; // Number of products per page
    $offset = ($page - 1) * $per_page;

    $args = array(
        'post_type' => 'product',
        'posts_per_page' => $per_page,
        'offset' => $offset,
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC' // Get the most recent products first
    );

    $products_query = get_posts($args);
    $products_data = array();

    foreach ($products_query as $post) {
        $product = wc_get_product($post->ID);

        if (!$product) continue; // Skip if product object cannot be retrieved

        if ($product->is_type('simple')) {
            $products_data[] = array(
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'sku' => $product->get_sku(),
                'stock_quantity' => $product->managing_stock() ? $product->get_stock_quantity() : null,
                'regular_price' => $product->get_regular_price(),
                'sale_price' => $product->get_sale_price(),
                'in_stock' => $product->is_in_stock(),
                'type' => 'simple',
                'image_url' => psm_get_webp_image_url($product->get_image_id(), 'thumbnail')
            );
        } elseif ($product->is_type('variable')) {
            $variations = $product->get_children();
            $variations_data = array();

            foreach ($variations as $variation_id) {
                $variation = wc_get_product($variation_id);
                if ($variation) {
                    $attributes_html = '';
                    $variation_attributes = $variation->get_variation_attributes();
                    foreach ($variation_attributes as $attr_name => $attr_value) {
                        $attr_value = urldecode($attr_value);
                        
                        $attr_label = wc_attribute_label(str_replace('attribute_', '', $attr_name));
                        
                        // Translate common attribute names to Persian
                        $persian_labels = array(
                            'Color' => 'رنگ',
                            'color' => 'رنگ',
                            'Size' => 'سایز',
                            'size' => 'سایز',
                            'Material' => 'جنس',
                            'material' => 'جنس',
                            'Style' => 'استایل',
                            'style' => 'استایل'
                        );
                        
                        $attr_label_display = isset($persian_labels[$attr_label]) ? $persian_labels[$attr_label] : $attr_label;
                        
                        $attributes_html .= '<span class="psm-attr-badge">';
                        $attributes_html .= '<span class="psm-attr-label">' . esc_html($attr_label_display) . ':</span> ';
                        $attributes_html .= '<span class="psm-attr-value">' . esc_html($attr_value) . '</span>';
                        $attributes_html .= '</span>';
                    }
                    
                    // Fallback if no attributes generated HTML, use the variation name itself
                    if (empty($attributes_html)) {
                        $attributes_html = '<span class="psm-attr-badge">' . esc_html($variation->get_name()) . '</span>';
                    }

                    $variations_data[] = array(
                        'id' => $variation->get_id(),
                        'name' => $variation->get_name(),
                        'attributes_html' => $attributes_html,
                        'sku' => $variation->get_sku(),
                        'stock_quantity' => $variation->managing_stock() ? $variation->get_stock_quantity() : null,
                        'regular_price' => $variation->get_regular_price(),
                        'sale_price' => $variation->get_sale_price(),
                        'in_stock' => $variation->is_in_stock()
                    );
                }
            }

            $products_data[] = array(
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'sku' => $product->get_sku(),
                'stock_quantity' => null,
                'regular_price' => null,
                'sale_price' => null,
                'in_stock' => $product->is_in_stock(),
                'type' => 'variable',
                'variations' => $variations_data,
                'variations_count' => count($variations_data),
                'image_url' => psm_get_webp_image_url($product->get_image_id(), 'thumbnail')
            );
        }
    }

    // Get total number of products for pagination info
    $total_args = array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'fields' => 'ids'
    );
    $total_products_count = count(get_posts($total_args));
    $total_pages = ceil($total_products_count / $per_page);

    wp_send_json_success(array(
        'products' => $products_data,
        'current_page' => $page,
        'total_pages' => $total_pages,
        'total_products' => $total_products_count,
        'has_more' => $page < $total_pages
    ));
}
add_action('wp_ajax_psm_get_all_products', 'psm_get_all_products');

/*-------------------------------------------
   AJAX: بروزرسانی جزئیات محصول
-------------------------------------------*/
function psm_update_product_details() {
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

    // Convert Persian digits to Latin before processing and sanitize
    $stock_quantity_input = isset($_POST['stock_quantity']) ? psm_convert_persian_to_latin($_POST['stock_quantity']) : null;
    $regular_price_input = isset($_POST['regular_price']) ? psm_convert_persian_to_latin($_POST['regular_price']) : null;
    $sale_price_input = isset($_POST['sale_price']) ? psm_convert_persian_to_latin($_POST['sale_price']) : null;

    // Intelligently handle null/empty inputs
    $stock_quantity = ($stock_quantity_input !== null && $stock_quantity_input !== '') ? intval($stock_quantity_input) : null;
    $regular_price = ($regular_price_input !== null) ? sanitize_text_field($regular_price_input) : null;
    $sale_price = ($sale_price_input !== null) ? sanitize_text_field($sale_price_input) : null;

    $product = wc_get_product($product_id);

    if (!$product) {
        wp_send_json_error(array('message' => 'محصول یافت نشد'));
        return;
    }

    // Update stock if provided
    if ($stock_quantity !== null) {
        $product->set_manage_stock(true);
        $product->set_stock_quantity($stock_quantity);
    }

    // Update regular price if provided
    if ($regular_price !== null) {
        $product->set_regular_price($regular_price);
    }

    // Update sale price if provided
    if ($sale_price !== null) {
        $product->set_sale_price($sale_price);
    }

    // Save the product
    $product->save();

    // If it's a variation, update parent product status if necessary
    if ($product->is_type('variation')) {
        $parent_id = $product->get_parent_id();
        if ($parent_id) {
            psm_update_variable_product_stock_status($parent_id);
        }
    }

    wp_send_json_success(array(
        'message' => 'اطلاعات محصول با موفقیت بروزرسانی شد',
        'product_id' => $product_id
    ));
}
add_action('wp_ajax_psm_update_product_details', 'psm_update_product_details');

/*-------------------------------------------
   AJAX: جستجوی محصولات
-------------------------------------------*/
function psm_search_products() {
    global $wpdb;
    $term = sanitize_text_field($_POST['term'] ?? '');
    $results = array();

    if (empty($term)) {
        wp_send_json_success([]);
        return;
    }

    $term_latin = psm_convert_persian_to_latin($term);
    $term_clean = str_replace(' ', '', $term_latin);

    $search_terms = array_unique(array_filter([
        $term,
        $term_latin,
        $term_clean,
    ]));

    $sql_parts = [];

    // If the term is purely numeric (even with Persian digits), treat it as an ID search directly
    if (preg_match('/^[0-9۰-۹]+$/', $term)) {
        $numeric_id = intval($term_latin);
        if ($numeric_id > 0) {
            $sql_parts[] = $wpdb->prepare('p.ID = %d', $numeric_id);
        }
    } else {
        // Search by title (post_title)
        $title_like = '%' . $wpdb->esc_like($term) . '%';
        $title_like_latin = '%' . $wpdb->esc_like($term_latin) . '%';
        $sql_parts[] = "(p.post_title LIKE '{$title_like}' OR p.post_title LIKE '{$title_like_latin}')";

        // Search by SKU
        $sku_like = '%' . $wpdb->esc_like($term_clean) . '%';
        $sql_parts[] = "pm.meta_value LIKE '{$sku_like}'";
    }

    // Construct the main query
    $sql = "SELECT DISTINCT p.ID, p.post_title, pm.meta_value AS sku
            FROM $wpdb->posts p
            LEFT JOIN $wpdb->postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
            WHERE p.post_type = 'product' AND p.post_status = 'publish'";

    if (!empty($sql_parts)) {
        $sql .= " AND (" . implode(" OR ", $sql_parts) . ")";
    } else {
        wp_send_json_success([]);
        return;
    }

    $sql .= " LIMIT 10";

    $products = $wpdb->get_results($sql);

    if ($products) {
        foreach ($products as $product) {
            $wc_product = wc_get_product($product->ID);
            if ($wc_product) {
                $results[] = array(
                    'id' => $product->ID,
                    'name' => $product->post_title,
                    'sku' => $product->sku
                );
            }
        }
    }

    wp_send_json_success($results);
}
add_action('wp_ajax_psm_search_products', 'psm_search_products');

/*-------------------------------------------
   AJAX: جستجوی تمام محصولات (بدون محدودیت)
-------------------------------------------*/
function psm_search_products_global() {
    global $wpdb;
    $term = sanitize_text_field($_POST['term'] ?? '');
    $results = array();

    if (empty($term)) {
        wp_send_json_success([]);
        return;
    }

    $term_latin = psm_convert_persian_to_latin($term);
    $term_clean = str_replace(' ', '', $term_latin);

    $search_terms = array_unique(array_filter([
        $term,
        $term_latin,
        $term_clean,
    ]));

    $sql_parts = [];

    // اگر عبارت فقط عددی است (حتی اعداد فارسی)، آن را به‌عنوان جستجوی ID بخیر
    if (preg_match('/^[0-9۰-۹]+$/', $term)) {
        $numeric_id = intval($term_latin);
        if ($numeric_id > 0) {
            $sql_parts[] = $wpdb->prepare('p.ID = %d', $numeric_id);
        }
    } else {
        // جستجو بر اساس نام (post_title)
        $title_like = '%' . $wpdb->esc_like($term) . '%';
        $title_like_latin = '%' . $wpdb->esc_like($term_latin) . '%';
        $sql_parts[] = "(p.post_title LIKE '{$title_like}' OR p.post_title LIKE '{$title_like_latin}')";

        // جستجو بر اساس SKU
        $sku_like = '%' . $wpdb->esc_like($term_clean) . '%';
        $sql_parts[] = "pm.meta_value LIKE '{$sku_like}'";
    }

    // ساخت query اصلی
    $sql = "SELECT DISTINCT p.ID, p.post_title, pm.meta_value AS sku
            FROM $wpdb->posts p
            LEFT JOIN $wpdb->postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
            WHERE p.post_type = 'product' AND p.post_status = 'publish'";

    if (!empty($sql_parts)) {
        $sql .= " AND (" . implode(" OR ", $sql_parts) . ")";
    } else {
        wp_send_json_success([]);
        return;
    }

    // بدون محدودیت - تمام نتایج را برگردان
    $products = $wpdb->get_results($sql);

    if ($products) {
        foreach ($products as $product) {
            $wc_product = wc_get_product($product->ID);
            if ($wc_product) {
                // برای محصولات متغیر، تمام ورژن‌های آن را شامل کن
                if ($wc_product->is_type('variable')) {
                    $variations = $wc_product->get_children();
                    $variable_data = array(
                        'id' => $product->ID,
                        'name' => $product->post_title,
                        'sku' => $product->sku,
                        'type' => 'variable',
                        'stock_quantity' => 0,
                        'variations' => array()
                    );
                    
                    if ($variations) {
                        foreach ($variations as $var_id) {
                            $var = wc_get_product($var_id);
                            if ($var) {
                                $variable_data['variations'][] = array(
                                    'id' => $var_id,
                                    'name' => $var->get_formatted_name(),
                                    'sku' => $var->get_sku(),
                                    'stock_quantity' => $var->get_stock_quantity()
                                );
                            }
                        }
                    }
                    $results[] = $variable_data;
                } else {
                    $results[] = array(
                        'id' => $product->ID,
                        'name' => $product->post_title,
                        'sku' => $product->sku,
                        'type' => 'simple',
                        'stock_quantity' => $wc_product->get_stock_quantity()
                    );
                }
            }
        }
    }

    wp_send_json_success($results);
}
add_action('wp_ajax_psm_search_products_global', 'psm_search_products_global');

/*-------------------------------------------
   AJAX: دریافت اطلاعات محصول با ID یا SKU
-------------------------------------------*/
function psm_get_product_by_id() {
    global $wpdb;
    $input = sanitize_text_field(trim($_POST['product_id'] ?? ''));
    $input_type = sanitize_text_field($_POST['input_type'] ?? 'sku');
    
    $product = false;

    if (empty($input)) {
        wp_send_json_error(array('message' => 'کد ورودی خالی است.'));
        return;
    }

    $product_id = 0;

    // 1. Attempt to find by ID directly if input is numeric
    if (($input_type === 'id' || $input_type === 'barcode' || is_numeric($input)) && is_numeric($input)) {
        $product_id = intval($input);
        $product = wc_get_product($product_id);
    }

    // 2. If not found by ID, or if type is SKU/barcode, try SKU lookup
    if (!$product && ($input_type === 'sku' || $input_type === 'barcode' || $input_type === 'id')) {
        $input_latin = psm_convert_persian_to_latin($input);
        $input_clean = str_replace(' ', '', $input_latin);

        $product_id_from_sku = wc_get_product_id_by_sku($input_latin);
        if (!$product_id_from_sku) {
            $product_id_from_sku = wc_get_product_id_by_sku($input);
        }
        if (!$product_id_from_sku) {
            $product_id_from_sku = wc_get_product_id_by_sku($input_clean);
        }
        
        if ($product_id_from_sku) {
            $product = wc_get_product($product_id_from_sku);
        } else {
            // Fallback: Direct DB query for SKU as a last resort
            $possible_skus = array_unique(array_filter([$input, $input_latin, $input_clean]));
            foreach ($possible_skus as $sku_to_check) {
                $product_id_db = $wpdb->get_var($wpdb->prepare(
                    "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value = %s",
                    $sku_to_check
                ));
                if ($product_id_db) {
                    $product = wc_get_product($product_id_db);
                    if ($product) break;
                }
            }
        }
    }

    // Process the found product
    if ($product) {
        if ($product->is_type('simple')) {
            wp_send_json_success(array(
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'price' => $product->get_price(),
                'in_stock' => $product->is_in_stock(),
                'stock_quantity' => ($product->managing_stock() ? $product->get_stock_quantity() : null),
                'is_variable' => false,
                'sku' => $product->get_sku()
            ));
        } elseif ($product->is_type('variation')) {
            wp_send_json_success(array(
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'price' => $product->get_price(),
                'in_stock' => $product->is_in_stock(),
                'stock_quantity' => ($product->managing_stock() ? $product->get_stock_quantity() : null),
                'is_variable' => false,
                'sku' => $product->get_sku(),
                'parent_id' => $product->get_parent_id()
            ));
        } elseif ($product->is_type('variable')) {
            $variations_data = array();
            $children = $product->get_children();
            foreach ($children as $child_id) {
                $variation = wc_get_product($child_id);
                if ($variation) {
                    $variation_attributes_html = '';
                    $variation_attributes = $variation->get_variation_attributes();
                    foreach ($variation_attributes as $attr_name => $attr_value) {
                        $attr_value = urldecode($attr_value);
                        $attr_label = wc_attribute_label(str_replace('attribute_', '', $attr_name));
                        $persian_labels = array(
                            'Color' => 'رنگ', 'color' => 'رنگ',
                            'Size' => 'سایز', 'size' => 'سایز',
                            'Material' => 'جنس', 'material' => 'جنس',
                            'Style' => 'استایل', 'style' => 'استایل'
                        );
                        $attr_label_display = isset($persian_labels[$attr_label]) ? $persian_labels[$attr_label] : $attr_label;
                        $variation_attributes_html .= '<span class="psm-attr-badge"><span class="psm-attr-label">' . esc_html($attr_label_display) . ':</span> <span class="psm-attr-value">' . esc_html($attr_value) . '</span></span>';
                    }
                    if (empty($variation_attributes_html)) {
                        $variation_attributes_html = '<span class="psm-attr-badge">' . esc_html($variation->get_name()) . '</span>';
                    }

                    $variations_data[] = array(
                        'id' => $variation->get_id(),
                        'name' => $variation->get_name(),
                        'attributes_html' => $variation_attributes_html,
                        'sku' => $variation->get_sku(),
                        'stock_quantity' => ($variation->managing_stock() ? $variation->get_stock_quantity() : null),
                        'price' => $variation->get_price(),
                        'in_stock' => $variation->is_in_stock()
                    );
                }
            }
            wp_send_json_success(array(
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'is_variable' => true,
                'variations' => $variations_data,
                'sku' => $product->get_sku()
            ));
        }
    } else {
        wp_send_json_error(array('message' => 'محصول با این کد یا SKU یافت نشد.'));
    }
}
add_action('wp_ajax_psm_get_product_by_id', 'psm_get_product_by_id');
add_action('wp_ajax_nopriv_psm_get_product_by_id', 'psm_get_product_by_id');

/*-------------------------------------------
   AJAX: به‌روزرسانی موجودی محصول
-------------------------------------------*/
function psm_update_stock() {
    global $wpdb;
    $input = sanitize_text_field(trim($_POST['code'] ?? ''));
    $input_type = sanitize_text_field($_POST['input_type'] ?? 'sku');
    $stock_quantity_input = isset($_POST['stock_quantity']) ? psm_convert_persian_to_latin($_POST['stock_quantity']) : '0';
    $stock_quantity = intval($stock_quantity_input);
    $operation = sanitize_text_field($_POST['operation'] ?? 'set');
    
    $product_id = 0;
    $product = false;

    if (empty($input)) {
        wp_send_json_error(array('message' => 'کد محصول یا ID خالی است.'));
        return;
    }

    // Validate operation and stock quantity for 'set'
    if ($operation === 'set' && $stock_quantity < 0) {
        wp_send_json_error(array('message' => 'مقدار موجودی تنظیم شده نمی‌تواند منفی باشد.'));
        return;
    }

    // Function to perform stock update on a single product/variation
    $perform_stock_update = function($product_object) use ($operation, $stock_quantity, $wpdb) {
        $product_object->set_manage_stock(true);
        $current_stock = $product_object->managing_stock() ? $product_object->get_stock_quantity() : 0;
        if ($current_stock === null) $current_stock = 0;

        $new_stock = $stock_quantity;
        if ($operation === 'increase') {
            $new_stock = $current_stock + $stock_quantity;
        } elseif ($operation === 'decrease') {
            $new_stock = $current_stock - $stock_quantity;
        }

        if ($new_stock < 0) {
            throw new Exception('موجودی نمی‌تواند منفی شود.');
        }

        $product_object->set_stock_quantity($new_stock);
        $product_object->save();
        return $product_object->get_name();
    };

    try {
        // 1. Attempt to find product by ID if input_type suggests it or if input is numeric
        if (($input_type === 'id' || $input_type === 'barcode' || is_numeric($input)) && is_numeric($input)) {
            $product_id_candidate = intval($input);
            $product = wc_get_product($product_id_candidate);
            if ($product) {
                if ($product->is_type('simple') || $product->is_type('variation')) {
                    $product_name = $perform_stock_update($product);
                    return wp_send_json_success(array('message' => sprintf('موجودی "%s" به‌روزرسانی شد.', $product_name)));
                } elseif ($product->is_type('variable')) {
                    $variations = $product->get_children();
                    if (empty($variations)) throw new Exception('محصول متغیر مورد نظر زیرمجموعه‌ای ندارد.');
                    $updated_count = 0;
                    foreach ($variations as $variation_id) {
                        $variation = wc_get_product($variation_id);
                        if ($variation) {
                            $perform_stock_update($variation);
                            $updated_count++;
                        }
                    }
                    return wp_send_json_success(array('message' => sprintf('موجودی تمام %d زیرمجموعه "%s" به‌روزرسانی شد.', $updated_count, $product->get_name())));
                }
            }
        }

        // 2. If not found by ID, or if input type is SKU/barcode, attempt SKU lookup
        if (!$product) {
            $input_latin = psm_convert_persian_to_latin($input);
            $input_clean = str_replace(' ', '', $input_latin);
            $possible_skus = array_unique(array_filter([$input, $input_latin, $input_clean]));

            foreach ($possible_skus as $sku_to_check) {
                $product_id_from_sku = wc_get_product_id_by_sku($sku_to_check);
                if ($product_id_from_sku) {
                    $product = wc_get_product($product_id_from_sku);
                    if ($product) break;
                }
            }

            // If not found by WooCommerce's function, try direct DB lookup
            if (!$product) {
                foreach ($possible_skus as $sku_to_check) {
                    $product_id_db = $wpdb->get_var($wpdb->prepare(
                        "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value = %s",
                        $sku_to_check
                    ));
                    if ($product_id_db) {
                        $product = wc_get_product($product_id_db);
                        if ($product) break;
                    }
                }
            }

            // Process the product found by SKU
            if ($product) {
                if ($product->is_type('simple') || $product->is_type('variation')) {
                    $product_name = $perform_stock_update($product);
                    return wp_send_json_success(array('message' => sprintf('موجودی "%s" به‌روزرسانی شد.', $product_name)));
                } elseif ($product->is_type('variable')) {
                    $variations = $product->get_children();
                    if (empty($variations)) throw new Exception('محصول متغیر مورد نظر زیرمجموعه‌ای ندارد.');
                    $updated_count = 0;
                    foreach ($variations as $variation_id) {
                        $variation = wc_get_product($variation_id);
                        if ($variation) {
                            $perform_stock_update($variation);
                            $updated_count++;
                        }
                    }
                    return wp_send_json_success(array('message' => sprintf('موجودی تمام %d زیرمجموعه "%s" به‌روزرسانی شد.', $updated_count, $product->get_name())));
                }
            }
        }

        // If product is still not found after all attempts
        if (!$product) {
            throw new Exception('محصول با این کد یا SKU یافت نشد.');
        }

    } catch (Exception $e) {
        error_log('PSM Update Stock Error: ' . $e->getMessage());
        return wp_send_json_error(array('message' => $e->getMessage()));
    }
}
add_action('wp_ajax_psm_update_stock', 'psm_update_stock');
add_action('wp_ajax_nopriv_psm_update_stock', 'psm_update_stock');

?>
