<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class StockAlertNotification extends Notification
{
    public function __construct(
        private readonly array $urgentNoPo,
        private readonly array $withPo,
    ) {}

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $now  = now()->timezone('Asia/Jakarta')->format('d M Y, H:i') . ' WIB';
        $mail = (new MailMessage)->subject("🚨 Alert Stok — {$now}");

        if (!empty($this->urgentNoPo)) {
            $mail->line('⚡ BEST SELLER — TANPA PO (PERLU TINDAKAN):');
            foreach ($this->groupByParent($this->urgentNoPo) as $parentSku => $variants) {
                $mail->line("{$parentSku} (" . count($variants) . ' variasi)');
                foreach ($variants as $v) {
                    $flag = $v['critical'] ? ' ❌' : ' ⚠️';
                    $mail->line("• {$v['sku']}: {$v['qty']} pcs{$flag}");
                }
            }
        }

        if (!empty($this->withPo)) {
            $mail->line('');
            $mail->line('ℹ️ STOK RENDAH — SUDAH ADA PO (INFO):');
            foreach ($this->groupByParent($this->withPo) as $parentSku => $variants) {
                $mail->line("{$parentSku} (" . count($variants) . ' variasi)');
                foreach ($variants as $v) {
                    $flag = $v['critical'] ? ' ❌' : '';
                    $mail->line("• {$v['sku']}: {$v['qty']} pcs (PO: {$v['po']}){$flag}");
                }
            }
        }

        $appUrl = rtrim(config('app.url'), '/');
        $mail->action('Cek Product Ads', $appUrl . '/product-ads');

        return $mail;
    }

    /** @return array<string, array> */
    private function groupByParent(array $variants): array
    {
        $groups = [];
        foreach ($variants as $v) {
            $groups[$v['parent_sku']][] = $v;
        }
        return $groups;
    }
}
