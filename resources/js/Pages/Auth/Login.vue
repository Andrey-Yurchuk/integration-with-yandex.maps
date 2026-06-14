<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = (): void => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};
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
                    Use the seed account to access organization settings and reviews
                </p>
            </header>

            <form
                class="space-y-4"
                @submit.prevent="submit"
            >
                <div>
                    <label
                        class="mb-1 block text-sm font-medium text-slate-700"
                        for="email"
                    >
                        Email
                    </label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        name="email"
                        autocomplete="username"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none"
                        :class="{ 'border-red-500': form.errors.email }"
                    >
                    <p
                        v-if="form.errors.email"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ form.errors.email }}
                    </p>
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
                        v-model="form.password"
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none"
                        :class="{ 'border-red-500': form.errors.password }"
                    >
                    <p
                        v-if="form.errors.password"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ form.errors.password }}
                    </p>
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input
                        v-model="form.remember"
                        type="checkbox"
                        name="remember"
                        class="rounded border-slate-300 text-slate-900 focus:ring-slate-500"
                    >
                    Remember me
                </label>

                <button
                    type="submit"
                    class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Signing in…' : 'Sign in' }}
                </button>
            </form>
        </section>
    </main>
</template>
