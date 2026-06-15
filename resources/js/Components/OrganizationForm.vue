<script setup lang="ts">
import type { Organization } from '@/types/domain';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps<{
    organization: Organization | null;
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

const isSyncInProgress = computed(() => {
    if (! props.organization) {
        return false;
    }

    return props.organization.sync_status === 'queued'
        || props.organization.sync_status === 'running';
});

const submit = (): void => {
    if (isSyncInProgress.value || form.processing) {
        return;
    }

    form.post('/organization', {
        preserveScroll: true,
    });
};
</script>

<template>
    <section class="rounded-lg border border-slate-200 bg-white p-6">
        <h2 class="text-base font-semibold text-slate-900">
            Yandex Maps link
        </h2>
        <p class="mt-1 text-sm text-slate-600">
            Paste a link to the organization card on Yandex Maps
        </p>

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
                    inputmode="url"
                    autocomplete="url"
                    class="w-full min-w-0 rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none"
                    :class="{ 'border-red-500': form.errors.source_url }"
                    placeholder="https://yandex.ru/maps/org/..."
                >
                <p
                    v-if="form.errors.source_url"
                    class="mt-1 text-sm text-red-600 break-words"
                >
                    {{ form.errors.source_url }}
                </p>
            </div>

            <div>
                <button
                    type="submit"
                    class="inline-flex min-w-[6.5rem] items-center justify-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="isSyncInProgress || form.processing"
                    :aria-busy="form.processing"
                >
                    {{ form.processing ? 'Saving and syncing…' : 'Save and sync' }}
                </button>
            </div>
        </form>
    </section>
</template>
