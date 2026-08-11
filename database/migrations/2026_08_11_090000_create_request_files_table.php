<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Files uploaded for a request's multipart/form-data body. The body JSON stays
     * the source of truth for which field uses which file — a row here is just the
     * stored blob, referenced by id from a `{"type": "file", "file_id": N}` field.
     */
    public function up(): void
    {
        Schema::create('request_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained()->cascadeOnDelete();
            $table->string('filename');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_files');
    }
};
