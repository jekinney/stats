<script setup lang="ts">
interface TopPlayer {
    name: string;
    skill: number;
    kills: number;
}

interface RecentKill {
    id: number;
    killer: string;
    victim: string;
    weapon: string;
    headshot: boolean;
}

interface Stats {
    total_players: number;
    active_servers: number;
    total_kills: number;
    kills_last_hour: number;
    top_player: TopPlayer;
    recent_kills: RecentKill[];
}

interface Props {
    stats: Stats | null;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    loading: false,
});
</script>

<template>
    <div class="live-stats">
        <div v-if="loading" class="loading" data-testid="loading">
            <p>Loading...</p>
        </div>

        <div v-else-if="stats" class="stats-container">
            <div class="stat-cards">
                <div class="stat-card" data-testid="stat-card">
                    <div class="stat-label">Total Players</div>
                    <div class="stat-value">
                        {{ stats.total_players.toLocaleString() }}
                    </div>
                </div>

                <div class="stat-card" data-testid="stat-card">
                    <div class="stat-label">Active Servers</div>
                    <div class="stat-value">
                        {{ stats.active_servers.toLocaleString() }}
                    </div>
                </div>

                <div class="stat-card" data-testid="stat-card">
                    <div class="stat-label">Total Kills</div>
                    <div class="stat-value">
                        {{ stats.total_kills.toLocaleString() }}
                    </div>
                </div>

                <div class="stat-card" data-testid="stat-card">
                    <div class="stat-label">Kills (Last Hour)</div>
                    <div class="stat-value">
                        {{ stats.kills_last_hour.toLocaleString() }}
                    </div>
                </div>
            </div>

            <div class="top-player-section">
                <h3 class="section-title">Top Player</h3>
                <div class="top-player-card">
                    <div class="player-name">{{ stats.top_player.name }}</div>
                    <div class="player-stats">
                        <div class="player-stat">
                            <span class="label">Skill:</span>
                            <span class="value">
                                {{ stats.top_player.skill.toLocaleString() }}
                            </span>
                        </div>
                        <div class="player-stat">
                            <span class="label">Kills:</span>
                            <span class="value">
                                {{ stats.top_player.kills.toLocaleString() }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="recent-kills-section">
                <h3 class="section-title">Recent Kills</h3>
                <div class="recent-kills" data-testid="recent-kills">
                    <div
                        v-for="kill in stats.recent_kills"
                        :key="kill.id"
                        class="kill-item"
                    >
                        <span class="killer">{{ kill.killer }}</span>
                        <span class="weapon">{{ kill.weapon }}</span>
                        <span class="victim">{{ kill.victim }}</span>
                        <span v-if="kill.headshot" class="headshot-badge"
                            >HS</span
                        >
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="empty-state">
            <p>No statistics available</p>
        </div>
    </div>
</template>

<style scoped>
.live-stats {
    width: 100%;
}

.loading {
    text-align: center;
    padding: 3rem;
    color: #6b7280;
    font-size: 1.125rem;
}

.empty-state {
    text-align: center;
    padding: 3rem;
    color: #6b7280;
}

.stats-container {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.stat-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.stat-card {
    padding: 1.5rem;
    background-color: #f9fafb;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
}

.stat-label {
    font-size: 0.875rem;
    color: #6b7280;
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: #111827;
}

.section-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #111827;
    margin: 0 0 1rem 0;
}

.top-player-section {
    margin-top: 1rem;
}

.top-player-card {
    padding: 1.25rem;
    background-color: #eff6ff;
    border-radius: 0.5rem;
    border: 1px solid #3b82f6;
}

.player-name {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e40af;
    margin-bottom: 0.75rem;
}

.player-stats {
    display: flex;
    gap: 2rem;
}

.player-stat {
    display: flex;
    gap: 0.5rem;
    align-items: baseline;
}

.player-stat .label {
    font-size: 0.875rem;
    color: #6b7280;
    font-weight: 500;
}

.player-stat .value {
    font-size: 1.125rem;
    font-weight: 600;
    color: #111827;
}

.recent-kills-section {
    margin-top: 1rem;
}

.recent-kills {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.kill-item {
    padding: 0.75rem;
    background-color: #f9fafb;
    border-radius: 0.375rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.875rem;
}

.killer {
    font-weight: 600;
    color: #059669;
}

.weapon {
    color: #6b7280;
    font-style: italic;
}

.victim {
    font-weight: 600;
    color: #dc2626;
}

.headshot-badge {
    margin-left: auto;
    padding: 0.125rem 0.5rem;
    background-color: #fbbf24;
    color: #78350f;
    border-radius: 0.25rem;
    font-weight: 600;
    font-size: 0.75rem;
}
</style>
