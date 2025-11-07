<script setup lang="ts">
import AppForm from '@/components/AppForm.vue';
import { ActionSheet, ActionSheetRoot } from '@/components/ui/action-sheet';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { HardDrive, MoreVertical, Pencil, Plus, Server } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface App {
    id: number;
    name: string;
    storage_size: number;
    used_space: number;
    available_space: number;
    created_at: string;
}

interface Props {
    apps: App[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Apps',
        href: '/apps',
    },
];

const showCreateDialog = ref(false);
const showEditDialog = ref(false);
const editingApp = ref<App | null>(null);
const showActionSheet = ref(false);
const actionSheetApp = ref<App | null>(null);

const formatGb = (gb: number): string => {
    return `${gb.toFixed(2)} GB`;
};

const formatPercent = (used: number, total: number): number => {
    if (total === 0) {
        return 0;
    }
    return Math.round((used / total) * 100);
};

const openEditDialog = (app: App) => {
    editingApp.value = app;
    showEditDialog.value = true;
};

const handleEditSuccess = () => {
    showEditDialog.value = false;
    editingApp.value = null;
    router.reload({ only: ['apps'] });
};

const openActionSheet = (app: App) => {
    actionSheetApp.value = app;
    showActionSheet.value = true;
};

const actionSheetButtons = computed(() => {
    if (!actionSheetApp.value) {
        return [];
    }

    return [
        {
            text: 'View Details',
            icon: HardDrive,
            handler: () => {
                showActionSheet.value = false;
                router.visit(`/apps/${actionSheetApp.value!.id}`);
            },
        },
        {
            text: 'Edit App',
            icon: Pencil,
            handler: () => {
                openEditDialog(actionSheetApp.value!);
                showActionSheet.value = false;
            },
        },
    ];
});
</script>

<template>
    <Head title="Apps" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">Apps</h1>
                    <p class="text-muted-foreground">
                        Manage your backup apps and storage
                    </p>
                </div>

                <!-- Desktop: Show button, Mobile: Show 3-dot menu -->
                <div class="hidden md:block">
                    <Dialog v-model:open="showCreateDialog">
                        <DialogTrigger as-child>
                            <Button>
                                <Plus class="mr-2 h-4 w-4" />
                                Add App
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Create New App</DialogTitle>
                                <DialogDescription>
                                    Create a new app to manage backups. You'll
                                    set the storage size limit.
                                </DialogDescription>
                            </DialogHeader>
                            <AppForm @success="showCreateDialog = false" />
                        </DialogContent>
                    </Dialog>
                </div>

                <!-- Mobile: 3-dot menu -->
                <div class="md:hidden">
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="
                            actionSheetApp = null;
                            showActionSheet = true;
                        "
                    >
                        <MoreVertical class="h-5 w-5" />
                    </Button>
                </div>
            </div>

            <div
                v-if="props.apps.length === 0"
                class="flex flex-col items-center justify-center gap-4 rounded-xl border p-12"
            >
                <Server class="h-12 w-12 text-muted-foreground" />
                <div class="text-center">
                    <h3 class="text-lg font-semibold">No apps yet</h3>
                    <p class="text-muted-foreground">
                        Get started by creating your first app
                    </p>
                </div>
                <Button @click="showCreateDialog = true">
                    <Plus class="mr-2 h-4 w-4" />
                    Create App
                </Button>
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Card
                    v-for="app in props.apps"
                    :key="app.id"
                    class="transition-shadow hover:shadow-md"
                >
                    <CardHeader>
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1">
                                <CardTitle class="text-lg">{{
                                    app.name
                                }}</CardTitle>
                                <CardDescription
                                    >Created
                                    {{
                                        new Date(
                                            app.created_at,
                                        ).toLocaleDateString()
                                    }}</CardDescription
                                >
                            </div>
                            <!-- Mobile: 3-dot menu -->
                            <div class="md:hidden">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8"
                                    @click="openActionSheet(app)"
                                >
                                    <MoreVertical class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <div
                                class="flex items-center justify-between text-sm"
                            >
                                <span class="text-muted-foreground"
                                    >Storage</span
                                >
                                <span class="font-medium">{{
                                    formatGb(app.storage_size)
                                }}</span>
                            </div>
                            <div
                                class="flex items-center justify-between text-sm"
                            >
                                <span class="text-muted-foreground">Used</span>
                                <span class="font-medium">{{
                                    formatGb(app.used_space)
                                }}</span>
                            </div>
                            <div
                                class="flex items-center justify-between text-sm"
                            >
                                <span class="text-muted-foreground"
                                    >Available</span
                                >
                                <span class="font-medium">{{
                                    formatGb(app.available_space)
                                }}</span>
                            </div>

                            <div class="space-y-1">
                                <div
                                    class="flex justify-between text-xs text-muted-foreground"
                                >
                                    <span
                                        >{{
                                            formatPercent(
                                                app.used_space,
                                                app.storage_size,
                                            )
                                        }}% used</span
                                    >
                                </div>
                                <div
                                    class="h-2 w-full overflow-hidden rounded-full bg-muted"
                                >
                                    <div
                                        class="h-full bg-primary transition-all"
                                        :style="{
                                            width: `${formatPercent(app.used_space, app.storage_size)}%`,
                                        }"
                                    ></div>
                                </div>
                            </div>
                        </div>

                        <!-- Desktop: Show buttons -->
                        <div class="hidden gap-2 md:flex">
                            <Button as-child variant="outline" class="flex-1">
                                <Link :href="`/apps/${app.id}`">
                                    <HardDrive class="mr-2 h-4 w-4" />
                                    View Details
                                </Link>
                            </Button>
                            <Button
                                variant="outline"
                                @click="openEditDialog(app)"
                            >
                                <Pencil class="h-4 w-4" />
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Edit Dialog -->
            <Dialog v-model:open="showEditDialog">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit App</DialogTitle>
                        <DialogDescription>
                            Update the app name and storage size.
                        </DialogDescription>
                    </DialogHeader>
                    <AppForm
                        v-if="editingApp"
                        :app="editingApp"
                        @success="handleEditSuccess"
                    />
                </DialogContent>
            </Dialog>

            <!-- Mobile Action Sheet -->
            <ActionSheetRoot v-model:open="showActionSheet">
                <ActionSheet
                    :buttons="
                        actionSheetApp
                            ? actionSheetButtons
                            : [
                                  {
                                      text: 'Add App',
                                      icon: Plus,
                                      handler: () => {
                                          showCreateDialog = true;
                                          showActionSheet = false;
                                      },
                                  },
                              ]
                    "
                    :header="actionSheetApp ? actionSheetApp.name : 'Apps'"
                    @action="showActionSheet = false"
                />
            </ActionSheetRoot>
        </div>
    </AppLayout>
</template>
