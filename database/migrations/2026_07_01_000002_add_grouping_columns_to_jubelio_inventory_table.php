<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jubelio_inventory', function (Blueprint $table) {
            $table->unsignedBigInteger('item_group_id')->nullable()->after('parent_sku')->index();
            $table->string('variation_label', 150)->nullable()->after('item_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('jubelio_inventory', function (Blueprint $table) {
            $table->dropColumn(['item_group_id', 'variation_label']);
        });
    }
};
