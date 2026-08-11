<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The cookie jar. Scoped per user as well as per workspace: a cookie is
     * almost always somebody's session, and members of a shared workspace
     * should not end up authenticated as each other because they happened to
     * hit the same login endpoint.
     */
    public function up(): void
    {
        Schema::create('request_cookies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('domain');
            $table->string('path')->default('/');
            $table->string('name');
            $table->text('value');
            $table->timestamp('expires_at')->nullable();
            $table->boolean('secure')->default(false);
            $table->boolean('http_only')->default(false);
            $table->timestamps();

            $table->unique(['workspace_id', 'user_id', 'domain', 'path', 'name'], 'request_cookies_identity_unique');
            $table->index(['workspace_id', 'user_id', 'domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_cookies');
    }
};
