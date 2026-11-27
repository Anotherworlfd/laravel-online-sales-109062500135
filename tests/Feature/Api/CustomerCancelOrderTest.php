<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerCancelOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private User $other;
    private Product $product;
    private ShippingService $shipping;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::create([
            'name' => 'Pelanggan',
            'email' => 'customer@example.com',
            'password' => 'password123',
            'role' => 'customer',
        ]);

        $this->other = User::create([
            'name' => 'Pelanggan Lain',
            'email' => 'other@example.com',
            'password' => 'password123',
            'role' => 'customer',
        ]);

        $category = Category::create(['name' => 'Elektronik', 'slug' => 'elektronik']);

        $this->product = Product::create([
            'category_id' => $category->id,
            'sku' => 'SKU-CANCEL',
            'name' => 'Mouse Wireless',
            'slug' => 'mouse-wireless',
            'description' => 'Produk contoh',
            'price' => 150000,
            'stock' => 5,
            'is_active' => true,
        ]);

        $this->shipping = ShippingService::create([
            'courier' => 'JNE',
            'service' => 'REG',
            'eta' => '2-3 hari',
            'cost' => 20000,
            'is_active' => true,
        ]);
    }

    public function test_customer_can_cancel_pending_order_and_stock_is_restored(): void
    {
        Sanctum::actingAs($this->customer, ['customer']);

        $orderId = $this->postJson('/api/v1/checkout', $this->payload(2))
            ->assertCreated()
            ->json('data.id');

        $this->assertSame(3, $this->product->fresh()->stock);

        $this->patchJson("/api/v1/orders/{$orderId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(5, $this->product->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'type'       => 'IN',
            'quantity'   => 2,
        ]);
    }

    public function test_customer_cannot_cancel_non_pending_order(): void
    {
        Sanctum::actingAs($this->customer, ['customer']);

        $orderId = $this->postJson('/api/v1/checkout', $this->payload(1))
            ->assertCreated()
            ->json('data.id');

        Order::whereKey($orderId)->update(['status' => 'confirmed']);

        $this->patchJson("/api/v1/orders/{$orderId}/cancel")->assertStatus(409);

        $this->assertSame(4, $this->product->fresh()->stock);
        $this->assertSame('confirmed', Order::find($orderId)->status);
    }

    public function test_customer_cannot_cancel_other_users_order(): void
    {
        Sanctum::actingAs($this->customer, ['customer']);

        $orderId = $this->postJson('/api/v1/checkout', $this->payload(1))
            ->assertCreated()
            ->json('data.id');

        Sanctum::actingAs($this->other, ['customer']);

        $this->patchJson("/api/v1/orders/{$orderId}/cancel")->assertNotFound();
        $this->assertSame('pending', Order::find($orderId)->status);
    }

    public function test_guest_cannot_cancel_order(): void
    {
        $this->patchJson('/api/v1/orders/1/cancel')->assertUnauthorized();
    }

    private function payload(int $qty): array
    {
        return [
            'recipient_name'      => 'Pelanggan',
            'phone'               => '081234567890',
            'address'             => 'Purwokerto',
            'shipping_service_id' => $this->shipping->id,
            'items'               => [
                ['product_id' => $this->product->id, 'quantity' => $qty],
            ],
        ];
    }
}