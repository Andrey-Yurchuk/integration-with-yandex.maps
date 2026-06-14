<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Sign in" />

    <main class="flex min-h-screen items-center justify-center px-4 py-12">
        <section class="w-full max-w-md rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <header class="mb-6">
                <p class="text-sm font-medium text-slate-500">
                    Yandex Maps integration
                </p>
                <h1 class="mt-1 text-2xl font-semibold text-slate-900">
                    Sign in
                </h1>
                <p class="mt-2 text-sm text-slate-600">
                    Use the seed account credentials to access organization settings and reviews.
                </p>
            </header>

            <form class="space-y-4" @submit.prevent>
                <div>
                    <label
                        class="mb-1 block text-sm font-medium text-slate-700"
                        for="email"
                    >
                        Email
                    </label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        autocomplete="username"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none"
                        placeholder="user@example.com"
                    >
                </div>

                <div>
                    <label
                        class="mb-1 block text-sm font-medium text-slate-700"
                        for="password"
                    >
                        Password
                    </label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none"
                        placeholder="••••••••"
                    >
                </div>

                <button
                    type="submit"
                    class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
                >
                    Sign in
                </button>
            </form>
        </section>
    </main>
</template>
