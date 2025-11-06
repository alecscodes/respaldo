<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Download } from 'lucide-vue-next';

const downloadScript = () => {
    window.location.href = '/script/download';
};
</script>

<template>
    <Button @click="downloadScript" class="w-full sm:w-auto">
        <Download class="mr-2 h-4 w-4 flex-shrink-0" />
        <span class="truncate">Download Script</span>
    </Button>
</template>
