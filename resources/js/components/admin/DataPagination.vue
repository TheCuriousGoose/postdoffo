<script setup lang="ts" generic="T">
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
import type { Paginated } from '@/types';

const props = defineProps<{
    meta: Paginated<T>;
}>();

const emit = defineEmits<{
    (e: 'update:page', page: number): void;
}>();
</script>

<template>
    <div
        class="flex flex-col-reverse items-center justify-between gap-4 px-6 py-4 sm:flex-row"
    >
        <p class="text-sm text-muted-foreground tabular-nums">
            <template v-if="props.meta.total > 0">
                Showing
                <span class="font-medium text-foreground">{{
                    props.meta.from
                }}</span>
                –<span class="font-medium text-foreground">{{
                    props.meta.to
                }}</span>
                of
                <span class="font-medium text-foreground">{{
                    props.meta.total
                }}</span>
            </template>
            <template v-else>No results</template>
        </p>

        <Pagination
            v-if="props.meta.last_page > 1"
            :items-per-page="props.meta.per_page"
            :total="props.meta.total"
            :default-page="props.meta.current_page"
            :page="props.meta.current_page"
            :sibling-count="1"
            show-edges
            class="mx-0 w-auto justify-end"
            @update:page="emit('update:page', $event)"
        >
            <PaginationContent v-slot="{ items }">
                <PaginationPrevious />
                <template v-for="(item, index) in items" :key="index">
                    <PaginationItem
                        v-if="item.type === 'page'"
                        :value="item.value"
                        :is-active="item.value === props.meta.current_page"
                    />
                    <PaginationEllipsis v-else :index="index" />
                </template>
                <PaginationNext />
            </PaginationContent>
        </Pagination>
    </div>
</template>
