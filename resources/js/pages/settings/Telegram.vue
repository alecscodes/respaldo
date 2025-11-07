<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/telegram';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Props {
    telegram_bot_token?: string;
    telegram_chat_id?: string;
}

const props = defineProps<Props>();
const page = usePage();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Telegram settings',
        href: edit().url,
    },
];

const botToken = ref<string>(props.telegram_bot_token || '');
const chatId = ref<string>(props.telegram_chat_id || '');
const isTesting = ref(false);
const testMessage = ref('');
const testMessageType = ref<'success' | 'error' | null>(null);

const canTest = computed(() => {
    return botToken.value.trim() !== '' && chatId.value.trim() !== '';
});

const sendTestMessage = (): void => {
    if (!canTest.value || isTesting.value) {
        return;
    }

    isTesting.value = true;
    testMessage.value = '';
    testMessageType.value = null;

    router.post(
        '/settings/telegram/test',
        {
            telegram_bot_token: botToken.value,
            telegram_chat_id: chatId.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                const message = page.props.message as string;
                const success = page.props.success;

                if (message || success !== undefined) {
                    testMessage.value =
                        message ||
                        (success
                            ? 'Test message sent successfully!'
                            : 'Failed to send test message.');
                    testMessageType.value = success ? 'success' : 'error';
                }
            },
            onError: (errors) => {
                testMessage.value =
                    errors.message || 'Failed to send test message.';
                testMessageType.value = 'error';
            },
            onFinish: () => {
                isTesting.value = false;
            },
        },
    );
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Telegram settings" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    title="Telegram notifications"
                    description="Configure Telegram notifications for backup alerts and errors"
                />

                <Form
                    :action="edit().url"
                    method="patch"
                    class="space-y-6"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <div class="grid gap-2">
                        <Label for="telegram_bot_token"
                            >Telegram Bot Token</Label
                        >
                        <Input
                            id="telegram_bot_token"
                            name="telegram_bot_token"
                            type="password"
                            :default-value="telegram_bot_token"
                            v-model="botToken"
                            placeholder="123456789:ABCdefGHIjklMNOpqrsTUVwxyz"
                        />
                        <InputError
                            class="mt-2"
                            :message="errors.telegram_bot_token"
                        />
                        <p class="text-sm text-muted-foreground">
                            Create a bot using @BotFather on Telegram to get
                            your bot token
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="telegram_chat_id">Telegram Chat ID</Label>
                        <Input
                            id="telegram_chat_id"
                            name="telegram_chat_id"
                            :default-value="telegram_chat_id"
                            v-model="chatId"
                            placeholder="123456789"
                        />
                        <InputError
                            class="mt-2"
                            :message="errors.telegram_chat_id"
                        />
                        <p class="text-sm text-muted-foreground">
                            Send a message to your bot and visit
                            https://api.telegram.org/bot&lt;your_bot_token&gt;/getUpdates
                            to find your chat ID
                        </p>
                    </div>

                    <div class="flex flex-col gap-4">
                        <div class="flex items-center gap-4">
                            <Button
                                type="button"
                                variant="outline"
                                :disabled="!canTest || isTesting || processing"
                                @click="sendTestMessage"
                            >
                                {{ isTesting ? 'Sending...' : 'Test Message' }}
                            </Button>

                            <Transition
                                enter-active-class="transition ease-in-out"
                                enter-from-class="opacity-0"
                                leave-active-class="transition ease-in-out"
                                leave-to-class="opacity-0"
                            >
                                <p
                                    v-show="recentlySuccessful"
                                    class="text-sm text-neutral-600"
                                >
                                    Saved.
                                </p>
                            </Transition>

                            <Button :disabled="processing">Save</Button>
                        </div>

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-show="testMessage"
                                :class="{
                                    'text-sm text-green-600':
                                        testMessageType === 'success',
                                    'text-sm text-red-600':
                                        testMessageType === 'error',
                                }"
                            >
                                {{ testMessage }}
                            </p>
                        </Transition>
                    </div>
                </Form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
