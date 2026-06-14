export type OrganizationSyncStatus =
    | 'awaiting'
    | 'queued'
    | 'running'
    | 'succeeded'
    | 'failed';

export type Organization = {
    id: number;
    source_url: string;
    normalized_url: string | null;
    yandex_object_id: string | null;
    sync_status: OrganizationSyncStatus;
    title: string | null;
    address: string | null;
    rating: string | null;
    ratings_count: number;
    reviews_count: number;
    last_sync_started_at: string | null;
    last_sync_finished_at: string | null;
    last_sync_error: string | null;
};

export type Review = {
    id: number;
    author_name: string;
    author_avatar_url: string | null;
    reviewed_at: string | null;
    text: string | null;
    rating: number | null;
};

export type PaginationMeta = {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    from: number | null;
    to: number | null;
};

export type PaginationLinks = {
    next: string | null;
    prev: string | null;
};

export type PaginatedReviews = {
    data: Review[];
    meta: PaginationMeta;
    links: PaginationLinks;
};

export type SyncStatus = {
    organization_id: number | null;
    sync_status: OrganizationSyncStatus | null;
    last_sync_started_at: string | null;
    last_sync_finished_at: string | null;
    last_sync_error: string | null;
    rating: string | null;
    ratings_count: number | null;
    reviews_count: number | null;
};
