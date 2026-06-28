<?php
/**
 * Admin Menu Setup for Product Stock Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

/*-------------------------------------------
   ثبت منوی ادمین پلاگین
-------------------------------------------*/
function psm_add_admin_menu() {
    add_menu_page(
        'مدیریت موجودی',
        'مدیریت موجودی',
        'manage_woocommerce',
        'psm-stock-manager',
        'psm_display_stock_page',
        'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white"><path d="M20 2H4c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM9 18H7v-5h2v5zm4 0h-2V6h2v12zm4 0h-2v-7h2v7z"/></svg>'),
        7
    );

    add_submenu_page(
        'psm-stock-manager',
        'لیست محصولات',
        'لیست محصولات',
        'manage_woocommerce',
        'psm-product-list',
        'psm_display_product_list_page'
    );
}
add_action('admin_menu', 'psm_add_admin_menu');

/*-------------------------------------------
   صفحه مدیریت موجودی
-------------------------------------------*/
function psm_display_stock_page() {
    include PSM_PLUGIN_DIR . 'templates/stock-page.php';
}

/*-------------------------------------------
   صفحه لیست محصولات با امکان ویرایش
-------------------------------------------*/
function psm_display_product_list_page() {
    include PSM_PLUGIN_DIR . 'templates/product-list-page.php';
}
?>
