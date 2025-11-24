<script setup lang="ts">
import AppForm from '@/components/AppForm.vue';
import BackupList from '@/components/BackupList.vue';
import DeleteAppConfirmationDialog from '@/components/DeleteAppConfirmationDialog.vue';
import InputError from '@/components/InputError.vue';
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
} from '@/components/ui/dialog';
import { useLargeFileUpload } from '@/composables/useLargeFileUpload';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    MoreVertical,
    Pencil,
    Trash,
    Trash2,
    Upload,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface App {
    id: number;
    name: string;
    storage_size: number;
    used_space: number;
    available_space: number;
    backup_period?: string | null;
    backup_days?: string[] | null;
    retention_days?: number | null;
    retention_count?: number | null;
}

interface Backup {
    id: number;
    filename: string;
    size: number;
    created_at: string;
}

interface Props {
    app: App;
    backups: Backup[];
}

const props = defineProps<Props>();

const showUploadDialog = ref(false);
const showDeleteDialog = ref(false);
const showEditDialog = ref(false);
const showActionSheet = ref(false);
const uploadFile = ref<File | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);

const uploadForm = useForm({
    file: null as File | null,
});

// Use chunked uploads for all files to ensure reliability and support for very large files (up to 2TB)
const {
    isUploading,
    progress,
    error: uploadError,
    upload: uploadLargeFile,
    cancel: cancelUpload,
    reset: resetUpload,
} = useLargeFileUpload();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Apps',
        href: '/apps',
    },
    {
        title: props.app.name,
        href: `/apps/${props.app.id}`,
    },
];

const formatGb = (gb: number): string => {
    return `${gb.toFixed(2)} GB`;
};

const formatPercent = (used: number, total: number): number => {
    if (total === 0) {
        return 0;
    }
    return Math.round((used / total) * 100);
};

const openDeleteDialog = () => {
    showDeleteDialog.value = true;
};

const confirmDelete = () => {
    router.delete(`/apps/${props.app.id}`);
};

const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        const file = target.files[0];
        uploadFile.value = file;
        uploadForm.file = file;
    }
};

const openFileDialog = () => {
    fileInputRef.value?.click();
};

const handleDragOver = (event: DragEvent) => {
    event.preventDefault();
    event.stopPropagation();
};

const handleDragLeave = (event: DragEvent) => {
    event.preventDefault();
    event.stopPropagation();
};

const handleDrop = (event: DragEvent) => {
    event.preventDefault();
    event.stopPropagation();

    const files = event.dataTransfer?.files;
    if (files && files.length > 0) {
        const file = files[0];
        // Check if file extension is allowed
        const fileName = file.name.toLowerCase();
        const allowedExtensions = [
            '.zip',
            '.tar',
            '.gz',
            '.img',
            '.iso',
            '.dmg',
            '.pkg',
            '.rar',
            '.7z',
        ];
        const isTarGz = fileName.endsWith('.tar.gz');
        const hasValidExtension =
            allowedExtensions.some((ext) => fileName.endsWith(ext)) || isTarGz;

        if (hasValidExtension) {
            uploadFile.value = file;
            uploadForm.file = file;
            // Also update the file input
            if (fileInputRef.value) {
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                fileInputRef.value.files = dataTransfer.files;
            }
        }
    }
};

const handleUpload = async () => {
    if (!uploadFile.value) {
        return;
    }

    // Use chunked uploads for all files to ensure reliability and support for very large files (up to 2TB)
    try {
        await uploadLargeFile({
            url: `/apps/${props.app.id}/backups`,
            file: uploadFile.value,
            onSuccess: handleUploadSuccess,
            onError: (errorMessage) => {
                console.error('Upload error:', errorMessage);
            },
        });
    } catch (error) {
        // Error is already handled in the composable
        console.error('Upload failed:', error);
    }
};

const handleUploadSuccess = () => {
    document.documentElement.removeAttribute('data-uploading');
    uploadFile.value = null;
    uploadForm.file = null;
    uploadForm.reset('file');
    resetUpload();
    showUploadDialog.value = false;
    const fileInput = fileInputRef.value;
    if (fileInput) {
        fileInput.value = '';
    }
    router.reload({ only: ['backups', 'app'] });
};

const applyRetentionForm = useForm({});

const handleApplyRetention = () => {
    if (!confirm('Apply retention policy and delete old backups?')) {
        return;
    }

    applyRetentionForm.post(`/apps/${props.app.id}/apply-retention`, {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({ only: ['backups', 'app'] });
        },
    });
};

const actionSheetButtons = computed(() => [
    {
        text: 'Edit App',
        icon: Pencil,
        handler: () => {
            showEditDialog.value = true;
            showActionSheet.value = false;
        },
    },
    {
        text: 'Delete App',
        icon: Trash2,
        role: 'destructive' as const,
        handler: () => {
            openDeleteDialog();
            showActionSheet.value = false;
        },
    },
    {
        text: 'Cancel',
        role: 'cancel' as const,
        handler: () => {
            showActionSheet.value = false;
        },
    },
]);
</script>

<template>
    <Head :title="props.app.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Button variant="ghost" size="icon" as-child>
                        <Link href="/apps">
                            <ArrowLeft class="h-4 w-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 class="text-2xl font-semibold">
                            {{ props.app.name }}
                        </h1>
                        <p class="text-muted-foreground">
                            App backup management
                        </p>
                    </div>
                </div>

                <!-- Desktop: Show buttons, Mobile: Show 3-dot menu -->
                <div class="hidden gap-2 md:flex">
                    <Button variant="outline" @click="showEditDialog = true">
                        <Pencil class="mr-2 h-4 w-4" />
                        Edit App
                    </Button>
                    <Button variant="destructive" @click="openDeleteDialog">
                        <Trash2 class="mr-2 h-4 w-4" />
                        Delete App
                    </Button>
                </div>

                <!-- Mobile: 3-dot menu -->
                <div class="md:hidden">
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="showActionSheet = true"
                    >
                        <MoreVertical class="h-5 w-5" />
                    </Button>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Storage Overview</CardTitle>
                        <CardDescription
                            >Current storage usage for this app</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <div
                                class="flex items-center justify-between text-sm"
                            >
                                <span class="text-muted-foreground"
                                    >Total Storage</span
                                >
                                <span class="font-medium">{{
                                    formatGb(props.app.storage_size)
                                }}</span>
                            </div>
                            <div
                                class="flex items-center justify-between text-sm"
                            >
                                <span class="text-muted-foreground">Used</span>
                                <span class="font-medium">{{
                                    formatGb(props.app.used_space)
                                }}</span>
                            </div>
                            <div
                                class="flex items-center justify-between text-sm"
                            >
                                <span class="text-muted-foreground"
                                    >Available</span
                                >
                                <span class="font-medium">{{
                                    formatGb(props.app.available_space)
                                }}</span>
                            </div>

                            <div class="space-y-1 pt-2">
                                <div
                                    class="flex justify-between text-xs text-muted-foreground"
                                >
                                    <span
                                        >{{
                                            formatPercent(
                                                props.app.used_space,
                                                props.app.storage_size,
                                            )
                                        }}% used</span
                                    >
                                </div>
                                <div
                                    class="h-3 w-full overflow-hidden rounded-full bg-muted"
                                >
                                    <div
                                        class="h-full bg-primary transition-all"
                                        :style="{
                                            width: `${formatPercent(props.app.used_space, props.app.storage_size)}%`,
                                        }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Backup Statistics</CardTitle>
                        <CardDescription
                            >Information about your backups</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-2">
                            <div
                                class="flex items-center justify-between text-sm"
                            >
                                <span class="text-muted-foreground"
                                    >Total Backups</span
                                >
                                <span class="font-medium">{{
                                    props.backups.length
                                }}</span>
                            </div>
                            <div
                                class="flex items-center justify-between text-sm"
                            >
                                <span class="text-muted-foreground"
                                    >Total Size</span
                                >
                                <span class="font-medium">{{
                                    formatGb(props.app.used_space)
                                }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <div
                        class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
                    >
                        <div>
                            <CardTitle>Backups</CardTitle>
                            <CardDescription
                                >Manage and download your
                                backups</CardDescription
                            >
                        </div>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <Button
                                v-if="app.retention_days || app.retention_count"
                                variant="outline"
                                @click="handleApplyRetention"
                                :disabled="applyRetentionForm.processing"
                                class="w-full sm:w-auto"
                            >
                                <Trash class="mr-2 h-4 w-4" />
                                {{
                                    applyRetentionForm.processing
                                        ? 'Cleaning...'
                                        : 'Apply Retention'
                                }}
                            </Button>
                            <Button
                                @click="showUploadDialog = true"
                                class="w-full sm:w-auto"
                            >
                                <Upload class="mr-2 h-4 w-4" />
                                Upload Backup
                            </Button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <BackupList
                        :app-id="props.app.id"
                        :backups="props.backups"
                    />
                </CardContent>
            </Card>

            <!-- Upload Dialog -->
            <div
                v-if="showUploadDialog"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
                @click.self="showUploadDialog = false"
            >
                <Card class="w-full max-w-md">
                    <CardHeader>
                        <CardTitle>Upload Backup</CardTitle>
                        <CardDescription>
                            Upload a backup file (zip, tar.gz, img, iso, dmg,
                            pkg, rar, 7z)
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <form @submit.prevent="handleUpload" class="space-y-4">
                            <div class="space-y-2">
                                <label
                                    for="backup-file"
                                    class="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-muted-foreground/25 p-6 transition-colors hover:border-muted-foreground/50"
                                    @click.prevent="openFileDialog"
                                    @dragover.prevent="handleDragOver"
                                    @dragleave.prevent="handleDragLeave"
                                    @drop.prevent="handleDrop"
                                >
                                    <Upload
                                        class="mb-2 h-8 w-8 text-muted-foreground"
                                    />
                                    <span class="text-sm font-medium">
                                        {{
                                            uploadFile
                                                ? uploadFile.name
                                                : 'Click to select file'
                                        }}
                                    </span>
                                    <span class="text-xs text-muted-foreground"
                                        >or drag and drop</span
                                    >
                                </label>
                                <input
                                    id="backup-file"
                                    ref="fileInputRef"
                                    type="file"
                                    accept=".zip,.tar,.gz,.img,.iso,.dmg,.pkg,.rar,.7z"
                                    class="hidden"
                                    @change="handleFileSelect"
                                />
                                <InputError
                                    :message="uploadError || undefined"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Supported formats: zip, tar, gz, tar.gz,
                                    img, iso, dmg, pkg, rar, 7z
                                </p>
                                <!-- Upload Progress -->
                                <div v-if="isUploading" class="space-y-2">
                                    <div
                                        class="flex items-center justify-between text-sm"
                                    >
                                        <span class="text-muted-foreground"
                                            >Uploading...</span
                                        >
                                        <span class="font-medium">
                                            {{ progress }}%
                                        </span>
                                    </div>
                                    <div
                                        class="h-2 w-full overflow-hidden rounded-full bg-muted"
                                    >
                                        <div
                                            class="h-full bg-primary transition-all duration-300 ease-out"
                                            :style="{
                                                width: `${progress}%`,
                                            }"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                            <div class="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="
                                        if (isUploading) {
                                            cancelUpload();
                                        }
                                        showUploadDialog = false;
                                        resetUpload();
                                    "
                                    :disabled="isUploading"
                                >
                                    {{
                                        isUploading ? 'Cancel Upload' : 'Cancel'
                                    }}
                                </Button>
                                <Button
                                    type="submit"
                                    :disabled="!uploadFile || isUploading"
                                >
                                    <Upload class="mr-2 h-4 w-4" />
                                    {{
                                        isUploading ? 'Uploading...' : 'Upload'
                                    }}
                                </Button>
                            </div>
                        </form>
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
                        :app="props.app"
                        @success="
                            showEditDialog = false;
                            router.reload({ only: ['app'] });
                        "
                    />
                </DialogContent>
            </Dialog>

            <DeleteAppConfirmationDialog
                v-model:open="showDeleteDialog"
                :app-name="props.app.name"
                :backup-count="props.backups.length"
                @confirm="confirmDelete"
            />

            <!-- Mobile Action Sheet -->
            <ActionSheetRoot v-model:open="showActionSheet">
                <ActionSheet
                    :buttons="actionSheetButtons"
                    :header="props.app.name"
                    @action="showActionSheet = false"
                />
            </ActionSheetRoot>
        </div>
    </AppLayout>
</template>
