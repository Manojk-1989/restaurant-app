<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ServerAnalyticsClient
{
    public function track(string $name, array $context = []): void
    {
        $key = (string) config('analytics.server_key', '');
        if ($key === '') {
            Log::warning('Server analytics key is missing; business event was not delivered.');
            return;
        }

        try {
            $response = Http::acceptJson()
                ->withHeader('X-Analytics-Key', $key)
                ->timeout(1.5)
                ->post((string) config('analytics.server_event_url'), [
                    'event_id' => (string) Str::uuid(),
                    'name' => $name,
                    'timestamp' => now('UTC')->toISOString(),
                    'visitor_id' => $context['visitor_id'] ?? null,
                    'session_id' => $context['session_id'] ?? null,
                    'path' => $context['path'] ?? null,
                    'properties' => $context['properties'] ?? [],
                ]);

            if (! $response->successful()) {
                Log::warning('Server analytics event was rejected.', ['status' => $response->status(), 'name' => $name]);
            }
        } catch (Throwable $exception) {
            Log::warning('Server analytics delivery failed.', ['name' => $name, 'message' => $exception->getMessage()]);
        }
    }
}