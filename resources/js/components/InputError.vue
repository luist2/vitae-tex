<script setup lang="ts">
import { inputErrorAnnouncementKey } from '@/lib/inputErrorAccessibility';
import { inject } from 'vue';

defineProps<{
    message?: string;
}>();

const announce = inject(inputErrorAnnouncementKey, true);
</script>

<template>
    <p
        v-show="message"
        :role="announce ? 'alert' : undefined"
        :aria-atomic="announce ? 'true' : undefined"
        class="text-sm text-red-600 dark:text-red-500"
    >
        {{ message }}
    </p>
</template>
