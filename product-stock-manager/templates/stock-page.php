<?php
/**
 * Stock Management Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap" dir="rtl">
    <h1>مدیریت موجودی</h1>
    
    <!-- کادرهای ورودی برای به‌روزرسانی موجودی -->
    <div class="manual-input">
        <h3>به‌روزرسانی موجودی کل</h3>
        <input type="text" id="manual-stock-id" placeholder="ID محصول">
        <input type="text" id="manual-stock-sku" placeholder="کد محصول">
        <input type="text" id="manual-stock-search" placeholder="جستجوی محصول">
        <input type="number" id="stock-quantity" min="0" placeholder=" تعداد">
        <div class="button-container">
            <button id="add-manual-stock" class="psm-button">به‌روزرسانی موجودی</button>
        </div>
    </div>
    
    <!-- کادرهای ورودی برای افزایش/کاهش موجودی -->
    <div class="manual-input">
        <h3>افزایش/کاهش موجودی</h3>
        <input type="text" id="manual-adjust-id" placeholder="ID محصول">
        <input type="text" id="manual-adjust-sku" placeholder="کد محصول">
        <input type="text" id="manual-adjust-search" placeholder="جستجوی محصول">
        <input type="number" id="adjust-quantity" placeholder="مقدار تغییر">
        <div class="button-container">
            <button id="increase-stock" class="psm-button psm-button-green">افزایش موجودی</button>
            <button id="decrease-stock" class="psm-button psm-button-red">کاهش موجودی</button>
        </div>
    </div>
    
    <div id="interactive" class="viewport"></div>
    <button id="start-stock-scan" class="psm-button psm-button-green">شروع اسکن</button>
    <button id="stop-stock-scan" class="psm-button psm-button-red">توقف اسکن</button>
    <div id="stock-result" style="margin-top:20px;"></div>
</div>

<style>
    <?php include PSM_PLUGIN_DIR . 'assets/css/stock-page.css'; ?>
</style>

<script>
    <?php 
    // Localize script with admin URL
    $admin_ajax_url = admin_url('admin-ajax.php');
    ?>
    var psmAdminAjaxUrl = '<?php echo esc_url($admin_ajax_url); ?>';
</script>
<script src="<?php echo PSM_PLUGIN_URL . 'assets/js/stock-page.js'; ?>"></script>
