<script setup lang="ts">
import DownloadScriptButton from '@/components/DownloadScriptButton.vue';
import UpdateNotification from '@/components/UpdateNotification.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    backupDiskSpace?: {
        total: number;
        used: number;
        available: number;
        percentage_used: number;
        path: string;
    };
}

const props = withDefaults(defineProps<Props>(), {
    backupDiskSpace: () => ({
        total: 0,
        used: 0,
        available: 0,
        percentage_used: 0,
        path: '',
    }),
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const formatBytes = (bytes: number): string => {
    if (bytes >= 1073741824) {
        return `${(bytes / 1073741824).toFixed(2)} GB`;
    }
    if (bytes >= 1048576) {
        return `${(bytes / 1048576).toFixed(2)} MB`;
    }
    if (bytes >= 1024) {
        return `${(bytes / 1024).toFixed(2)} KB`;
    }
    return `${bytes} B`;
};

const totalFormatted = computed(() => formatBytes(props.backupDiskSpace.total));
const usedFormatted = computed(() => formatBytes(props.backupDiskSpace.used));
const availableFormatted = computed(() =>
    formatBytes(props.backupDiskSpace.available),
);

const percentageColor = computed(() => {
    const percentage = props.backupDiskSpace.percentage_used;
    if (percentage >= 90) {
        return 'bg-red-500';
    }
    if (percentage >= 75) {
        return 'bg-yellow-500';
    }
    return 'bg-green-500';
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <UpdateNotification />

            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <!-- Backup Storage Card -->
                <div
                    class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-sidebar p-6 dark:border-sidebar-border"
                >
                    <div class="flex flex-col gap-4">
                        <div>
                            <h3
                                class="text-sm font-medium text-sidebar-foreground/70"
                            >
                                Storage
                            </h3>
                            <p class="mt-1 text-xs text-sidebar-foreground/50">
                                {{ backupDiskSpace.path }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <div
                                class="flex items-center justify-between text-sm"
                            >
                                <span class="text-sidebar-foreground/70"
                                    >Used</span
                                >
                                <span
                                    class="font-medium text-sidebar-foreground"
                                >
                                    {{ usedFormatted }}
                                </span>
                            </div>

                            <div
                                class="flex items-center justify-between text-sm"
                            >
                                <span class="text-sidebar-foreground/70"
                                    >Available</span
                                >
                                <span
                                    class="font-medium text-sidebar-foreground"
                                >
                                    {{ availableFormatted }}
                                </span>
                            </div>

                            <div
                                class="flex items-center justify-between text-sm"
                            >
                                <span class="text-sidebar-foreground/70"
                                    >Total</span
                                >
                                <span
                                    class="font-medium text-sidebar-foreground"
                                >
                                    {{ totalFormatted }}
                                </span>
                            </div>

                            <div class="mt-4">
                                <div
                                    class="mb-1 flex items-center justify-between text-xs"
                                >
                                    <span class="text-sidebar-foreground/70">
                                        {{
                                            backupDiskSpace.percentage_used.toFixed(
                                                1,
                                            )
                                        }}% Used
                                    </span>
                                </div>
                                <div
                                    class="h-2 w-full overflow-hidden rounded-full bg-sidebar-foreground/10"
                                >
                                    <div
                                        :class="percentageColor"
                                        class="h-full transition-all duration-300"
                                        :style="{
                                            width: `${Math.min(backupDiskSpace.percentage_used, 100)}%`,
                                        }"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CLI Script Card -->
                <div
                    class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-sidebar p-6 dark:border-sidebar-border"
                >
                    <div class="flex h-full flex-col gap-4">
                        <div>
                            <h3
                                class="text-sm font-medium text-sidebar-foreground/70"
                            >
                                CLI Script
                            </h3>
                            <p class="mt-1 text-xs text-sidebar-foreground/50">
                                Download the command-line script to manage
                                backups from your terminal
                            </p>
                        </div>

                        <div class="mt-auto">
                            <DownloadScriptButton />
                        </div>
                    </div>
                </div>

                <!-- Placeholder card for future features -->
                <div
                    class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
                >
                    <div class="flex h-full items-center justify-center">
                        <p class="text-sm text-sidebar-foreground/50">
                            Coming soon
                        </p>
                    </div>
                </div>
            </div>
            <div
                class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border"
            >
                <div class="flex h-full items-center justify-center">
                    <p class="text-sm text-sidebar-foreground/50">
                        More content coming soon
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
