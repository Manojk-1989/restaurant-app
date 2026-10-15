<?php

return [
    'server_key' => env('ANALYTICS_SERVER_KEY'),
    'server_event_url' => env('ANALYTICS_SERVER_EVENT_URL', 'http://127.0.0.1:8000/api/server-events'),
];