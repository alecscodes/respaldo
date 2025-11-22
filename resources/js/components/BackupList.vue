<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/vue3';
import { Download, Trash2 } from 'lucide-vue-next';

interface Backup {
    id: number;
    filename: string;
    size: number;
    created_at: string;
}

interface Props {
    appId: number;
    backups: Backup[];
}

const props = defineProps<Props>();

const formatGb = (gb: number): string => {
    if (gb < 0.01) {
        return '< 0.01 GB';
    }
    return `${gb.toFixed(2)} GB`;
};

const formatDate = (date: string): string => {
    return new Date(date).toLocaleString();
};

const downloadBackup = (backupId: number) => {
    window.location.href = `/backups/${backupId}/download`;
};

const deleteBackup = (backupId: number) => {
    if (confirm('Are you sure you want to delete this backup?')) {
        router.delete(`/backups/${backupId}`, {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <div
        v-if="props.backups.length === 0"
        class="py-8 text-center text-muted-foreground"
    >
        <p>No backups yet. Use the CLI script to create your first backup.</p>
    </div>

    <div v-else class="space-y-2">
        <div
            class="grid grid-cols-3 gap-4 border-b pb-2 text-sm font-medium text-muted-foreground"
        >
            <div>Size</div>
            <div>Created At</div>
            <div class="text-right">Actions</div>
        </div>
        <div
            v-for="backup in props.backups"
            :key="backup.id"
            class="grid grid-cols-3 gap-4 border-b py-3 text-sm"
        >
            <div>{{ formatGb(backup.size) }}</div>
            <div class="text-muted-foreground">
                {{ formatDate(backup.created_at) }}
            </div>
            <div class="flex justify-end gap-2">
                <Button
                    variant="ghost"
                    size="icon"
                    @click="downloadBackup(backup.id)"
                >
                    <Download class="h-4 w-4" />
                </Button>
                <Button
                    variant="ghost"
                    size="icon"
                    @click="deleteBackup(backup.id)"
                >
                    <Trash2 class="h-4 w-4 text-destructive" />
                </Button>
            </div>
        </div>
    </div>
</template>
