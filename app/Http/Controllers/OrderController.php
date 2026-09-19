<?php

namespace App\Http\Controllers;

use App\Application\Orders\CreateOrderService;
use App\Http\Requests\CreateOrderRequest;
use App\Application\Orders\GetOrderService;
use App\Application\Orders\GetOrdersService;
use Illuminate\Http\JsonResponse;
use App\Application\Orders\IdempotencyService;
use Illuminate\Http\Request;
use App\Http\Resources\OrderResource;
use App\Application\Orders\CancelOrderService;
use App\Http\Resources\OrderLogResource;
use App\Application\Orders\UpdateOrderStatusService;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Domain\Orders\OrderStatus;

class OrderController extends Controller
{
    public function index(
        GetOrdersService $getOrdersService,
    ): JsonResponse {
        $orders = $getOrdersService->execute(
            request()->user()->id,
        );

        return response()->json([
            'data' => $orders,
        ]);
    }
    public function store(
        CreateOrderRequest $request,
        CreateOrderService $createOrderService,
        IdempotencyService $idempotencyService,
    ): JsonResponse {
        $userId = $request->user()->id;

        $key = $request->header('Idempotency-Key');

        if (!$key) {
            return response()->json([
                'message' => 'The Idempotency-Key header is required.',
            ], 400);
        }

        if (strlen($key) > 128) {
            return response()->json([
                'message' => 'The Idempotency-Key header must not exceed 128 characters.',
            ], 422);
        }

        $requestHash = hash(
            'sha256',
            $request->getContent()
        );

        $existing = $idempotencyService->getExisting(
            $userId,
            $key,
            $requestHash,
        );

        if ($existing) {
            return response()->json(
                $existing->response_body,
                $existing->response_status
            );
        }

        $idempotencyKey = $idempotencyService->create(
            $userId,
            $key,
            $requestHash,
        );

        $order = $createOrderService->execute(
            $userId,
            $request->validated('items'),
        );

        $body = [
            'message' => 'Order created successfully.',
            'data' => (new OrderResource($order))->resolve($request),
        ];

        $idempotencyService->storeResponse(
            $idempotencyKey,
            201,
            $body,
        );

        return response()->json($body, 201);
    }

    public function show(
        int $order,
        GetOrderService $getOrderService,
    ): JsonResponse {
        $order = $getOrderService->execute($order);

        if (!request()->user()->can('view', $order)) {
            abort(404);
        }

        return response()->json([
            'data' => $order,
        ]);
    }

    public function cancel(
        int $order,
        CancelOrderService $cancelOrderService,
    ): JsonResponse {
        $orderModel = \App\Models\Order::findOrFail($order);

        if (!request()->user()->can('cancel', $orderModel)) {
            abort(404);
        }

        $order = $cancelOrderService->execute($order);

        return response()->json([
            'data' => $order,
        ]);
    }

    public function logs(
        int $order,
        GetOrderService $getOrderService,
    ): JsonResponse {
        $orderModel = $getOrderService->execute($order);

        if (!request()->user()->can('view', $orderModel)) {
            abort(404);
        }

        return response()->json([
            'data' => OrderLogResource::collection(
                $getOrderService->getLogs($order)
            ),
        ]);
    }

    public function updateStatus(
        int $order,
        UpdateOrderStatusRequest $request,
        UpdateOrderStatusService $updateOrderStatusService,
    ): JsonResponse {
        $status = OrderStatus::from($request->validated('status'));

        $updatedOrder = $updateOrderStatusService->execute(
            $order,
            $status,
        );

        return response()->json([
            'data' => $updatedOrder,
        ]);
    }
}
