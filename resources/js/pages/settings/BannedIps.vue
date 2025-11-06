<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ShieldOff, Trash2 } from 'lucide-vue-next';

interface BannedIp {
    ip: string;
    reason: string | null;
    banned_at: string;
}

interface Props {
    bannedIps: BannedIp[];
}

defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Banned IPs',
        href: '/settings/banned-ips',
    },
];

function unbanIp(ip: string): void {
    if (confirm(`Are you sure you want to unban IP address ${ip}?`)) {
        router.delete('/settings/banned-ips/unban', {
            data: { ip },
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['bannedIps'] });
            },
        });
    }
}

function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleString();
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Banned IPs" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    title="Banned IP Addresses"
                    description="View and manage IP addresses that have been automatically banned"
                />

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <div
                        v-if="($page.props as any).flash?.success"
                        class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-200"
                    >
                        {{ ($page.props as any).flash.success }}
                    </div>
                </Transition>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <div
                        v-if="($page.props as any).flash?.error"
                        class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-200"
                    >
                        {{ ($page.props as any).flash.error }}
                    </div>
                </Transition>

                <Card v-if="bannedIps.length === 0">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <ShieldOff class="h-5 w-5" />
                            No Banned IPs
                        </CardTitle>
                        <CardDescription>
                            There are currently no banned IP addresses.
                        </CardDescription>
                    </CardHeader>
                </Card>

                <div v-else class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold">
                                Banned IPs ({{ bannedIps.length }})
                            </h3>
                            <p class="text-sm text-muted-foreground">
                                List of all banned IP addresses, sorted by most
                                recent
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-4">
                        <Card v-for="bannedIp in bannedIps" :key="bannedIp.ip">
                            <CardContent class="pt-6">
                                <div class="flex items-start justify-between">
                                    <div class="space-y-2">
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="font-mono font-semibold"
                                            >
                                                {{ bannedIp.ip }}
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <Badge
                                                v-if="bannedIp.reason"
                                                variant="secondary"
                                            >
                                                {{ bannedIp.reason }}
                                            </Badge>
                                            <span
                                                v-else
                                                class="text-sm text-muted-foreground"
                                            >
                                                No reason provided
                                            </span>
                                        </div>
                                        <p
                                            class="text-sm text-muted-foreground"
                                        >
                                            Banned at:
                                            {{ formatDate(bannedIp.banned_at) }}
                                        </p>
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="unbanIp(bannedIp.ip)"
                                    >
                                        <Trash2 class="mr-2 h-4 w-4" />
                                        Unban
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
