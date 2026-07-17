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
        Schema::table('collections', function (Blueprint $table) {
            $table->string('auth_type')->nullable()->after('headers');
            $table->json('auth')->nullable()->after('auth_type');
        });

        Schema::table('requests', function (Blueprint $table) {
            $table->string('auth_type')->nullable()->after('headers');
            $table->json('auth')->nullable()->after('auth_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropColumn(['auth_type', 'auth']);
        });

        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn(['auth_type', 'auth']);
        });
    }
};
