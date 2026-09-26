<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_item_photos', function (Blueprint $table) {
            $table->text('sync_error')->nullable()->after('sync_status');
        });
    }

    public function down(): void
    {
        Schema::table('report_item_photos', function (Blueprint $table) {
            $table->dropColumn('sync_error');
        });
    }
};
