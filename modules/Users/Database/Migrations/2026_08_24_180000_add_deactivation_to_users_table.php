<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guarded: this touches a KERNEL table, so a project that already has
        // the column (or has renamed it) must not have its migration blow up
        // on install.
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'deactivated_at')) {
                // A timestamp, not a boolean: "when did this happen" is the
                // question support actually asks, and a boolean cannot answer
                // it.
                $table->timestamp('deactivated_at')->nullable()->after('last_login_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'deactivated_at')) {
                $table->dropColumn('deactivated_at');
            }
        });
    }
};
