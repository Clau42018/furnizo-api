jQuery(document).ready(function($) {
    // Trimiterea unei comenzi individuale
    $('.send-superball-order').on('click', function(e) {
        e.preventDefault();
        var order_id = $(this).data('order-id');
        var $button = $(this);

        if (confirm(superball_ajax.strings.confirm_send_order.replace('{order_id}', order_id))) {
            $button.prop('disabled', true).text(superball_ajax.strings.sending);

            $.ajax({
                url: superball_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'send_superball_order',
                    nonce: superball_ajax.nonce,
                    order_id: order_id
                },
                success: function(response) {
                    if (response.success) {
                        alert(superball_ajax.strings.success_sent.replace('{count}', 1));
                        location.reload();
                    } else {
                        alert(response.data.message || superball_ajax.strings.error_occurred);
                        $button.prop('disabled', false).text(superball_ajax.strings.send_order);
                    }
                },
                error: function() {
                    alert(superball_ajax.strings.error_occurred);
                    $button.prop('disabled', false).text(superball_ajax.strings.send_order);
                }
            });
        }
    });



jQuery(document).ready(function($) {
    // Handle "Update Stocks Now" button click
    $('#update-stocks-now').on('click', function(e) {
        e.preventDefault();

        // Confirm action
        if (!confirm(superball_ajax.strings.confirm_send_all)) {
            return;
        }

        // Disable the button and show loading text
        var button = $(this);
        var statusSpan = $('#update-stocks-now-status');
        button.prop('disabled', true);
        statusSpan.text(superball_ajax.strings.updating_stocks);

        // Make AJAX request to trigger stock update
        $.post(superball_ajax.ajax_url, {
            action: 'update_stock_now',
            nonce: superball_ajax.nonce
        }, function(response) {
            if (response.success) {
                statusSpan.text(response.data.message);
            } else {
                statusSpan.text(response.data.message);
            }

            // Re-enable the button
            button.prop('disabled', false);
        }).fail(function() {
            statusSpan.text(superball_ajax.strings.update_failure);
            button.prop('disabled', false);
        });
    });

    // Handle "Import Products Now" button click
    $('#import-products-now').on('click', function(e) {
        e.preventDefault();

        // Confirm action
        if (!confirm('Ești sigur că dorești să inițiezi importul produselor?')) {
            return;
        }

        // Disable the button and show loading text
        var button = $(this);
        var statusSpan = $('#import-products-now-status');
        button.prop('disabled', true);
        statusSpan.text(superball_ajax.strings.importing_products);

        // Make AJAX request to trigger product import
        $.post(superball_ajax.ajax_url, {
            action: 'import_products_now',
            nonce: superball_ajax.nonce
        }, function(response) {
            if (response.success) {
                statusSpan.text(response.data.message);
            } else {
                statusSpan.text(response.data.message);
            }

            // Re-enable the button
            button.prop('disabled', false);
        }).fail(function() {
            statusSpan.text(superball_ajax.strings.import_failure);
            button.prop('disabled', false);
        });
    });

    // Handle Price Markup Preview
    $('input[name="superball_api_settings[price_markup]"]').on('input', function() {
        var markup = parseFloat($(this).val());
        if (isNaN(markup) || markup < 0) {
            markup = 0;
        }
        var example_price = 100; // Exemplu de preț fără TVA
        var calculated_price = example_price * (1 + (markup / 100));
        $('#price-preview').html('<strong>' + example_price + '</strong> x (1 + (' + markup + ' / 100)) = <strong>' + calculated_price.toFixed(2) + '</strong>');
    });

    // Trigger input event on page load to show initial preview
    $('input[name="superball_api_settings[price_markup]"]').trigger('input');
});
