<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

type OrganizationPayload = {
    id: number;
    source_url: string;
    normalized_url: string | null;
    yandex_object_id: string | null;
    sync_status: string;
    title: string | null;
    address: string | null;
    rating: string | null;
    ratings_count: number;
    reviews_count: number;
    last_sync_started_at: string | null;
    last_sync_finished_at: string | null;
    last_sync_error: string | null;
};

type ReviewPayload = {
    id: number;
    author_name: string;
    author_avatar_url: string | null;
    reviewed_at: string | null;
    text: string | null;
    rating: number | null;
};

type ReviewsPage = {
    data: ReviewPayload[];
    meta: {
        current_page: number;
        per_page: number;
        total: number;
        last_page: number;
        from: number | null;
        to: number | null;
    };
    links: {
        next: string | null;
        prev: string | null;
    };
};

const props = defineProps<{
    organization: OrganizationPayload | null;
    reviews: ReviewsPage;
}>();

const reviewsPage = ref<ReviewsPage>(props.reviews);
const reviewsLoading = ref(false);

const form = useForm({
    source_url: props.organization?.source_url ?? '',
});

watch(
    () => props.organization?.source_url,
    (sourceUrl) => {
        if (sourceUrl !== undefined) {
            form.source_url = sourceUrl;
        }
    },
);

watch(
    () => props.reviews,
    (reviews) => {
        reviewsPage.value = reviews;
    },
);

const syncStatusLabel = computed(() => {
    const status = props.organization?.sync_status;

    if (! status) {
        return null;
    }

    return status.replaceAll('_', ' ');
});

const submit = (): void => {
    form.post('/organization');
};

const loadReviewsPage = async (page: number): Promise<void> => {
    if (reviewsLoading.value || page < 1) {
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

        reviewsPage.value = await response.json() as ReviewsPage;
    } finally {
        reviewsLoading.value = false;
    }
};
</script>

<template>
    <Head title="Organization" />

    <AuthenticatedLayout title="Organization settings">
        <section class="space-y-8">
            <div class="rounded-lg border border-slate-200 bg-white p-6">
                <h2 class="text-base font-semibold text-slate-900">
                    Yandex Maps link
                </h2>

                <form
                    class="mt-4 space-y-4"
                    @submit.prevent="submit"
                >
                    <div>
                        <label
                            class="mb-1 block text-sm font-medium text-slate-700"
                            for="source-url"
                        >
                            Organization URL
                        </label>
                        <input
                            id="source-url"
                            v-model="form.source_url"
                            type="url"
                            name="source_url"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none"
                            :class="{ 'border-red-500': form.errors.source_url }"
                            placeholder="https://yandex.ru/maps/org/..."
                        >
                        <p
                            v-if="form.errors.source_url"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ form.errors.source_url }}
                        </p>
                    </div>

                    <button
                        type="submit"
                        class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Saving…' : 'Save link' }}
                    </button>
                </form>

                <dl
                    v-if="organization"
                    class="mt-6 space-y-3 border-t border-slate-200 pt-6 text-sm"
                >
                    <div v-if="organization.title">
                        <dt class="font-medium text-slate-700">
                            Organization
                        </dt>
                        <dd class="mt-1 text-slate-900">
                            {{ organization.title }}
                        </dd>
                    </div>

                    <div v-if="organization.address">
                        <dt class="font-medium text-slate-700">
                            Address
                        </dt>
                        <dd class="mt-1 text-slate-900">
                            {{ organization.address }}
                        </dd>
                    </div>

                    <div v-if="organization.rating !== null">
                        <dt class="font-medium text-slate-700">
                            Rating
                        </dt>
                        <dd class="mt-1 text-slate-900">
                            {{ organization.rating }}
                            <span class="text-slate-500">
                                ({{ organization.ratings_count }} ratings, {{ organization.reviews_count }} reviews)
                            </span>
                        </dd>
                    </div>

                    <div>
                        <dt class="font-medium text-slate-700">
                            Sync status
                        </dt>
                        <dd class="mt-1 capitalize text-slate-900">
                            {{ syncStatusLabel }}
                        </dd>
                    </div>

                    <div v-if="organization.sync_status === 'failed' && organization.last_sync_error">
                        <dt class="font-medium text-slate-700">
                            Last sync error
                        </dt>
                        <dd class="mt-1 text-red-600">
                            {{ organization.last_sync_error }}
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-6">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-base font-semibold text-slate-900">
                        Reviews
                    </h2>
                    <p
                        v-if="reviewsPage.meta.total > 0"
                        class="text-sm text-slate-500"
                    >
                        {{ reviewsPage.meta.from }}–{{ reviewsPage.meta.to }} of {{ reviewsPage.meta.total }}
                    </p>
                </div>

                <p
                    v-if="reviewsPage.data.length === 0"
                    class="mt-2 text-sm text-slate-600"
                >
                    No reviews in the database yet.
                </p>

                <ul
                    v-else
                    class="mt-4 divide-y divide-slate-200"
                >
                    <li
                        v-for="review in reviewsPage.data"
                        :key="review.id"
                        class="py-4"
                    >
                        <div class="flex items-center justify-between gap-4">
                            <p class="text-sm font-medium text-slate-900">
                                {{ review.author_name }}
                            </p>
                            <p
                                v-if="review.rating !== null"
                                class="text-sm text-slate-600"
                            >
                                {{ review.rating }}/5
                            </p>
                        </div>
                        <p
                            v-if="review.reviewed_at"
                            class="mt-1 text-xs text-slate-500"
                        >
                            {{ review.reviewed_at }}
                        </p>
                        <p
                            v-if="review.text"
                            class="mt-2 text-sm text-slate-700"
                        >
                            {{ review.text }}
                        </p>
                    </li>
                </ul>

                <div
                    v-if="reviewsPage.meta.last_page > 1"
                    class="mt-4 flex items-center justify-between gap-4"
                >
                    <button
                        type="button"
                        class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="reviewsLoading || reviewsPage.meta.current_page <= 1"
                        @click="loadReviewsPage(reviewsPage.meta.current_page - 1)"
                    >
                        Previous
                    </button>
                    <span class="text-sm text-slate-600">
                        Page {{ reviewsPage.meta.current_page }} of {{ reviewsPage.meta.last_page }}
                    </span>
                    <button
                        type="button"
                        class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="reviewsLoading || reviewsPage.meta.current_page >= reviewsPage.meta.last_page"
                        @click="loadReviewsPage(reviewsPage.meta.current_page + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
