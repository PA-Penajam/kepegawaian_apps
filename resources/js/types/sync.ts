// Tipe data untuk Klien Sinkronisasi data pegawai

export type SyncConsumer = {
    id: string;
    nama: string;
    slug: string;
    base_url: string | null;
    deskripsi: string | null;
    is_active: boolean;
    last_pull_at: string | null;
    last_pull_status: string | null;
    last_pull_rows: number;
    last_connection_test_at: string | null;
    last_connection_test_status: string | null;
    last_connection_test_message: string | null;
    pulls_count?: number;
    created_at: string;
    updated_at: string;
};

export type PegawaiSyncPull = {
    id: string;
    sync_consumer_id: string | null;
    status: string;
    rows_returned: number;
    page: number;
    per_page: number;
    duration_ms: number;
    token_name: string | null;
    client_agent: string | null;
    pulled_at: string;
    consumer?: SyncConsumer | null;
};

export type SyncTokenOnce = {
    consumer_id: string;
    consumer_slug: string;
    /**
     * Plaintext token Sanctum yang baru diputar. String kosong bila aksi
     * ini tidak memutar token (mis. regenerasi HMAC secret saja).
     */
    plaintext: string;
    expires_at: string | null;
    /**
     * HMAC secret unik per konsumen yang baru diterbitkan. String kosong
     * bila aksi ini tidak memutar secret (mis. regenerasi token saja).
     * Hanya tampil sekali — tidak dapat dilihat kembali.
     */
    hmac_secret: string;
};

export type SyncConnectionTest = {
    success: boolean;
    message: string;
};
