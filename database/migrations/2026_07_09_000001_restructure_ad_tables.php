<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restrukturisasi tabel iklan jadi 3 tabel (2026-07-09).
 *
 *   ads                    -> identitas iklan per (store, product) + status/testing.
 *                             Importer buat identitas; kolom status milik aplikasi.
 *   ad_weekly_performances -> metrik mingguan per (ad, campaign, minggu). Importer.
 *   ad_logs                -> catatan (meniru product_ad_logs). Aplikasi.
 *
 * Menggantikan skema lama `ads`+`ad_products` (grain campaign/produk) dan membuang
 * `ad_plans`+`ad_plan_logs` (percobaan sebelumnya, tak jadi dipakai).
 *
 * Tabel ini juga dibuat langsung di DB oleh importer (C:\ads) — CREATE di sini
 * di-guard `hasTable` agar aman untuk DB yang tabelnya sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('ad_plan_logs');
        Schema::dropIfExists('ad_plans');

        if (!Schema::hasTable('ads')) {
            Schema::create('ads', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->bigInteger('store_id');                 // -> stores.id
                $table->bigInteger('product_id');               // snowflake TikTok 19 digit
                $table->string('product_name', 512)->nullable();
                $table->enum('status', ['active', 'stopped', 'completed'])->default('active');
                $table->enum('testing_status', ['testing', 'success', 'fail'])->nullable();
                $table->timestamp('testing_started_at')->nullable();
                $table->timestamp('testing_completed_at')->nullable();
                $table->timestamps();

                $table->unique(['store_id', 'product_id'], 'uniq_store_product');
                $table->index('product_id', 'idx_product');
            });
        }

        if (!Schema::hasTable('ad_weekly_performances')) {
            Schema::create('ad_weekly_performances', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('ad_id');            // -> ads.id
                $table->bigInteger('campaign_id');
                $table->string('campaign_name', 255)->nullable();
                $table->date('period_start');
                $table->date('period_end');
                $table->bigInteger('cost')->default(0);         // "Biaya" (field TikTok: cost)
                $table->bigInteger('net_cost')->default(0);     // billed_cost
                $table->bigInteger('budget')->default(0);
                $table->integer('orders_sku')->default(0);
                $table->bigInteger('gmv')->default(0);
                $table->bigInteger('cost_per_order')->default(0);
                $table->decimal('roi', 12, 2)->default(0);
                $table->string('roi_protection', 100)->nullable();
                $table->string('optimization', 100)->nullable();
                $table->string('currency', 8)->default('IDR');
                $table->json('raw_json')->nullable();
                $table->timestamps();

                $table->unique(['ad_id', 'campaign_id', 'period_start', 'period_end'], 'uniq_ad_campaign_period');
                $table->index('period_start', 'idx_period');
                $table->index('campaign_id', 'idx_campaign');
                $table->foreign('ad_id', 'fk_awp_ad')->references('id')->on('ads')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('ad_logs')) {
            Schema::create('ad_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('ad_id');            // -> ads.id
                $table->date('action_date');
                $table->text('description');
                $table->timestamps();

                $table->index(['ad_id', 'action_date'], 'idx_ad_date');
                $table->foreign('ad_id', 'fk_adlog_ad')->references('id')->on('ads')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_logs');
        Schema::dropIfExists('ad_weekly_performances');
        Schema::dropIfExists('ads');
    }
};
