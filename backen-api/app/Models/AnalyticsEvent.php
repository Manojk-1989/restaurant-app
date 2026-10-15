<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsEvent extends Model
{
    protected $table = 'analytics_events';

    protected $primaryKey = 'event_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'event_id', 'event_name', 'source', 'occurred_at', 'visitor_id', 'session_id', 'page_path', 'properties', 'received_at',
    ];

    protected function casts(): array
    {
        return ['properties' => 'array'];
    }
}