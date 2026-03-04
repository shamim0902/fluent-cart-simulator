# FluentCart Order Simulator

Automatically generate realistic test orders for FluentCart on a configurable schedule. A developer tool for testing reports, emails, exports, and performance at scale.

[![Download Latest Release](https://img.shields.io/github/v/release/shamim0902/fluent-cart-simulator?label=Download%20Latest&style=for-the-badge&color=0073aa)](https://github.com/shamim0902/fluent-cart-simulator/releases/latest/download/fluent-cart-simulator.zip)

## Requirements

- WordPress 5.6+
- PHP 7.4+
- FluentCart 1.2.5+

## Installation

1. Download the latest release zip from the button above (or from [Releases](https://github.com/shamim0902/fluent-cart-simulator/releases))
2. In WordPress admin, go to **Plugins > Add New > Upload Plugin**
3. Upload the zip file and click **Install Now**
4. Activate the plugin

Or manually:

1. Extract the zip to `/wp-content/plugins/fluent-cart-simulator/`
2. Run `composer install --no-dev` inside the plugin directory
3. Activate the plugin from the WordPress Plugins screen

## How to Use

### 1. Open the Simulator

Navigate to **Tools > FC Simulator** in your WordPress admin dashboard.

### 2. Configure Settings

| Setting | Description |
|---|---|
| **Orders per Hour** | Number of orders to generate automatically. Set to `0` to disable. |
| **Product IDs** | Comma-separated list of product IDs to include. Leave blank for all published products. |
| **Items per Order** | Minimum and maximum number of line items in each generated order. |
| **Status Distribution** | Percentage of orders in each status: Completed, Processing, Failed, On Hold. |
| **Create New Customers** | Check to generate fake customers with realistic names/addresses. Uncheck to assign orders to existing customers. |
| **Payment Methods** | Select which payment methods to randomly assign (COD, Stripe, PayPal, etc.). |

### 3. Start Generating

- Click **Save Settings** to start automatic generation at the configured rate
- Orders are generated in batches every 5 minutes (e.g., 60/hr = ~5 orders every 5 minutes)
- A warning banner appears across admin pages while the simulation is active

### 4. Quick Generate

Click **Generate 5 Orders Now** to instantly create 5 test orders without enabling the scheduler.

### 5. Stop the Simulation

Click **Stop Simulation** or set orders per hour to `0` and save. Deactivating the plugin also stops all scheduled generation.

### 6. Clean Up

Click **Purge All Simulated Orders** to permanently delete all simulator-created orders and their related data (items, transactions, etc.). Real orders are never affected.

## How It Works

- Uses **Action Scheduler** (bundled with FluentCart) for reliable background generation, with WP-Cron as fallback
- All generated orders are tagged with `mode=test` and a `simulated` config flag
- Orders include realistic data: line items from real products, proper totals, payment transactions, and customer records
- Invoice and receipt numbers are generated through FluentCart's native system
- The purge function targets only simulator-tagged orders — your real data is safe

## Important Notes

- **Disable outgoing emails** or use an SMTP service in test mode while the simulator is running
- Designed for **development and staging environments** — not recommended for production use
- Simulated orders appear identical to real orders in the FluentCart admin (by design, for realistic testing)
- When the plugin is deactivated, all scheduled events are cleared; existing simulated orders remain until purged

## License

GPLv2 or later
