<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guarded: projects that predate the extraction already carry a `files`
        // table from the template kernel. Adding this module to one of those
        // should adopt the existing table, not collide with it.
        if (Schema::hasTable('files')) {
            if (! Schema::hasColumn('files', 'processed')) {
                Schema::table('files', function (Blueprint $table) {
                    $table->boolean('processed')->default(true);
                });
            }

            return;
        }

        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('path');
            $table->string('type');
            $table->integer('size');
            $table->string('disk');
            $table->json('responsive_paths');
            // false only while a presigned S3 upload is waiting on variant
            // generation; the direct-upload path writes rows already processed.
            $table->boolean('processed')->default(true);
            $table->whoDidIt();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
