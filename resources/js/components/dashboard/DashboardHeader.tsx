import { usePage } from '@inertiajs/react';
import { Calendar, User } from 'lucide-react';
import { Badge } from '@/components/ui/badge';

// You might need to define or import the PageProps type
interface PageProps {
    auth?: {
        user?: {
            name: string;
            [key: string]: any;
        };
    };
    [key: string]: any;
}

export function DashboardHeader() {
    const { auth } = usePage<PageProps>().props;
    const userName = auth?.user?.name || 'Pengguna';

    const getGreeting = () => {
        const hour = new Date().getHours();

        if (hour < 11) {
            return 'Selamat Pagi';
        }

        if (hour < 15) {
            return 'Selamat Siang';
        }

        if (hour < 18) {
            return 'Selamat Sore';
        }

        return 'Selamat Malam';
    };

    const todayDate = new Date().toLocaleDateString('id-ID', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });

    return (
        <div className="flex flex-col gap-4 rounded-xl border border-border bg-card p-6 md:flex-row md:items-center md:justify-between">
            <div className="space-y-1">
                <h1 className="text-2xl font-bold tracking-tight text-foreground">
                    {getGreeting()}, {userName}!
                </h1>
                <p className="flex items-center gap-1.5 text-sm text-muted-foreground">
                    <Calendar className="h-4 w-4" />
                    {todayDate}
                </p>
            </div>
            <div className="flex items-center gap-2">
                <Badge
                    variant="secondary"
                    className="px-3 py-1 text-sm font-medium"
                >
                    <User className="mr-1.5 h-3.5 w-3.5" />
                    Sistem Informasi Kepegawaian
                </Badge>
            </div>
        </div>
    );
}
