<script setup lang="ts">
import type { Organization } from '@/types/domain';
import { router } from '@inertiajs/vue3';

const props = defineProps<{
    organizations: Organization[];
    organization: Organization | null;
}>();

const activate = (organizationId: number): void => {
    if (organizationId === props.organization?.id) {
        return;
    }

    router.post(`/organizations/${organizationId}/activate`, {}, {
        preserveScroll: true,
    });
};
</script>

<template>
    <section
        v-if="organizations.length > 1"
        class="rounded-lg border border-slate-200 bg-white p-6"
    >
        <h2 class="text-base font-semibold text-slate-900">
            Your organizations
        </h2>
        <p class="mt-1 text-sm text-slate-600">
            Switch between saved Yandex Maps cards
        </p>

        <label
            class="mt-4 block text-sm font-medium text-slate-700"
            for="organization-switcher"
        >
            Active organization
        </label>
        <select
            id="organization-switcher"
            class="mt-1 w-full cursor-pointer rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none"
            :value="organization?.id ?? ''"
            @change="activate(Number(($event.target as HTMLSelectElement).value))"
        >
            <option
                v-for="item in organizations"
                :key="item.id"
                :value="item.id"
            >
                {{ item.title ?? item.source_url }}
            </option>
        </select>
    </section>
</template>
