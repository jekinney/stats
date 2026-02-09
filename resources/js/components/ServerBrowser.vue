<script setup lang="ts">
import { computed } from 'vue';

interface Server {
    id: number;
    name: string;
    game: string;
    map: string;
    players: number;
    max_players: number;
    address: string;
    online: boolean;
}

interface Props {
    servers: Server[];
    showOffline?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showOffline: true,
});

const filteredServers = computed(() => {
    if (props.showOffline) {
        return props.servers;
    }
    return props.servers.filter((server) => server.online);
});
</script>

<template>
    <div class="server-browser">
        <div v-if="filteredServers.length === 0" class="empty-state">
            <p>No servers available</p>
        </div>

        <div v-else class="server-list">
            <div
                v-for="server in filteredServers"
                :key="server.id"
                class="server-item"
                data-testid="server-item"
            >
                <div class="server-header">
                    <div class="status-indicator">
                        <span
                            v-if="server.online"
                            class="online-dot"
                            data-testid="online-indicator"
                        />
                        <span
                            v-else
                            class="offline-dot"
                            data-testid="offline-indicator"
                        />
                    </div>
                    <h3 class="server-name">{{ server.name }}</h3>
                </div>

                <div class="server-details">
                    <div class="detail-item">
                        <span class="label">Map:</span>
                        <span class="value">{{ server.map }}</span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Players:</span>
                        <span class="value">
                            {{ server.players }}/{{ server.max_players }}
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Address:</span>
                        <span class="value address">{{ server.address }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.server-browser {
    width: 100%;
}

.empty-state {
    text-align: center;
    padding: 2rem;
    color: #6b7280;
}

.server-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.server-item {
    padding: 1rem;
    background-color: #f9fafb;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
}

.server-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}

.status-indicator {
    display: flex;
    align-items: center;
}

.online-dot,
.offline-dot {
    width: 0.75rem;
    height: 0.75rem;
    border-radius: 50%;
}

.online-dot {
    background-color: #10b981;
}

.offline-dot {
    background-color: #ef4444;
}

.server-name {
    font-size: 1.125rem;
    font-weight: 600;
    margin: 0;
    color: #111827;
}

.server-details {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.detail-item {
    display: flex;
    gap: 0.5rem;
    font-size: 0.875rem;
}

.label {
    color: #6b7280;
    font-weight: 500;
}

.value {
    color: #111827;
}

.address {
    font-family: monospace;
    font-size: 0.8125rem;
}
</style>
