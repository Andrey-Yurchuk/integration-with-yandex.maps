<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Organization" />

    <AuthenticatedLayout title="Organization settings">
        <section class="space-y-8">
            <div class="rounded-lg border border-slate-200 bg-white p-6">
                <h2 class="text-base font-semibold text-slate-900">
                    Yandex Maps link
                </h2>
                <p class="mt-1 text-sm text-slate-600">
                    Paste a link to the organization card. Saving and synchronization will be added on the next stages.
                </p>

                <form class="mt-4 space-y-4" @submit.prevent>
                    <div>
                        <label
                            class="mb-1 block text-sm font-medium text-slate-700"
                            for="source-url"
                        >
                            Organization URL
                        </label>
                        <input
                            id="source-url"
                            type="url"
                            name="source_url"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none"
                            placeholder="https://yandex.ru/maps/org/..."
                        >
                    </div>

                    <button
                        type="submit"
                        class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
                    >
                        Save link
                    </button>
                </form>
            </div>

            <div class="rounded-lg border border-dashed border-slate-300 bg-white p-6">
                <h2 class="text-base font-semibold text-slate-900">
                    Reviews
                </h2>
                <p class="mt-2 text-sm text-slate-600">
                    Organization summary, sync status, and paginated reviews will appear here after parser integration.
                </p>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
