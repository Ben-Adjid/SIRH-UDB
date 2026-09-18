<?php

namespace Tests\Unit;

use App\Models\Schedule;
use App\Models\Teacher;
use App\Models\User;
use App\Models\Ufr;
use App\Models\Subject;
use App\Models\Classroom;
use App\Models\AcademicYear;
use App\Services\ScheduleConflictService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleConflictServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $service;
    protected $teacher;
    protected $subject;
    protected $classroom;
    protected $academicYear;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleAndPermissionSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('teacher');

        $ufr = Ufr::create(['name' => 'Test UFR', 'code' => 'TEST']);

        $this->teacher = Teacher::create([
            'user_id' => $user->id,
            'ufr_id' => $ufr->id,
            'employee_id' => 'EMP-TEST-001',
            'contract_type' => 'vacataire',
            'hourly_rate' => 5000,
            'volume_hours' => 200,
            'hire_date' => '2025-01-01',
            'status' => 'active',
        ]);

        $this->subject = Subject::create([
            'name' => 'Test Subject',
            'code' => 'TST-SUB',
            'level' => 'L1',
            'credit_hours' => 3,
        ]);

        $this->classroom = Classroom::create([
            'name' => 'Test Room',
            'code' => 'TST-R01',
            'capacity' => 30,
            'type' => 'classroom',
        ]);

        $this->academicYear = AcademicYear::firstOrCreate(
            ['start_year' => 2025, 'end_year' => 2026],
            [
                'name' => '2025-2026',
                'is_active' => true,
            ]
        );

        $this->service = new ScheduleConflictService();
    }

    public function test_no_conflict_for_non_overlapping_schedules()
    {
        Schedule::create([
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
            'academic_year_id' => $this->academicYear->id,
            'level' => 'L1',
            'semester' => 'S1',
            'start_date' => '2025-10-01',
            'end_date' => '2026-01-31',
            'day_of_week' => 'Lundi',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'status' => 'scheduled',
            'type' => 'course',
            'is_archived' => false,
        ]);

        $hasConflict = $this->service->hasConflict(
            $this->teacher->id,
            'Lundi',
            '10:00',
            '12:00',
        );

        $this->assertFalse($hasConflict);
    }

    public function test_conflict_detected_for_overlapping_schedules()
    {
        Schedule::create([
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
            'academic_year_id' => $this->academicYear->id,
            'level' => 'L1',
            'semester' => 'S1',
            'start_date' => '2025-10-01',
            'end_date' => '2026-01-31',
            'day_of_week' => 'Mardi',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'status' => 'scheduled',
            'type' => 'course',
            'is_archived' => false,
        ]);

        $hasConflict = $this->service->hasConflict(
            $this->teacher->id,
            'Mardi',
            '10:00',
            '12:00',
        );

        $this->assertTrue($hasConflict);
    }

    public function test_same_teacher_same_day_overlapping_times()
    {
        Schedule::create([
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
            'academic_year_id' => $this->academicYear->id,
            'level' => 'L1',
            'semester' => 'S1',
            'start_date' => '2025-10-01',
            'end_date' => '2026-01-31',
            'day_of_week' => 'Mercredi',
            'start_time' => '14:00',
            'end_time' => '16:00',
            'status' => 'scheduled',
            'type' => 'course',
            'is_archived' => false,
        ]);

        Schedule::create([
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
            'academic_year_id' => $this->academicYear->id,
            'level' => 'L1',
            'semester' => 'S1',
            'start_date' => '2025-10-01',
            'end_date' => '2026-01-31',
            'day_of_week' => 'Mercredi',
            'start_time' => '15:00',
            'end_time' => '17:00',
            'status' => 'scheduled',
            'type' => 'course',
            'is_archived' => false,
        ]);

        $hasConflict = $this->service->hasConflict(
            $this->teacher->id,
            'Mercredi',
            '15:30',
            '16:30',
        );

        $this->assertTrue($hasConflict);
    }

    public function test_different_teacher_no_conflict()
    {
        $user2 = User::factory()->create();
        $user2->assignRole('teacher');

        $teacher2 = Teacher::create([
            'user_id' => $user2->id,
            'ufr_id' => $this->teacher->ufr_id,
            'employee_id' => 'EMP-TEST-002',
            'contract_type' => 'vacataire',
            'hourly_rate' => 5000,
            'volume_hours' => 200,
            'hire_date' => '2025-01-01',
            'status' => 'active',
        ]);

        Schedule::create([
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
            'academic_year_id' => $this->academicYear->id,
            'level' => 'L1',
            'semester' => 'S1',
            'start_date' => '2025-10-01',
            'end_date' => '2026-01-31',
            'day_of_week' => 'Jeudi',
            'start_time' => '08:00',
            'end_time' => '11:00',
            'status' => 'scheduled',
            'type' => 'course',
            'is_archived' => false,
        ]);

        $hasConflict = $this->service->hasConflict(
            $teacher2->id,
            'Jeudi',
            '08:00',
            '11:00',
        );

        $this->assertFalse($hasConflict);
    }
}
