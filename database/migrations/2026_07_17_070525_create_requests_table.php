<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('method', 10)->default('GET');
            $table->text('url');
            $table->unsignedInteger('order')->default(0);
            $table->json('headers')->nullable();
            $table->json('query_params')->nullable();
            $table->json('body')->nullable();
            $table->string('body_type')->default('none');
            $table->text('pre_request_script')->nullable();
            $table->text('test_script')->nullable();
            $table->timestamps();

            $table->index(['collection_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
};
