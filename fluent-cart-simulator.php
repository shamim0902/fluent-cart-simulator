<?php

defined('ABSPATH') || exit('Direct access not allowed.');

/*
Plugin Name: FluentCart Order Simulator
Description: Automatically generates test orders for FluentCart on a configurable schedule. A developer tool for testing at scale.
Version: 1.0.1
Author: FluentCart Team
Author URI: https://fluentcart.com
Plugin URI: https://fluentcart.com
License: GPLv2 or later
Text Domain: fluent-cart-simulator
*/

define('FCSIM_VERSION', '1.0.1');
define('FCSIM_PLUGIN_FILE', __FILE__);
define('FCSIM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FCSIM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FCSIM_MIN_FLUENTCART_VERSION', '1.2.5');

// Load composer autoload (Faker library)
if (file_exists(FCSIM_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once FCSIM_PLUGIN_DIR . 'vendor/autoload.php';
}

// Register custom cron interval
add_filter('cron_schedules', function ($schedules) {
    $schedules['fcsim_five_minutes'] = [
        'interval' => 300,
        'display'  => 'Every Five Minutes (FC Simulator)',
    ];
    return $schedules;
});

add_action('plugins_loaded', function () {
    if (!fcsim_check_dependencies()) {
        return;
    }

    // PSR-4 autoloader
    spl_autoload_register(function ($class) {
        $prefix = 'FluentCartSimulator\\';
        $base_dir = FCSIM_PLUGIN_DIR . 'includes/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    });

    (new \FluentCartSimulator\SimulatorAdmin())->register();
    (new \FluentCartSimulator\SimulatorScheduler())->register();
}, 20);

register_deactivation_hook(__FILE__, function () {
    if (class_exists('\FluentCartSimulator\SimulatorScheduler')) {
        \FluentCartSimulator\SimulatorScheduler::clearSchedule();
    }
});

function fcsim_check_dependencies()
{
    if (!defined('FLUENTCART_VERSION')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>FluentCart Order Simulator</strong> requires FluentCart to be installed and activated.';
            echo '</p></div>';
        });
        return false;
    }

    if (version_compare(FLUENTCART_VERSION, FCSIM_MIN_FLUENTCART_VERSION, '<')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>FluentCart Order Simulator</strong> requires FluentCart version ' . esc_html(FCSIM_MIN_FLUENTCART_VERSION) . ' or higher. ';
            echo 'You are running version ' . esc_html(FLUENTCART_VERSION) . '.';
            echo '</p></div>';
        });
        return false;
    }

    return true;
}
