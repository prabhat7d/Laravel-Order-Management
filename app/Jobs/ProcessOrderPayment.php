<?php

namespace App\Jobs;

use App\Domain\Orders\OrderStatus;
use App\Domain\Payments\PaymentGatewayFactory;
use App\Events\OrderCreated;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use App\Domain\Orders\OrderStateMachine;
use App\Application\Orders\OrderLogService;

class ProcessOrderPayment implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly int $orderId,
        public readonly string $provider = 'mock',
    ) {}

    public function handle(
        PaymentGatewayFactory $paymentGatewayFactory,
        OrderStateMachine $stateMachine,
        OrderLogService $orderLogService,
    ): void {
        $order = Order::with('items')->findOrFail($this->orderId);

        try {
            $gateway = $paymentGatewayFactory->make($this->provider);

            $orderLogService->record(
                $order,
                'payment.started',
                'Payment processing started.',
                $this->provider,
                $order->status->value,
            );

            $gateway->charge($order);

            $order->update([
                'status' => $stateMachine->transition(
                    $order->status,
                    OrderStatus::CONFIRMED,
                ),
            ]);

            $orderLogService->record(
                $order,
                'payment.succeeded',
                'Payment completed successfully.',
                $this->provider,
                OrderStatus::CONFIRMED->value,
            );

            OrderCreated::dispatch($order);
        } catch (Throwable $exception) {
            DB::transaction(function () use ($order, $stateMachine) {
                $order = Order::query()
                    ->lockForUpdate()
                    ->findOrFail($order->id);

                if ($order->status !== OrderStatus::PENDING) {
                    return;
                }

                foreach ($order->items as $item) {
                    Product::query()
                        ->whereKey($item->product_id)
                        ->lockForUpdate()
                        ->increment('stock_quantity', $item->quantity);
                }

                $order->update([
                    'status' => $stateMachine->transition(
                        $order->status,
                        OrderStatus::FAILED,
                    ),
                ]);
            });

            $orderLogService->record(
                $order,
                'payment.failed',
                $exception->getMessage(),
                $this->provider,
                OrderStatus::FAILED->value,
            );

            throw $exception;
        }
    }
}
