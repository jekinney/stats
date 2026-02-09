<script setup lang="ts">
import { computed } from 'vue';

interface Kill {
    id: number;
    killer: string;
    victim: string;
    weapon: string;
    headshot: boolean;
}

interface Props {
    kills: Kill[];
    maxItems?: number;
}

const props = withDefaults(defineProps<Props>(), {
    maxItems: 50,
});

const displayedKills = computed(() => {
    return props.kills.slice(0, props.maxItems);
});
</script>

<template>
    <div class="kill-feed">
        <div v-if="kills.length === 0" class="empty-state">
            <p>No recent kills</p>
        </div>

        <div v-else class="kill-list">
            <div
                v-for="kill in displayedKills"
                :key="kill.id"
                class="kill-event"
                data-testid="kill-event"
            >
                <span class="killer">{{ kill.killer }}</span>
                <span class="weapon">{{ kill.weapon }}</span>
                <span
                    v-if="kill.headshot"
                    class="headshot-badge"
                    data-testid="headshot"
                >
                    HS
                </span>
                <span class="victim">{{ kill.victim }}</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.kill-feed {
    width: 100%;
}

.empty-state {
    text-align: center;
    padding: 2rem;
    color: #6b7280;
}

.kill-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.kill-event {
    padding: 0.75rem;
    background-color: #f9fafb;
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.875rem;
}

.killer {
    font-weight: 600;
    color: #10b981;
}

.weapon {
    padding: 0.25rem 0.5rem;
    background-color: #e5e7eb;
    border-radius: 0.25rem;
    color: #374151;
    font-size: 0.75rem;
}

.headshot-badge {
    padding: 0.25rem 0.5rem;
    background-color: #ef4444;
    color: white;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 600;
}

.victim {
    font-weight: 600;
    color: #ef4444;
}
</style>
