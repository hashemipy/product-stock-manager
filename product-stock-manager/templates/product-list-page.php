<?php
/**
 * Product List Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap psm-container" dir="rtl">
    <div class="psm-header">
        <h1>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="32" height="32" style="vertical-align: middle; margin-left: 10px;">
                <path d="M20 2H4c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM9 18H7v-5h2v5zm4 0h-2V6h2v12zm4 0h-2v-7h2v7z"/>
            </svg>
            مدیریت محصولات
        </h1>
    </div>

    <div class="psm-filters">
        <input type="text" id="psm-search-products" placeholder="🔍 جستجوی محصول..." class="psm-search-input">
        <select id="psm-filter-stock" class="psm-filter-select">
            <option value="all">📦 همه محصولات</option>
            <option value="in_stock">✅ موجود</option>
            <option value="out_of_stock">❌ ناموجود</option>
            <option value="low_stock">⚠️ موجودی کم</option>
        </select>
        <button id="psm-refresh-list" class="psm-button psm-button-primary">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
                <path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/>
            </svg>
            بروزرسانی
        </button>
    </div>

    <div id="psm-products-container">
        <div class="psm-loading">
            <div class="psm-spinner"></div>
            در حال بارگذاری محصولات...
        </div>
    </div>

    <!-- دکمه بارگذاری بیشتر -->
    <div id="psm-load-more-container" style="text-align: center; margin-top: 20px; display: none;">
        <button id="psm-load-more" class="psm-button psm-button-primary psm-button-large">
            📥 بارگذاری 50 محصول بعدی
        </button>
        <div id="psm-pagination-info" style="margin-top: 10px; color: #666; font-size: 13px;"></div>
    </div>

    <!-- دکمه ذخیره کلی شناور -->
    <div id="psm-floating-save" style="display: none;">
        <div class="psm-save-info">
            <span id="psm-changes-count">0</span> محصول تغییر یافته
        </div>
        <button id="psm-save-all" class="psm-button psm-button-success">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
                <path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/>
            </svg>
            ذخیره همه تغییرات
        </button>
    </div>
</div>

<style>
    <?php include PSM_PLUGIN_DIR . 'assets/css/product-list-page.css'; ?>
</style>

    <script>
    <?php 
    $admin_ajax_url = admin_url('admin-ajax.php');
    $nonce = wp_create_nonce('psm_nonce');
    ?>
    var psmAdminAjaxUrl = '<?php echo esc_url($admin_ajax_url); ?>';
    var psm_ajax_url = '<?php echo esc_url($admin_ajax_url); ?>';
    var psm_nonce = '<?php echo esc_attr($nonce); ?>';
</script>
<script src="<?php echo PSM_PLUGIN_URL . 'assets/js/product-list-page.js'; ?>"></script>
