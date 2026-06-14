<script setup lang="ts">
import OrganizationForm from '@/Components/OrganizationForm.vue';
import OrganizationSummary from '@/Components/OrganizationSummary.vue';
import Pagination from '@/Components/Pagination.vue';
import ReviewList from '@/Components/ReviewList.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type {
    Organization,
    PaginatedReviews,
    SyncStatus,
} from '@/types/domain';
import { Head, router } from '@inertiajs/vue3';
import { onUnmounted, ref, watch } from 'vue';

const POLL_INTERVAL_MS = 2500;

const props = defineProps<{
    organization: Organization | null;
    reviews: PaginatedReviews;
}>();

const organization = ref<Organization | null>(props.organization);
const reviews = ref<PaginatedReviews>(props.reviews);
const reviewsLoading = ref(false);

let pollTimer: ReturnType<typeof setInterval> | null = null;
let pollInFlight = false;

watch(
    () => props.organization,
    (value) => {
        organization.value = value;
    },
);

watch(
    () => props.reviews,
    (value) => {
        reviews.value = value;
    },
);

const isActiveSyncStatus = (status: Organization['sync_status'] | null | undefined): boolean => {
    return status === 'awaiting' || status === 'queued' || status === 'running';
};

const stopPolling = (): void => {
    if (pollTimer !== null) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
};

const applySyncStatus = (status: SyncStatus): void => {
    if (! organization.value || status.organization_id === null) {
        return;
    }

    organization.value = {
        ...organization.value,
        sync_status: status.sync_status ?? organization.value.sync_status,
        last_sync_started_at: status.last_sync_started_at,
        last_sync_finished_at: status.last_sync_finished_at,
        last_sync_error: status.last_sync_error,
        rating: status.rating ?? organization.value.rating,
        ratings_count: status.ratings_count ?? organization.value.ratings_count,
        reviews_count: status.reviews_count ?? organization.value.reviews_count,
    };
};

const loadReviewsPage = async (page: number): Promise<void> => {
    if (reviewsLoading.value || page < 1 || page > reviews.value.meta.last_page) {
        return;
    }

    reviewsLoading.value = true;

    try {
        const response = await fetch(`/organization/reviews?page=${page}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (! response.ok) {
            return;
        }

        reviews.value = await response.json() as PaginatedReviews;
    } finally {
        reviewsLoading.value = false;
    }
};

const pollSyncStatus = async (): Promise<void> => {
    if (pollInFlight) {
        return;
    }

    pollInFlight = true;

    try {
        const response = await fetch('/organization/sync-status', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (! response.ok) {
            return;
        }

        const status = await response.json() as SyncStatus;

        applySyncStatus(status);

        if (status.sync_status === 'succeeded' || status.sync_status === 'failed') {
            stopPolling();

            router.reload({
                only: ['organization', 'reviews'],
                preserveScroll: true,
            });
        }
    } finally {
        pollInFlight = false;
    }
};

const startPolling = (): void => {
    if (pollTimer !== null) {
        return;
    }

    void pollSyncStatus();

    pollTimer = setInterval(() => {
        void pollSyncStatus();
    }, POLL_INTERVAL_MS);
};

watch(
    () => organization.value?.sync_status,
    (status) => {
        if (isActiveSyncStatus(status)) {
            startPolling();
        } else {
            stopPolling();
        }
    },
    { immediate: true },
);

onUnmounted(() => {
    stopPolling();
});
</script>

<template>
    <Head title="Organization" />

    <AuthenticatedLayout title="Organization settings">
        <div class="space-y-6">
            <OrganizationForm :organization="organization" />

            <OrganizationSummary :organization="organization" />

            <div class="space-y-4">
                <ReviewList
                    :organization="organization"
                    :reviews="reviews"
                    :loading="reviewsLoading"
                />

                <Pagination
                    :meta="reviews.meta"
                    :links="reviews.links"
                    :loading="reviewsLoading"
                    @page-change="loadReviewsPage"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
