<?php

namespace App\Http\Controllers;

use App\Models\RestaurantOrder;
use App\Services\ServerAnalyticsClient;
use App\Support\MenuCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController
{
    public function store(Request $request, ServerAnalyticsClient $analytics): JsonResponse
    {
        $input = $request->validate([
            'lines' => ['required', 'array', 'min:1', 'max:10'],
            'lines.*.id' => ['required', 'string'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:10'],
            'visitor_id' => ['nullable', 'string', 'max:128'],
            'session_id' => ['nullable', 'string', 'max:128'],
        ]);

        $menu = collect(MenuCatalog::ITEMS)->keyBy('id');
        $lines = [];
        foreach ($input['lines'] as $requestedLine) {
            $item = $menu->get($requestedLine['id']);
            if ($item === null) {
                return response()->json(['error' => 'Unknown menu item'], 422);
            }
            $lines[] = [
                'id' => $item['id'],
                'name' => $item['name'],
                'quantity' => $requestedLine['quantity'],
                'price_cents' => $item['price_cents'],
            ];
        }

        $totalCents = array_sum(array_map(fn (array $line): int => $line['price_cents'] * $line['quantity'], $lines));
        $order = DB::transaction(fn (): RestaurantOrder => RestaurantOrder::query()->create([
            'order_id' => (string) Str::uuid(),
            'lines' => $lines,
            'total_cents' => $totalCents,
        ]));

        $analytics->track('order_created', [
            'visitor_id' => $input['visitor_id'] ?? null,
            'session_id' => $input['session_id'] ?? null,
            'path' => '/',
            'properties' => [
                'order_id' => $order->order_id,
                'item_count' => array_sum(array_column($lines, 'quantity')),
                'total_cents' => $totalCents,
            ],
        ]);

        return response()->json([
            'id' => $order->order_id,
            'lines' => $order->lines,
            'total_cents' => $order->total_cents,
            'created_at' => $order->created_at->toISOString(),
        ], 201);
    }
}