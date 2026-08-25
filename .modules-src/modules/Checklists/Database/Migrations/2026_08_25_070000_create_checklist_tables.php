<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('checklist_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_template_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->text('help')->nullable();
            $table->boolean('requires_evidence')->default(false);
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['checklist_template_id', 'position']);
        });

        Schema::create('checklists', function (Blueprint $table) {
            $table->id();

            // nullOnDelete, not cascade: deleting a template must not delete the
            // record of every inspection ever carried out under it. The instance
            // already carries its own copy of the name and the items, so it
            // stays readable with the template gone.
            $table->foreignId('checklist_template_id')->nullable()->constrained()->nullOnDelete();

            $table->morphs('subject');
            $table->string('name');
            $table->string('status', 20)->default('open')->index();
            $table->timestamp('completed_at')->nullable();
            $table->string('signed_by')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id', 'status']);
        });

        Schema::create('checklist_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_id')->constrained()->cascadeOnDelete();

            // Copied from the template item, not joined to it — a template is
            // edited over time and an instance has to keep saying what was
            // actually checked.
            $table->string('label');
            $table->text('help')->nullable();
            $table->boolean('requires_evidence')->default(false);
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('position')->default(0);

            $table->string('status', 20)->default('pending')->index();
            $table->text('note')->nullable();

            // No FK to the Files module's table: Checklists installs without it,
            // and a constraint on a table that may not exist is a migration
            // that fails on half the projects that want this module.
            $table->unsignedBigInteger('file_id')->nullable();

            $table->timestamp('answered_at')->nullable();
            $table->timestamps();
            $table->index(['checklist_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_responses');
        Schema::dropIfExists('checklists');
        Schema::dropIfExists('checklist_template_items');
        Schema::dropIfExists('checklist_templates');
    }
};
