<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController
{
    public function __invoke(Request $request): JsonResponse
    {
        $dates = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $from = CarbonImmutable::parse($dates['from'], 'UTC')->startOfDay();
        $to = CarbonImmutable::parse($dates['to'], 'UTC')->startOfDay();
        if ($from->diffInDays($to) > 365) {
            return response()->json(['error' => 'Date range cannot exceed 366 days'], 422);
        }
        $toExclusive = $to->addDay();

        $range = fn () => DB::table('analytics_events')
            ->where('occurred_at', '>=', $from->format('Y-m-d H:i:s'))
            ->where('occurred_at', '<', $toExclusive->format('Y-m-d H:i:s'));

        $metrics = [
            'page_views' => (clone $range())->where('event_name', 'page_view')->count(),
            'unique_visitors' => (clone $range())->where('source', 'browser')->whereNotNull('visitor_id')->distinct()->count('visitor_id'),
            'orders' => (clone $range())->where('event_name', 'order_created')->count(),
            'total_events' => (clone $range())->count(),
        ];

        $pages = $range()
            ->where('event_name', 'page_view')
            ->whereNotNull('page_path')
            ->select('page_path as path')
            ->selectRaw('COUNT(*) as views')
            ->groupBy('page_path')
            ->orderByDesc('views')
            ->limit(10)
            ->get()
            ->map(fn (object $page): array => ['path' => $page->path, 'views' => (int) $page->views])
            ->values();

        $trends = $range()
            ->selectRaw('DATE(occurred_at) as date, event_name as name, COUNT(*) as count')
            ->groupByRaw('DATE(occurred_at), event_name')
            ->orderBy('date')
            ->orderBy('name')
            ->get()
            ->map(fn (object $event): array => ['date' => $event->date, 'name' => $event->name, 'count' => (int) $event->count])
            ->values();

        return response()->json(compact('metrics', 'pages', 'trends'));
    }
}