<?php

namespace App\Services;

use App\Models\Player;
use App\Models\Weapon;

class SkillCalculator
{
    /**
     * K-factor for skill calculation (determines how quickly ratings change)
     */
    private const K_FACTOR = 32;

    /**
     * Headshot bonus multiplier
     */
    private const HEADSHOT_BONUS = 1.25;

    /**
     * Minimum skill value
     */
    private const MIN_SKILL = 0;

    /**
     * Calculate new skill for a killer
     */
    public function calculateKillSkill(Player $killer, Player $victim, Weapon $weapon, bool $headshot = false): float
    {
        // Calculate expected score using ELO formula
        $expectedScore = $this->calculateExpectedScore($killer->skill, $victim->skill);

        // Base skill gain (actual score is 1 for a kill)
        $skillChange = self::K_FACTOR * (1 - $expectedScore);

        // Apply weapon modifier (harder weapons give more skill)
        $skillChange *= $weapon->modifier;

        // Apply headshot bonus
        if ($headshot) {
            $skillChange *= self::HEADSHOT_BONUS;
        }

        // Calculate new skill
        $newSkill = $killer->skill + $skillChange;

        return max($newSkill, self::MIN_SKILL);
    }

    /**
     * Calculate new skill for a victim who died
     */
    public function calculateDeathSkill(Player $victim, Player $killer): float
    {
        // Calculate expected score using ELO formula
        $expectedScore = $this->calculateExpectedScore($victim->skill, $killer->skill);

        // Skill loss (actual score is 0 for a death)
        $skillChange = self::K_FACTOR * (0 - $expectedScore);

        // Calculate new skill
        $newSkill = $victim->skill + $skillChange;

        return max($newSkill, self::MIN_SKILL);
    }

    /**
     * Calculate expected score using ELO formula
     */
    private function calculateExpectedScore(float $playerSkill, float $opponentSkill): float
    {
        return 1 / (1 + pow(10, ($opponentSkill - $playerSkill) / 400));
    }
}
