<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->string('pdf_status', 20)->default('idle')->after('pdf_generated_at');
            $table->text('pdf_error')->nullable()->after('pdf_status');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['pdf_status', 'pdf_error']);
        });
    }
};
