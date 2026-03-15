<?php

namespace FluentCartSimulator;

use FluentCart\Api\StoreSettings;
use FluentCart\App\Events\Order\OrderCreated;
use FluentCart\App\Events\Order\OrderPaid;
use FluentCart\App\Helpers\Status;
use FluentCart\App\Models\Customer;
use FluentCart\App\Models\Order;
use FluentCart\App\Models\OrderAddress;
use FluentCart\App\Models\OrderItem;
use FluentCart\App\Models\OrderTransaction;
use FluentCart\App\Models\Product;

class OrderGenerator
{
    public static function generate($count = 1)
    {
        $settings = get_option('fcsim_settings', []);

        $faker = \Faker\Factory::create();

        $products = self::getAvailableProducts($settings);

        if (empty($products)) {
            return 0;
        }

        $minItems = max(1, intval($settings['min_items_per_order'] ?? 1));
        $maxItems = max($minItems, intval($settings['max_items_per_order'] ?? 5));
        $statusDist = $settings['status_distribution'] ?? [
            'completed'  => 40,
            'processing' => 30,
            'failed'     => 15,
            'on-hold'    => 15,
        ];
        $createCustomers = !empty($settings['create_new_customers']);
        $paymentMethods = $settings['payment_methods'] ?? ['offline_payment'];

        if (empty($paymentMethods)) {
            $paymentMethods = ['offline_payment'];
        }

        $ordersCreated = 0;

        for ($i = 0; $i < $count; $i++) {
            $order = self::createSingleOrder(
                $faker,
                $products,
                $minItems,
                $maxItems,
                $statusDist,
                $createCustomers,
                $paymentMethods
            );
            if ($order) {
                $ordersCreated++;
            }
        }

        // Recalculate customer stats after batch
        if ($ordersCreated > 0) {
            try {
                (new \FluentCart\App\Helpers\CustomerHelper())->calculateCustomerStats();
            } catch (\Exception $e) {
                // Non-critical, silently skip
            }
        }

        return $ordersCreated;
    }

    private static function getAvailableProducts($settings)
    {
        $query = Product::with('variants')->where('post_status', 'publish');

        $productIds = $settings['product_ids'] ?? [];
        if (!empty($productIds)) {
            $query->whereIn('ID', $productIds);
        }

        $products = $query->take(100)->get()->toArray();

        // Filter out products without variants
        return array_filter($products, function ($product) {
            return !empty($product['variants']);
        });
    }

    private static function createSingleOrder($faker, $products, $minItems, $maxItems, $statusDist, $createCustomers, $paymentMethods)
    {
        // 1. Get or create customer
        $customerId = $createCustomers
            ? CustomerGenerator::createFakeCustomer($faker)
            : CustomerGenerator::getRandomExistingCustomer();

        if (!$customerId) {
            return null;
        }

        // 2. Select random products
        $products = array_values($products);
        $itemCount = wp_rand($minItems, min($maxItems, count($products)));
        $selectedKeys = (array) array_rand($products, max(1, min($itemCount, count($products))));

        // 3. Build order items and calculate totals
        $totalPrice = 0;
        $tempOrderItems = [];
        $createdDate = current_time('mysql', true);
        $fulfillmentType = 'digital';

        foreach ($selectedKeys as $key) {
            $product = $products[$key];
            if (empty($product['variants'])) {
                continue;
            }

            $variant = $faker->randomElement($product['variants']);
            $quantity = wp_rand(1, 3);
            $itemPrice = (float) ($variant['item_price'] ?? 0);

            if ($itemPrice <= 0) {
                continue;
            }

            $subtotal = $quantity * $itemPrice;
            $totalPrice += $subtotal;

            $variantFulfillment = $variant['fulfillment_type'] ?? 'digital';
            if ($variantFulfillment === 'physical') {
                $fulfillmentType = 'physical';
            }

            $tempOrderItems[] = [
                'post_title'       => $product['post_title'],
                'title'            => $variant['variation_title'] ?? '',
                'fulfillment_type' => $variantFulfillment,
                'payment_type'     => $variant['payment_type'] ?? 'onetime',
                'quantity'         => $quantity,
                'unit_price'       => $itemPrice,
                'line_total'       => $subtotal,
                'subtotal'         => $subtotal,
                'post_id'          => $product['ID'],
                'object_id'        => $variant['id'],
                'discount_total'   => 0,
                'created_at'       => $createdDate,
            ];
        }

        if (empty($tempOrderItems) || $totalPrice <= 0) {
            return null;
        }

        // 4. Determine order & payment status
        $status = self::pickWeightedStatus($statusDist);
        $paymentStatus = self::derivePaymentStatus($status);

        // 5. Calculate payment amounts
        $totalPaid = 0;
        $totalRefund = 0;
        $completedAt = null;

        if ($paymentStatus === 'paid') {
            $totalPaid = $totalPrice;
        } elseif ($paymentStatus === 'failed' || $paymentStatus === 'pending') {
            $totalPaid = 0;
        }

        if ($status === 'completed') {
            $completedAt = $createdDate;
        }

        $paymentMethod = $faker->randomElement($paymentMethods);

        $paymentMethodTitles = [
            'offline_payment' => 'Cash on Delivery',
            'stripe'          => 'Stripe',
            'paypal'          => 'PayPal',
            'square'          => 'Square',
            'razorpay'        => 'Razorpay',
            'paystack'        => 'Paystack',
        ];
        $paymentMethodTitle = $paymentMethodTitles[$paymentMethod] ?? ucwords(str_replace('_', ' ', $paymentMethod));

        $currency = self::getStoreCurrency();

        // 6. Create the order
        $ipAddress = wp_rand(1, 255) . '.' . wp_rand(0, 255) . '.' . wp_rand(0, 255) . '.' . wp_rand(1, 254);

        $orderData = [
            'parent_id'             => 0,
            'customer_id'           => $customerId,
            'payment_method'        => $paymentMethod,
            'payment_method_title'  => $paymentMethodTitle,
            'payment_status'        => $paymentStatus,
            'currency'              => $currency,
            'subtotal'              => $totalPrice,
            'total_amount'          => $totalPrice,
            'total_paid'            => $totalPaid,
            'total_refund'          => $totalRefund,
            'status'                => $status,
            'mode'                  => Status::ORDER_MODE_TEST,
            'type'                  => Status::ORDER_TYPE_PAYMENT,
            'fulfillment_type'      => $fulfillmentType,
            'ip_address'            => $ipAddress,
            'created_at'            => $createdDate,
            'completed_at'          => $completedAt,
        ];

        $order = Order::query()->create($orderData);

        if (!$order || !$order->id) {
            return null;
        }

        // Mark as simulated via order meta (reliable, survives config overwrites)
        $order->updateMeta('_fcsim_simulated', FCSIM_VERSION);

        // 6b. Create order addresses (billing + shipping)
        self::createOrderAddresses($order->id, $customerId, $createdDate);

        // 7. Create order items
        foreach ($tempOrderItems as &$item) {
            $item['order_id'] = $order->id;
        }
        unset($item);

        OrderItem::query()->insert($tempOrderItems);

        // 8. Create transaction
        $transactionStatus = self::deriveTransactionStatus($paymentStatus);
        $transaction = OrderTransaction::query()->create([
            'order_id'          => $order->id,
            'order_type'        => Status::ORDER_TYPE_PAYMENT,
            'payment_method'    => $paymentMethod,
            'payment_mode'      => Status::ORDER_MODE_TEST,
            'transaction_type'  => Status::TRANSACTION_TYPE_CHARGE,
            'status'            => $transactionStatus,
            'currency'          => $currency,
            'total'             => $totalPaid ?: $totalPrice,
            'created_at'        => $createdDate,
        ]);

        self::dispatchLifecycleEvents($order, $transaction, $paymentStatus);

        return $order;
    }

    private static function dispatchLifecycleEvents(Order $order, $transaction, $paymentStatus)
    {
        $order = Order::query()
            ->with(['customer', 'order_items', 'shipping_address', 'billing_address'])
            ->find($order->id);

        if (!$order) {
            return;
        }

        (new OrderCreated($order, null, $order->customer, $transaction))->dispatch();

        if (!$transaction || !in_array($transaction->status, Status::getTransactionSuccessStatuses(), true)) {
            return;
        }

        $eventData = [
            'order'       => $order,
            'customer'    => $order->customer,
            'transaction' => $transaction,
        ];

        do_action('fluent_cart/payment_' . $paymentStatus, $eventData);

        if (!empty($transaction->transaction_type)) {
            do_action('fluent_cart/payment_' . $transaction->transaction_type . '_' . $paymentStatus, $eventData);
        }

        if ($paymentStatus !== Status::PAYMENT_PAID) {
            return;
        }

        (new OrderPaid($order, $order->customer, $transaction))->dispatch();

        if (self::shouldRunPaidActionsInline()) {
            do_action('fluent_cart/order_paid_ansyc_private_handle', [
                'order_id' => $order->id,
            ]);
        }
    }

    private static function shouldRunPaidActionsInline()
    {
        return function_exists('doing_action') && doing_action(SimulatorScheduler::HOOK_NAME);
    }

    private static function createOrderAddresses($orderId, $customerId, $createdDate)
    {
        $addresses = CustomerGenerator::getCustomerAddressData($customerId);

        foreach (['billing', 'shipping'] as $type) {
            $source = $addresses[$type] ?? null;
            if (!$source) {
                continue;
            }

            $meta = [];
            if (!empty($source->phone)) {
                $meta['other_data']['phone'] = $source->phone;
            }

            OrderAddress::query()->create([
                'order_id'   => $orderId,
                'type'       => $type,
                'name'       => $source->name ?? '',
                'address_1'  => $source->address_1 ?? '',
                'address_2'  => $source->address_2 ?? '',
                'city'       => $source->city ?? '',
                'state'      => $source->state ?? '',
                'postcode'   => $source->postcode ?? '',
                'country'    => $source->country ?? '',
                'meta'       => $meta,
            ]);
        }
    }

    private static function pickWeightedStatus(array $distribution)
    {
        $total = array_sum($distribution);
        if ($total <= 0) {
            return 'processing';
        }

        $rand = wp_rand(1, $total);
        $cumulative = 0;

        foreach ($distribution as $status => $weight) {
            $cumulative += (int) $weight;
            if ($rand <= $cumulative) {
                return $status;
            }
        }

        return 'processing';
    }

    private static function derivePaymentStatus($orderStatus)
    {
        switch ($orderStatus) {
            case 'completed':
            case 'processing':
                return 'paid';
            case 'on-hold':
                return 'pending';
            case 'failed':
                return 'failed';
            case 'canceled':
                return 'pending';
            default:
                return 'pending';
        }
    }

    private static function deriveTransactionStatus($paymentStatus)
    {
        switch ($paymentStatus) {
            case 'paid':
                return 'succeeded';
            case 'failed':
                return 'failed';
            case 'pending':
            default:
                return 'pending';
        }
    }

    private static function getStoreCurrency()
    {
        try {
            return (new StoreSettings())->getCurrency();
        } catch (\Exception $e) {
            return 'USD';
        }
    }
}
