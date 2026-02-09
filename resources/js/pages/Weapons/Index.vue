<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import WeaponStatistics from '@/components/WeaponStatistics.vue';
import type { BreadcrumbItem } from '@/types';

interface Weapon {
    code: string;
    name: string;
    total_kills: number;
    headshots: number;
    accuracy: number;
}

interface Props {
    weapons: Weapon[];
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Weapons',
        href: '/weapons',
    },
];
</script>

<template>
    <Head title="Weapons" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div>
                <h1 class="mb-2 text-3xl font-bold">Weapon Statistics</h1>
                <p class="text-muted-foreground">
                    Detailed statistics for all weapons
                </p>
            </div>

            <WeaponStatistics :weapons="weapons" />
        </div>
    </AppLayout>
</template>
