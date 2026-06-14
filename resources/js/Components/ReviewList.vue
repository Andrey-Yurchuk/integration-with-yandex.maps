<script setup lang="ts">
import type { Organization, PaginatedReviews } from '@/types/domain';
import { computed } from 'vue';

const props = defineProps<{
    organization: Organization | null;
    reviews: PaginatedReviews;
    loading?: boolean;
}>();

const isSyncing = computed(() => {
    const status = props.organization?.sync_status;

    return status === 'awaiting' || status === 'queued' || status === 'running';
});

const emptyMessage = computed((): string | null => {
    if (! props.organization) {
        return 'Save an organization link above to load reviews';
    }

    if (props.reviews.data.length > 0) {
        return null;
    }

    if (isSyncing.value) {
        return 'Synchronization in progress, reviews will appear when sync completes';
    }

    if (props.organization.sync_status === 'failed') {
        return 'Synchronization failed, check the error in the summary and try saving the link again';
    }

    return 'No reviews found for this organization';
});

const formatReviewDate = (value: string | null): string | null => {
    if (! value) {
        return null;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
};
</script>

<template>
    <section class="rounded-lg border border-slate-200 bg-white p-6">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="text-base font-semibold text-slate-900">
                Reviews
            </h2>
            <p
                v-if="reviews.meta.total > 0"
                class="text-sm text-slate-500"
            >
                {{ reviews.meta.from }}–{{ reviews.meta.to }} of {{ reviews.meta.total }}
            </p>
        </div>

        <p
            v-if="loading && reviews.data.length === 0"
            class="mt-3 text-sm text-slate-600"
        >
            Loading reviews…
        </p>

        <p
            v-else-if="emptyMessage"
            class="mt-3 text-sm text-slate-600"
        >
            {{ emptyMessage }}
        </p>

        <ul
            v-else
            class="mt-4 divide-y divide-slate-200"
            :class="{ 'opacity-60': loading }"
        >
            <li
                v-for="review in reviews.data"
                :key="review.id"
                class="py-4 first:pt-0 last:pb-0"
            >
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <p class="text-sm font-medium text-slate-900 break-words">
                        {{ review.author_name }}
                    </p>
                    <p
                        v-if="review.rating !== null"
                        class="shrink-0 text-sm text-slate-600"
                    >
                        {{ review.rating }}/5
                    </p>
                </div>
                <p
                    v-if="review.reviewed_at"
                    class="mt-1 text-xs text-slate-500"
                >
                    {{ formatReviewDate(review.reviewed_at) }}
                </p>
                <p
                    v-if="review.text"
                    class="mt-2 whitespace-pre-wrap break-words text-sm text-slate-700"
                >
                    {{ review.text }}
                </p>
            </li>
        </ul>
    </section>
</template>
