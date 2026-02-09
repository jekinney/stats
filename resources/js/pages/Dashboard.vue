<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import LiveStats from '@/components/LiveStats.vue';
import KillFeed from '@/components/KillFeed.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';

interface TopPlayer {
    id: number;
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

interface DashboardStats {
    total_players: number;
    active_servers: number;
    total_kills: number;
    kills_last_hour: number;
    top_player: TopPlayer;
    recent_kills: RecentKill[];
}

defineProps<{
    stats: DashboardStats;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Live Stats Component -->
            <div>
                <h2 class="mb-4 text-2xl font-bold">Live Statistics</h2>
                <LiveStats :stats="stats" />
            </div>

            <!-- Recent Kill Feed -->
            <div>
                <h2 class="mb-4 text-2xl font-bold">Recent Kills</h2>
                <KillFeed :kills="stats.recent_kills" />
            </div>
        </div>
    </AppLayout>
</template>
