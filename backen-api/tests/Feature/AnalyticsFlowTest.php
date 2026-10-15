<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\RestaurantOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnalyticsFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['analytics.server_key' => 'test-server-key']);
    }

    public function test_browser_events_are_validated_and_idempotent(): void
    {
        $event = [
            'event_id' => 'page-view-1',
            'name' => 'page_view',
            'timestamp' => '2026-10-14T12:00:00.000Z',
            'visitor_id' => 'visitor-a',
            'session_id' => 'session-a',
            'path' => '/',
            'properties' => ['title' => 'Good Table'],
        ];

        $this->postJson('/api/events', $event)->assertAccepted()->assertJson(['accepted' => true]);
        $this->postJson('/api/events', $event)->assertAccepted()->assertJson(['accepted' => false]);
        $this->postJson('/api/events', [...$event, 'name' => 'invalid event name'])->assertUnprocessable();

        $this->assertDatabaseCount('analytics_events', 1);
        $this->assertSame('browser', AnalyticsEvent::query()->firstOrFail()->source);
    }

    public function test_server_events_require_a_shared_key_and_aggregate_correctly(): void
    {
        $orderEvent = [
            'event_id' => 'order-event-1',
            'name' => 'order_created',
            'timestamp' => '2026-10-14T13:00:00.000Z',
            'properties' => ['total_cents' => 1650],
        ];
        $this->postJson('/api/server-events', $orderEvent)->assertUnauthorized();
        $this->withHeader('X-Analytics-Key', 'test-server-key')
            ->postJson('/api/server-events', $orderEvent)
            ->assertAccepted();
        $this->postJson('/api/events', [
            'event_id' => 'page-event-2',
            'name' => 'page_view',
            'timestamp' => '2026-10-14T12:00:00.000Z',
            'visitor_id' => 'visitor-a',
            'path' => '/',
        ])->assertAccepted();

        $this->getJson('/api/analytics?from=2026-10-14&to=2026-10-14')
            ->assertOk()
            ->assertJsonPath('metrics.page_views', 1)
            ->assertJsonPath('metrics.unique_visitors', 1)
            ->assertJsonPath('metrics.orders', 1)
            ->assertJsonPath('metrics.total_events', 2)
            ->assertJsonPath('pages.0.path', '/')
            ->assertJsonPath('pages.0.views', 1);
    }

    public function test_orders_use_server_prices_and_send_a_server_event(): void
    {
        Http::fake(['http://127.0.0.1:8000/api/server-events' => Http::response([], 202)]);

        $response = $this->postJson('/api/orders', [
            'lines' => [['id' => 'tomato-pasta', 'quantity' => 2]],
            'visitor_id' => 'visitor-order',
            'session_id' => 'session-order',
        ]);

        $response->assertCreated()->assertJsonPath('total_cents', 3300);
        $this->assertDatabaseCount('restaurant_orders', 1);
        Http::assertSent(fn (ClientRequest $request): bool =>
            $request->url() === 'http://127.0.0.1:8000/api/server-events'
            && $request->hasHeader('X-Analytics-Key', 'test-server-key')
            && $request['name'] === 'order_created'
            && $request['visitor_id'] === 'visitor-order'
            && $request['properties']['total_cents'] === 3300
        );
    }

    public function test_invalid_date_ranges_are_rejected(): void
    {
        $this->getJson('/api/analytics?from=2026-02-30&to=2026-03-01')->assertUnprocessable();
        $this->getJson('/api/analytics?from=2025-01-01&to=2026-10-15')->assertUnprocessable();
    }

    public function test_an_order_survives_analytics_delivery_failure(): void
    {
        Http::fake(['http://127.0.0.1:8000/api/server-events' => Http::response([], 503)]);

        $this->postJson('/api/orders', ['lines' => [['id' => 'market-salad', 'quantity' => 1]]])
            ->assertCreated();

        $this->assertSame(1, RestaurantOrder::query()->count());
    }
}