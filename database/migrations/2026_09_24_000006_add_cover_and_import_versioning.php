<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->text('cover_url')->nullable()->after('event_end');
            $table->string('cover_path')->nullable()->after('cover_url');
            $table->string('cover_template', 30)->nullable()->after('cover_path');
        });

        Schema::table('report_imports', function (Blueprint $table) {
            $table->unsignedInteger('version')->nullable()->after('status');
            $table->string('file_hash', 64)->nullable()->after('version');
            $table->unsignedInteger('photos_added')->default(0)->after('file_hash');
            $table->index(['report_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['cover_url', 'cover_path', 'cover_template']);
        });

        Schema::table('report_imports', function (Blueprint $table) {
            $table->dropIndex(['report_id', 'version']);
            $table->dropColumn(['version', 'file_hash', 'photos_added']);
        });
    }
};
