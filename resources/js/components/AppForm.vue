<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

interface Props {
    app?: {
        id: number;
        name: string;
        storage_size: number;
        backup_period?: string | null;
        backup_days?: string[] | null;
    };
}

const props = defineProps<Props>();

const emit = defineEmits<{
    success: [];
}>();

const form = useForm({
    name: props.app?.name || '',
    storage_size: props.app?.storage_size || 0,
    backup_period: props.app?.backup_period || null,
    backup_days: props.app?.backup_days || [],
});

const isWeekly = computed(() => form.backup_period === 'weekly');

const dayOptions = [
    { value: 'M', label: 'Monday' },
    { value: 'T', label: 'Tuesday' },
    { value: 'W', label: 'Wednesday' },
    { value: 'R', label: 'Thursday' },
    { value: 'F', label: 'Friday' },
    { value: 'S', label: 'Saturday' },
    { value: 'U', label: 'Sunday' },
];

const toggleDay = (day: string, checked: boolean | 'indeterminate') => {
    const isChecked = checked === true;
    const index = form.backup_days.indexOf(day);
    if (isChecked && index === -1) {
        form.backup_days.push(day);
    } else if (!isChecked && index > -1) {
        form.backup_days.splice(index, 1);
    }
};

watch(
    () => form.backup_period,
    (newPeriod) => {
        if (newPeriod !== 'weekly') {
            form.backup_days = [];
        }
    },
);

const submit = () => {
    form.transform((data) => ({
        ...data,
        backup_days: form.backup_period === 'weekly' ? data.backup_days : null,
    }));

    const submitOptions = {
        preserveScroll: true,
        onSuccess: () => emit('success'),
    };

    if (props.app) {
        form.put(`/apps/${props.app.id}`, submitOptions);
    } else {
        form.post('/apps', submitOptions);
    }
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-4">
        <div class="space-y-2">
            <Label for="name">App Name</Label>
            <Input
                id="name"
                v-model="form.name"
                type="text"
                required
                placeholder="My App"
            />
            <InputError :message="form.errors.name" />
        </div>

        <div class="space-y-2">
            <Label for="storage_size">Storage Size (GB)</Label>
            <Input
                id="storage_size"
                v-model.number="form.storage_size"
                type="number"
                step="0.1"
                min="0.1"
                required
                placeholder="10"
            />
            <InputError :message="form.errors.storage_size" />
            <p class="text-xs text-muted-foreground">
                Enter the maximum storage size in GB for this app
            </p>
        </div>

        <div class="space-y-2">
            <Label for="backup_period">Backup Schedule</Label>
            <Select id="backup_period" v-model="form.backup_period">
                <option :value="null">No scheduled backups</option>
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
            </Select>
            <InputError :message="form.errors.backup_period" />
            <p class="text-xs text-muted-foreground">
                Select how often backups should be created automatically
            </p>
        </div>

        <div v-if="isWeekly" class="space-y-2">
            <Label
                >Backup Days (Weekly)
                <span class="text-destructive">*</span></Label
            >
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <label
                    v-for="day in dayOptions"
                    :key="day.value"
                    class="flex cursor-pointer items-center gap-2 rounded-md border border-input p-3 transition-colors hover:bg-accent"
                >
                    <Checkbox
                        :id="`backup-day-${day.value}`"
                        :model-value="form.backup_days.includes(day.value)"
                        @update:model-value="
                            (checked: boolean | 'indeterminate') =>
                                toggleDay(day.value, checked)
                        "
                    />
                    <span class="text-sm font-medium">{{ day.label }}</span>
                </label>
            </div>
            <InputError :message="form.errors.backup_days" />
            <p class="text-xs text-muted-foreground">
                Select which days of the week to create backups
            </p>
        </div>

        <div class="flex justify-end gap-2">
            <Button type="submit" :disabled="form.processing">
                {{
                    form.processing
                        ? 'Saving...'
                        : app
                          ? 'Update App'
                          : 'Create App'
                }}
            </Button>
        </div>
    </form>
</template>
