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
                    :title="`Banned IP Addresses (${bannedIps.length})`"
                    description="View and manage IP addresses that have been automatically banned"
                />

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
