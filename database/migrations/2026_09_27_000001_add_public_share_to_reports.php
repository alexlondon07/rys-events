<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->string('public_share_token', 64)->nullable()->unique()->after('pdf_error');
            $table->timestamp('public_share_enabled_at')->nullable()->after('public_share_token');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropUnique(['public_share_token']);
            $table->dropColumn(['public_share_token', 'public_share_enabled_at']);
        });
    }
};
