<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_imports', function (Blueprint $table) {
            $table->index(['report_id', 'file_hash']);
        });
    }

    public function down(): void
    {
        Schema::table('report_imports', function (Blueprint $table) {
            $table->dropIndex(['report_id', 'file_hash']);
        });
    }
};
