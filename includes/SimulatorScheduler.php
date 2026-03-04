<?php

namespace FluentCartSimulator;

class SimulatorScheduler
{
    const HOOK_NAME = 'fcsim_generate_orders';

    public function register()
    {
        add_action(self::HOOK_NAME, [$this, 'runScheduledGeneration']);
    }

    public function runScheduledGeneration()
    {
        $settings = get_option('fcsim_settings', []);
        $ordersPerHour = intval($settings['orders_per_hour'] ?? 0);

        if ($ordersPerHour <= 0) {
            self::clearSchedule();
            return;
        }

        // 5-minute intervals = 12 runs per hour
        $ordersPerRun = max(1, (int) ceil($ordersPerHour / 12));

        $created = OrderGenerator::generate($ordersPerRun);

        $stats = get_option('fcsim_stats', [
            'total_generated' => 0,
            'last_run'        => null,
        ]);
        $stats['total_generated'] = ($stats['total_generated'] ?? 0) + $created;
        $stats['last_run'] = current_time('mysql');
        update_option('fcsim_stats', $stats);
    }

    public static function reschedule($ordersPerHour)
    {
        self::clearSchedule();

        if ($ordersPerHour <= 0) {
            return;
        }

        if (function_exists('as_schedule_recurring_action')) {
            as_schedule_recurring_action(
                time(),
                5 * MINUTE_IN_SECONDS,
                self::HOOK_NAME,
                [],
                'fluent-cart-simulator',
                true
            );
        } else {
            if (!wp_next_scheduled(self::HOOK_NAME)) {
                wp_schedule_event(time(), 'fcsim_five_minutes', self::HOOK_NAME);
            }
        }
    }

    public static function clearSchedule()
    {
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions(self::HOOK_NAME);
        }

        $timestamp = wp_next_scheduled(self::HOOK_NAME);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::HOOK_NAME);
        }
    }
}
