<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 40 40"
        :class="className"
        v-bind="$attrs"
    >
        <!-- Shield background -->
        <path
            fill="currentColor"
            fill-rule="evenodd"
            clip-rule="evenodd"
            d="M20 2L6 8v12c0 8 6 16 14 18 8-2 14-10 14-18V8L20 2zm0 2.5L7.5 9.5v10.5c0 7.2 5.3 14.1 12.5 15.9 7.2-1.8 12.5-8.7 12.5-15.9V9.5L20 4.5z"
        />
        <!-- Circular rewind arrow -->
        <path
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M12 20a8 8 0 0 1 16 0"
        />
        <!-- Arrowhead pointing counter-clockwise (rewind) -->
        <path
            fill="currentColor"
            d="M12 20l-3-3 3-3v6z"
        />
        <line
            x1="12"
            y1="20"
            x2="16"
            y2="20"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
        />
    </svg>
</template>
