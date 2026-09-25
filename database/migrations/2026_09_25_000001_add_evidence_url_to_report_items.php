<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_items', function (Blueprint $table) {
            $table->string('evidence_url')->nullable()->after('drive_folder_id');
        });

        DB::table('report_items')
            ->whereNotNull('drive_folder_id')
            ->orderBy('id')
            ->chunkById(200, function ($items): void {
                foreach ($items as $item) {
                    DB::table('report_items')
                        ->where('id', $item->id)
                        ->update([
                            'evidence_url' => 'https://drive.google.com/drive/folders/'.$item->drive_folder_id,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('report_items', function (Blueprint $table) {
            $table->dropColumn('evidence_url');
        });
    }
};
