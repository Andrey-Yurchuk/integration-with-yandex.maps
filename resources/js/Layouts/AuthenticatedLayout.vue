<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{
    title?: string;
}>();

const page = usePage();

const userName = computed(() => {
    const user = page.props.auth?.user as { name?: string } | null;

    return user?.name ?? 'User';
});
</script>

<template>
    <div class="min-h-screen">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                <div>
                    <p class="text-sm font-medium text-slate-500">
                        Yandex Maps integration
                    </p>
                    <h1 class="text-lg font-semibold text-slate-900">
                        {{ title ?? 'Organization settings' }}
                    </h1>
                </div>

                <div class="flex items-center gap-4">
                    <span class="text-sm text-slate-600">
                        {{ userName }}
                    </span>

                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        type="button"
                        class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50"
                    >
                        Sign out
                    </Link>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
            <slot />
        </main>
    </div>
</template>
