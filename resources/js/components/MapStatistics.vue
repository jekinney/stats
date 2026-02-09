<script setup lang="ts">
import { computed } from 'vue';

interface Map {
    code: string;
    name: string;
    total_rounds: number;
    ct_wins: number;
    t_wins: number;
    average_round_time: number;
    popularity: number;
}

interface Props {
    maps: Map[];
}

const props = defineProps<Props>();

const sortedMaps = computed(() => {
    return [...props.maps].sort((a, b) => b.popularity - a.popularity);
});

const calculateWinPercentage = (wins: number, totalRounds: number): string => {
    if (totalRounds === 0) return '0';
    return ((wins / totalRounds) * 100).toFixed(0);
};
</script>

<template>
    <div class="map-statistics">
        <div v-if="sortedMaps.length === 0" class="empty-state">
            <p>No map statistics available</p>
        </div>

        <div v-else class="map-list">
            <div
                v-for="map in sortedMaps"
                :key="map.code"
                class="map-item"
                data-testid="map-item"
            >
                <div class="map-header">
                    <h3 class="map-name">{{ map.name }}</h3>
                    <span class="popularity-badge">
                        {{ map.popularity.toFixed(1) }}% popularity
                    </span>
                </div>

                <div class="map-stats">
                    <div class="stat-group">
                        <span class="stat-label">Total Rounds:</span>
                        <span class="stat-value">
                            {{ map.total_rounds.toLocaleString() }}
                        </span>
                    </div>

                    <div class="stat-group">
                        <span class="stat-label">CT Win Rate:</span>
                        <span class="stat-value">
                            {{
                                calculateWinPercentage(
                                    map.ct_wins,
                                    map.total_rounds,
                                )
                            }}%
                        </span>
                    </div>

                    <div class="stat-group">
                        <span class="stat-label">T Win Rate:</span>
                        <span class="stat-value">
                            {{
                                calculateWinPercentage(
                                    map.t_wins,
                                    map.total_rounds,
                                )
                            }}%
                        </span>
                    </div>

                    <div class="stat-group">
                        <span class="stat-label">Avg Round Time:</span>
                        <span class="stat-value"
                            >{{ map.average_round_time }}s</span
                        >
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.map-statistics {
    width: 100%;
}

.empty-state {
    text-align: center;
    padding: 2rem;
    color: #6b7280;
}

.map-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.map-item {
    padding: 1.25rem;
    background-color: #f9fafb;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
}

.map-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.map-name {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0;
    color: #111827;
}

.popularity-badge {
    padding: 0.25rem 0.75rem;
    background-color: #3b82f6;
    color: white;
    border-radius: 1rem;
    font-size: 0.875rem;
    font-weight: 500;
}

.map-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 0.75rem;
}

.stat-group {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.stat-label {
    font-size: 0.875rem;
    color: #6b7280;
    font-weight: 500;
}

.stat-value {
    font-size: 1.125rem;
    font-weight: 600;
    color: #111827;
}
</style>
