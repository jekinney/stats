<script setup lang="ts">
import { computed } from 'vue';

interface Weapon {
    code: string;
    name: string;
    kills: number;
    deaths: number;
    accuracy: number;
    headshot_percentage: number;
}

interface Props {
    weapons: Weapon[];
}

const props = defineProps<Props>();

const sortedWeapons = computed(() => {
    return [...props.weapons].sort((a, b) => b.kills - a.kills);
});
</script>

<template>
    <div class="weapon-statistics">
        <div v-if="weapons.length === 0" class="empty-state">
            <p>No weapon statistics</p>
        </div>

        <table v-else class="stats-table">
            <thead>
                <tr>
                    <th>Weapon</th>
                    <th>Kills</th>
                    <th>Deaths</th>
                    <th>Accuracy</th>
                    <th>Headshot %</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="weapon in sortedWeapons" :key="weapon.code">
                    <td class="weapon-name">{{ weapon.name }}</td>
                    <td>{{ weapon.kills.toLocaleString() }}</td>
                    <td>{{ weapon.deaths.toLocaleString() }}</td>
                    <td>{{ weapon.accuracy.toFixed(1) }}%</td>
                    <td>{{ weapon.headshot_percentage.toFixed(1) }}%</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<style scoped>
.weapon-statistics {
    width: 100%;
}

.empty-state {
    text-align: center;
    padding: 2rem;
    color: #6b7280;
}

.stats-table {
    width: 100%;
    border-collapse: collapse;
}

.stats-table th,
.stats-table td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

.stats-table thead th {
    font-weight: 600;
    background-color: #f9fafb;
    color: #374151;
}

.stats-table tbody tr:hover {
    background-color: #f3f4f6;
}

.weapon-name {
    font-weight: 500;
}
</style>
