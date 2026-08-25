<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 24)->default('todo');
            $table->string('priority', 16)->default('normal');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            // Optional owner record — a task can stand alone or hang off an
            // order, a ticket, anything.
            $table->nullableMorphs('taskable');
            $table->unsignedInteger('position')->default(0);
            $table->whoDidIt();
            $table->timestamps();

            // The two queries that actually run: "my open tasks by due date"
            // and "this column of the board".
            $table->index(['assigned_to', 'status', 'due_at']);
            $table->index(['status', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
