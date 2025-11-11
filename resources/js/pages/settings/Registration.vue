<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/registration';
import { type BreadcrumbItem } from '@/types';
import { Form, Head } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

interface Props {
    registration_enabled?: boolean;
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Registration settings',
        href: edit().url,
    },
];

const registrationEnabled = ref<boolean>(props.registration_enabled ?? false);

// Update form when props change (after successful save/redirect)
watch(
    () => props.registration_enabled,
    (newValue) => {
        registrationEnabled.value = newValue ?? false;
    },
);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Registration settings" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    title="Registration settings"
                    description="Control whether users can register new accounts"
                />

                <Form
                    :action="edit().url"
                    method="patch"
                    class="space-y-6"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <div
                        class="flex flex-row items-start space-y-0 gap-x-3 rounded-md border p-4 shadow"
                    >
                        <Checkbox
                            id="registration_enabled"
                            v-model="registrationEnabled"
                            name="registration_enabled"
                        />

                        <div class="space-y-1 leading-none">
                            <Label
                                for="registration_enabled"
                                class="cursor-pointer text-sm leading-none font-medium peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                            >
                                Enable user registration
                            </Label>

                            <p class="text-sm text-muted-foreground">
                                When enabled, the /register route is active and
                                users can create new accounts. When disabled,
                                registration routes and functions are inactive.
                                Registration is still allowed only if no users
                                exist in the system (for initial setup).
                            </p>

                            <InputError
                                v-if="errors.registration_enabled"
                                class="mt-1"
                                :message="errors.registration_enabled"
                            />
                        </div>
                    </div>

                    <input
                        type="hidden"
                        name="registration_enabled"
                        :value="registrationEnabled ? '1' : '0'"
                    />

                    <div class="flex items-center gap-4">
                        <Button type="submit" :disabled="processing">
                            Save
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
                    </div>
                </Form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
