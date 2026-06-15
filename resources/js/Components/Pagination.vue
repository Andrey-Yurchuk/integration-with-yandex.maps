<script setup lang="ts">
import type { PaginationLinks, PaginationMeta } from '@/types/domain';

defineProps<{
    meta: PaginationMeta;
    links: PaginationLinks;
    loading?: boolean;
}>();

const emit = defineEmits<{
    'page-change': [page: number];
}>();

const goToPage = (page: number): void => {
    emit('page-change', page);
};
</script>

<template>
    <nav
        v-if="meta.last_page > 1"
        class="flex flex-wrap items-center justify-between gap-4"
        aria-label="Reviews pagination"
    >
        <button
            type="button"
            class="inline-flex min-w-[5.5rem] cursor-pointer items-center justify-center rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="loading || meta.current_page <= 1"
            @click="goToPage(meta.current_page - 1)"
        >
            Previous
        </button>

        <p class="text-sm text-slate-600">
            <span v-if="loading">Loading…</span>
            <span v-else>
                Page {{ meta.current_page }} of {{ meta.last_page }}
            </span>
        </p>

        <button
            type="button"
            class="inline-flex min-w-[5.5rem] cursor-pointer items-center justify-center rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="loading || meta.current_page >= meta.last_page"
            @click="goToPage(meta.current_page + 1)"
        >
            Next
        </button>
    </nav>
</template>
