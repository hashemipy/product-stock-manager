jQuery(document).ready(function($) {
    let scannerActive = false;
    let productCache = {};
    let isProcessing = false;
    let lastScannedCode = null;
    let lastScanTime = 0;
    let scanMode = 'update';

    // تابع تبدیل اعداد فارسی به لاتین
    function convertPersianToLatin(code) {
        const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        const latinDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        let result = code || '';
        for (let i = 0; i < persianDigits.length; i++) {
            result = result.replace(new RegExp(persianDigits[i], 'g'), latinDigits[i]);
        }
        return result.trim();
    }

    // تابع تبدیل اعداد لاتین به فارسی
    function convertLatinToPersian(str) {
        const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        const latinDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        let result = str || '';
        for (let i = 0; i < latinDigits.length; i++) {
            result = result.replace(new RegExp(latinDigits[i], 'g'), persianDigits[i]);
        }
        return result.trim();
    }

    // تنظیم جستجوی هوشمند
    function setupAutocomplete(selector) {
        $(selector).autocomplete({
            source: function(request, response) {
                $.ajax({
                    url: psmAdminAjaxUrl,
                    method: 'POST',
                    data: {
                        action: 'psm_search_products',
                        term: request.term
                    },
                    success: function(data) {
                        if (data.success) {
                            response(data.data.map(item => ({
                                label: item.name + ' (ID: ' + item.id + ', SKU: ' + (item.sku || 'ندارد') + ')',
                                value: item.name,
                                id: item.id,
                                sku: item.sku
                            })));
                        } else {
                            response([]);
                        }
                    }
                });
            },
            minLength: 2,
            select: function(event, ui) {
                let section = $(this).attr('id').includes('stock-search') ? 'stock' : 'adjust';
                $('#manual-' + section + '-id').val(ui.item.id);
                $('#manual-' + section + '-sku').val(ui.item.sku || '');
            }
        });
    }
    setupAutocomplete('#manual-stock-search');
    setupAutocomplete('#manual-adjust-search');

    // تابع مدیریت محصول
    function handleProduct(productData, mode, quantity, operation = 'set') {
        let stockQuantity = parseInt(convertPersianToLatin(quantity));
        if (isNaN(stockQuantity) || (operation === 'set' && stockQuantity < 0)) {
            $('#stock-result').html('<span style="color:red;">لطفاً مقدار معتبر وارد کنید.</span>');
            if (scannerActive) {
                setTimeout(function() { Quagga.onDetected(onStockBarcodeScanned); }, 500);
            }
            return;
        }

        isProcessing = true;
        $('#stock-result').html('<span style="color:blue;">در حال به‌روزرسانی موجودی...</span>');
        $.ajax({
            url: psmAdminAjaxUrl,
            method: 'POST',
            data: {
                action: 'psm_update_stock',
                code: productData.id,
                stock_quantity: stockQuantity,
                input_type: 'id',
                operation: operation
            },
            success: function(response) {
                if (response.success) {
                    $('#stock-result').html('<span style="color:green;">' + response.data.message + '</span>');
                } else {
                    $('#stock-result').html('<span style="color:red;">' + (response.data.message || 'خطا در به‌روزرسانی موجودی.') + '</span>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.log('AJAX Error:', textStatus, errorThrown, jqXHR.responseText);
                $('#stock-result').html('<span style="color:red;">خطا در ارتباط با سرور: ' + textStatus + '</span>');
            },
            complete: function() {
                isProcessing = false;
                if (scannerActive) {
                    setTimeout(function() { Quagga.onDetected(onStockBarcodeScanned); }, 500);
                }
            }
        });
    }

    // شروع اسکن
    $('#start-stock-scan').on('click', function() {
        if (scannerActive) return;
        scannerActive = true;
        Quagga.init({
            inputStream: {
                name: "Live",
                type: "LiveStream",
                constraints: {
                    facingMode: "environment",
                    width: 640,
                    height: 480
                },
                target: document.querySelector('#interactive')
            },
            frequency: 10,
            numOfWorkers: navigator.hardwareConcurrency || 4,
            locate: true,
            decoder: { readers: ["code_128_reader"] }
        }, function(err) {
            if (err) {
                console.log('Quagga Error:', err);
                alert('خطا در راه‌اندازی اسکنر.');
                scannerActive = false;
                return;
            }
            Quagga.start();
        });
        Quagga.onDetected(onStockBarcodeScanned);
    });

    // اسکن بارکد
    function onStockBarcodeScanned(result) {
        if (!scannerActive || isProcessing) return;
        let currentTime = new Date().getTime();
        let code = convertPersianToLatin(result.codeResult.code);
        if (lastScannedCode === code && currentTime - lastScanTime < 1000) {
            setTimeout(function() { Quagga.onDetected(onStockBarcodeScanned); }, 500);
            return;
        }
        lastScannedCode = code;
        lastScanTime = currentTime;
        Quagga.offDetected(onStockBarcodeScanned);

        if (productCache[code]) {
            if (productCache[code].is_variable) {
                showVariationSelector(productCache[code].variations, code);
            } else {
                promptForQuantity(productCache[code]);
            }
            return;
        }

        isProcessing = true;
        $('#stock-result').html('<span style="color:blue;">در حال جستجوی محصول...</span>');
        $.ajax({
            url: psmAdminAjaxUrl,
            method: 'POST',
            data: { action: 'psm_get_product_by_id', product_id: code, input_type: 'barcode' },
            success: function(response) {
                if (response.success) {
                    productCache[code] = response.data;
                    if (response.data.is_variable) {
                        showVariationSelector(response.data.variations, code);
                    } else {
                        promptForQuantity(response.data);
                    }
                } else {
                    $('#stock-result').html('<span style="color:red;">' + (response.data.message || 'محصول با این کد یافت نشد.') + '</span>');
                    if (scannerActive) {
                        setTimeout(function() { Quagga.onDetected(onStockBarcodeScanned); }, 500);
                    }
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.log('AJAX Error:', textStatus, errorThrown, jqXHR.responseText);
                $('#stock-result').html('<span style="color:red;">خطا در ارتباط با سرور: ' + textStatus + '</span>');
                if (scannerActive) {
                    setTimeout(function() { Quagga.onDetected(onStockBarcodeScanned); }, 500);
                }
            },
            complete: function() {
                isProcessing = false;
                if (scannerActive) {
                    Quagga.onDetected(onStockBarcodeScanned);
                }
            }
        });
    }

    // درخواست مقدار از کاربر
    function promptForQuantity(productData) {
        let promptText = 'مقدار موجودی برای "' + productData.name + '" را وارد کنید:';
        let rawQuantity = prompt(promptText, '0');
        if (rawQuantity === null || rawQuantity.trim() === '') {
            $('#stock-result').html('<span style="color:red;">عملیات لغو شد.</span>');
            if (scannerActive) {
                setTimeout(function() { Quagga.onDetected(onStockBarcodeScanned); }, 500);
            }
            return;
        }
        handleProduct(productData, 'update', rawQuantity, 'set');
    }

    // انتخاب زیرمجموعه برای محصولات متغیر
    function showVariationSelector(variations, scannedCode) {
        if (!variations || variations.length === 0) {
            $('#stock-result').html('<span style="color:red;">برای این محصول متغیر، زیرمجموعه‌ای یافت نشد.</span>');
            if (scannerActive) {
                setTimeout(function() { Quagga.onDetected(onStockBarcodeScanned); }, 500);
            }
            return;
        }

        let optionsHtml = '<option value="">-- انتخاب زیرمجموعه --</option>';
        variations.forEach(function(v) {
            let stockInfo = v.stock_quantity == null ? 'نامحدود' : v.stock_quantity;
            optionsHtml += `<option value="${v.id}">[${v.sku || v.id}] ${v.name} (موجودی: ${stockInfo})</option>`;
        });

        let selectHtml = `<div style="text-align:center; padding: 15px;">
                            <label for="variation-selector">لطفاً زیرمجموعه محصول را انتخاب کنید:</label><br><br>
                            <select id="variation-selector" style="padding: 8px; border-radius: 4px; border: 1px solid #ccc; min-width: 250px;">
                                ${optionsHtml}
                            </select>
                            <button id="select-variation-btn" style="margin-left: 10px; padding: 8px 15px; border-radius: 4px; border: none; background-color: #0073aa; color: white; cursor: pointer;">تایید</button>
                     </div>`;

        $('#stock-result').html(selectHtml).css('margin-top', '20px');

        $('#select-variation-btn').off('click').on('click', function() {
            let selectedVariationId = $('#variation-selector').val();
            if (selectedVariationId) {
                let selectedVariation = variations.find(v => v.id == selectedVariationId);
                if (selectedVariation) {
                    productCache[scannedCode] = { ...productCache[scannedCode], ...selectedVariation };
                    promptForQuantity(selectedVariation);
                } else {
                    $('#stock-result').html('<span style="color:red;">خطا در انتخاب زیرمجموعه.</span>');
                    if (scannerActive) {
                        setTimeout(function() { Quagga.onDetected(onStockBarcodeScanned); }, 500);
                    }
                }
            } else {
                $('#stock-result').html('<span style="color:red;">لطفاً یک زیرمجموعه انتخاب کنید.</span>');
            }
        });
    }

    // توقف اسکن
    $('#stop-stock-scan').on('click', function() {
        if (scannerActive) {
            scannerActive = false;
            Quagga.stop();
            Quagga.offDetected();
            $('#interactive').empty();
            $('#stock-result').html('');
        }
    });

    // ورود دستی - به‌روزرسانی موجودی
    $('#add-manual-stock').on('click', function() {
        if (isProcessing) return;
        isProcessing = true;
        let $button = $(this);
        $button.prop('disabled', true).text('در حال به‌روزرسانی موجودی...');
        $('#stock-result').html('');
        let id = convertPersianToLatin($('#manual-stock-id').val().trim());
        let sku = convertPersianToLatin($('#manual-stock-sku').val().trim());
        let code = id || sku;
        let input_type = id ? 'id' : (sku ? 'sku' : '');
        let stockQuantity = $('#stock-quantity').val().trim();

        if (!code) {
            $('#stock-result').html('<span style="color:red;">لطفاً ID محصول یا کد محصول را وارد کنید.</span>');
            isProcessing = false;
            $button.prop('disabled', false).text('به‌روزرسانی موجودی');
            return;
        }
        if (stockQuantity === '' || isNaN(stockQuantity) || parseInt(stockQuantity) < 0) {
            $('#stock-result').html('<span style="color:red;">لطفاً مقدار موجودی معتبر وارد کنید.</span>');
            isProcessing = false;
            $button.prop('disabled', false).text('به‌روزرسانی موجودی');
            return;
        }

        $.ajax({
            url: psmAdminAjaxUrl,
            method: 'POST',
            data: {
                action: 'psm_update_stock',
                code: code,
                stock_quantity: parseInt(stockQuantity),
                input_type: input_type,
                operation: 'set'
            },
            success: function(response) {
                if (response.success) {
                    $('#stock-result').html('<span style="color:green;">' + response.data.message + '</span>');
                    $('#manual-stock-id').val('');
                    $('#manual-stock-sku').val('');
                    $('#manual-stock-search').val('');
                    $('#stock-quantity').val('');
                } else {
                    $('#stock-result').html('<span style="color:red;">' + (response.data.message || 'خطا در به‌روزرسانی موجودی.') + '</span>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.log('AJAX Error:', textStatus, errorThrown);
                $('#stock-result').html('<span style="color:red;">خطا در ارتباط با سرور.</span>');
            },
            complete: function() {
                isProcessing = false;
                $button.prop('disabled', false).text('به‌روزرسانی موجودی');
            }
        });
    });

    // ورود دستی - افزایش موجودی
    $('#increase-stock').on('click', function() {
        if (isProcessing) return;
        isProcessing = true;
        let $button = $(this);
        $button.prop('disabled', true).text('در حال افزایش موجودی...');
        $('#stock-result').html('');
        let id = convertPersianToLatin($('#manual-adjust-id').val().trim());
        let sku = convertPersianToLatin($('#manual-adjust-sku').val().trim());
        let code = id || sku;
        let input_type = id ? 'id' : (sku ? 'sku' : '');
        let quantity = $('#adjust-quantity').val().trim();

        if (!code) {
            $('#stock-result').html('<span style="color:red;">لطفاً ID محصول یا کد محصول را وارد کنید.</span>');
            isProcessing = false;
            $button.prop('disabled', false).text('افزایش موجودی');
            return;
        }
        if (quantity === '' || isNaN(quantity)) {
            $('#stock-result').html('<span style="color:red;">لطفاً مقدار تغییر معتبر وارد کنید.</span>');
            isProcessing = false;
            $button.prop('disabled', false).text('افزایش موجودی');
            return;
        }

        $.ajax({
            url: psmAdminAjaxUrl,
            method: 'POST',
            data: {
                action: 'psm_update_stock',
                code: code,
                stock_quantity: parseInt(quantity),
                input_type: input_type,
                operation: 'increase'
            },
            success: function(response) {
                if (response.success) {
                    $('#stock-result').html('<span style="color:green;">' + response.data.message + '</span>');
                    $('#manual-adjust-id').val('');
                    $('#manual-adjust-sku').val('');
                    $('#manual-adjust-search').val('');
                    $('#adjust-quantity').val('');
                } else {
                    $('#stock-result').html('<span style="color:red;">' + (response.data.message || 'خطا در افزایش موجودی.') + '</span>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.log('AJAX Error:', textStatus, errorThrown);
                $('#stock-result').html('<span style="color:red;">خطا در ارتباط با سرور.</span>');
            },
            complete: function() {
                isProcessing = false;
                $button.prop('disabled', false).text('افزایش موجودی');
            }
        });
    });

    // ورود دستی - کاهش موجودی
    $('#decrease-stock').on('click', function() {
        if (isProcessing) return;
        isProcessing = true;
        let $button = $(this);
        $button.prop('disabled', true).text('در حال کاهش موجودی...');
        $('#stock-result').html('');
        let id = convertPersianToLatin($('#manual-adjust-id').val().trim());
        let sku = convertPersianToLatin($('#manual-adjust-sku').val().trim());
        let code = id || sku;
        let input_type = id ? 'id' : (sku ? 'sku' : '');
        let quantity = $('#adjust-quantity').val().trim();

        if (!code) {
            $('#stock-result').html('<span style="color:red;">لطفاً ID محصول یا کد محصول را وارد کنید.</span>');
            isProcessing = false;
            $button.prop('disabled', false).text('کاهش موجودی');
            return;
        }
        if (quantity === '' || isNaN(quantity)) {
            $('#stock-result').html('<span style="color:red;">لطفاً مقدار تغییر معتبر وارد کنید.</span>');
            isProcessing = false;
            $button.prop('disabled', false).text('کاهش موجودی');
            return;
        }

        $.ajax({
            url: psmAdminAjaxUrl,
            method: 'POST',
            data: {
                action: 'psm_update_stock',
                code: code,
                stock_quantity: parseInt(quantity),
                input_type: input_type,
                operation: 'decrease'
            },
            success: function(response) {
                if (response.success) {
                    $('#stock-result').html('<span style="color:green;">' + response.data.message + '</span>');
                    $('#manual-adjust-id').val('');
                    $('#manual-adjust-sku').val('');
                    $('#manual-adjust-search').val('');
                    $('#adjust-quantity').val('');
                } else {
                    $('#stock-result').html('<span style="color:red;">' + (response.data.message || 'خطا در کاهش موجودی.') + '</span>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.log('AJAX Error:', textStatus, errorThrown);
                $('#stock-result').html('<span style="color:red;">خطا در ارتباط با سرور.</span>');
            },
            complete: function() {
                isProcessing = false;
                $button.prop('disabled', false).text('کاهش موجودی');
            }
        });
    });

    $('#add-manual-stock').on('click', function() { scanMode = 'update'; });
});
