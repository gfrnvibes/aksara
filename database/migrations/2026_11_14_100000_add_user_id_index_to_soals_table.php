<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // A teacher's bank lists their own questions too; PostgreSQL does not index foreign keys by itself.
    public function up(): void
    {
        Schema::table('soals', fn (Blueprint $table) => $table->index('user_id'));
    }

    public function down(): void
    {
        Schema::table('soals', fn (Blueprint $table) => $table->dropIndex(['user_id']));
    }
};
