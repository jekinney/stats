<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Player>
 */
class PlayerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gameCode = fake()->randomElement(['csgo', 'tf2', 'css', 'l4d2', 'dods']);

        return [
            'game_code' => $gameCode,
            'steam_id' => 'STEAM_1:'.fake()->numberBetween(0, 1).':'.fake()->numberBetween(10000, 99999999),
            'last_name' => fake()->userName(),
            'skill' => fake()->randomFloat(2, 500, 3000),
            'kills' => fake()->numberBetween(0, 10000),
            'deaths' => fake()->numberBetween(0, 10000),
            'headshots' => fake()->numberBetween(0, 5000),
            'hide_ranking' => fake()->boolean(10), // 10% chance of being hidden
            'connection_time' => fake()->numberBetween(0, 1000000),
            'last_event' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (\App\Models\Player $player) {
            // Ensure the game exists before creating the player
            \App\Models\Game::firstOrCreate(
                ['code' => $player->game_code],
                [
                    'name' => match ($player->game_code) {
                        'csgo' => 'Counter-Strike: Global Offensive',
                        'tf2' => 'Team Fortress 2',
                        'css' => 'Counter-Strike: Source',
                        'l4d2' => 'Left 4 Dead 2',
                        'dods' => 'Day of Defeat: Source',
                        default => $player->game_code,
                    },
                    'enabled' => true,
                ]
            );
        });
    }
}
