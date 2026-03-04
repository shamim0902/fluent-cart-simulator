=== FluentCart Order Simulator ===
Contributors: fluentcart
Tags: fluentcart, simulator, test data, orders, development
Requires at least: 5.6
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically generates realistic test orders for FluentCart on a configurable schedule. A developer tool for testing at scale.

== Description ==

The FluentCart Order Simulator is a developer tool that generates realistic test order data for your FluentCart store. Whether you need to test reports, email notifications, exports, or performance at scale, this plugin lets you simulate hundreds or thousands of orders without manual effort.

**Features:**

* Define the number of orders generated per hour (set to 0 to disable)
* Limit which products are included in generated orders (or use all published products)
* Set minimum and maximum number of items per order
* Configure order status distribution (completed, processing, failed, on-hold percentages)
* Choose to create new fake customers or assign orders to existing customers
* Select which payment methods to randomly assign to orders
* One-click "Generate Now" for instant test data
* Purge all simulated orders and related data in one click
* All simulated orders are clearly marked as test orders (`mode=test`)

**How It Works:**

Orders are generated in batches every 5 minutes using Action Scheduler (bundled with FluentCart) or WP-Cron as a fallback. For example, setting 60 orders per hour will generate approximately 5 orders every 5 minutes.

Each generated order includes:

* Realistic order items from your published products
* Proper order totals and pricing
* Payment transactions
* Customer assignment (new fake customer or existing)
* Correct invoice and receipt numbers

== Installation ==

1. Ensure FluentCart (v1.2.5 or higher) is installed and activated
2. Upload the `fluent-cart-simulator` folder to `/wp-content/plugins/`
3. Activate the plugin through the WordPress Plugins menu
4. Go to **Tools > FC Simulator** to configure settings

== How to Use ==

1. Navigate to **Tools > FC Simulator** in your WordPress admin
2. Configure your simulation settings:
   - **Orders per Hour** - How many orders to generate automatically (0 = disabled)
   - **Product IDs** - Comma-separated list of product IDs to include (blank = all products)
   - **Items per Order** - Min/max number of line items in each order
   - **Status Distribution** - Percentage of orders in each status (completed, processing, failed, on-hold)
   - **Create New Customers** - Check to generate fake customers, uncheck to use existing ones
   - **Payment Methods** - Select which payment methods to randomly assign
3. Click **Save Settings** to start automatic generation
4. Use **Generate 5 Orders Now** for immediate test data
5. Use **Stop Simulation** to halt automatic generation
6. Use **Purge All Simulated Orders** to clean up test data when done

== Important Notes ==

* All simulated orders are marked with `mode=test` and a simulator flag in the order config
* Purging only removes orders created by this simulator - your real orders are safe
* We recommend disabling outgoing emails or using an SMTP service in test mode while the simulator is running
* The simulator uses FluentCart's native models, so generated orders appear identical to real orders in the admin dashboard
* An admin notice will appear across WordPress admin pages while the simulation is active

== Frequently Asked Questions ==

= Will this affect my real orders? =

No. All simulated orders are tagged with `mode=test` and a `simulated` marker. The purge function only deletes orders with these markers. Your real orders remain untouched.

= How do I stop the simulation? =

Either set "Orders per Hour" to 0 and save, or click the "Stop Simulation" button on the settings page. Deactivating the plugin also stops all scheduled generation.

= Can I use this on a production site? =

This is designed as a development/staging tool. While it won't interfere with real orders, we strongly recommend using it only on development or staging environments.

= What happens when I deactivate the plugin? =

All scheduled generation events are cleared. Simulated orders already in the database remain until you purge them or manually delete them.

= Does this require WooCommerce? =

No. This plugin is built exclusively for FluentCart and has no WooCommerce dependency.

== Changelog ==

= 1.0.0 =
* Initial release
* Configurable order generation rate
* Product filtering by ID
* Order status distribution control
* Fake customer generation
* Payment method selection
* One-click generate and purge
* Action Scheduler and WP-Cron support
