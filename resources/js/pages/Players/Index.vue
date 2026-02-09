<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PlayerRankings from '@/components/PlayerRankings.vue';
import type { BreadcrumbItem } from '@/types';

interface Player {
    id: number;
    name: string;
    skill: number;
    kills: number;
    deaths: number;
    kd_ratio: number;
}

interface PaginationLinks {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    players: {
        data: Player[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: PaginationLinks[];
    };
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Players',
        href: '/players',
    },
];

const handlePlayerSelected = (playerId: number) => {
    router.visit(`/players/${playerId}`);
};
</script>

<template>
    <Head title="Players" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div>
                <h1 class="mb-2 text-3xl font-bold">Player Rankings</h1>
                <p class="text-muted-foreground">
                    Top players ranked by skill rating
                </p>
            </div>

            <PlayerRankings
                :players="players.data"
                @player-selected="handlePlayerSelected"
            />

            <!-- Pagination -->
            <div
                v-if="players.last_page > 1"
                class="mt-4 flex items-center justify-between"
            >
                <p class="text-sm text-muted-foreground">
                    Showing {{ players.data.length }} of
                    {{ players.total }} players
                </p>
                <div class="flex gap-2">
                    <button
                        v-for="link in players.links"
                        :key="link.label"
                        :disabled="!link.url || link.active"
                        :class="[
                            'rounded px-3 py-1 text-sm',
                            link.active
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted hover:bg-muted/80',
                            !link.url && 'cursor-not-allowed opacity-50',
                        ]"
                        @click="link.url && router.visit(link.url)"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
