<?php

namespace FluentCartSimulator;

use FluentCart\App\Models\Order;
use FluentCart\App\Models\OrderItem;
use FluentCart\App\Models\OrderTransaction;

class PurgeHandler
{
    public static function purge()
    {
        $orderIds = self::getSimulatedOrderIds();

        if (empty($orderIds)) {
            return ['deleted' => 0, 'message' => 'No simulated orders found.'];
        }

        $totalDeleted = 0;

        // Process in chunks to avoid memory/query limits
        foreach (array_chunk($orderIds, 500) as $chunk) {
            OrderItem::query()->whereIn('order_id', $chunk)->delete();
            OrderTransaction::query()->whereIn('order_id', $chunk)->delete();

            // Delete order meta if the model exists
            if (class_exists('\\FluentCart\\App\\Models\\OrderMeta')) {
                \FluentCart\App\Models\OrderMeta::query()->whereIn('order_id', $chunk)->delete();
            }

            // Delete order addresses
            if (class_exists('\\FluentCart\\App\\Models\\OrderAddress')) {
                \FluentCart\App\Models\OrderAddress::query()->whereIn('order_id', $chunk)->delete();
            }

            // Delete applied coupons
            if (class_exists('\\FluentCart\\App\\Models\\AppliedCoupon')) {
                \FluentCart\App\Models\AppliedCoupon::query()->whereIn('order_id', $chunk)->delete();
            }

            $deleted = Order::query()->whereIn('id', $chunk)->delete();
            $totalDeleted += $deleted;
        }

        // Reset stats
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
        return Order::query()
            ->where('mode', 'test')
            ->where('config', 'LIKE', '%"simulated":true%')
            ->count();
    }

    private static function getSimulatedOrderIds()
    {
        return Order::query()
            ->where('mode', 'test')
            ->where('config', 'LIKE', '%"simulated":true%')
            ->pluck('id')
            ->toArray();
    }
}
