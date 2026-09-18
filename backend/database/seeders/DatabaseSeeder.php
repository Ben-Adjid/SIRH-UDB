<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            UfrAndTeachingTypeSeeder::class,
            TestUsersSeeder::class,
            ScheduleSeeder::class,
            ProfCourseSeeder::class,
            UfrAccessSeeder::class,
            UfrTeacherSeeder::class,
        ]);
    }
}
