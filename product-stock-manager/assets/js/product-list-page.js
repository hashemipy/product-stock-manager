jQuery(document).ready(function($) {
    let allProductsData = [];
    let currentPage = 1;
    let totalPages = 1;
    let isLoading = false;
    let changedProducts = {};

    function convertPersianToLatin(str) {
        const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        const latinDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        let result = str || '';
        for (let i = 0; i < persianDigits.length; i++) {
            result = result.replace(new RegExp(persianDigits[i], 'g'), latinDigits[i]);
        }
        return result.trim();
    }

    function convertLatinToPersian(str) {
        const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        const latinDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        let result = str || '';
        for (let i = 0; i < latinDigits.length; i++) {
            result = result.replace(new RegExp(latinDigits[i], 'g'), persianDigits[i]);
        }
        return result.trim();
    }

    function updateChangesCount() {
        let count = Object.keys(changedProducts).length;
        if (count > 0) {
            $('#psm-changes-count').text(count);
            $('#psm-floating-save').fadeIn();
        } else {
            $('#psm-floating-save').fadeOut();
        }
    }

    function trackChange(element) {
        let $input;
        let productId;
        let field;

        if ($(element).hasClass('psm-stock-input') || $(element).hasClass('psm-regular-price') || $(element).hasClass('psm-sale-price')) {
            $input = $(element);
            productId = $input.data('id');
            if ($input.hasClass('psm-stock-input')) {
                field = 'stock_quantity';
            } else if ($input.hasClass('psm-regular-price')) {
                field = 'regular_price';
            } else if ($input.hasClass('psm-sale-price')) {
                field = 'sale_price';
            }
        } else if ($(element).hasClass('psm-increase-stock') || $(element).hasClass('psm-decrease-stock')) {
            $input = $(element).siblings('.psm-stock-input');
            productId = $input.data('id');
            field = 'stock_quantity';
        } else {
            return;
        }

        if (!productId || !field) return;

        let currentValue = convertPersianToLatin($input.val());
        let originalValue = $input.data('original');

        if (currentValue != originalValue) {
            if (!changedProducts[productId]) {
                changedProducts[productId] = {};
            }
            changedProducts[productId][field] = currentValue;
            $('tr[data-product-id="' + productId + '"]').addClass('psm-changed');
        } else {
            if (changedProducts[productId] && changedProducts[productId][field] !== undefined) {
                if (changedProducts[productId][field] === originalValue) {
                    delete changedProducts[productId][field];
                    if (Object.keys(changedProducts[productId]).length === 0) {
                        delete changedProducts[productId];
                    }
                    $('tr[data-product-id="' + productId + '"]').removeClass('psm-changed');
                }
            }
        }

        updateChangesCount();
    }

    function loadProducts(page = 1, append = false) {
        if (isLoading) return;
        isLoading = true;

        if (!append) {
            $('#psm-products-container').html('<div class="psm-loading"><div class="psm-spinner"></div>در حال بارگذاری محصولات...</div>');
        } else {
            $('#psm-load-more').prop('disabled', true).html('<div class="psm-spinner" style="width: 20px; height: 20px; border-width: 3px; display: inline-block; margin-left: 10px;"></div> در حال بارگذاری...');
        }

        $.ajax({
            url: psmAdminAjaxUrl,
            method: 'POST',
            data: {
                action: 'psm_get_all_products',
                page: page
            },
            success: function(response) {
                if (response.success) {
                    if (append) {
                        allProductsData = allProductsData.concat(response.data.products);
                    } else {
                        allProductsData = response.data.products;
                    }

                    currentPage = response.data.current_page;
                    totalPages = response.data.total_pages;

                    renderProducts(allProductsData);
                    updatePaginationInfo(response.data);
                } else {
                    $('#psm-products-container').html('<div class="psm-no-products">❌ خطا در بارگذاری محصولات</div>');
                }
            },
            error: function() {
                $('#psm-products-container').html('<div class="psm-no-products">⚠️ خطا در ارتباط با سرور</div>');
            },
            complete: function() {
                isLoading = false;
                $('#psm-load-more').prop('disabled', false).html('📥 بارگذاری 50 محصول بعدی');
            }
        });
    }

    function updatePaginationInfo(data) {
        if (data.has_more) {
            $('#psm-load-more-container').show();
            $('#psm-pagination-info').html(`📊 صفحه <strong>${data.current_page}</strong> از <strong>${data.total_pages}</strong> (<strong>${allProductsData.length}</strong> از <strong>${data.total_products}</strong> محصول)`);
        } else {
            $('#psm-load-more-container').hide();
        }
    }

    function renderProducts(products) {
        if (products.length === 0) {
            $('#psm-products-container').html('<div class="psm-no-products">📭 محصولی یافت نشد</div>');
            return;
        }

        let html = '<table class="psm-products-table"><thead><tr>';
        html += '<th>محصول</th>';
        html += '<th style="text-align: center;">موجودی</th>';
        html += '<th>قیمت (تومان)</th>';
        html += '<th>قیمت حراجی</th>';
        html += '<th style="text-align: center;">وضعیت</th>';
        html += '</tr></thead><tbody>';

        products.forEach(function(product) {
            if (product.type === 'variable') {
                html += '<tr class="psm-variable-product" data-product-id="' + product.id + '">';
                html += '<td>';
                html += '<span class="psm-variable-icon">▶</span>';
                html += '<span class="psm-variations-count">' + product.variations_count + ' متغیر</span>';
                html += '<div class="psm-product-name">';
                if (product.image_url) {
                    html += '<img src="' + product.image_url + '" alt="' + product.name + '" class="psm-product-thumbnail">';
                }
                html += '<div class="psm-product-info">';
                html += '<span class="psm-product-meta">🆔 ' + product.id + '</span>';
                if (product.sku) {
                    html += '<span class="psm-product-meta">📦 ' + product.sku + '</span>';
                }
                html += '<br>' + product.name;
                html += '</div>';
                html += '</div>';
                html += '</td>';
                html += '<td colspan="4" style="color: #667eea; font-weight: 600; text-align: center; font-size: 11px;">کلیک کنید برای مشاهده متغیرها</td>';
                html += '</tr>';

                product.variations.forEach(function(variation) {
                    let stockStatus = variation.stock_quantity > 10 ? 'in-stock' : (variation.stock_quantity > 0 ? 'low-stock' : 'out-of-stock');
                    let stockText = variation.stock_quantity > 10 ? 'موجود' : (variation.stock_quantity > 0 ? 'موجودی کم' : 'ناموجود');

                    html += '<tr class="psm-variation-row" data-parent-id="' + product.id + '" data-product-id="' + variation.id + '">';
                    html += '<td>';
                    html += '<div class="psm-product-name">';
                    html += '<div class="psm-product-info">';
                    html += '<span class="psm-product-meta">🆔 ' + variation.id + '</span>';
                    if (variation.sku) {
                        html += '<span class="psm-product-meta">📦 ' + variation.sku + '</span>';
                    }
                    html += '<br><div class="psm-variation-attrs">' + variation.attributes_html + '</div>';
                    html += '</div>';
                    html += '</div>';
                    html += '</td>';

                    html += '<td><div class="psm-stock-controls">';
                    html += '<button class="psm-button psm-button-danger psm-button-small psm-decrease-stock" data-id="' + variation.id + '">−</button>';
                    html += '<input type="number" class="psm-stock-input psm-changed-input" data-id="' + variation.id + '" data-original="' + (variation.stock_quantity == null ? '' : variation.stock_quantity) + '" value="' + (variation.stock_quantity == null ? '' : variation.stock_quantity) + '" min="0">';
                    html += '<button class="psm-button psm-button-success psm-button-small psm-increase-stock" data-id="' + variation.id + '">+</button>';
                    html += '</div></td>';

                    html += '<td><input type="text" class="psm-price-input psm-regular-price psm-changed-input" data-id="' + variation.id + '" data-original="' + (variation.regular_price || '') + '" value="' + (variation.regular_price || '') + '" placeholder="قیمت"></td>';
                    html += '<td><input type="text" class="psm-price-input psm-sale-price psm-changed-input" data-id="' + variation.id + '" data-original="' + (variation.sale_price || '') + '" value="' + (variation.sale_price || '') + '" placeholder="حراجی"></td>';
                    html += '<td style="text-align: center;"><span class="psm-stock-badge ' + stockStatus + '">' + stockText + '</span></td>';
                    html += '</tr>';
                });
            } else {
                let stockStatus = product.stock_quantity > 10 ? 'in-stock' : (product.stock_quantity > 0 ? 'low-stock' : 'out-of-stock');
                let stockText = product.stock_quantity > 10 ? 'موجود' : (product.stock_quantity > 0 ? 'موجودی کم' : 'ناموجود');

                html += '<tr class="psm-simple-product" data-product-id="' + product.id + '">';
                html += '<td>';
                html += '<div class="psm-product-name">';
                if (product.image_url) {
                    html += '<img src="' + product.image_url + '" alt="' + product.name + '" class="psm-product-thumbnail">';
                }
                html += '<div class="psm-product-info">';
                html += '<span class="psm-product-meta">🆔 ' + product.id + '</span>';
                if (product.sku) {
                    html += '<span class="psm-product-meta">📦 ' + product.sku + '</span>';
                }
                html += '<br>' + product.name;
                html += '</div>';
                html += '</div>';
                html += '</td>';

                html += '<td><div class="psm-stock-controls">';
                html += '<button class="psm-button psm-button-danger psm-button-small psm-decrease-stock" data-id="' + product.id + '">−</button>';
                html += '<input type="number" class="psm-stock-input psm-changed-input" data-id="' + product.id + '" data-original="' + (product.stock_quantity == null ? '' : product.stock_quantity) + '" value="' + (product.stock_quantity == null ? '' : product.stock_quantity) + '" min="0">';
                html += '<button class="psm-button psm-button-success psm-button-small psm-increase-stock" data-id="' + product.id + '">+</button>';
                html += '</div></td>';

                html += '<td><input type="text" class="psm-price-input psm-regular-price psm-changed-input" data-id="' + product.id + '" data-original="' + (product.regular_price || '') + '" value="' + (product.regular_price || '') + '" placeholder="قیمت"></td>';
                html += '<td><input type="text" class="psm-price-input psm-sale-price psm-changed-input" data-id="' + product.id + '" data-original="' + (product.sale_price || '') + '" value="' + (product.sale_price || '') + '" placeholder="حراجی"></td>';
                html += '<td style="text-align: center;"><span class="psm-stock-badge ' + stockStatus + '">' + stockText + '</span></td>';
                html += '</tr>';
            }
        });

        html += '</tbody></table>';
        $('#psm-products-container').html(html);
    }

    $(document).on('click', '.psm-variable-product', function() {
        let productId = $(this).data('product-id');
        let $variations = $('.psm-variation-row[data-parent-id="' + productId + '"]');
        let $icon = $(this).find('.psm-variable-icon');

        $variations.toggleClass('show');
        $icon.toggleClass('expanded');
    });

    $(document).on('click', '.psm-increase-stock', function() {
        let $input = $(this).siblings('.psm-stock-input');
        let currentValue = parseInt(convertPersianToLatin($input.val())) || 0;
        $input.val(currentValue + 1).trigger('change');
    });

    $(document).on('click', '.psm-decrease-stock', function() {
        let $input = $(this).siblings('.psm-stock-input');
        let currentValue = parseInt(convertPersianToLatin($input.val())) || 0;
        if (currentValue > 0) {
            $input.val(currentValue - 1).trigger('change');
        }
    });

    $(document).on('click', '.psm-increase-stock, .psm-decrease-stock', function() {
        trackChange(this);
    });

    $(document).on('change input', '.psm-stock-input, .psm-regular-price, .psm-sale-price', function() {
        trackChange(this);
    });

    $('#psm-save-all').on('click', function() {
        if (Object.keys(changedProducts).length === 0) {
            return;
        }

        let $button = $(this);
        $button.prop('disabled', true).html('<div class="psm-spinner" style="width: 20px; height: 20px; border-width: 3px; display: inline-block; margin-left: 10px;"></div> در حال ذخیره...');

        let savePromises = [];

        $.each(changedProducts, function(productId, changes) {
            let $row = $('tr[data-product-id="' + productId + '"]');
            let stockQuantity = changes.stock_quantity !== undefined ? changes.stock_quantity : $row.find('.psm-stock-input').val();
            let regularPrice = changes.regular_price !== undefined ? changes.regular_price : $row.find('.psm-regular-price').val();
            let salePrice = changes.sale_price !== undefined ? changes.sale_price : $row.find('.psm-sale-price').val();

            stockQuantity = convertPersianToLatin(stockQuantity);
            regularPrice = convertPersianToLatin(regularPrice);
            salePrice = convertPersianToLatin(salePrice);

            let promise = $.ajax({
                url: psmAdminAjaxUrl,
                method: 'POST',
                data: {
                    action: 'psm_update_product_details',
                    product_id: productId,
                    stock_quantity: stockQuantity,
                    regular_price: regularPrice,
                    sale_price: salePrice
                }
            });

            savePromises.push(promise);
        });

        $.when.apply($, savePromises).done(function() {
            $button.html('✅ همه تغییرات ذخیره شد!');

            $.each(changedProducts, function(productId, changes) {
                let $row = $('tr[data-product-id="' + productId + '"]');
                let newStockQuantity = parseInt(changes.stock_quantity !== undefined ? changes.stock_quantity : $row.find('.psm-stock-input').val()) || 0;

                if (changes.stock_quantity !== undefined) {
                    $row.find('.psm-stock-input').data('original', changes.stock_quantity);
                }
                if (changes.regular_price !== undefined) {
                    $row.find('.psm-regular-price').data('original', changes.regular_price);
                }
                if (changes.sale_price !== undefined) {
                    $row.find('.psm-sale-price').data('original', changes.sale_price);
                }
                $row.removeClass('psm-changed');

                let stockStatus = newStockQuantity > 10 ? 'in-stock' : (newStockQuantity > 0 ? 'low-stock' : 'out-of-stock');
                let stockText = newStockQuantity > 10 ? 'موجود' : (newStockQuantity > 0 ? 'موجودی کم' : 'ناموجود');
                let $badge = $row.find('.psm-stock-badge');
                $badge.removeClass('in-stock out-of-stock low-stock').addClass(stockStatus).text(stockText);

                if ($row.hasClass('psm-variation-row')) {
                    let parentId = $row.data('parent-id');
                    if (parentId) {
                        let $parentRow = $('tr[data-product-id="' + parentId + '"]');
                        let hasInStockVariation = $('.psm-variation-row[data-parent-id="' + parentId + '"].show .psm-stock-badge.in-stock').length > 0;
                        let allOutOfStock = $('.psm-variation-row[data-parent-id="' + parentId + '"].show .psm-stock-badge.out-of-stock').length === $('.psm-variation-row[data-parent-id="' + parentId + '"].show').length;
                        if (allOutOfStock) {
                            $parentRow.find('td[colspan="4"]').html('<span style="color: #991b1b; font-weight: 600;">همه زیرمجموعه‌ها ناموجود</span>');
                        } else if (hasInStockVariation) {
                            $parentRow.find('td[colspan="4"]').html('<span style="color: #065f46; font-weight: 600;">حداقل یک زیرمجموعه موجود</span>');
                        }
                    }
                }
            });
            changedProducts = {};
            updateChangesCount();

            setTimeout(function() {
                $button.prop('disabled', false).html('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg> ذخیره همه تغییرات');
            }, 2000);
        }).fail(function(jqXHR, textStatus, errorThrown) {
            alert('❌ خطا در ذخیره برخی محصولات. لطفاً دوباره تلاش کنید.');
            console.error("Save all failed:", textStatus, errorThrown, jqXHR.responseText);
            $button.prop('disabled', false).html('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg> ذخیره همه تغییرات');
        });
    });

    // متغیر برای تایمر debounce جستجو
    let searchTimeout;
    
    $('#psm-search-products').on('keyup', function() {
        clearTimeout(searchTimeout);
        let searchTerm = $(this).val().trim();
        
        // اگر جستجو خالی است، تمام محصولات را نمایش بده
        if (searchTerm === '') {
            currentPage = 1;
            loadProducts(currentPage);
            return;
        }
        
        // debounce: منتظر باش تا کاربر تایپ کردن را متوقف کند
        searchTimeout = setTimeout(function() {
            console.log("[v0] جستجو شروع شد برای: " + searchTerm);
            $.ajax({
                url: psm_ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'psm_search_products_global',
                    term: searchTerm,
                    nonce: psm_nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        console.log("[v0] نتایج جستجو:", response.data.length);
                        allProductsData = response.data;
                        renderProducts(response.data);
                        
                        // نمایش پیام تعداد نتایج
                        let resultCount = response.data.length;
                        let countMessage = resultCount === 0 ? 'محصولی یافت نشد' : resultCount + ' محصول یافت شد';
                        $('#psm-products-count').html(countMessage);
                        
                        // پنهان کردن دکمه بارگذاری در حالت جستجو
                        updateLoadMoreButton();
                    } else {
                        console.log("[v0] هیچ نتیجه‌ای یافت نشد");
                        renderProducts([]);
                        $('#psm-products-count').html('محصولی یافت نشد');
                        updateLoadMoreButton();
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error("[v0] خطا در جستجو:", textStatus, errorThrown);
                    alert('❌ خطا در جستجو. لطفاً دوباره تلاش کنید.');
                }
            });
        }, 500); // تأخیر 500 میلی‌ثانیه قبل از AJAX
    });

    // تابع برای نمایش/پنهان کردن دکمه بارگذاری
    function updateLoadMoreButton() {
        let searchTerm = $('#psm-search-products').val().trim();
        if (searchTerm !== '') {
            // در حالت جستجو، دکمه بارگذاری را پنهان کن
            $('#psm-load-more').hide();
        } else {
            // در حالت عادی، دکمه را نمایش بده
            $('#psm-load-more').show();
        }
    }

    $('#psm-filter-stock').on('change', function() {
        let filter = $(this).val();
        let filtered = allProductsData;

        if (filter === 'in_stock') {
            filtered = allProductsData.filter(p => p.type === 'variable'
                ? p.variations.some(v => v.stock_quantity !== null && v.stock_quantity > 10)
                : (p.stock_quantity !== null && p.stock_quantity > 10)
            );
        } else if (filter === 'out_of_stock') {
            filtered = allProductsData.filter(p => p.type === 'variable'
                ? p.variations.every(v => v.stock_quantity === null || v.stock_quantity <= 0)
                : (p.stock_quantity !== null && p.stock_quantity <= 0)
            );
        } else if (filter === 'low_stock') {
            filtered = allProductsData.filter(p => p.type === 'variable'
                ? p.variations.some(v => v.stock_quantity !== null && v.stock_quantity > 0 && v.stock_quantity <= 10)
                : (p.stock_quantity !== null && p.stock_quantity > 0 && p.stock_quantity <= 10)
            );
        }
        renderProducts(filtered);
    });

    $('#psm-refresh-list').on('click', function() {
        currentPage = 1;
        allProductsData = [];
        changedProducts = {};
        updateChangesCount();
        loadProducts(1, false);
    });

    $('#psm-load-more').on('click', function() {
        if (currentPage < totalPages) {
            loadProducts(currentPage + 1, true);
        }
    });

    loadProducts(1, false);
});
