<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { router, usePage } from '@inertiajs/vue3';

interface Props {
    app?: {
        id: number;
        name: string;
        storage_size: number;
    };
}

const props = defineProps<Props>();

const emit = defineEmits<{
    success: [];
}>();

const page = usePage();

const submit = (e: Event) => {
    const form = e.target as HTMLFormElement;
    const formData = new FormData(form);
    const data = {
        name: formData.get('name'),
        storage_size: parseFloat(formData.get('storage_size') as string),
    };

    if (props.app) {
        router.put(`/apps/${props.app.id}`, data, {
            preserveScroll: true,
            onSuccess: () => {
                emit('success');
            },
        });
    } else {
        router.post('/apps', data, {
            preserveScroll: true,
            onSuccess: () => {
                emit('success');
            },
        });
    }
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-4">
        <div class="space-y-2">
            <Label for="name">App Name</Label>
            <Input
                id="name"
                name="name"
                type="text"
                :default-value="app?.name"
                required
                placeholder="My App"
            />
            <InputError :message="(page.props.errors as any)?.name" />
        </div>

        <div class="space-y-2">
            <Label for="storage_size">Storage Size (GB)</Label>
            <Input
                id="storage_size"
                name="storage_size"
                type="number"
                step="0.1"
                min="0.1"
                :default-value="app?.storage_size?.toFixed(2) || ''"
                required
                placeholder="10"
            />
            <InputError :message="(page.props.errors as any)?.storage_size" />
            <p class="text-xs text-muted-foreground">
                Enter the maximum storage size in GB for this app
            </p>
        </div>

        <div class="flex justify-end gap-2">
            <Button type="submit">
                {{ app ? 'Update App' : 'Create App' }}
            </Button>
        </div>
    </form>
</template>
