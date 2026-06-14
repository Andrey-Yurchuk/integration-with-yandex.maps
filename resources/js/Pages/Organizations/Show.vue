<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

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

const props = defineProps<{
    organization: OrganizationPayload | null;
}>();

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
                    <div>
                        <dt class="font-medium text-slate-700">
                            Saved link
                        </dt>
                        <dd class="mt-1 break-all text-slate-900">
                            {{ organization.source_url }}
                        </dd>
                    </div>

                    <div v-if="organization.normalized_url">
                        <dt class="font-medium text-slate-700">
                            Normalized URL
                        </dt>
                        <dd class="mt-1 break-all text-slate-900">
                            {{ organization.normalized_url }}
                        </dd>
                    </div>

                    <div v-if="organization.yandex_object_id">
                        <dt class="font-medium text-slate-700">
                            Object ID
                        </dt>
                        <dd class="mt-1 text-slate-900">
                            {{ organization.yandex_object_id }}
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
                </dl>
            </div>

            <div class="rounded-lg border border-dashed border-slate-300 bg-white p-6">
                <h2 class="text-base font-semibold text-slate-900">
                    Reviews
                </h2>
                <p class="mt-2 text-sm text-slate-600">
                    Reviews will appear here after synchronization is implemented.
                </p>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
