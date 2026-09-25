<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('editor')->after('password');
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('dane_code', 2)->unique();
            $table->string('name');
            $table->string('normalized_name')->unique();
            $table->timestamps();
        });

        Schema::create('municipalities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('dane_code', 5)->unique();
            $table->string('name');
            $table->string('normalized_name');
            $table->timestamps();
            $table->unique(['department_id', 'normalized_name']);
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('municipality_id')->nullable()->constrained()->nullOnDelete();
            $table->string('contract_number', 100)->unique();
            $table->date('report_date')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('subject')->nullable();
            $table->longText('contract_object')->nullable();
            $table->string('event_name')->nullable();
            $table->date('event_start')->nullable();
            $table->date('event_end')->nullable();
            $table->longText('introduction')->nullable();
            $table->longText('event_description')->nullable();
            $table->longText('conclusion')->nullable();
            $table->string('signer_name')->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->string('pdf_path')->nullable();
            $table->timestamp('pdf_generated_at')->nullable();
            $table->timestamp('updated_in_app_at')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('report_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->string('ref', 80);
            $table->string('type', 20);
            $table->text('category_label')->nullable();
            $table->longText('specification')->nullable();
            $table->string('artist_name')->nullable();
            $table->longText('narrative')->nullable();
            $table->decimal('quantity', 12, 3)->nullable();
            $table->string('unit', 50)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('photo_layout', 20)->nullable();
            $table->string('drive_folder_id')->nullable();
            $table->timestamp('drive_synced_at')->nullable();
            $table->boolean('add_standard_texts')->default(false);
            $table->text('internal_notes')->nullable();
            $table->timestamp('updated_in_app_at')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
            $table->unique(['report_id', 'ref']);
            $table->index(['report_id', 'sort_order']);
        });

        Schema::create('report_item_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_item_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20)->default('upload');
            $table->string('drive_file_id')->nullable();
            $table->text('drive_url')->nullable();
            $table->string('path')->nullable();
            $table->string('thumb_path')->nullable();
            $table->string('original_name')->nullable();
            $table->text('caption')->nullable();
            $table->string('layout', 20)->default('pair');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('sync_status', 20)->default('pending');
            $table->timestamp('taken_at')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamps();
            $table->unique(['report_item_id', 'drive_file_id']);
            $table->index(['report_item_id', 'sort_order']);
        });

        Schema::create('report_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_name');
            $table->string('file_path');
            $table->string('status', 20)->default('previewing');
            $table->json('summary')->nullable();
            $table->json('errors')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->index(['report_id', 'created_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_imports');
        Schema::dropIfExists('report_item_photos');
        Schema::dropIfExists('report_items');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('municipalities');
        Schema::dropIfExists('departments');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
