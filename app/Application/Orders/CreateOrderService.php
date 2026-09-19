<?php

namespace App\Application\Orders;

use App\Domain\Orders\OrderRepository;
use App\Domain\Orders\OrderStatus;
use App\Domain\Products\ProductRepository;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use App\Domain\Orders\Exceptions\InsufficientStockException;
use App\Domain\Orders\Exceptions\ProductUnavailableException;
use App\Jobs\ProcessOrderPayment;

class CreateOrderService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly ProductRepository $productRepository,
        private readonly OrderLogService $orderLogService,
    ) {}

    public function execute(
        int $userId,
        array $items,
    ): Order {
        return DB::transaction(function () use ($userId, $items) {
            $order = new Order();

            $order->user_id = $userId;
            $order->order_number = 'ORD-' . strtoupper(str()->random(10));
            $order->status = OrderStatus::PENDING;

            $order->fill([
                'subtotal' => 0,
                'total' => 0,
                'currency' => 'INR',
            ]);

            $order = $this->orderRepository->save($order);

            $subtotal = 0;

            foreach ($items as $item) {
                $product = $this->productRepository->findForUpdate(
                    $item['product_id']
                );

                if (!$product || !$product->is_active) {
                    throw new ProductUnavailableException($item['product_id']);
                }

                if ($product->stock_quantity < $item['quantity']) {
                    throw new InsufficientStockException($product->name);
                }

                $itemSubtotal = $product->price * $item['quantity'];

                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'unit_price' => $product->price,
                    'quantity' => $item['quantity'],
                    'subtotal' => $itemSubtotal,
                ]);

                $product->decrement(
                    'stock_quantity',
                    $item['quantity']
                );

                $subtotal += $itemSubtotal;
            }

            $order->subtotal = $subtotal;
            $order->total = $subtotal;

            $order = $this->orderRepository->save($order);

            $this->orderLogService->record(
                $order,
                'order.created',
                'Order created successfully.',
                null,
                $order->status->value,
                [
                    'user_id' => $userId,
                    'total' => $order->total,
                ],
            );

            ProcessOrderPayment::dispatch($order->id, 'mock');

            return $order->load([
                'items',
                'payments',
            ]);
        });
    }
}
