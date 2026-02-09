<script setup lang="ts">
interface WeaponStat {
    weapon: string;
    kills: number;
    deaths: number;
}

interface RecentKill {
    id: number;
    victim: string;
    weapon: string;
    headshot: boolean;
}

interface Player {
    id: number;
    name: string;
    skill: number;
    kills: number;
    deaths: number;
    kd_ratio: number;
    headshot_percentage: number;
    favorite_weapon: string;
    weapon_stats: WeaponStat[];
    recent_kills: RecentKill[];
}

interface Props {
    player: Player;
}

defineProps<Props>();
</script>

<template>
    <div class="player-profile">
        <div class="profile-header">
            <h1 class="player-name">{{ player.name }}</h1>
            <div class="favorite-weapon-badge" data-testid="favorite-weapon">
                Favorite: {{ player.favorite_weapon }}
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Skill</div>
                <div class="stat-value">{{ player.skill.toFixed(0) }}</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">K/D Ratio</div>
                <div class="stat-value">{{ player.kd_ratio.toFixed(2) }}</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Kills</div>
                <div class="stat-value">{{ player.kills }}</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Deaths</div>
                <div class="stat-value">{{ player.deaths }}</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Headshot %</div>
                <div class="stat-value">{{ player.headshot_percentage }}%</div>
            </div>
        </div>

        <div class="weapon-stats-section">
            <h2>Weapon Statistics</h2>
            <table class="weapon-stats" data-testid="weapon-stats">
                <thead>
                    <tr>
                        <th>Weapon</th>
                        <th>Kills</th>
                        <th>Deaths</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="stat in player.weapon_stats" :key="stat.weapon">
                        <td>{{ stat.weapon }}</td>
                        <td>{{ stat.kills }}</td>
                        <td>{{ stat.deaths }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="recent-kills-section">
            <h2>Recent Kills</h2>
            <div class="kill-feed" data-testid="recent-kills">
                <div
                    v-for="kill in player.recent_kills"
                    :key="kill.id"
                    class="kill-item"
                >
                    <span class="victim">{{ kill.victim }}</span>
                    <span class="weapon">{{ kill.weapon }}</span>
                    <span
                        v-if="kill.headshot"
                        class="headshot-icon"
                        data-testid="headshot-icon"
                    >
                        HS
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.player-profile {
    padding: 2rem;
}

.profile-header {
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.player-name {
    font-size: 2rem;
    font-weight: bold;
    margin: 0;
}

.favorite-weapon-badge {
    padding: 0.5rem 1rem;
    background-color: #3b82f6;
    color: white;
    border-radius: 0.5rem;
    font-size: 0.875rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    padding: 1rem;
    background-color: #f9fafb;
    border-radius: 0.5rem;
    text-align: center;
}

.stat-label {
    font-size: 0.875rem;
    color: #6b7280;
    margin-bottom: 0.5rem;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: bold;
    color: #111827;
}

.weapon-stats-section,
.recent-kills-section {
    margin-bottom: 2rem;
}

h2 {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 1rem;
}

.weapon-stats {
    width: 100%;
    border-collapse: collapse;
}

.weapon-stats th,
.weapon-stats td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

.weapon-stats thead th {
    font-weight: 600;
    background-color: #f9fafb;
}

.kill-feed {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.kill-item {
    padding: 0.75rem;
    background-color: #f9fafb;
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.victim {
    font-weight: 600;
}

.weapon {
    color: #6b7280;
}

.headshot-icon {
    padding: 0.25rem 0.5rem;
    background-color: #ef4444;
    color: white;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 600;
}
</style>
