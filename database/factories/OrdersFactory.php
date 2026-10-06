<?php

namespace Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use LemonSqueezy\Laravel\Order;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class OrdersFactory extends Factory
{
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'billable_id' => rand(1, 1000),
            'billable_type' => 'App\\Models\\User',
            'identifier' => Str::random(36),
            'lemon_squeezy_id' => rand(1, 1000),
            'customer_id' => rand(1, 1000),
            'product_id' => rand(1, 1000),
            'variant_id' => rand(1, 1000),
            'order_number' => rand(1, 1000),
            'currency' => $this->faker->randomElement(['USD', 'EUR', 'GBP']),
            'subtotal' => $subtotal = rand(400, 1000),
            'discount_total' => $discount = rand(1, 400),
            'tax' => $tax = rand(1, 50),
            'total' => $subtotal - $discount + $tax,
            'tax_name' => $this->faker->randomElement(['VAT', 'Sales Tax']),
            'receipt_url' => null,
            'ordered_at' => $orderedAt = Carbon::make($this->faker->dateTimeBetween('-1 year', 'now')),
            'refunded' => $refunded = $this->faker->boolean(75),
            'refunded_at' => $refunded ? $orderedAt->addWeek() : null,
            'status' => $refunded ? Order::STATUS_REFUNDED : Order::STATUS_PAID,
        ];
    }
}
