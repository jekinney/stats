<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Game>
 */
class GameFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->randomElement(['csgo', 'tf2', 'css', 'l4d2', 'dods', 'hl2dm']),
            'name' => fake()->randomElement([
                'Counter-Strike: Global Offensive',
                'Team Fortress 2',
                'Counter-Strike: Source',
                'Left 4 Dead 2',
                'Day of Defeat: Source',
                'Half-Life 2: Deathmatch',
            ]),
            'enabled' => true,
        ];
    }
}
