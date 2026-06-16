<script setup lang="ts">
import { Download } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { download } from '@/routes/script';

const downloadScript = () => {
    window.location.href = download.url();
};
</script>

<template>
    <Button @click="downloadScript" class="w-full">
        <Download class="mr-2 h-4 w-4 flex-shrink-0" />
        <span class="truncate">Download Script</span>
    </Button>
</template>
