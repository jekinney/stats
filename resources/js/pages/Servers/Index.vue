<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import ServerBrowser from '@/components/ServerBrowser.vue';
import type { BreadcrumbItem } from '@/types';

interface Server {
    id: number;
    name: string;
    address: string;
    port: number;
    game_code: string;
    active: boolean;
    total_frags: number;
}

interface Props {
    servers: Server[];
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Servers',
        href: '/servers',
    },
];
</script>

<template>
    <Head title="Servers" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div>
                <h1 class="mb-2 text-3xl font-bold">Game Servers</h1>
                <p class="text-muted-foreground">
                    Browse all registered game servers
                </p>
            </div>

            <ServerBrowser :servers="servers" />
        </div>
    </AppLayout>
</template>
