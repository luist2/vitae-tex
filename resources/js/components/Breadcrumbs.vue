<script setup lang="ts">
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import type { BreadcrumbItem as BreadcrumbItemData } from '@/types';

defineProps<{
    breadcrumbs: BreadcrumbItemData[];
}>();
</script>

<template>
    <Breadcrumb>
        <BreadcrumbList>
            <template v-for="(item, index) in breadcrumbs" :key="item.href">
                <BreadcrumbItem>
                    <BreadcrumbPage v-if="index === breadcrumbs.length - 1">
                        {{ item.title }}
                    </BreadcrumbPage>
                    <BreadcrumbLink v-else :href="item.href">
                        {{ item.title }}
                    </BreadcrumbLink>
                </BreadcrumbItem>
                <BreadcrumbSeparator v-if="index < breadcrumbs.length - 1" />
            </template>
        </BreadcrumbList>
    </Breadcrumb>
</template>
