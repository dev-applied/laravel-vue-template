<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('example_notes', function (Blueprint $table) {
            $table->id();
            $table->string('note');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('example_notes');
    }
};
