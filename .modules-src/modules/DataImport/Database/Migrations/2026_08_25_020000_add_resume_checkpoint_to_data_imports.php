<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_imports', function (Blueprint $table) {
            // The last CSV line this import has finished with.
            //
            // Every row commits in its own transaction, which is what lets a
            // 5,000-row file with one bad row import the other 4,999 — but it
            // also means a job that dies at row 18,000 has already written
            // 18,000 rows. Without a mark of how far it got, the retry starts
            // at line 1 and writes them all again.
            $table->unsignedInteger('processed_rows')->default(0)->after('failed_rows');
        });
    }

    public function down(): void
    {
        Schema::table('data_imports', function (Blueprint $table) {
            $table->dropColumn('processed_rows');
        });
    }
};
