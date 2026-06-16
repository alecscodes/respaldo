<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';

import AppearanceTabs from '@/components/AppearanceTabs.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import type { BreadcrumbItem } from '@/types';
import { edit } from '@/routes/appearance';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Appearance settings',
        href: edit().url,
    },
];

setLayoutProps({ breadcrumbs: breadcrumbItems });
</script>

<template>
    <div>
        <Head title="Appearance settings" />

        <div class="space-y-6">
            <HeadingSmall
                title="Appearance settings"
                description="Update your account's appearance settings"
            />
            <AppearanceTabs />
        </div>
    </div>
</template>
