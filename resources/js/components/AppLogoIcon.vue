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
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" :class="className" fill="none" v-bind="$attrs">
        <path
            d="M14 5.5h14l8 8v25A4.5 4.5 0 0 1 31.5 43h-17A4.5 4.5 0 0 1 10 38.5V10a4.5 4.5 0 0 1 4-4.47Z"
            stroke="currentColor"
            stroke-width="3"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
        <path d="M28 6v8h8M17.5 22l6 13 6-13" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
</template>
