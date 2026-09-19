<?php

namespace Tests\Feature;

use App\Domain\Orders\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use App\Jobs\ProcessOrderPayment;
use RuntimeException;
use App\Domain\Orders\OrderStateMachine;
use DomainException;
use App\Models\OrderLog;
use App\Application\Orders\UpdateOrderStatusService;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_create_order(): void
    {
        $product = Product::factory()->create([
            'stock_quantity' => 10,
        ]);

        $response = $this->postJson('/api/orders', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
        ], [
            'Idempotency-Key' => fake()->uuid(),
        ]);

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_create_order(): void
    {
        $user = User::factory()->create();

        $product = Product::factory()->create([
            'price' => 100,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/orders', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
        ], [
            'Idempotency-Key' => fake()->uuid(),
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Order created successfully.')
            ->assertJsonPath('data.order_number', fn($value) => str_starts_with($value, 'ORD-'))
            ->assertJsonPath('data.status', OrderStatus::PENDING->value)
            ->assertJsonPath('data.subtotal', 200)
            ->assertJsonPath('data.total', 200);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'status' => OrderStatus::CONFIRMED->value,
            'subtotal' => 200,
            'total' => 200,
        ]);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'quantity' => 2,
            'subtotal' => 200,
        ]);

        $this->assertDatabaseHas('payments', [
            'status' => 'paid',
            'amount' => 200,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 8,
        ]);
    }

    public function test_order_fails_when_stock_is_insufficient(): void
    {
        $user = User::factory()->create();

        $product = Product::factory()->create([
            'stock_quantity' => 1,
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/orders', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 5,
                ],
            ],
        ], [
            'Idempotency-Key' => fake()->uuid(),
        ]);

        $response
            ->assertStatus(409)
            ->assertJson([
                'message' => "Insufficient stock for {$product->name}.",
            ]);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('payments', 0);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 1,
        ]);
    }

    public function test_same_idempotency_key_does_not_create_duplicate_order(): void
    {
        $user = User::factory()->create();

        $product = Product::factory()->create([
            'price' => 100,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $idempotencyKey = fake()->uuid();

        $payload = [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
        ];

        $firstResponse = $this->postJson(
            '/api/orders',
            $payload,
            ['Idempotency-Key' => $idempotencyKey],
        );

        $secondResponse = $this->postJson(
            '/api/orders',
            $payload,
            ['Idempotency-Key' => $idempotencyKey],
        );

        $firstResponse->assertCreated();
        $secondResponse->assertCreated();

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('payments', 1);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 8,
        ]);

        $this->assertEquals(
            $firstResponse->json('data.id'),
            $secondResponse->json('data.id')
        );
    }

    public function test_user_cannot_view_another_users_order(): void
    {
        $owner = User::factory()->create();
        $anotherUser = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $owner->id,
        ]);

        Sanctum::actingAs($anotherUser);

        $response = $this->getJson(
            "/api/orders/{$order->id}"
        );

        $response->assertNotFound();
    }

    public function test_user_can_view_their_own_order(): void
    {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            "/api/orders/{$order->id}"
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $order->id);
    }

    public function test_user_can_list_their_orders(): void
    {
        $user = User::factory()->create();

        Order::factory(3)->create([
            'user_id' => $user->id,
        ]);

        $anotherUser = User::factory()->create();

        Order::factory(2)->create([
            'user_id' => $anotherUser->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/orders');

        $response->assertOk();

        $this->assertCount(
            3,
            $response->json('data.data')
        );
    }

    public function test_rejects_an_idempotency_key_longer_than_128_characters()
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $product = Product::factory()->create([
            'stock_quantity' => 10,
        ]);

        $response = $this->withHeader(
            'Idempotency-Key',
            str_repeat('a', 129)
        )->postJson('/api/orders', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'message' => 'The Idempotency-Key header must not exceed 128 characters.',
            ]);
    }

    public function test_marks_an_order_as_failed_when_payment_fails()
    {
        $user = User::factory()->create();

        $product = Product::factory()->create([
            'price' => 100,
            'stock_quantity' => 10,
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::PENDING,
            'subtotal' => 100,
            'total' => 100,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'unit_price' => 100,
            'quantity' => 1,
            'subtotal' => 100,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Payment failed.');

        try {
            ProcessOrderPayment::dispatch($order->id, 'failing');
        } finally {
            $this->assertEquals(
                OrderStatus::FAILED,
                $order->fresh()->status
            );

            $this->assertCount(
                1,
                $order->fresh()->payments
            );

            $this->assertEquals(
                'failed',
                $order->fresh()->payments->first()->status
            );
        }
    }

    public function test_allows_valid_order_status_transitions()
    {
        $stateMachine = app(OrderStateMachine::class);

        $this->assertTrue(
            $stateMachine->canTransition(
                OrderStatus::PENDING,
                OrderStatus::CONFIRMED
            )
        );

        $this->assertTrue(
            $stateMachine->canTransition(
                OrderStatus::CONFIRMED,
                OrderStatus::PROCESSING
            )
        );

        $this->assertTrue(
            $stateMachine->canTransition(
                OrderStatus::PROCESSING,
                OrderStatus::COMPLETED
            )
        );
    }

    public function test_rejects_invalid_order_status_transition()
    {
        $stateMachine = app(OrderStateMachine::class);

        $this->expectException(DomainException::class);

        $stateMachine->transition(
            OrderStatus::COMPLETED,
            OrderStatus::PENDING
        );
    }

    public function test_user_can_cancel_own_order()
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $product = Product::factory()->create([
            'price' => 100,
            'stock_quantity' => 10,
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::CONFIRMED,
            'subtotal' => 100,
            'total' => 100,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'unit_price' => 100,
            'quantity' => 2,
            'subtotal' => 200,
        ]);

        $product->decrement('stock_quantity', 2);

        $response = $this->postJson(
            "/api/orders/{$order->id}/cancel"
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                OrderStatus::CANCELLED->value
            );

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::CANCELLED->value,
        ]);

        $this->assertEquals(
            10,
            $product->fresh()->stock_quantity
        );
    }

    public function test_user_cannot_cancel_another_users_order()
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        Sanctum::actingAs($otherUser);

        $order = Order::factory()->create([
            'user_id' => $owner->id,
            'status' => OrderStatus::CONFIRMED,
        ]);

        $response = $this->postJson(
            "/api/orders/{$order->id}/cancel"
        );

        $response->assertNotFound();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::CONFIRMED->value,
        ]);
    }

    public function test_completed_order_cannot_be_cancelled()
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $product = Product::factory()->create([
            'price' => 100,
            'stock_quantity' => 10,
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::COMPLETED,
            'subtotal' => 100,
            'total' => 100,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'unit_price' => 100,
            'quantity' => 1,
            'subtotal' => 100,
        ]);

        $response = $this->postJson(
            "/api/orders/{$order->id}/cancel"
        );

        $response->assertStatus(409);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::COMPLETED->value,
        ]);
    }

    public function test_cancelled_order_cannot_be_cancelled_again()
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::CANCELLED,
        ]);

        $response = $this->postJson(
            "/api/orders/{$order->id}/cancel"
        );

        $response->assertStatus(409);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::CANCELLED->value,
        ]);
    }

    public function test_cancelling_an_order_twice_does_not_restore_stock_twice()
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $product = Product::factory()->create([
            'price' => 100,
            'stock_quantity' => 8,
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::CONFIRMED,
            'subtotal' => 200,
            'total' => 200,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'unit_price' => 100,
            'quantity' => 2,
            'subtotal' => 200,
        ]);

        $response = $this->postJson(
            "/api/orders/{$order->id}/cancel"
        );

        $response->assertOk();

        $this->assertEquals(
            10,
            $product->fresh()->stock_quantity
        );

        $response = $this->postJson(
            "/api/orders/{$order->id}/cancel"
        );

        $response->assertStatus(409);

        $this->assertEquals(
            10,
            $product->fresh()->stock_quantity
        );
    }

    public function test_rejects_duplicate_products_in_an_order()
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $product = Product::factory()->create();

        $response = $this->withHeader(
            'Idempotency-Key',
            fake()->uuid()
        )->postJson('/api/orders', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_rejects_quantity_greater_than_100()
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $product = Product::factory()->create();

        $response = $this->withHeader(
            'Idempotency-Key',
            fake()->uuid()
        )->postJson('/api/orders', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 101,
                ],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_rejects_more_than_50_items()
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $products = Product::factory()->count(51)->create();

        $items = $products->map(fn($product) => [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->values()->all();

        $response = $this->withHeader(
            'Idempotency-Key',
            fake()->uuid()
        )->postJson('/api/orders', [
            'items' => $items,
        ]);

        $response->assertStatus(422);
    }

    public function test_order_lifecycle_logs_are_created()
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $product = Product::factory()->create([
            'price' => 100,
            'stock_quantity' => 10,
        ]);

        $response = $this->withHeader(
            'Idempotency-Key',
            fake()->uuid()
        )->postJson('/api/orders', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
        ]);

        $response->assertCreated();

        $orderId = $response->json('data.id');

        $this->assertDatabaseHas('order_logs', [
            'order_id' => $orderId,
            'event' => 'order.created',
        ]);

        $this->assertDatabaseHas('order_logs', [
            'order_id' => $orderId,
            'event' => 'payment.started',
        ]);

        $this->assertDatabaseHas('order_logs', [
            'order_id' => $orderId,
            'event' => 'payment.succeeded',
        ]);
    }

    public function test_failed_payment_creates_failure_log()
    {
        $user = User::factory()->create();

        $product = Product::factory()->create([
            'price' => 100,
            'stock_quantity' => 10,
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::PENDING,
            'subtotal' => 100,
            'total' => 100,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'unit_price' => 100,
            'quantity' => 1,
            'subtotal' => 100,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Payment failed.');

        try {
            ProcessOrderPayment::dispatch($order->id, 'failing');
        } finally {
            $this->assertDatabaseHas('order_logs', [
                'order_id' => $order->id,
                'event' => 'payment.started',
            ]);

            $this->assertDatabaseHas('order_logs', [
                'order_id' => $order->id,
                'event' => 'payment.failed',
                'provider' => 'failing',
                'status' => OrderStatus::FAILED->value,
            ]);
        }
    }

    public function test_user_can_view_own_order_logs()
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $order = Order::factory()->create([
            'user_id' => $user->id,
        ]);

        OrderLog::create([
            'order_id' => $order->id,
            'event' => 'order.created',
            'status' => OrderStatus::PENDING->value,
            'message' => 'Order created successfully.',
        ]);

        $response = $this->getJson(
            "/api/orders/{$order->id}/logs"
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.event',
                'order.created'
            );
    }

    public function test_user_cannot_view_another_users_order_logs()
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $owner->id,
        ]);

        OrderLog::create([
            'order_id' => $order->id,
            'event' => 'order.created',
            'status' => OrderStatus::PENDING->value,
            'message' => 'Order created successfully.',
        ]);

        Sanctum::actingAs($otherUser);

        $response = $this->getJson(
            "/api/orders/{$order->id}/logs"
        );

        $response->assertNotFound();
    }

    public function test_cancelling_order_creates_cancellation_log()
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $product = Product::factory()->create([
            'price' => 100,
            'stock_quantity' => 8,
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::CONFIRMED,
            'subtotal' => 200,
            'total' => 200,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'unit_price' => 100,
            'quantity' => 2,
            'subtotal' => 200,
        ]);

        $response = $this->postJson(
            "/api/orders/{$order->id}/cancel"
        );

        $response->assertOk();

        $this->assertDatabaseHas('order_logs', [
            'order_id' => $order->id,
            'event' => 'order.cancelled',
            'status' => OrderStatus::CANCELLED->value,
        ]);
    }

    public function test_confirmed_order_can_move_to_processing()
    {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::CONFIRMED,
        ]);

        $service = app(UpdateOrderStatusService::class);

        $updatedOrder = $service->execute(
            $order->id,
            OrderStatus::PROCESSING,
        );

        $this->assertEquals(
            OrderStatus::PROCESSING,
            $updatedOrder->status
        );

        $this->assertDatabaseHas('order_logs', [
            'order_id' => $order->id,
            'event' => 'order.status_changed',
            'status' => OrderStatus::PROCESSING->value,
        ]);
    }

    public function test_processing_order_can_move_to_completed()
    {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::PROCESSING,
        ]);

        $service = app(UpdateOrderStatusService::class);

        $updatedOrder = $service->execute(
            $order->id,
            OrderStatus::COMPLETED,
        );

        $this->assertEquals(
            OrderStatus::COMPLETED,
            $updatedOrder->status
        );

        $this->assertDatabaseHas('order_logs', [
            'order_id' => $order->id,
            'event' => 'order.status_changed',
            'status' => OrderStatus::COMPLETED->value,
        ]);
    }

    public function test_invalid_order_status_transition_is_rejected()
    {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::COMPLETED,
        ]);

        $service = app(UpdateOrderStatusService::class);

        $this->expectException(DomainException::class);

        $service->execute(
            $order->id,
            OrderStatus::PENDING,
        );
    }

    public function test_authenticated_user_can_update_order_status(): void
    {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::PENDING,
        ]);

        $response = $this->actingAs($user)
            ->patchJson("/api/orders/{$order->id}/status", [
                'status' => 'confirmed',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_order_status_update_requires_valid_status(): void
    {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::PENDING,
        ]);

        $response = $this->actingAs($user)
            ->patchJson("/api/orders/{$order->id}/status", [
                'status' => 'invalid-status',
            ]);

        $response->assertUnprocessable();
    }

    public function test_order_status_update_rejects_invalid_transition(): void
    {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::COMPLETED,
        ]);

        $response = $this->actingAs($user)
            ->patchJson("/api/orders/{$order->id}/status", [
                'status' => 'processing',
            ]);

        $response->assertStatus(409);
    }
}
