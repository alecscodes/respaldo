<script setup lang="ts">
import { AlertTriangle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Props {
    open: boolean;
    appName: string;
    backupCount?: number;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    confirm: [];
}>();

const confirmText = ref('');
const CONFIRM_WORD = 'CONFIRM';
const isConfirmValid = computed(() => confirmText.value === CONFIRM_WORD);

const reset = () => {
    confirmText.value = '';
};

watch(
    () => props.open,
    (newValue) => {
        if (!newValue) {
            reset();
        }
    },
);

const handleConfirm = () => {
    if (isConfirmValid.value) {
        emit('confirm');
        emit('update:open', false);
        reset();
    }
};

const handleCancel = () => {
    emit('update:open', false);
    reset();
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <div class="flex items-center gap-2">
                    <AlertTriangle class="h-5 w-5 text-destructive" />
                    <DialogTitle>Delete App</DialogTitle>
                </div>
                <DialogDescription>
                    This action cannot be undone. This will permanently delete
                    the app
                    <strong>"{{ appName }}"</strong>
                    <span v-if="backupCount !== undefined && backupCount > 0">
                        and all {{ backupCount }} backup{{
                            backupCount === 1 ? '' : 's'
                        }}
                        associated with it.
                    </span>
                    <span v-else>and all backups associated with it.</span>
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4 py-4">
                <div class="space-y-2">
                    <Label for="confirm-input">
                        Type <strong>{{ CONFIRM_WORD }}</strong> to confirm:
                    </Label>
                    <Input
                        id="confirm-input"
                        v-model="confirmText"
                        :placeholder="CONFIRM_WORD"
                        class="font-mono"
                        @keyup.enter="handleConfirm"
                    />
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="handleCancel">
                    Cancel
                </Button>
                <Button
                    variant="destructive"
                    :disabled="!isConfirmValid"
                    @click="handleConfirm"
                >
                    Delete App
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
