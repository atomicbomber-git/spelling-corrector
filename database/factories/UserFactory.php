<?php

namespace Database\Factories;

use App\DokumenWord;
use App\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => $this->faker->firstName . ' ' . $this->faker->lastName,
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'level' => $this->faker->randomElement(array_keys(User::LEVELS)),
        ];
    }

    public function mahasiswa()
    {
        return $this->state([
            'nomor_induk' => $this->faker->unique()->nik(),
            'level' => User::LEVEL_MAHASISWA,
        ]);
    }
}