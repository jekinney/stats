<script setup lang="ts">
import { computed } from 'vue';

interface Player {
    id: number;
    name: string;
    skill: number;
    kills: number;
    deaths: number;
    kd_ratio: number;
}

interface Props {
    players: Player[];
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    loading: false,
});

const emit = defineEmits<{
    'player-selected': [playerId: number];
}>();

const sortedPlayers = computed(() => {
    return [...props.players].sort((a, b) => b.skill - a.skill);
});

const handleRowClick = (playerId: number) => {
    emit('player-selected', playerId);
};
</script>

<template>
    <div class="player-rankings">
        <div v-if="loading" class="loading-state">
            <div data-testid="loading-spinner" class="spinner" />
            <p>Loading rankings...</p>
        </div>

        <div v-else-if="sortedPlayers.length === 0" class="empty-state">
            <p>No players found</p>
        </div>

        <table v-else class="rankings-table">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Player</th>
                    <th>Skill</th>
                    <th>Kills</th>
                    <th>Deaths</th>
                    <th>K/D</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(player, index) in sortedPlayers"
                    :key="player.id"
                    class="player-row"
                    @click="handleRowClick(player.id)"
                >
                    <td>{{ index + 1 }}</td>
                    <td>{{ player.name }}</td>
                    <td>{{ player.skill.toFixed(0) }}</td>
                    <td>{{ player.kills }}</td>
                    <td>{{ player.deaths }}</td>
                    <td>{{ player.kd_ratio.toFixed(2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<style scoped>
.player-rankings {
    width: 100%;
}

.loading-state,
.empty-state {
    text-align: center;
    padding: 2rem;
}

.spinner {
    width: 3rem;
    height: 3rem;
    border: 4px solid #f3f4f6;
    border-top-color: #3b82f6;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 1rem;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.rankings-table {
    width: 100%;
    border-collapse: collapse;
}

.rankings-table th,
.rankings-table td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

.rankings-table thead th {
    font-weight: 600;
    background-color: #f9fafb;
}

.player-row {
    cursor: pointer;
    transition: background-color 0.2s;
}

.player-row:hover {
    background-color: #f3f4f6;
}
</style>
