<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Weapon>
 */
class WeaponFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gameCode = fake()->randomElement(['csgo', 'tf2', 'css', 'l4d2', 'dods']);
        $weaponData = $this->getWeaponData($gameCode);

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

        // Make code unique by appending timestamp and random number
        $uniqueCode = $weaponData['code'].'_'.time().'_'.fake()->randomNumber(5);

        return [
            'code' => $uniqueCode,
            'game_code' => $gameCode,
            'name' => $weaponData['name'],
            'modifier' => $weaponData['modifier'],
            'enabled' => fake()->boolean(95),
        ];
    }

    private function getWeaponData(string $gameCode): array
    {
        $weapons = [
            'csgo' => [
                ['code' => 'ak47', 'name' => 'AK-47', 'modifier' => 1.0],
                ['code' => 'awp', 'name' => 'AWP', 'modifier' => 1.5],
                ['code' => 'm4a1', 'name' => 'M4A1', 'modifier' => 1.0],
                ['code' => 'deagle', 'name' => 'Desert Eagle', 'modifier' => 1.5],
                ['code' => 'knife', 'name' => 'Knife', 'modifier' => 2.0],
                ['code' => 'glock', 'name' => 'Glock-18', 'modifier' => 0.75],
            ],
            'tf2' => [
                ['code' => 'scattergun', 'name' => 'Scattergun', 'modifier' => 1.0],
                ['code' => 'rocket_launcher', 'name' => 'Rocket Launcher', 'modifier' => 1.0],
                ['code' => 'flamethrower', 'name' => 'Flamethrower', 'modifier' => 0.8],
                ['code' => 'minigun', 'name' => 'Minigun', 'modifier' => 0.9],
            ],
        ];

        $gameWeapons = $weapons[$gameCode] ?? $weapons['csgo'];

        return fake()->randomElement($gameWeapons);
    }
}
