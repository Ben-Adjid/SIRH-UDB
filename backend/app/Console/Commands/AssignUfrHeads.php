<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Ufr;

class AssignUfrHeads extends Command {
    protected $signature = 'ufr:assign-heads {--chef-user-id= : User ID of Chef UFR} {--all-ufrs : Assign all UFRs to this Chef UFR}';
    protected $description = 'Assign Chef UFR as head of UFR(s)';

    public function handle() {
        $chefUserId = $this->option('chef-user-id');
        $assignAllUfrs = $this->option('all-ufrs');

        if (!$chefUserId) {
            // List available Chef UFR users
            $chefUsers = User::whereHas('roles', fn($q) => $q->where('name', 'academic_manager'))->get();

            if ($chefUsers->isEmpty()) {
                $this->error('❌ No Chef UFR (academic_manager) users found!');
                return 1;
            }

            $this->info('📋 Available Chef UFR users:');
            foreach ($chefUsers as $user) {
                $this->line("  ID: {$user->id}, Name: {$user->name}, Email: {$user->email}");
            }

            $chefUserId = $this->ask('Enter Chef UFR user ID');
        }

        $chefUser = User::find($chefUserId);
        if (!$chefUser || !$chefUser->hasRole('academic_manager')) {
            $this->error("❌ User {$chefUserId} not found or not Chef UFR!");
            return 1;
        }

        $this->info("📌 Assigning UFRs to: {$chefUser->name} (ID: {$chefUser->id})");

        if ($assignAllUfrs) {
            // Assign all UFRs
            Ufr::query()->update(['head_id' => $chefUser->id]);
            $count = Ufr::count();
            $this->info("✅ Assigned {$count} UFR(s) to Chef UFR!");
        } else {
            // List UFRs and let user select
            $ufrs = Ufr::select('id', 'name', 'head_id')->get();

            $this->info('📋 Available UFRs:');
            foreach ($ufrs as $ufr) {
                $headName = $ufr->head_id ? User::find($ufr->head_id)?->name : '(none)';
                $this->line("  ID: {$ufr->id}, Name: {$ufr->name}, Current Head: {$headName}");
            }

            $ufrId = $this->ask('Enter UFR ID to assign (or comma-separated list: 1,2,3)');
            $ufrIds = array_map('trim', explode(',', $ufrId));

            $updated = Ufr::whereIn('id', $ufrIds)->update(['head_id' => $chefUser->id]);
            $this->info("✅ Assigned {$updated} UFR(s)!");
        }

        // Verify
        $assigned = Ufr::where('head_id', $chefUser->id)->pluck('name');
        $this->info('🎯 Chef UFR now heads: ' . $assigned->implode(', '));

        return 0;
    }
}
