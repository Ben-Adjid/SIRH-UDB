<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use App\Models\Teacher;

return new class extends Migration
{
    public function up(): void
    {
        foreach (Teacher::whereNotNull('pin_code')->get() as $teacher) {
            if (!str_starts_with($teacher->pin_code, '$2y$') && !str_starts_with($teacher->pin_code, '$2b$')) {
                $teacher->update(['pin_code' => Hash::make($teacher->pin_code)]);
            }
        }
    }

    public function down(): void
    {
        // Cannot reverse PIN hashing
    }
};
