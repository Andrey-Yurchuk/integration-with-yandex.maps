<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    title?: string;
}>();
</script>

<template>
    <div class="min-h-screen">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-4 sm:px-6">
                <div>
                    <p class="text-sm font-medium text-slate-500">
                        Yandex Maps integration
                    </p>
                    <h1 class="text-lg font-semibold text-slate-900">
                        {{ title ?? 'Organization settings' }}
                    </h1>
                </div>

                <Link
                    href="/login"
                    class="text-sm text-slate-600 underline-offset-4 hover:text-slate-900 hover:underline"
                >
                    Sign out
                </Link>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
            <slot />
        </main>
    </div>
</template>
