<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_ref', 80)->nullable();
            $table->foreignId('report_import_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 20)->default('updated');
            $table->string('field', 100)->nullable();
            $table->string('label')->nullable();
            $table->longText('old_value')->nullable();
            $table->longText('new_value')->nullable();
            $table->string('source', 20)->default('app');
            $table->timestamp('created_at')->nullable();
            $table->index(['report_id', 'id']);
            $table->index(['report_item_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_activity_logs');
    }
};
