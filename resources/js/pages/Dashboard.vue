<script setup lang="ts">
import DownloadScriptButton from '@/components/DownloadScriptButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import { ChevronDown, HardDrive } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Backup {
    id: number;
    filename: string;
    size: number;
    size_bytes: number;
    created_at: string;
    app: {
        id: number;
        name: string;
    };
}

interface App {
    id: number;
    name: string;
    created_at: string;
}

interface Props {
    backupDiskSpace?: {
        total: number;
        used: number;
        available: number;
        percentage_used: number;
        path: string;
    };
    latestBackups?: Backup[];
    latestApps?: App[];
}

const props = withDefaults(defineProps<Props>(), {
    backupDiskSpace: () => ({
        total: 0,
        used: 0,
        available: 0,
        percentage_used: 0,
        path: '',
    }),
    latestBackups: () => [],
    latestApps: () => [],
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

const formatGb = (gb: number): string => {
    if (gb < 0.01) {
        return '< 0.01 GB';
    }
    return `${gb.toFixed(2)} GB`;
};

const formatDate = (date: string): string => {
    return new Date(date).toLocaleString();
};

const showInstructions = ref(false);
const instructionsRef = ref<HTMLElement | null>(null);

onClickOutside(instructionsRef, () => {
    showInstructions.value = false;
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
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
                    class="relative overflow-visible rounded-xl border border-sidebar-border/70 bg-sidebar p-6 dark:border-sidebar-border"
                >
                    <div class="flex h-full flex-col gap-4">
                        <div>
                            <h3
                                class="text-sm font-medium text-sidebar-foreground/70"
                            >
                                CLI Script
                            </h3>
                            <p
                                class="mt-1 text-xs text-sidebar-foreground/50 sm:text-sm"
                            >
                                Download the command-line script to manage
                                backups from your terminal
                            </p>
                        </div>

                        <div class="mt-auto space-y-3">
                            <DownloadScriptButton />

                            <div class="relative" ref="instructionsRef">
                                <button
                                    @click="
                                        showInstructions = !showInstructions
                                    "
                                    class="flex w-full items-center justify-between rounded-lg border border-sidebar-border/50 bg-sidebar-foreground/5 px-3 py-2 text-xs text-sidebar-foreground/70 transition-colors hover:bg-sidebar-foreground/10 dark:border-sidebar-border/50"
                                >
                                    <span>Usage Instructions</span>
                                    <ChevronDown
                                        :class="[
                                            'h-4 w-4 transition-transform',
                                            showInstructions && 'rotate-180',
                                        ]"
                                    />
                                </button>

                                <Transition
                                    enter-active-class="transition ease-out duration-200"
                                    enter-from-class="opacity-0 scale-95 translate-y-2"
                                    enter-to-class="opacity-100 scale-100 translate-y-0"
                                    leave-active-class="transition ease-in duration-150"
                                    leave-from-class="opacity-100 scale-100 translate-y-0"
                                    leave-to-class="opacity-0 scale-95 translate-y-2"
                                >
                                    <div
                                        v-if="showInstructions"
                                        class="absolute top-full left-0 z-50 mt-2 max-h-[40vh] w-[calc(100vw-3rem)] space-y-4 overflow-y-auto rounded-lg border border-sidebar-border/50 bg-sidebar p-4 text-xs text-sidebar-foreground/70 shadow-lg sm:right-0 sm:left-auto sm:w-96"
                                    >
                                        <div class="space-y-2">
                                            <h4
                                                class="font-semibold text-sidebar-foreground"
                                            >
                                                1. First Time Setup
                                            </h4>
                                            <p>
                                                After downloading, make the
                                                script executable:
                                            </p>
                                            <div
                                                class="overflow-x-auto rounded-md bg-sidebar p-3 font-mono text-xs"
                                            >
                                                <div
                                                    class="text-sidebar-foreground/50"
                                                >
                                                    # Option 1: Run with bash
                                                </div>
                                                <div class="mt-1">
                                                    bash respaldo.sh
                                                </div>
                                                <div
                                                    class="mt-3 text-sidebar-foreground/50"
                                                >
                                                    # Option 2: Make executable
                                                </div>
                                                <div class="mt-1">
                                                    chmod +x respaldo.sh
                                                </div>
                                                <div class="mt-1">
                                                    ./respaldo.sh
                                                </div>
                                            </div>
                                        </div>

                                        <div class="space-y-2">
                                            <h4
                                                class="font-semibold text-sidebar-foreground"
                                            >
                                                2. Install as Binary Command
                                            </h4>
                                            <p>
                                                Add to your PATH to use from
                                                anywhere:
                                            </p>
                                            <div
                                                class="overflow-x-auto rounded-md bg-sidebar p-3 font-mono text-xs"
                                            >
                                                <div
                                                    class="text-sidebar-foreground/50"
                                                >
                                                    # User-level (recommended)
                                                </div>
                                                <div class="mt-1">
                                                    mkdir -p ~/bin
                                                </div>
                                                <div class="mt-1">
                                                    mv respaldo.sh
                                                    ~/bin/respaldo
                                                </div>
                                                <div class="mt-1">
                                                    chmod +x ~/bin/respaldo
                                                </div>
                                                <div class="mt-1">
                                                    export
                                                    PATH="$HOME/bin:$PATH"
                                                </div>
                                                <div
                                                    class="mt-3 text-sidebar-foreground/50"
                                                >
                                                    # System-wide
                                                </div>
                                                <div class="mt-1">
                                                    sudo mv respaldo.sh
                                                    /usr/local/bin/respaldo
                                                </div>
                                                <div class="mt-1">
                                                    sudo chmod +x
                                                    /usr/local/bin/respaldo
                                                </div>
                                            </div>
                                        </div>

                                        <div class="space-y-2">
                                            <h4
                                                class="font-semibold text-sidebar-foreground"
                                            >
                                                3. Automated Backups with Cron
                                            </h4>
                                            <p>
                                                Set up a cron job for automatic
                                                backups:
                                            </p>
                                            <div
                                                class="overflow-x-auto rounded-md bg-sidebar p-3 font-mono text-xs"
                                            >
                                                <div
                                                    class="text-sidebar-foreground/50"
                                                >
                                                    # Edit crontab
                                                </div>
                                                <div class="mt-1">
                                                    crontab -e
                                                </div>
                                                <div
                                                    class="mt-3 text-sidebar-foreground/50"
                                                >
                                                    # Daily backup at 2 AM
                                                </div>
                                                <div class="mt-1">
                                                    0 2 * * * respaldo
                                                    /path/to/project
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </Transition>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Latest Apps Card -->
                <div
                    class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-sidebar p-6 dark:border-sidebar-border"
                >
                    <div class="flex h-full flex-col gap-4">
                        <div>
                            <h3
                                class="text-sm font-medium text-sidebar-foreground/70"
                            >
                                Latest Apps
                            </h3>
                            <p class="mt-1 text-xs text-sidebar-foreground/50">
                                Your most recently created apps
                            </p>
                        </div>

                        <div
                            v-if="props.latestApps.length === 0"
                            class="flex flex-1 items-center justify-center"
                        >
                            <p class="text-xs text-sidebar-foreground/50">
                                No apps yet
                            </p>
                        </div>

                        <div v-else class="space-y-2">
                            <Link
                                v-for="app in props.latestApps"
                                :key="app.id"
                                :href="`/apps/${app.id}`"
                                class="flex items-center gap-3 rounded-lg border border-sidebar-border/50 p-3 transition-colors hover:border-sidebar-border hover:bg-sidebar-foreground/5"
                            >
                                <HardDrive
                                    class="h-4 w-4 flex-shrink-0 text-sidebar-foreground/50"
                                />
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="truncate text-sm font-medium text-sidebar-foreground"
                                    >
                                        {{ app.name }}
                                    </p>
                                    <p
                                        class="text-xs text-sidebar-foreground/50"
                                    >
                                        {{
                                            new Date(
                                                app.created_at,
                                            ).toLocaleDateString()
                                        }}
                                    </p>
                                </div>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Latest Backups Card -->
            <div
                class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-sidebar p-6 dark:border-sidebar-border"
            >
                <div class="flex flex-col gap-4">
                    <div>
                        <h3
                            class="text-sm font-medium text-sidebar-foreground/70"
                        >
                            Latest Backups
                        </h3>
                        <p class="mt-1 text-xs text-sidebar-foreground/50">
                            Recent backup activity across all your apps
                        </p>
                    </div>

                    <div
                        v-if="props.latestBackups.length === 0"
                        class="py-8 text-center"
                    >
                        <p class="text-sm text-sidebar-foreground/50">
                            No backups yet. Use the CLI script to create your
                            first backup.
                        </p>
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            class="grid grid-cols-12 gap-4 border-b border-sidebar-border/50 pb-2 text-xs font-medium text-sidebar-foreground/70"
                        >
                            <div class="col-span-5">Filename</div>
                            <div class="col-span-2">App</div>
                            <div class="col-span-2">Size</div>
                            <div class="col-span-3">Created At</div>
                        </div>
                        <div
                            v-for="backup in props.latestBackups"
                            :key="backup.id"
                            class="grid grid-cols-12 gap-4 border-b border-sidebar-border/50 py-2 text-sm"
                        >
                            <div
                                class="col-span-5 truncate font-medium text-sidebar-foreground"
                            >
                                {{ backup.filename }}
                            </div>
                            <div class="col-span-2">
                                <Link
                                    :href="`/apps/${backup.app.id}`"
                                    class="text-sidebar-foreground/70 hover:text-sidebar-foreground hover:underline"
                                >
                                    {{ backup.app.name }}
                                </Link>
                            </div>
                            <div class="col-span-2 text-sidebar-foreground/70">
                                {{ formatGb(backup.size) }}
                            </div>
                            <div
                                class="col-span-3 text-xs text-sidebar-foreground/50"
                            >
                                {{ formatDate(backup.created_at) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
