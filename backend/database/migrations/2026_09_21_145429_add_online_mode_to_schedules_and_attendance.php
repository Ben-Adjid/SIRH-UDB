<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->enum('teaching_mode', ['in_person', 'online', 'hybrid'])->default('in_person')->after('teaching_type_id');
            $table->string('online_link')->nullable()->after('teaching_mode');
            $table->text('online_notes')->nullable()->after('online_link');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn(['teaching_mode', 'online_link', 'online_notes']);
        });
    }
};
