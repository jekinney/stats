<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Server>
 */
class ServerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gameCode = fake()->randomElement(['csgo', 'tf2', 'css', 'l4d2', 'dods']);

        // Ensure the game exists
        \App\Models\Game::firstOrCreate(
            ['code' => $gameCode],
            [
                'name' => match ($gameCode) {
                    'csgo' => 'Counter-Strike: Global Offensive',
                    'tf2' => 'Team Fortress 2',
                    'css' => 'Counter-Strike: Source',
                    'l4d2' => 'Left 4 Dead 2',
                    'dods' => 'Day of Defeat: Source',
                    default => $gameCode,
                },
                'enabled' => true,
            ]
        );

        return [
            'game_code' => $gameCode,
            'name' => fake()->company().' '.fake()->randomElement(['Server', 'Gaming', 'Arena']).' #'.fake()->numberBetween(1, 999),
            'address' => fake()->localIpv4(),
            'port' => fake()->numberBetween(27015, 27050),
            'public_address' => fake()->boolean(80) ? fake()->ipv4() : null,
            'enabled' => fake()->boolean(90),
            'map' => fake()->randomElement(['de_dust2', 'de_inferno', 'de_mirage', 'de_nuke', 'de_cache', 'de_overpass']),
            'last_activity' => fake()->dateTimeBetween('-1 hour', 'now'),
        ];
    }
}
