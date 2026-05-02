import { router } from '@inertiajs/react';
import { Bell, CheckCheck } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
    const [notifications, setNotifications] = useState<Notification[]>([]);
    const [unreadCount, setUnreadCount] = useState(0);
    const [isLoading, setIsLoading] = useState(false);

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

    // Tandai satu notifikasi sebagai dibaca
    const markAsRead = async (notification: Notification) => {
        if (notification.read_at) {
            if (notification.link) {
                router.visit(notification.link);
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
        <DropdownMenu onOpenChange={(open) => {
 if (open) {
fetchNotifications();
} 
}}>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
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
            </DropdownMenuTrigger>

            <DropdownMenuContent
                align="end"
                className="w-80 border-2 border-foreground shadow-[4px_4px_0_rgba(0,0,0,1)]"
            >
                {/* Header */}
                <DropdownMenuLabel className="flex items-center justify-between border-b-2 border-foreground px-4 py-3">
                    <span className="text-sm font-bold">Notifikasi</span>
                    {unreadCount > 0 && (
                        <button
                            type="button"
                            onClick={(e) => {
                                e.stopPropagation();
                                markAllAsRead();
                            }}
                            disabled={isLoading}
                            className="flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground disabled:opacity-50"
                        >
                            <CheckCheck className="h-3.5 w-3.5" />
                            Tandai semua dibaca
                        </button>
                    )}
                </DropdownMenuLabel>

                {/* Daftar Notifikasi */}
                <div className="max-h-80 overflow-y-auto">
                    {notifications.length === 0 ? (
                        <div className="px-4 py-8 text-center text-sm text-muted-foreground">
                            Tidak ada notifikasi
                        </div>
                    ) : (
                        notifications.map((notification) => (
                            <DropdownMenuItem
                                key={notification.id}
                                onClick={() => markAsRead(notification)}
                                className={cn(
                                    'flex flex-col gap-0.5 border-b border-border px-4 py-3 text-left',
                                    !notification.read_at && 'bg-accent/50',
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
                            </DropdownMenuItem>
                        ))
                    )}
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
