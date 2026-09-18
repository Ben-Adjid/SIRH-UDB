<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code')->unique();
            $table->integer('capacity')->default(50);
            $table->string('location')->nullable();
            $table->enum('type', ['classroom', 'lab', 'amphitheater'])->default('classroom');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('classrooms'); }
};
