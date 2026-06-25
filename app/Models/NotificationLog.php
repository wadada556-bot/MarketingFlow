<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $table      = 'notification_logs';
    public    $timestamps = false;

    protected $fillable = ['type', 'key', 'sent_at'];

    protected $casts = ['sent_at' => 'datetime'];

    public static function isOnCooldown(string $type, string $key, int $hours = 7): bool
    {
        return static::where('type', $type)
            ->where('key', $key)
            ->where('sent_at', '>=', now()->subHours($hours))
            ->exists();
    }

    public static function record(string $type, string $key): void
    {
        static::upsert(
            [['type' => $type, 'key' => $key, 'sent_at' => now()->toDateTimeString()]],
            ['type', 'key'],
            ['sent_at'],
        );
    }
}
