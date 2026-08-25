<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            // The field definitions. JSON because the shape is the point — a
            // column per field would mean a migration per wording change,
            // which is the thing this module exists to avoid.
            $table->json('schema');
            $table->string('success_message')->nullable();
            $table->boolean('is_published')->default(false);
            // A public form is submittable without an account — intake and
            // application forms usually are.
            $table->boolean('is_public')->default(false);
            $table->timestamp('closes_at')->nullable();
            $table->whoDidIt();
            $table->timestamps();

            $table->index(['is_published', 'is_public']);
        });

        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('answers');
            // The schema AS IT WAS when this was submitted. Without it, editing
            // a form silently rewrites the meaning of every answer already
            // collected — the single worst failure mode in a form builder.
            $table->json('schema_snapshot');
            $table->ipAddress('ip_address')->nullable();
            $table->timestamps();

            $table->index(['form_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
        Schema::dropIfExists('forms');
    }
};
