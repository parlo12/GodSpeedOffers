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
        Schema::table('chat_box_messages', function (Blueprint $table) {
            //
            $table->string('media_type', 50)->nullable()->after('media_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_box_messages', function (Blueprint $table) {
            //
            $table->dropColumn('media_type');
        });
    }
};
