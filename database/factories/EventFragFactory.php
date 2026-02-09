<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EventFrag>
 */
class EventFragFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gameCode = fake()->randomElement(['csgo', 'tf2', 'css', 'l4d2', 'dods']);
        $weaponCode = $this->getWeaponCode($gameCode);

        // Ensure weapon exists
        \App\Models\Weapon::firstOrCreate(
            ['code' => $weaponCode],
            [
                'game_code' => $gameCode,
                'name' => $weaponCode,
                'modifier' => 1.0,
                'enabled' => true,
            ]
        );

        return [
            'killer_id' => \App\Models\Player::factory(),
            'victim_id' => \App\Models\Player::factory(),
            'weapon_code' => $weaponCode,
            'headshot' => fake()->boolean(30), // 30% headshot rate
            'map' => fake()->randomElement(['de_dust2', 'de_inferno', 'de_mirage', 'de_nuke', 'de_cache']),
            'event_time' => fake()->dateTimeBetween('-7 days', 'now'),
            'pos_x' => fake()->numberBetween(-8000, 8000),
            'pos_y' => fake()->numberBetween(-8000, 8000),
            'pos_z' => fake()->numberBetween(-500, 500),
        ];
    }

    private function getWeaponCode(string $gameCode): string
    {
        $weapons = [
            'csgo' => ['ak47', 'awp', 'm4a1', 'deagle', 'knife', 'glock'],
            'tf2' => ['scattergun', 'rocket_launcher', 'flamethrower', 'minigun'],
        ];

        $gameWeapons = $weapons[$gameCode] ?? $weapons['csgo'];

        return fake()->randomElement($gameWeapons);
    }
}
