<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Workspace-level "globals": the lowest-precedence variable layer, applied
     * regardless of which environment is active. Mirrors environment_variables
     * (including the is_secret masking flag) but scoped to the workspace.
     */
    public function up(): void
    {
        Schema::create('workspace_variables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->text('value')->nullable();
            $table->boolean('is_secret')->default(false);
            $table->timestamps();

            $table->unique(['workspace_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_variables');
    }
};
