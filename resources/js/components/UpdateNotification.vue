<script setup lang="ts">
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Form, router, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    CheckCircle2,
    Download,
    RefreshCw,
} from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

interface UpdateInfo {
    available: boolean;
    current_commit: string | null;
    remote_commit: string | null;
    commits_behind: number;
    error: string | null;
}

interface UpdateResult {
    success: boolean;
    message: string;
    output: string | null;
    error: string | null;
}

interface FlashData {
    updateResult?: UpdateResult;
}

const page = usePage();
const updateInfo = ref<UpdateInfo | null>(null);
const isChecking = ref(false);
const updateResult = ref<UpdateResult | null>(null);
const showUpdateResult = ref(false);

const checkForUpdates = async (): Promise<void> => {
    isChecking.value = true;
    updateResult.value = null;
    showUpdateResult.value = false;

    try {
        const response = await fetch('/settings/updates/check');
        const data: UpdateInfo = await response.json();
        updateInfo.value = data;
    } catch (error) {
        updateInfo.value = {
            available: false,
            current_commit: null,
            remote_commit: null,
            commits_behind: 0,
            error:
                error instanceof Error
                    ? error.message
                    : 'Failed to check for updates',
        };
    } finally {
        isChecking.value = false;
    }
};

const handleUpdateSuccess = (): void => {
    setTimeout(() => {
        const flash = (page.props.flash as FlashData)?.updateResult;
        if (flash) {
            updateResult.value = flash;
            showUpdateResult.value = true;
            if (flash.success) {
                setTimeout(() => router.reload(), 2000);
            } else {
                setTimeout(() => checkForUpdates(), 1000);
            }
        }
    }, 100);
};

onMounted(() => {
    checkForUpdates();
});
</script>

<template>
    <div
        v-if="updateInfo && (updateInfo.available || updateInfo.error)"
        class="mb-4"
    >
        <Alert
            :variant="updateInfo.error ? 'destructive' : 'default'"
            class="border-blue-500/50 bg-blue-500/10 dark:border-blue-500/50 dark:bg-blue-500/10"
        >
            <AlertCircle v-if="updateInfo.error" class="size-4" />
            <Download v-else class="size-4" />

            <AlertTitle>
                {{
                    updateInfo.error
                        ? 'Unable to check for updates'
                        : 'Update Available'
                }}
            </AlertTitle>

            <AlertDescription>
                <div class="space-y-2">
                    <p v-if="updateInfo.error" class="text-sm">
                        {{ updateInfo.error }}
                    </p>

                    <div v-else-if="updateInfo.available" class="space-y-2">
                        <p class="text-sm">
                            There are
                            <strong>{{ updateInfo.commits_behind }}</strong>
                            new commit(s) available.
                        </p>

                        <div
                            v-if="showUpdateResult && updateResult"
                            class="mt-2"
                        >
                            <Alert
                                :variant="
                                    updateResult.success
                                        ? 'default'
                                        : 'destructive'
                                "
                                class="mt-2"
                            >
                                <CheckCircle2
                                    v-if="updateResult.success"
                                    class="size-4"
                                />
                                <AlertCircle v-else class="size-4" />

                                <AlertTitle>
                                    {{ updateResult.message }}
                                </AlertTitle>

                                <AlertDescription v-if="updateResult.error">
                                    <p class="text-sm">
                                        {{ updateResult.error }}
                                    </p>
                                </AlertDescription>

                                <AlertDescription
                                    v-if="
                                        updateResult.output &&
                                        updateResult.success
                                    "
                                >
                                    <pre
                                        class="mt-2 max-h-40 overflow-auto rounded bg-muted p-2 text-xs"
                                    >
                                        {{ updateResult.output }}
                                    </pre>
                                </AlertDescription>
                            </Alert>
                        </div>

                        <div class="flex gap-2">
                            <Form
                                action="/settings/updates/update"
                                method="post"
                                preserve-scroll
                                @success="handleUpdateSuccess"
                            >
                                <template #default="{ processing }">
                                    <Button
                                        :disabled="processing || isChecking"
                                        size="sm"
                                        type="submit"
                                    >
                                        <RefreshCw
                                            v-if="processing"
                                            class="mr-2 size-4 animate-spin"
                                        />
                                        <Download v-else class="mr-2 size-4" />
                                        {{
                                            processing
                                                ? 'Updating...'
                                                : 'Update Now'
                                        }}
                                    </Button>
                                </template>
                            </Form>

                            <Button
                                :disabled="isChecking"
                                size="sm"
                                variant="outline"
                                @click="checkForUpdates"
                            >
                                <RefreshCw
                                    v-if="isChecking"
                                    class="mr-2 size-4 animate-spin"
                                />
                                <RefreshCw v-else class="mr-2 size-4" />
                                Refresh
                            </Button>
                        </div>
                    </div>
                </div>
            </AlertDescription>
        </Alert>
    </div>
</template>
