<script setup lang="ts">
import { cn } from '@/lib/utils';
import {
    DialogContent,
    type DialogContentEmits,
    type DialogContentProps,
    DialogPortal,
    useForwardPropsEmits,
} from 'reka-ui';
import { computed } from 'vue';
import SheetOverlay from '../sheet/SheetOverlay.vue';

interface ActionSheetButton {
    text: string;
    role?: 'destructive' | 'cancel' | 'default';
    icon?: any;
    handler?: () => void | boolean | Promise<void | boolean>;
    disabled?: boolean;
}

interface ActionSheetProps extends DialogContentProps {
    class?: string;
    header?: string;
    subHeader?: string;
    buttons: ActionSheetButton[];
}

const props = defineProps<ActionSheetProps>();
const emits = defineEmits<DialogContentEmits & { action: [button: ActionSheetButton] }>();

const delegatedProps = useForwardPropsEmits(props, emits);

const sortedButtons = computed(() => {
    // Sort buttons: destructive first, then regular, cancel last
    const buttons = [...props.buttons];
    const cancelButtons = buttons.filter((b) => b.role === 'cancel');
    const destructiveButtons = buttons.filter(
        (b) => b.role === 'destructive',
    );
    const regularButtons = buttons.filter(
        (b) => b.role !== 'cancel' && b.role !== 'destructive',
    );

    return [...destructiveButtons, ...regularButtons, ...cancelButtons];
});

const handleButtonClick = async (button: ActionSheetButton) => {
    if (button.disabled || !button.handler) {
        return;
    }

    const result = await button.handler();
    // Emit action event so parent can close the sheet if needed
    emits('action', button);
    // If handler returns false, don't close the sheet
    if (result === false) {
        return;
    }
};
</script>

<template>
    <DialogPortal>
        <SheetOverlay />
        <DialogContent
            data-slot="action-sheet-content"
            :class="
                cn(
                    'fixed inset-x-0 bottom-0 z-50 mx-auto w-full max-w-lg rounded-t-xl border-t bg-background p-0 shadow-lg data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[state=closed]:slide-out-to-bottom data-[state=open]:slide-in-from-bottom',
                    props.class,
                )
            "
            v-bind="delegatedProps"
        >
            <div class="flex flex-col">
                <!-- Handle bar -->
                <div class="flex justify-center pt-3 pb-2">
                    <div class="h-1 w-12 rounded-full bg-muted-foreground/30" />
                </div>

                <!-- Header -->
                <div v-if="header || subHeader" class="px-4 pb-2">
                    <h3 v-if="header" class="text-center text-lg font-semibold">
                        {{ header }}
                    </h3>
                    <p
                        v-if="subHeader"
                        class="text-center text-sm text-muted-foreground"
                    >
                        {{ subHeader }}
                    </p>
                </div>

                <!-- Buttons -->
                <div class="flex flex-col divide-y divide-border overflow-hidden">
                    <button
                        v-for="(button, index) in sortedButtons"
                        :key="index"
                        :disabled="button.disabled"
                        :class="
                            cn(
                                'flex w-full items-center justify-center gap-3 px-4 py-3 text-base font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none',
                                button.role === 'destructive' &&
                                    'text-destructive hover:bg-destructive/10 active:bg-destructive/20',
                                button.role === 'cancel' &&
                                    'font-semibold hover:bg-accent active:bg-accent/80',
                                button.role !== 'destructive' &&
                                    button.role !== 'cancel' &&
                                    'hover:bg-accent active:bg-accent/80',
                            )
                        "
                        @click="handleButtonClick(button)"
                    >
                        <component
                            v-if="button.icon"
                            :is="button.icon"
                            class="h-5 w-5"
                        />
                        <span>{{ button.text }}</span>
                    </button>
                </div>
            </div>
        </DialogContent>
    </DialogPortal>
</template>

