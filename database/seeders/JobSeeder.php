<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AmJob;
use App\Models\Skill;

class JobSeeder extends Seeder
{
    public function run(): void
    {
        // Create skills
        $skills = Skill::factory()->count(15)->create();

        // Create jobs and attach skills
        AmJob::factory()->count(5)->create()->each(function ($job) use ($skills) {
            $job->skills()->attach(
                $skills->random(rand(2, 4))->pluck('id')->toArray()
            );
        });
    }
}
