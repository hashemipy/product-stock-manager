# راهنمای نصب و استفاده پلاگین Product Stock Manager

## نصب سریع

### مرحله 1: کپی فایل‌ها
```
wp-content/plugins/product-stock-manager/
├── product-stock-manager.php
├── includes/
├── assets/
├── templates/
└── README.md
```

### مرحله 2: فعال کردن پلاگین
1. وارد ادمین WordPress شوید
2. به بخش پلاگین‌ها بروید
3. پلاگین "Product Stock Manager" را فعال کنید

### مرحله 3: استفاده
1. از منوی «مدیریت موجودی» استفاده کنید
2. دو صفحه اصلی:
   - **مدیریت موجودی**: برای اسکن بارکد و ورود دستی
   - **لیست محصولات**: برای ویرایش دسته‌ای

## ساختار پلاگین

### فایل اصلی
- **product-stock-manager.php**: نقطه ورود پلاگین

### فایل‌های PHP
```
includes/
├── functions.php          → توابع کمکی
├── admin-menu.php         → منوی ادمین
└── ajax-handlers.php      → AJAX endpoints
```

### فایل‌های Frontend
```
assets/
├── css/
│   ├── stock-page.css
│   └── product-list-page.css
└── js/
    ├── stock-page.js
    └── product-list-page.js

templates/
├── stock-page.php
└── product-list-page.php
```

## متطلبات

- WordPress 5.0+
- WooCommerce 3.0+
- PHP 7.2+
- دسترسی `manage_woocommerce`

## ویژگی‌های اصلی

### صفحه 1: مدیریت موجودی
- اسکن بارکد (استفاده از Quagga.js)
- ورود دستی ID/SKU
- جستجوی محصول
- افزایش/کاهش موجودی
- پشتیبانی از اعداد فارسی

### صفحه 2: لیست محصولات
- نمایش تمام محصولات
- محصولات ساده و متغیر
- ویرایش موجودی درج‌ای
- ویرایش قیمت‌ها
- صفحه‌بندی خودکار
- فیلترینگ بر اساس وضعیت
- جستجوی فوری
- ذخیره دسته‌ای

## نوکات ایمنی

### توصیه‌های امنیتی
1. تأیید نقش کاربری (`manage_woocommerce`)
2. بررسی ورودی‌های کاربر
3. استفاده از `wp_send_json_*()` برای پاسخ‌های AJAX
4. توصیه: اضافه کردن nonce برای AJAX

### بدون پشتیبانی فعلی
- nonce verification (توصیه می‌شود اضافه کنید)
- Rate limiting
- User session validation

## AJAX Endpoints

### GET محصولات
```
Action: psm_get_all_products
POST: page (1-based)
```

### بروزرسانی محصول
```
Action: psm_update_product_details
POST: product_id, stock_quantity, regular_price, sale_price
```

### جستجو
```
Action: psm_search_products
POST: term
```

### بروزرسانی موجودی
```
Action: psm_update_stock
POST: code, stock_quantity, input_type (id/sku), operation (set/increase/decrease)
```

## کاربران و نقش‌ها

### دسترسی مورد نیاز
```php
// کاربران با قابلیت:
- manage_woocommerce
```

### مثال اضافه کردن برای نقش Custom
```php
$role = get_role('shop_manager');
$role->add_cap('manage_woocommerce');
```

## بهبودهای پیشنهادی

- [ ] اضافه کردن nonce برای امنیت بیشتر
- [ ] Rate limiting برای AJAX
- [ ] Export/Import CSV
- [ ] تاریخچه تغییرات
- [ ] گزارش‌های موجودی
- [ ] تنبیه‌های موجودی کم
- [ ] محدودیت موجودی برای کاربران

## حل مشکلات

### مشکل: اسکن بارکد کار نمی‌کند
- بررسی دسترسی دوربین
- بررسی کنسول مرورگر برای خطاها
- اطمینان از HTTPS (در بعضی موارد لازم است)

### مشکل: محصولات بارگذاری نمی‌شوند
- بررسی logs WordPress
- اطمینان از فعال بودن WooCommerce
- بررسی دسترسی database

### مشکل: AJAX خطا می‌دهد
- بررسی admin_url در console
- اطمینان از صحت POST data
- بررسی PHP errors

## پشتیبانی

برای کمک یا گزارش bugs، لطفاً تماس حاصل کنید.

---

**نویسنده**: hashemipy  
**نسخه**: 1.1.0  
**آخرین بروزرسانی**: 1403 (2024)
