<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            // The destination the uploader chose.
            //
            // Both upload paths have always SENT this — `useFileUpload` puts
            // `folder_id` in the payload and AppFileUploadBtn exposes it as a
            // prop — and Laravel silently discarded it, because there was no
            // column, no fillable entry and no validation rule. A project could
            // set the prop, watch the upload succeed, and find every file in
            // the same undifferentiated pile.
            //
            // Deliberately NOT a foreign key: this module does not own a
            // folders table and should not invent one. It records the id the
            // project's own folder feature supplied, and the project's own
            // listing filters on it.
            $table->unsignedBigInteger('folder_id')->nullable()->after('disk')->index();
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropIndex(['folder_id']);
            $table->dropColumn('folder_id');
        });
    }
};
