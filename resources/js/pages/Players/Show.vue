<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PlayerProfile from '@/components/PlayerProfile.vue';
import type { BreadcrumbItem } from '@/types';

interface Frag {
    victim: string;
    weapon: string;
    headshot: boolean;
    time: string;
}

interface Player {
    id: number;
    name: string;
    skill: number;
    kills: number;
    deaths: number;
    kd_ratio: number;
    game_code: string;
    recent_frags: Frag[];
}

interface Props {
    player: Player;
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Players',
        href: '/players',
    },
    {
        title: 'Player Profile',
        href: '#',
    },
];
</script>

<template>
    <Head :title="`${player.name} - Player Profile`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div>
                <h1 class="mb-2 text-3xl font-bold">{{ player.name }}</h1>
                <p class="text-muted-foreground">Player Profile</p>
            </div>

            <PlayerProfile :player="player" />
        </div>
    </AppLayout>
</template>
