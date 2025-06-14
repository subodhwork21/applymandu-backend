<?php
namespace Database\Factories;

use App\Models\AmJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AmJobFactory extends Factory
{
    protected $model = AmJob::class;

    public function definition(): array
    {
        $title = $this->faker->jobTitle;
        $department = $this->faker->randomElement(['Engineering', 'Marketing', 'Sales', 'Design']);
        $titleSlug = Str::slug($title);
        $departmentSlug = Str::slug($department);
        $salaryMin = $this->faker->randomFloat(2, 20000, 60000);
        $salaryMax = $this->faker->randomFloat(2, 60001, 120000);

        // Find an employer user properly
        $employer = User::whereHas('roles', function($query) {
            $query->where('name', 'employer');
        })->inRandomOrder()->first();

        return [
            'title' => $title,
            'location' => $this->faker->city,
            'description' => $this->faker->paragraph(4),
            'location_type' => $this->faker->randomElement(['on-site', 'remote', 'hybrid']),
            'experience_level' => $this->faker->randomElement(['Entry Level', 'Mid Level', 'Senior Level']),
            'employment_type' => $this->faker->randomElement(['Full-time', 'Part-time', 'Contract', 'Remote', 'Internship']),
            'salary_min' => $salaryMin,
            'salary_max' => $salaryMax,
            'requirements' => json_encode($this->faker->sentences(3)),
            'responsibilities' => json_encode($this->faker->sentences(3)),
            'benefits' => json_encode($this->faker->sentences(3)),
            'posted_date' => now(),
            'employer_id' => $employer ? $employer->id : 1,
            'department' => $department,
            'application_deadline' => now()->addDays(30),
            'slug' => $titleSlug . '-' . $departmentSlug . '-' . Str::random(5), // Added random string to avoid duplicates
            'status' => 1,
        ];
    }
}
