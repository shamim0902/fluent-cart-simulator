<?php

namespace FluentCartSimulator;

class SimulatorAdmin
{
    public function register()
    {
        add_action('admin_menu', [$this, 'addAdminMenu']);

        add_action('wp_ajax_fcsim_save_settings', [$this, 'ajaxSaveSettings']);
        add_action('wp_ajax_fcsim_generate_now', [$this, 'ajaxGenerateNow']);
        add_action('wp_ajax_fcsim_purge_orders', [$this, 'ajaxPurgeOrders']);
        add_action('wp_ajax_fcsim_stop_simulation', [$this, 'ajaxStopSimulation']);

        add_action('admin_notices', [$this, 'showRunningNotice']);
    }

    public function addAdminMenu()
    {
        add_management_page(
            'FluentCart Order Simulator',
            'FC Simulator',
            'manage_options',
            'fluent-cart-simulator',
            [$this, 'renderSettingsPage']
        );
    }

    public function renderSettingsPage()
    {
        $settings = self::getSettings();
        $stats = get_option('fcsim_stats', [
            'total_generated' => 0,
            'last_run'        => null,
        ]);
        $simulatedCount = PurgeHandler::getSimulatedOrderCount();
        $ordersPerHour = intval($settings['orders_per_hour'] ?? 0);
        $isRunning = $ordersPerHour > 0;

        include FCSIM_PLUGIN_DIR . 'views/settings-page.php';
    }

    public function showRunningNotice()
    {
        $screen = get_current_screen();
        if ($screen && $screen->id === 'tools_page_fluent-cart-simulator') {
            return; // Don't show on the settings page itself
        }

        $settings = self::getSettings();
        $ordersPerHour = intval($settings['orders_per_hour'] ?? 0);

        if ($ordersPerHour > 0) {
            printf(
                '<div class="notice notice-warning"><p><strong>FluentCart Simulator</strong> is actively generating %d orders per hour. <a href="%s">Manage Simulator</a></p></div>',
                $ordersPerHour,
                esc_url(admin_url('tools.php?page=fluent-cart-simulator'))
            );
        }
    }

    public function ajaxSaveSettings()
    {
        check_ajax_referer('fcsim_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized.']);
        }

        $ordersPerHour = intval(sanitize_text_field($_POST['orders_per_hour'] ?? '0'));

        $productIdsRaw = sanitize_text_field($_POST['product_ids'] ?? '');
        $productIds = array_filter(array_map('intval', explode(',', $productIdsRaw)));

        $minItems = max(1, intval($_POST['min_items_per_order'] ?? 1));
        $maxItems = max($minItems, intval($_POST['max_items_per_order'] ?? 5));

        $statusDist = [
            'completed'  => max(0, intval($_POST['status_completed'] ?? 40)),
            'processing' => max(0, intval($_POST['status_processing'] ?? 30)),
            'failed'     => max(0, intval($_POST['status_failed'] ?? 15)),
            'on-hold'    => max(0, intval($_POST['status_on_hold'] ?? 15)),
        ];

        $createNewCustomers = !empty($_POST['create_new_customers']);

        $paymentMethods = [];
        if (!empty($_POST['payment_methods']) && is_array($_POST['payment_methods'])) {
            $paymentMethods = array_map('sanitize_text_field', $_POST['payment_methods']);
        }
        if (empty($paymentMethods)) {
            $paymentMethods = ['offline_payment'];
        }

        $settings = [
            'orders_per_hour'      => $ordersPerHour,
            'product_ids'          => $productIds,
            'min_items_per_order'  => $minItems,
            'max_items_per_order'  => $maxItems,
            'status_distribution'  => $statusDist,
            'create_new_customers' => $createNewCustomers,
            'payment_methods'      => $paymentMethods,
        ];

        update_option('fcsim_settings', $settings);

        // Reschedule based on new settings
        SimulatorScheduler::reschedule($ordersPerHour);

        wp_send_json_success([
            'message'  => 'Settings saved successfully.',
            'settings' => $settings,
        ]);
    }

    public function ajaxGenerateNow()
    {
        check_ajax_referer('fcsim_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized.']);
        }

        $count = max(1, intval($_POST['count'] ?? 5));
        $count = min($count, 100); // Safety cap

        $created = OrderGenerator::generate($count);

        $stats = get_option('fcsim_stats', ['total_generated' => 0, 'last_run' => null]);
        $stats['total_generated'] = ($stats['total_generated'] ?? 0) + $created;
        $stats['last_run'] = current_time('mysql');
        update_option('fcsim_stats', $stats);

        wp_send_json_success([
            'message' => sprintf('Generated %d orders.', $created),
            'created' => $created,
            'stats'   => $stats,
        ]);
    }

    public function ajaxPurgeOrders()
    {
        check_ajax_referer('fcsim_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized.']);
        }

        $result = PurgeHandler::purge();

        wp_send_json_success($result);
    }

    public function ajaxStopSimulation()
    {
        check_ajax_referer('fcsim_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized.']);
        }

        $settings = self::getSettings();
        $settings['orders_per_hour'] = 0;
        update_option('fcsim_settings', $settings);

        SimulatorScheduler::clearSchedule();

        wp_send_json_success(['message' => 'Simulation stopped.']);
    }

    public static function getSettings()
    {
        return wp_parse_args(get_option('fcsim_settings', []), [
            'orders_per_hour'      => 0,
            'product_ids'          => [],
            'min_items_per_order'  => 1,
            'max_items_per_order'  => 5,
            'status_distribution'  => [
                'completed'  => 40,
                'processing' => 30,
                'failed'     => 15,
                'on-hold'    => 15,
            ],
            'create_new_customers' => true,
            'payment_methods'      => ['offline_payment'],
        ]);
    }
}
