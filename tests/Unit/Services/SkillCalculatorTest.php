<?php

use App\Models\Player;
use App\Models\Weapon;
use App\Services\SkillCalculator;

test('skill increases on kill', function () {
    $killer = new Player(['skill' => 1000]);
    $victim = new Player(['skill' => 1000]);
    $weapon = new Weapon(['modifier' => 1.0]);

    $calculator = new SkillCalculator;
    $newSkill = $calculator->calculateKillSkill($killer, $victim, $weapon);

    expect($newSkill)->toBeGreaterThan(1000);
});

test('skill decreases on death', function () {
    $killer = new Player(['skill' => 1000]);
    $victim = new Player(['skill' => 1000]);

    $calculator = new SkillCalculator;
    $newSkill = $calculator->calculateDeathSkill($victim, $killer);

    expect($newSkill)->toBeLessThan(1000);
});

test('killing higher skilled player gives more skill', function () {
    $killer = new Player(['skill' => 1000]);
    $lowSkillVictim = new Player(['skill' => 800]);
    $highSkillVictim = new Player(['skill' => 1500]);
    $weapon = new Weapon(['modifier' => 1.0]);

    $calculator = new SkillCalculator;

    $killer->skill = 1000;
    $skillFromLow = $calculator->calculateKillSkill($killer, $lowSkillVictim, $weapon);

    $killer->skill = 1000;
    $skillFromHigh = $calculator->calculateKillSkill($killer, $highSkillVictim, $weapon);

    expect($skillFromHigh)->toBeGreaterThan($skillFromLow);
});

test('weapon modifier affects skill gain', function () {
    $killer = new Player(['skill' => 1000]);
    $victim = new Player(['skill' => 1000]);
    $normalWeapon = new Weapon(['modifier' => 1.0]);
    $hardWeapon = new Weapon(['modifier' => 2.0]); // Knife

    $calculator = new SkillCalculator;

    $killer->skill = 1000;
    $normalSkill = $calculator->calculateKillSkill($killer, $victim, $normalWeapon);

    $killer->skill = 1000;
    $hardSkill = $calculator->calculateKillSkill($killer, $victim, $hardWeapon);

    expect($hardSkill)->toBeGreaterThan($normalSkill);
});

test('headshot bonus is applied', function () {
    $killer = new Player(['skill' => 1000]);
    $victim = new Player(['skill' => 1000]);
    $weapon = new Weapon(['modifier' => 1.0]);

    $calculator = new SkillCalculator;

    $killer->skill = 1000;
    $normalKill = $calculator->calculateKillSkill($killer, $victim, $weapon, false);

    $killer->skill = 1000;
    $headshotKill = $calculator->calculateKillSkill($killer, $victim, $weapon, true);

    expect($headshotKill)->toBeGreaterThan($normalKill);
});

test('skill cannot go below minimum', function () {
    $victim = new Player(['skill' => 100]);
    $killer = new Player(['skill' => 2000]);

    $calculator = new SkillCalculator;

    // Die many times
    for ($i = 0; $i < 100; $i++) {
        $newSkill = $calculator->calculateDeathSkill($victim, $killer);
        $victim->skill = $newSkill;
    }

    expect($victim->skill)->toBeGreaterThanOrEqual(0);
});
