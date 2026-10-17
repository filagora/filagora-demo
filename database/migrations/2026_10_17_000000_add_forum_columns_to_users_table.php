<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The two columns of this app's users that the forum add-ons read: the name `@mentions` match, and
 * the moment before which everything counts as read.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 40)->nullable()->unique()->after('name');
            $table->timestamp('forum_read_baseline_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'forum_read_baseline_at']);
        });
    }
};
