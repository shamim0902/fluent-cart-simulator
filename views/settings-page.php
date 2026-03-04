<?php
defined('ABSPATH') || exit;

/** @var array $settings */
/** @var array $stats */
/** @var int $simulatedCount */
/** @var int $ordersPerHour */
/** @var bool $isRunning */

$statusDist = $settings['status_distribution'] ?? [];
$productIdsStr = implode(', ', $settings['product_ids'] ?? []);
$paymentMethods = $settings['payment_methods'] ?? ['offline_payment'];

$availablePaymentMethods = [
    'offline_payment' => 'Cash on Delivery',
    'stripe'          => 'Stripe',
    'paypal'          => 'PayPal',
    'square'          => 'Square',
    'razorpay'        => 'Razorpay',
    'paystack'        => 'Paystack',
    'airwallex'       => 'Airwallex',
];
?>
<div class="wrap">
    <h1>FluentCart Order Simulator</h1>
    <p>Automatically generate test orders for FluentCart. All simulated orders are marked with <code>mode=test</code> and can be purged at any time.</p>

    <?php if ($isRunning): ?>
        <div class="notice notice-warning inline" style="margin: 15px 0;">
            <p><strong>Simulation is ACTIVE</strong> &mdash; generating approximately <?php echo intval($ordersPerHour); ?> orders per hour.</p>
        </div>
    <?php endif; ?>

    <!-- Status Panel -->
    <div class="card" style="max-width: 600px; margin-bottom: 20px;">
        <h2 style="margin-top: 0;">Status</h2>
        <table class="widefat striped" style="border: 0;">
            <tbody>
                <tr>
                    <td><strong>Simulation</strong></td>
                    <td>
                        <?php if ($isRunning): ?>
                            <span style="color: #d63638; font-weight: bold;">Running (<?php echo intval($ordersPerHour); ?>/hr)</span>
                        <?php else: ?>
                            <span style="color: #50575e;">Stopped</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>Total Generated (session)</strong></td>
                    <td id="fcsim-total-generated"><?php echo intval($stats['total_generated'] ?? 0); ?></td>
                </tr>
                <tr>
                    <td><strong>Last Run</strong></td>
                    <td id="fcsim-last-run"><?php echo esc_html($stats['last_run'] ?? 'Never'); ?></td>
                </tr>
                <tr>
                    <td><strong>Simulated Orders in DB</strong></td>
                    <td id="fcsim-sim-count"><?php echo intval($simulatedCount); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Settings Form -->
    <form id="fcsim-settings-form" method="post">
        <?php wp_nonce_field('fcsim_nonce', 'fcsim_nonce_field'); ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="orders_per_hour">Orders per Hour</label></th>
                <td>
                    <input type="number" id="orders_per_hour" name="orders_per_hour"
                           value="<?php echo intval($ordersPerHour); ?>"
                           min="0" max="1000" class="small-text">
                    <p class="description">Set to 0 to disable automatic generation. Orders are generated in batches every 5 minutes.</p>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="product_ids">Limit to Product IDs</label></th>
                <td>
                    <input type="text" id="product_ids" name="product_ids"
                           value="<?php echo esc_attr($productIdsStr); ?>"
                           class="regular-text" placeholder="e.g. 12, 34, 56">
                    <p class="description">Comma-separated product IDs. Leave blank to use all published products.</p>
                </td>
            </tr>

            <tr>
                <th scope="row">Items per Order</th>
                <td>
                    <label>
                        Min: <input type="number" name="min_items_per_order"
                                    value="<?php echo intval($settings['min_items_per_order'] ?? 1); ?>"
                                    min="1" max="20" class="small-text">
                    </label>
                    &nbsp;&nbsp;
                    <label>
                        Max: <input type="number" name="max_items_per_order"
                                    value="<?php echo intval($settings['max_items_per_order'] ?? 5); ?>"
                                    min="1" max="20" class="small-text">
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row">Order Status Distribution (%)</th>
                <td>
                    <fieldset>
                        <label style="display: inline-block; margin-right: 15px;">
                            Completed: <input type="number" name="status_completed"
                                              value="<?php echo intval($statusDist['completed'] ?? 40); ?>"
                                              min="0" max="100" class="small-text">
                        </label>
                        <label style="display: inline-block; margin-right: 15px;">
                            Processing: <input type="number" name="status_processing"
                                               value="<?php echo intval($statusDist['processing'] ?? 30); ?>"
                                               min="0" max="100" class="small-text">
                        </label>
                        <label style="display: inline-block; margin-right: 15px;">
                            Failed: <input type="number" name="status_failed"
                                           value="<?php echo intval($statusDist['failed'] ?? 15); ?>"
                                           min="0" max="100" class="small-text">
                        </label>
                        <label style="display: inline-block; margin-right: 15px;">
                            On Hold: <input type="number" name="status_on_hold"
                                            value="<?php echo intval($statusDist['on-hold'] ?? 15); ?>"
                                            min="0" max="100" class="small-text">
                        </label>
                        <p class="description">Percentages should add up to 100. If they don't, they'll be used as relative weights.</p>
                    </fieldset>
                </td>
            </tr>

            <tr>
                <th scope="row">Create New Customers</th>
                <td>
                    <label>
                        <input type="checkbox" name="create_new_customers" value="1"
                               <?php checked(!empty($settings['create_new_customers'])); ?>>
                        Generate fake customers for orders
                    </label>
                    <p class="description">If unchecked, orders will be assigned to existing customers.</p>
                </td>
            </tr>

            <tr>
                <th scope="row">Payment Methods</th>
                <td>
                    <fieldset>
                        <?php foreach ($availablePaymentMethods as $slug => $label): ?>
                            <label style="display: block; margin-bottom: 5px;">
                                <input type="checkbox" name="payment_methods[]" value="<?php echo esc_attr($slug); ?>"
                                       <?php checked(in_array($slug, $paymentMethods)); ?>>
                                <?php echo esc_html($label); ?>
                            </label>
                        <?php endforeach; ?>
                        <p class="description">Select payment methods to randomly assign to generated orders.</p>
                    </fieldset>
                </td>
            </tr>
        </table>

        <p class="submit" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <button type="submit" class="button button-primary" id="fcsim-save">Save Settings</button>
            <button type="button" class="button" id="fcsim-generate-now">Generate 5 Orders Now</button>
            <?php if ($isRunning): ?>
                <button type="button" class="button" id="fcsim-stop" style="color: #d63638; border-color: #d63638;">
                    Stop Simulation
                </button>
            <?php endif; ?>
        </p>
    </form>

    <!-- Danger Zone -->
    <hr style="margin: 30px 0 20px;">
    <div class="card" style="max-width: 600px; border-left-color: #d63638;">
        <h2 style="margin-top: 0; color: #d63638;">Danger Zone</h2>
        <p>This will permanently delete all simulated orders (mode=test with simulator marker) and their related data (items, transactions, etc.).</p>
        <p>
            <button type="button" class="button" id="fcsim-purge" style="color: #d63638; border-color: #d63638;">
                Purge All Simulated Orders (<span id="fcsim-purge-count"><?php echo intval($simulatedCount); ?></span> orders)
            </button>
        </p>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var nonce = $('#fcsim_nonce_field').val();
    var $feedback = $('<div class="notice inline" style="margin: 10px 0; display: none;"><p></p></div>');
    $('#fcsim-settings-form .submit').before($feedback);

    function showFeedback(message, type) {
        $feedback
            .removeClass('notice-success notice-error notice-info')
            .addClass('notice-' + (type || 'success'))
            .find('p').text(message).end()
            .slideDown(200);
        setTimeout(function() { $feedback.slideUp(200); }, 5000);
    }

    function setButtonLoading($btn, loading) {
        if (loading) {
            $btn.prop('disabled', true).data('orig-text', $btn.text()).text('Processing...');
        } else {
            $btn.prop('disabled', false).text($btn.data('orig-text'));
        }
    }

    // Save Settings
    $('#fcsim-settings-form').on('submit', function(e) {
        e.preventDefault();
        var $btn = $('#fcsim-save');
        setButtonLoading($btn, true);

        var formData = $(this).serialize();
        formData += '&action=fcsim_save_settings&nonce=' + nonce;

        // Ensure unchecked checkboxes are captured
        if (!$('input[name="create_new_customers"]').is(':checked')) {
            formData += '&create_new_customers=0';
        }

        $.post(ajaxurl, formData, function(response) {
            setButtonLoading($btn, false);
            if (response.success) {
                showFeedback(response.data.message, 'success');
                setTimeout(function() { location.reload(); }, 1000);
            } else {
                showFeedback(response.data.message || 'Error saving settings.', 'error');
            }
        }).fail(function() {
            setButtonLoading($btn, false);
            showFeedback('Request failed. Please try again.', 'error');
        });
    });

    // Generate Now
    $('#fcsim-generate-now').on('click', function() {
        var $btn = $(this);
        setButtonLoading($btn, true);

        $.post(ajaxurl, {
            action: 'fcsim_generate_now',
            nonce: nonce,
            count: 5
        }, function(response) {
            setButtonLoading($btn, false);
            if (response.success) {
                showFeedback(response.data.message, 'success');
                if (response.data.stats) {
                    $('#fcsim-total-generated').text(response.data.stats.total_generated || 0);
                    $('#fcsim-last-run').text(response.data.stats.last_run || 'Just now');
                }
                // Refresh simulated count
                $('#fcsim-sim-count').text(parseInt($('#fcsim-sim-count').text()) + (response.data.created || 0));
                $('#fcsim-purge-count').text(parseInt($('#fcsim-purge-count').text()) + (response.data.created || 0));
            } else {
                showFeedback(response.data.message || 'Error generating orders.', 'error');
            }
        }).fail(function() {
            setButtonLoading($btn, false);
            showFeedback('Request failed. Please try again.', 'error');
        });
    });

    // Stop Simulation
    $('#fcsim-stop').on('click', function() {
        if (!confirm('Stop the order simulation?')) return;
        var $btn = $(this);
        setButtonLoading($btn, true);

        $.post(ajaxurl, {
            action: 'fcsim_stop_simulation',
            nonce: nonce
        }, function(response) {
            setButtonLoading($btn, false);
            if (response.success) {
                showFeedback(response.data.message, 'success');
                setTimeout(function() { location.reload(); }, 1000);
            }
        }).fail(function() {
            setButtonLoading($btn, false);
            showFeedback('Request failed.', 'error');
        });
    });

    // Purge Orders
    $('#fcsim-purge').on('click', function() {
        var count = $('#fcsim-purge-count').text();
        if (!confirm('Are you sure you want to permanently delete ' + count + ' simulated orders? This cannot be undone.')) return;
        var $btn = $(this);
        setButtonLoading($btn, true);

        $.post(ajaxurl, {
            action: 'fcsim_purge_orders',
            nonce: nonce
        }, function(response) {
            setButtonLoading($btn, false);
            if (response.success) {
                showFeedback(response.data.message, 'success');
                $('#fcsim-sim-count').text('0');
                $('#fcsim-purge-count').text('0');
                $('#fcsim-total-generated').text('0');
            } else {
                showFeedback(response.data.message || 'Error purging orders.', 'error');
            }
        }).fail(function() {
            setButtonLoading($btn, false);
            showFeedback('Request failed.', 'error');
        });
    });
});
</script>
