<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    CheckCircle2,
    Info,
    Search,
    Trash2,
    X,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Log {
    id: number;
    level: string;
    category: string;
    message: string;
    context: Record<string, unknown> | null;
    user: {
        id: number;
        name: string;
        email: string;
    } | null;
    ip_address: string | null;
    user_agent: string | null;
    created_at: string;
}

interface PaginatedLogs {
    data: Log[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface App {
    id: number;
    name: string;
}

interface User {
    id: number;
    name: string;
    email: string;
}

interface Props {
    logs: PaginatedLogs;
    categories: string[];
    apps: App[];
    users: User[];
    levels: string[];
    filters?: {
        search?: string;
        category?: string;
        level?: string;
        user_id?: number;
        app_id?: number;
        date_from?: string;
        date_to?: string;
    };
}

const props = withDefaults(defineProps<Props>(), {
    filters: () => ({}),
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Logs',
        href: '/logs',
    },
];

const searchQuery = ref(props.filters?.search || '');
const selectedCategory = ref(props.filters?.category || '');
const selectedLevel = ref(props.filters?.level || '');
const selectedApp = ref(
    props.filters?.app_id ? String(props.filters.app_id) : '',
);
const selectedUser = ref(
    props.filters?.user_id ? String(props.filters.user_id) : '',
);
const convertToDatetimeLocal = (dateString: string): string => {
    if (!dateString) return '';
    const date = new Date(dateString);
    const pad = (n: number): string => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
};

const convertToISO = (datetimeLocal: string): string => {
    return datetimeLocal ? new Date(datetimeLocal).toISOString() : '';
};

const dateFrom = ref(
    props.filters?.date_from
        ? convertToDatetimeLocal(props.filters.date_from)
        : '',
);
const dateTo = ref(
    props.filters?.date_to ? convertToDatetimeLocal(props.filters.date_to) : '',
);
const showFilters = ref(false);
const showDeleteDialog = ref(false);

const isRegexPattern = (pattern: string): boolean => {
    if (!pattern || pattern.length < 2) {
        return false;
    }

    // Check for common regex metacharacters that indicate regex intent
    const regexMetachars = /[.*+?^${}[\]()|\\]/;
    if (!regexMetachars.test(pattern)) {
        return false;
    }

    // Try to compile as regex to validate
    try {
        new RegExp(pattern);
        return true;
    } catch {
        return false;
    }
};

const levelColors = {
    emergency: 'bg-red-500/10 text-red-500 border-red-500/20',
    alert: 'bg-orange-500/10 text-orange-500 border-orange-500/20',
    critical: 'bg-red-600/10 text-red-600 border-red-600/20',
    error: 'bg-red-500/10 text-red-500 border-red-500/20',
    warning: 'bg-yellow-500/10 text-yellow-500 border-yellow-500/20',
    notice: 'bg-blue-500/10 text-blue-500 border-blue-500/20',
    info: 'bg-green-500/10 text-green-500 border-green-500/20',
    debug: 'bg-gray-500/10 text-gray-500 border-gray-500/20',
};

const levelIcons = {
    emergency: XCircle,
    alert: AlertCircle,
    critical: XCircle,
    error: XCircle,
    warning: AlertCircle,
    notice: Info,
    info: CheckCircle2,
    debug: Info,
};

const formatDate = (date: string): string => {
    return new Date(date).toLocaleString();
};

const applyFilters = (): void => {
    const params: Record<string, string | number | boolean> = {};

    if (searchQuery.value) {
        params.search = searchQuery.value;
        if (isRegexPattern(searchQuery.value)) {
            params.use_regex = true;
        }
    }

    if (selectedCategory.value) {
        params.category = selectedCategory.value;
    }

    if (selectedLevel.value) {
        params.level = selectedLevel.value;
    }

    if (selectedApp.value) {
        params.app_id = Number(selectedApp.value);
    }

    if (selectedUser.value) {
        params.user_id = Number(selectedUser.value);
    }

    if (dateFrom.value) {
        params.date_from = convertToISO(dateFrom.value);
    }

    if (dateTo.value) {
        params.date_to = convertToISO(dateTo.value);
    }

    router.get('/logs', params, {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearFilters = (): void => {
    searchQuery.value = '';
    selectedCategory.value = '';
    selectedLevel.value = '';
    selectedApp.value = '';
    selectedUser.value = '';
    dateFrom.value = '';
    dateTo.value = '';

    router.get(
        '/logs',
        {},
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

const hasActiveFilters = computed(() => {
    return (
        searchQuery.value ||
        selectedCategory.value ||
        selectedLevel.value ||
        selectedApp.value ||
        selectedUser.value ||
        dateFrom.value ||
        dateTo.value
    );
});

const getFilterParams = (): Record<string, string | number | boolean> => {
    const params: Record<string, string | number | boolean> = {};

    if (searchQuery.value) {
        params.search = searchQuery.value;
        if (isRegexPattern(searchQuery.value)) {
            params.use_regex = true;
        }
    }

    if (selectedCategory.value) {
        params.category = selectedCategory.value;
    }

    if (selectedLevel.value) {
        params.level = selectedLevel.value;
    }

    if (selectedApp.value) {
        params.app_id = Number(selectedApp.value);
    }

    if (selectedUser.value) {
        params.user_id = Number(selectedUser.value);
    }

    if (dateFrom.value) {
        params.date_from = convertToISO(dateFrom.value);
    }

    if (dateTo.value) {
        params.date_to = convertToISO(dateTo.value);
    }

    return params;
};

const goToPage = (page: number): void => {
    const params = getFilterParams();
    params.page = page;
    router.get('/logs', params, {
        preserveState: true,
        preserveScroll: false,
        onSuccess: () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
    });
};

const deleteLogs = (): void => {
    const params = getFilterParams();
    router.delete('/logs', {
        data: params,
        preserveScroll: true,
        onSuccess: () => {
            showDeleteDialog.value = false;
            clearFilters();
        },
    });
};

watch([searchQuery], () => {
    // Debounce search
    const timeout = setTimeout(() => {
        applyFilters();
    }, 500);

    return () => clearTimeout(timeout);
});
</script>

<template>
    <Head title="Logs" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-sidebar-foreground">
                        Application Logs
                    </h1>
                    <p class="mt-1 text-sm text-sidebar-foreground/70">
                        View and search through application logs
                    </p>
                </div>
                <button
                    @click="showDeleteDialog = true"
                    class="flex items-center gap-2 rounded-lg border border-red-500/50 bg-red-500/10 px-2 py-1.5 text-sm text-red-500 transition-colors hover:bg-red-500/20 md:px-4 md:py-2"
                >
                    <Trash2 class="h-4 w-4" />
                    <span class="hidden md:inline">Clear Logs</span>
                </button>
            </div>

            <!-- Search and Filters -->
            <div
                class="rounded-xl border border-sidebar-border/70 bg-sidebar p-4 dark:border-sidebar-border"
            >
                <div class="flex flex-col gap-4">
                    <!-- Search Bar -->
                    <div class="flex gap-2">
                        <div class="relative flex-1">
                            <Search
                                class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-sidebar-foreground/50"
                            />
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search logs (auto-detects regex)"
                                class="w-full rounded-lg border border-sidebar-border/50 bg-sidebar-foreground/5 px-10 py-2 text-sm text-sidebar-foreground placeholder:text-sidebar-foreground/50 focus:border-sidebar-border focus:ring-2 focus:ring-sidebar-border/20 focus:outline-none dark:border-sidebar-border/50"
                            />
                        </div>
                        <button
                            @click="showFilters = !showFilters"
                            class="rounded-lg border border-sidebar-border/50 bg-sidebar-foreground/5 px-4 py-2 text-sm text-sidebar-foreground/70 transition-colors hover:bg-sidebar-foreground/10"
                        >
                            Filters
                        </button>
                        <button
                            v-if="hasActiveFilters"
                            @click="clearFilters"
                            class="flex items-center gap-2 rounded-lg border border-sidebar-border/50 bg-sidebar-foreground/5 px-4 py-2 text-sm text-sidebar-foreground/70 transition-colors hover:bg-sidebar-foreground/10"
                        >
                            <X class="h-4 w-4" />
                            Clear
                        </button>
                    </div>

                    <!-- Advanced Filters -->
                    <Transition
                        enter-active-class="transition ease-out duration-200"
                        enter-from-class="opacity-0 -translate-y-2"
                        enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="transition ease-in duration-150"
                        leave-from-class="opacity-100 translate-y-0"
                        leave-to-class="opacity-0 -translate-y-2"
                    >
                        <div
                            v-if="showFilters"
                            class="grid gap-4 border-t border-sidebar-border/50 pt-4 md:grid-cols-2 lg:grid-cols-4"
                        >
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-sidebar-foreground/70"
                                >
                                    Category
                                </label>
                                <select
                                    v-model="selectedCategory"
                                    @change="applyFilters"
                                    class="w-full rounded-lg border border-sidebar-border/50 bg-sidebar-foreground/5 px-3 py-2 text-sm text-sidebar-foreground focus:border-sidebar-border focus:ring-2 focus:ring-sidebar-border/20 focus:outline-none"
                                >
                                    <option value="">All Categories</option>
                                    <option
                                        v-for="category in categories"
                                        :key="category"
                                        :value="category"
                                    >
                                        {{ category }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-sidebar-foreground/70"
                                >
                                    App
                                </label>
                                <select
                                    v-model="selectedApp"
                                    @change="applyFilters"
                                    class="w-full rounded-lg border border-sidebar-border/50 bg-sidebar-foreground/5 px-3 py-2 text-sm text-sidebar-foreground focus:border-sidebar-border focus:ring-2 focus:ring-sidebar-border/20 focus:outline-none"
                                >
                                    <option value="">All Apps</option>
                                    <option
                                        v-for="app in apps"
                                        :key="app.id"
                                        :value="String(app.id)"
                                    >
                                        {{ app.name }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-sidebar-foreground/70"
                                >
                                    User
                                </label>
                                <select
                                    v-model="selectedUser"
                                    @change="applyFilters"
                                    class="w-full rounded-lg border border-sidebar-border/50 bg-sidebar-foreground/5 px-3 py-2 text-sm text-sidebar-foreground focus:border-sidebar-border focus:ring-2 focus:ring-sidebar-border/20 focus:outline-none"
                                >
                                    <option value="">All Users</option>
                                    <option
                                        v-for="user in users"
                                        :key="user.id"
                                        :value="String(user.id)"
                                    >
                                        {{ user.name }} ({{ user.email }})
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-sidebar-foreground/70"
                                >
                                    Level
                                </label>
                                <select
                                    v-model="selectedLevel"
                                    @change="applyFilters"
                                    class="w-full rounded-lg border border-sidebar-border/50 bg-sidebar-foreground/5 px-3 py-2 text-sm text-sidebar-foreground focus:border-sidebar-border focus:ring-2 focus:ring-sidebar-border/20 focus:outline-none"
                                >
                                    <option value="">All Levels</option>
                                    <option
                                        v-for="level in levels"
                                        :key="level"
                                        :value="level"
                                    >
                                        {{ level.toUpperCase() }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-sidebar-foreground/70"
                                >
                                    Date From
                                </label>
                                <input
                                    v-model="dateFrom"
                                    @change="applyFilters"
                                    type="datetime-local"
                                    class="w-full rounded-lg border border-sidebar-border/50 bg-sidebar-foreground/5 px-3 py-2 text-sm text-sidebar-foreground focus:border-sidebar-border focus:ring-2 focus:ring-sidebar-border/20 focus:outline-none"
                                />
                            </div>

                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-sidebar-foreground/70"
                                >
                                    Date To
                                </label>
                                <input
                                    v-model="dateTo"
                                    @change="applyFilters"
                                    type="datetime-local"
                                    class="w-full rounded-lg border border-sidebar-border/50 bg-sidebar-foreground/5 px-3 py-2 text-sm text-sidebar-foreground focus:border-sidebar-border focus:ring-2 focus:ring-sidebar-border/20 focus:outline-none"
                                />
                            </div>
                        </div>
                    </Transition>
                </div>
            </div>

            <!-- Logs Table -->
            <div
                class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-sidebar dark:border-sidebar-border"
            >
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr
                                class="border-b border-sidebar-border/50 bg-sidebar-foreground/5"
                            >
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-sidebar-foreground/70 uppercase"
                                >
                                    Level
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-sidebar-foreground/70 uppercase"
                                >
                                    Category
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-sidebar-foreground/70 uppercase"
                                >
                                    Message
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-sidebar-foreground/70 uppercase"
                                >
                                    User
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-sidebar-foreground/70 uppercase"
                                >
                                    Date
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-if="logs.data.length === 0"
                                class="border-b border-sidebar-border/50"
                            >
                                <td
                                    colspan="5"
                                    class="px-4 py-8 text-center text-sm text-sidebar-foreground/50"
                                >
                                    No logs found
                                </td>
                            </tr>
                            <tr
                                v-for="log in logs.data"
                                :key="log.id"
                                class="border-b border-sidebar-border/50 transition-colors hover:bg-sidebar-foreground/5"
                            >
                                <td class="px-4 py-3">
                                    <span
                                        :class="[
                                            'inline-flex items-center gap-1 rounded-full border px-2 py-1 text-xs font-medium',
                                            levelColors[
                                                log.level as keyof typeof levelColors
                                            ] || levelColors.debug,
                                        ]"
                                    >
                                        <component
                                            :is="
                                                levelIcons[
                                                    log.level as keyof typeof levelIcons
                                                ] || Info
                                            "
                                            class="h-3 w-3"
                                        />
                                        {{ log.level.toUpperCase() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="text-sm text-sidebar-foreground/70"
                                    >
                                        {{ log.category }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="max-w-md">
                                        <p
                                            class="text-sm text-sidebar-foreground"
                                        >
                                            {{ log.message }}
                                        </p>
                                        <details
                                            v-if="log.context"
                                            class="mt-1"
                                        >
                                            <summary
                                                class="cursor-pointer text-xs text-sidebar-foreground/50 hover:text-sidebar-foreground/70"
                                            >
                                                View Context
                                            </summary>
                                            <pre
                                                class="mt-2 overflow-x-auto rounded bg-sidebar-foreground/5 p-2 text-xs text-sidebar-foreground/70"
                                                >{{
                                                    JSON.stringify(
                                                        log.context,
                                                        null,
                                                        2,
                                                    )
                                                }}</pre
                                            >
                                        </details>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div
                                        v-if="log.user"
                                        class="text-sm text-sidebar-foreground/70"
                                    >
                                        <div class="font-medium">
                                            {{ log.user.name }}
                                        </div>
                                        <div
                                            class="text-xs text-sidebar-foreground/50"
                                        >
                                            {{ log.user.email }}
                                        </div>
                                    </div>
                                    <span
                                        v-else
                                        class="text-xs text-sidebar-foreground/50"
                                    >
                                        System
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="text-xs text-sidebar-foreground/70"
                                    >
                                        {{ formatDate(log.created_at) }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="logs.last_page > 1"
                    class="flex items-center justify-between border-t border-sidebar-border/50 px-4 py-3"
                >
                    <div class="text-sm text-sidebar-foreground/70">
                        Showing {{ logs.from }} to {{ logs.to }} of
                        {{ logs.total }} results
                    </div>
                    <div class="flex gap-2">
                        <button
                            @click="goToPage(logs.current_page - 1)"
                            :disabled="logs.current_page === 1"
                            class="rounded-lg border border-sidebar-border/50 bg-sidebar-foreground/5 px-3 py-1 text-sm text-sidebar-foreground/70 transition-colors hover:bg-sidebar-foreground/10 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Previous
                        </button>
                        <button
                            @click="goToPage(logs.current_page + 1)"
                            :disabled="logs.current_page === logs.last_page"
                            class="rounded-lg border border-sidebar-border/50 bg-sidebar-foreground/5 px-3 py-1 text-sm text-sidebar-foreground/70 transition-colors hover:bg-sidebar-foreground/10 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="showDeleteDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Clear Logs</DialogTitle>
                    <DialogDescription>
                        {{
                            hasActiveFilters
                                ? 'Are you sure you want to delete all logs matching the current filters? This action cannot be undone.'
                                : 'Are you sure you want to delete all logs? This action cannot be undone.'
                        }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="showDeleteDialog = false">
                        Cancel
                    </Button>
                    <Button variant="destructive" @click="deleteLogs">
                        <Trash2 class="mr-2 h-4 w-4" />
                        {{
                            hasActiveFilters
                                ? 'Delete Filtered Logs'
                                : 'Delete All Logs'
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
