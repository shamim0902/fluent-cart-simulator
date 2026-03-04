<?php

namespace FluentCartSimulator;

class PurgeHandler
{
    public static function purge()
    {
        global $wpdb;

        $orderIds = self::getSimulatedOrderIds();

        if (empty($orderIds)) {
            return ['deleted' => 0, 'message' => 'No simulated orders found.'];
        }

        $totalDeleted = 0;
        $ordersTable = $wpdb->prefix . 'fct_orders';

        foreach (array_chunk($orderIds, 500) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '%d'));

            // Delete order items
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}fct_order_items WHERE order_id IN ({$placeholders})",
                ...$chunk
            ));

            // Delete order transactions
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}fct_order_transactions WHERE order_id IN ({$placeholders})",
                ...$chunk
            ));

            // Delete order meta (this also removes our _fcsim_simulated marker)
            $metaTable = $wpdb->prefix . 'fct_order_meta';
            if ($wpdb->get_var("SHOW TABLES LIKE '{$metaTable}'") === $metaTable) {
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$metaTable} WHERE order_id IN ({$placeholders})",
                    ...$chunk
                ));
            }

            // Delete order addresses
            $addressTable = $wpdb->prefix . 'fct_order_addresses';
            if ($wpdb->get_var("SHOW TABLES LIKE '{$addressTable}'") === $addressTable) {
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$addressTable} WHERE order_id IN ({$placeholders})",
                    ...$chunk
                ));
            }

            // Delete applied coupons
            $couponsTable = $wpdb->prefix . 'fct_applied_coupons';
            if ($wpdb->get_var("SHOW TABLES LIKE '{$couponsTable}'") === $couponsTable) {
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$couponsTable} WHERE order_id IN ({$placeholders})",
                    ...$chunk
                ));
            }

            // Delete orders
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$ordersTable} WHERE id IN ({$placeholders})",
                ...$chunk
            ));
            $totalDeleted += (int) $deleted;
        }

        update_option('fcsim_stats', [
            'total_generated' => 0,
            'last_run'        => null,
            'last_purge'      => current_time('mysql'),
        ]);

        return [
            'deleted' => $totalDeleted,
            'message' => sprintf('Successfully deleted %d simulated orders and related data.', $totalDeleted),
        ];
    }

    public static function getSimulatedOrderCount()
    {
        global $wpdb;
        $metaTable = $wpdb->prefix . 'fct_order_meta';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$metaTable}'") !== $metaTable) {
            return 0;
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$metaTable} WHERE meta_key = %s",
            '_fcsim_simulated'
        ));
    }

    private static function getSimulatedOrderIds()
    {
        global $wpdb;
        $metaTable = $wpdb->prefix . 'fct_order_meta';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$metaTable}'") !== $metaTable) {
            return [];
        }

        return $wpdb->get_col($wpdb->prepare(
            "SELECT order_id FROM {$metaTable} WHERE meta_key = %s",
            '_fcsim_simulated'
        ));
    }
}
