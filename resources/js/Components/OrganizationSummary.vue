<script setup lang="ts">
import type { Organization, OrganizationSyncStatus } from '@/types/domain';
import { computed } from 'vue';

const props = defineProps<{
    organization: Organization | null;
}>();

const syncStatusLabels: Record<OrganizationSyncStatus, string> = {
    awaiting: 'Awaiting sync',
    queued: 'Queued',
    running: 'Syncing',
    succeeded: 'Succeeded',
    failed: 'Failed',
};

const syncStatusClasses: Record<OrganizationSyncStatus, string> = {
    awaiting: 'text-slate-500',
    queued: 'text-blue-600',
    running: 'text-amber-600',
    succeeded: 'text-green-600',
    failed: 'text-red-600',
};

const displayUrl = computed(() => {
    if (! props.organization) {
        return null;
    }

    return props.organization.normalized_url ?? props.organization.source_url;
});

const syncStatusLabel = computed(() => {
    if (! props.organization) {
        return null;
    }

    return syncStatusLabels[props.organization.sync_status];
});

const syncStatusClass = computed(() => {
    if (! props.organization) {
        return '';
    }

    return syncStatusClasses[props.organization.sync_status];
});

const formatTimestamp = (value: string | null): string | null => {
    if (! value) {
        return null;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
};

const lastSyncStartedAt = computed(() => formatTimestamp(props.organization?.last_sync_started_at ?? null));
const lastSyncFinishedAt = computed(() => formatTimestamp(props.organization?.last_sync_finished_at ?? null));
</script>

<template>
    <section class="rounded-lg border border-slate-200 bg-white p-6">
        <h2 class="text-base font-semibold text-slate-900">
            Organization summary
        </h2>

        <p
            v-if="! organization"
            class="mt-3 text-sm text-slate-600"
        >
            Save a Yandex Maps link to load organization data
        </p>

        <dl
            v-else
            class="mt-4 space-y-4 text-sm"
        >
            <div v-if="organization.title">
                <dt class="font-medium text-slate-700">
                    Title
                </dt>
                <dd class="mt-1 break-words text-slate-900">
                    {{ organization.title }}
                </dd>
            </div>
            <div
                v-else-if="organization.sync_status === 'queued' || organization.sync_status === 'running' || organization.sync_status === 'awaiting'"
            >
                <dt class="font-medium text-slate-700">
                    Title
                </dt>
                <dd class="mt-1 text-slate-500">
                    Waiting for synchronization…
                </dd>
            </div>

            <div v-if="organization.address">
                <dt class="font-medium text-slate-700">
                    Address
                </dt>
                <dd class="mt-1 break-words text-slate-900">
                    {{ organization.address }}
                </dd>
            </div>

            <div v-if="displayUrl">
                <dt class="font-medium text-slate-700">
                    Link
                </dt>
                <dd class="mt-1 break-all text-slate-900">
                    <a
                        :href="displayUrl"
                        class="cursor-pointer text-slate-900 underline decoration-slate-300 underline-offset-2 hover:decoration-slate-500"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        {{ displayUrl }}
                    </a>
                </dd>
            </div>

            <div>
                <dt class="font-medium text-slate-700">
                    Sync status
                </dt>
                <dd
                    class="mt-1 font-medium"
                    :class="syncStatusClass"
                >
                    {{ syncStatusLabel }}
                </dd>
            </div>

            <div v-if="lastSyncStartedAt">
                <dt class="font-medium text-slate-700">
                    Last sync started
                </dt>
                <dd class="mt-1 text-slate-900">
                    {{ lastSyncStartedAt }}
                </dd>
            </div>

            <div v-if="lastSyncFinishedAt">
                <dt class="font-medium text-slate-700">
                    Last sync finished
                </dt>
                <dd class="mt-1 text-slate-900">
                    {{ lastSyncFinishedAt }}
                </dd>
            </div>

            <div v-if="organization.sync_status === 'failed' && organization.last_sync_error">
                <dt class="font-medium text-slate-700">
                    Last sync error
                </dt>
                <dd class="mt-1 break-words text-red-600">
                    {{ organization.last_sync_error }}
                </dd>
            </div>

            <div class="border-t border-slate-200 pt-4">
                <dt class="font-medium text-slate-700">
                    Rating and counts
                </dt>
                <dd class="mt-2 grid gap-2 sm:grid-cols-3">
                    <div>
                        <p class="text-xs text-slate-500">
                            Average rating
                        </p>
                        <p class="mt-0.5 text-slate-900">
                            {{ organization.rating ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">
                            Ratings
                        </p>
                        <p class="mt-0.5 text-slate-900">
                            {{ organization.ratings_count }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">
                            Reviews
                        </p>
                        <p class="mt-0.5 text-slate-900">
                            {{ organization.reviews_count }}
                        </p>
                    </div>
                </dd>
            </div>
        </dl>
    </section>
</template>
