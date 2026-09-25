<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('text_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50);
            $table->string('name');
            $table->longText('body_with_variables');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['key', 'active']);
        });

        Schema::create('item_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->text('category_label')->nullable();
            $table->longText('specification')->nullable();
            $table->longText('default_narrative')->nullable();
            $table->string('default_unit', 50)->nullable();
            $table->decimal('default_quantity', 12, 3)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['type', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_catalog');
        Schema::dropIfExists('text_templates');
    }
};
