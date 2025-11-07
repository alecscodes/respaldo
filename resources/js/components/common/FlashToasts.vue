<script setup lang="ts">
import { Toaster } from '@/components/ui/sonner';
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, watch } from 'vue';
import { toast } from 'vue-sonner';
import 'vue-sonner/style.css';

type ErrorsBag = Record<string, string | string[]>;

const page = usePage();

const flash = computed(
    () =>
        (page.props.flash ?? {}) as {
            success?: string;
            error?: string;
            warning?: string;
            info?: string;
            message?: string;
        },
);
const errors = computed(() => (page.props.errors ?? {}) as ErrorsBag);

function showFlashMessages(): void {
    if (flash.value.success) {
        toast.success(flash.value.success);
    }
    if (flash.value.error) {
        toast.error(flash.value.error);
    }
    if (flash.value.warning) {
        toast.warning(flash.value.warning);
    }
    if (flash.value.info) {
        toast.message(flash.value.info);
    }
    if (flash.value.message) {
        toast.message(flash.value.message);
    }
}

function showValidationErrors(): void {
    const entries = Object.entries(errors.value ?? {});
    if (entries.length === 0) {
        return;
    }

    entries
        .map(([k, v]) => `${k}: ${Array.isArray(v) ? v.join(', ') : v}`)
        .sort()
        .forEach((value) => toast.error(value));
}

onMounted(() => {
    showFlashMessages();
    showValidationErrors();
});

watch(flash, () => {
    showFlashMessages();
});

watch(errors, () => {
    showValidationErrors();
});
</script>

<template>
    <Toaster
        position="top-right"
        richColors
        closeButton
        data-testid="app-toaster"
    />
</template>
