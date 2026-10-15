<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use JsonException;

class EventController
{
    public function browser(Request $request): JsonResponse
    {
        return $this->record($request, 'browser');
    }

    public function server(Request $request): JsonResponse
    {
        return $this->record($request, 'server');
    }

    private function record(Request $request, string $source): JsonResponse
    {
        $event = $request->validate([
            'event_id' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:80', 'regex:/\A[a-zA-Z][a-zA-Z0-9_.-]{0,79}\z/'],
            'timestamp' => ['nullable', 'date'],
            'visitor_id' => ['nullable', 'string', 'max:128'],
            'session_id' => ['nullable', 'string', 'max:128'],
            'path' => ['nullable', 'string', 'max:512', 'regex:/\A\//'],
            'properties' => ['nullable', 'array'],
        ]);

        $properties = $event['properties'] ?? [];
        try {
            $propertiesJson = json_encode($properties, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return response()->json(['error' => 'properties must be JSON serializable'], 422);
        }

        if (strlen($propertiesJson) > 8192) {
            return response()->json(['error' => 'properties exceed 8 KB'], 422);
        }

        $occurredAt = isset($event['timestamp'])
            ? CarbonImmutable::parse($event['timestamp'])->utc()
            : CarbonImmutable::now('UTC');

        if ($occurredAt->isAfter(CarbonImmutable::now('UTC')->addMinutes(10))) {
            return response()->json(['error' => 'timestamp is too far in the future'], 422);
        }

        $inserted = DB::table('analytics_events')->insertOrIgnore([
            'event_id' => $event['event_id'],
            'event_name' => $event['name'],
            'source' => $source,
            'occurred_at' => $occurredAt->format('Y-m-d H:i:s.v'),
            'visitor_id' => $event['visitor_id'] ?? null,
            'session_id' => $event['session_id'] ?? null,
            'page_path' => $event['path'] ?? null,
            'properties' => $propertiesJson,
            'received_at' => CarbonImmutable::now('UTC')->format('Y-m-d H:i:s'),
        ]);

        return response()->json([
            'accepted' => $inserted === 1,
            'event_id' => $event['event_id'],
        ], 202);
    }
}