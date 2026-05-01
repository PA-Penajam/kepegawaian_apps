import { router } from '@inertiajs/react';
import { Bell, CheckCheck } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';

interface Notification {
    id: string;
    title: string;
    body: string;
    link: string | null;
    read_at: string | null;
    created_at: string;
}

interface NotificationsResponse {
    notifications: Notification[];
    unread_count: number;
}

/**
 * Menghitung waktu relatif dalam Bahasa Indonesia.
 */
function timeAgo(dateStr: string): string {
    const now = new Date();
    const date = new Date(dateStr);
    const diffMs = now.getTime() - date.getTime();
    const diffSeconds = Math.floor(diffMs / 1000);
    const diffMinutes = Math.floor(diffSeconds / 60);
    const diffHours = Math.floor(diffMinutes / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffSeconds < 60) {
return 'baru saja';
}

    if (diffMinutes < 60) {
return `${diffMinutes} menit lalu`;
}

    if (diffHours < 24) {
return `${diffHours} jam lalu`;
}

    if (diffDays < 7) {
return `${diffDays} hari lalu`;
}

    return date.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
    });
}

export function NotificationBell() {
    const [isOpen, setIsOpen] = useState(false);
    const [notifications, setNotifications] = useState<Notification[]>([]);
    const [unreadCount, setUnreadCount] = useState(0);
    const [isLoading, setIsLoading] = useState(false);
    const dropdownRef = useRef<HTMLDivElement>(null);

    // Ambil notifikasi dari server
    const fetchNotifications = useCallback(async () => {
        try {
            const response = await fetch('/notifications', {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (response.ok) {
                const data: NotificationsResponse = await response.json();
                setNotifications(data.notifications);
                setUnreadCount(data.unread_count);
            }
        } catch {
            // Gagal fetch notifikasi — abaikan secara silent
        }
    }, []);

    // Polling setiap 30 detik untuk pembaruan notifikasi
    useEffect(() => {
        fetchNotifications();
        const interval = setInterval(fetchNotifications, 30000);

        return () => clearInterval(interval);
    }, [fetchNotifications]);

    // Tutup dropdown ketika klik di luar area
    useEffect(() => {
        function handleClickOutside(event: MouseEvent) {
            if (
                dropdownRef.current &&
                !dropdownRef.current.contains(event.target as Node)
            ) {
                setIsOpen(false);
            }
        }

        document.addEventListener('mousedown', handleClickOutside);

        return () =>
            document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    // Tandai satu notifikasi sebagai dibaca
    const markAsRead = async (notification: Notification) => {
        if (notification.read_at) {
            // Sudah dibaca — langsung navigasi jika ada link
            if (notification.link) {
                router.visit(notification.link);
                setIsOpen(false);
            }

            return;
        }

        try {
            await fetch(`/notifications/${notification.id}/read`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN':
                        document
                            .querySelector<HTMLMetaElement>(
                                'meta[name="csrf-token"]',
                            )
                            ?.getAttribute('content') ?? '',
                },
            });

            // Perbarui state lokal
            setNotifications((prev) =>
                prev.map((n) =>
                    n.id === notification.id
                        ? { ...n, read_at: new Date().toISOString() }
                        : n,
                ),
            );
            setUnreadCount((prev) => Math.max(0, prev - 1));

            // Navigasi ke halaman terkait jika ada link
            if (notification.link) {
                router.visit(notification.link);
                setIsOpen(false);
            }
        } catch {
            // Gagal menandai sebagai dibaca
        }
    };

    // Tandai semua notifikasi sebagai dibaca
    const markAllAsRead = async () => {
        setIsLoading(true);

        try {
            await fetch('/notifications/read-all', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN':
                        document
                            .querySelector<HTMLMetaElement>(
                                'meta[name="csrf-token"]',
                            )
                            ?.getAttribute('content') ?? '',
                },
            });

            setNotifications((prev) =>
                prev.map((n) => ({
                    ...n,
                    read_at: n.read_at ?? new Date().toISOString(),
                })),
            );
            setUnreadCount(0);
        } catch {
            // Gagal menandai semua sebagai dibaca
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <div className="relative" ref={dropdownRef}>
            {/* Tombol Bell */}
            <button
                type="button"
                onClick={() => {
                    setIsOpen(!isOpen);

                    if (!isOpen) {
fetchNotifications();
}
                }}
                className="relative inline-flex items-center justify-center rounded-md border-2 border-foreground bg-background p-2 text-foreground shadow-[2px_2px_0_rgba(0,0,0,1)] transition-all hover:bg-accent hover:shadow-[1px_1px_0_rgba(0,0,0,1)] active:shadow-none active:translate-x-[2px] active:translate-y-[2px]"
                aria-label="Notifikasi"
            >
                <Bell className="h-5 w-5" />
                {unreadCount > 0 && (
                    <span className="absolute -top-1.5 -right-1.5 flex h-5 min-w-5 items-center justify-center rounded-full border-2 border-foreground bg-destructive px-1 text-[10px] font-bold text-destructive-foreground">
                        {unreadCount > 99 ? '99+' : unreadCount}
                    </span>
                )}
            </button>

            {/* Dropdown Panel */}
            {isOpen && (
                <div className="absolute right-0 top-full z-50 mt-2 w-80 origin-top-right rounded-md border-2 border-foreground bg-background shadow-[4px_4px_0_rgba(0,0,0,1)]">
                    {/* Header */}
                    <div className="flex items-center justify-between border-b-2 border-foreground px-4 py-3">
                        <h3 className="text-sm font-bold">Notifikasi</h3>
                        {unreadCount > 0 && (
                            <button
                                type="button"
                                onClick={markAllAsRead}
                                disabled={isLoading}
                                className="flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground disabled:opacity-50"
                            >
                                <CheckCheck className="h-3.5 w-3.5" />
                                Tandai semua dibaca
                            </button>
                        )}
                    </div>

                    {/* Daftar Notifikasi */}
                    <div className="max-h-80 overflow-y-auto">
                        {notifications.length === 0 ? (
                            <div className="px-4 py-8 text-center text-sm text-muted-foreground">
                                Tidak ada notifikasi
                            </div>
                        ) : (
                            notifications.map((notification) => (
                                <button
                                    key={notification.id}
                                    type="button"
                                    onClick={() => markAsRead(notification)}
                                    className={cn(
                                        'flex w-full flex-col gap-0.5 border-b border-border px-4 py-3 text-left transition-colors hover:bg-accent',
                                        !notification.read_at &&
                                            'bg-accent/50',
                                    )}
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <span className="text-sm font-semibold leading-tight">
                                            {notification.title}
                                        </span>
                                        {!notification.read_at && (
                                            <span className="mt-0.5 h-2 w-2 shrink-0 rounded-full bg-primary" />
                                        )}
                                    </div>
                                    <p className="text-xs text-muted-foreground line-clamp-2">
                                        {notification.body}
                                    </p>
                                    <span className="mt-1 text-[10px] text-muted-foreground/70">
                                        {timeAgo(notification.created_at)}
                                    </span>
                                </button>
                            ))
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
