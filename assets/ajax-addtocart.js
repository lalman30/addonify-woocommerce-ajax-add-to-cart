(function ($) {

    'use strict';

    // Document ready function

    $(document).ready(function () {

        $(document).on('submit', '.single-product .entry-summary form.cart', function (event) {

            event.preventDefault();

            let addToCartForm = $(this);

            let productID = 0;

            let productType = '';

            // Set class `loading` in the add to cart button.
            let addtoCartButton = addToCartForm.find('.single_add_to_cart_button');
            addtoCartButton.addClass('loading');

            // Select the product container
            let productContainer = jQuery('.product');

            // Check if the product container exists
            if (productContainer.length > 0) {
                // Get the product class list
                let productClasses = productContainer.attr('class');

                // Check if the class contains the product type
                if (productClasses.includes('product-type-simple')) {
                    productType = 'simple';
                } else if (productClasses.includes('product-type-variable')) {
                    productType = 'variable';
                } else if (productClasses.includes('product-type-external')) {
                    productType = 'external';
                } else if (productClasses.includes('product-type-grouped')) {
                    productType = 'grouped';
                }
            }


            if (productType === 'external') {
                event.currentTarget.submit();
                return;
            }

            let formData = {
                type: '',
                data: {},
            };

            // Prepare data for grouped form.
            if (productType === 'grouped') {
                formData.type = 'grouped';
                let allProductsRows = addToCartForm.find('.woocommerce-grouped-product-list-item');
                if (allProductsRows.length > 0) {
                    allProductsRows.map((index, productRow) => {
                        if (productRow.classList.contains('product-type-simple')) {
                            let productRowID = productRow.id;
                            let productID = parseInt(productRowID.replace('product-', ''));
                            let quantity = 0;
                            if (productRow.classList.contains('sold-individually')) {
                                let quantityEle = addToCartForm.find('#' + productRowID + ' input.wc-grouped-product-add-to-cart-checkbox');
                                quantity = quantityEle.is(':checked') ? quantityEle.val() : 0;
                            } else {
                                let quantityEle = addToCartForm.find('#' + productRowID + ' input.qty');
                                quantity = quantityEle.val();
                            }

                            formData.data[productID] = (quantity === '') ? 0 : quantity;
                        }
                    });
                }
            }

            // Prepare data for variable form.
            if (productType === 'variable') {
                formData.type = 'variable';
                productID = $('input[name="product_id"]').val();
                formData.data.product_id = productID;
                formData.data.variation_id = $('input[name="variation_id"]').val();
                formData.data.quantity = $('input.qty').val();
                let variations = {};
                let variationEles = addToCartForm.find('table.variations td.value select');
                if (variationEles.length > 0) {
                    variationEles.map((index, variationEle) => {
                        let variationName = variationEle.getAttribute('name');
                        let variationValue = variationEle.value;
                        variations[variationName] = variationValue;
                    });
                }
                formData.data.variations = variations;
            }

            // Prepare data for simple form.
            if (!addToCartForm.hasClass('variations_form') && !addToCartForm.hasClass('grouped_form')) {
                formData.type = 'simple';
                productID = addtoCartButton.val();
                formData.data.product_id = productID;
                formData.data.quantity = $('.quantity input.qty').val();
            }

            formData.nonce = ajaxSingleAddtocartJSObj.nonce;

            // Make AJAX request and handle response.
            $.ajax({
                'url': ajaxSingleAddtocartJSObj.ajax_url,
                'method': 'POST',
                'data': {
                    action: ajaxSingleAddtocartJSObj.ajaxSingleAddToCartAction,
                    form_data: formData
                },
                'success': function (response) {
                    if (response.status) {
                        // Trigger WooCommerce event for added-to-cart
                        addtoCartButton.data('product_id', productID);
                        $(document.body).trigger('added_to_cart', [response.fragments, response.cart_hash, addtoCartButton]);

                        // Reset quantity fields
                        let quantityFields = addToCartForm.find('input.qty');
                        if (quantityFields.length > 0) {
                            $.each(quantityFields, function (key, quantityField) {
                                let minVal = quantityField.getAttribute('min') || 1;
                                quantityField.value = minVal;

                                let changeEvent = new Event('change');
                                quantityField.dispatchEvent(changeEvent);
                            });
                        }

                        // Reset add-to-cart form
                        addToCartForm.trigger('reset');
                    } else {
                        // Handle failure
                        addtoCartButton.removeClass('loading');
                        event.currentTarget.submit(); // Fallback to default behavior
                        return;
                    }

                    // WooCommerce notices handling
                    $(document.body).trigger('wc_fragment_refresh'); // Refresh mini cart

                    // Fire all notices.
                    let notices = response.notices;

                    let wcNoticeWrappers = $('.woocommerce-notices-wrapper');
                    if (wcNoticeWrappers.length > 0) {
                        wcNoticeWrappers.html(notices);
                    }
                },
                'error': function (jxhr, status, errorThrown) {
                    throw new Error(errorThrown);
                    event.currentTarget.submit();
                    return;
                },
            });

        });
    });

})(jQuery);