<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class HppChangedNotification extends Notification
{
    public function __construct(
        private readonly array $changes,
        private readonly int $totalSynced = 0,
    ) {}

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $now = now()->timezone('Asia/Jakarta')->format('d M Y, H:i') . ' WIB';

        if (empty($this->changes)) {
            return (new MailMessage)
                ->subject("HPP Sync — {$now}")
                ->line("Tidak ada perubahan HPP.")
                ->line("Total {$this->totalSynced} SKU dicek dari Jubelio.");
        }

        $mail = (new MailMessage)
            ->subject("⚠️ Perubahan HPP — {$now}");

        foreach ($this->changes as $c) {
            $old    = 'Rp ' . number_format($c['old'], 0, ',', '.');
            $new    = 'Rp ' . number_format($c['new'], 0, ',', '.');
            $pct    = $c['old'] > 0
                ? round(($c['new'] - $c['old']) / $c['old'] * 100, 1)
                : 0;
            $pctStr = ($pct >= 0 ? '+' : '') . number_format($pct, 1, ',', '.') . '%';
            $mail->line("• {$c['sku']}: {$old} → {$new} ({$pctStr})");
        }

        $cnt = count($this->changes);
        $mail->line("Total: {$cnt} SKU berubah dari {$this->totalSynced} yang dicek.");

        return $mail;
    }
}
