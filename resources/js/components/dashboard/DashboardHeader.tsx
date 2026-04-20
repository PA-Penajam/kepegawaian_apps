import { usePage } from '@inertiajs/react';
import { BlurFade } from '@/components/ui/blur-fade';
import { Badge } from '@/components/ui/badge';
import { Calendar, User } from 'lucide-react';

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
        if (hour < 11) return 'Selamat Pagi';
        if (hour < 15) return 'Selamat Siang';
        if (hour < 18) return 'Selamat Sore';
        return 'Selamat Malam';
    };

    const todayDate = new Date().toLocaleDateString('id-ID', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });

    return (
        <BlurFade delay={0.05}>
            <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between rounded-xl bg-card p-6 shadow-sm border relative overflow-hidden">
                <div className="relative z-10 space-y-1">
                    <h1 className="text-2xl font-bold tracking-tight text-foreground">
                        {getGreeting()}, {userName}! 👋
                    </h1>
                    <p className="text-sm text-muted-foreground flex items-center gap-1.5">
                        <Calendar className="h-4 w-4" />
                        {todayDate}
                    </p>
                </div>
                <div className="relative z-10 flex items-center gap-2">
                    <Badge variant="secondary" className="px-3 py-1 text-sm font-medium">
                        <User className="mr-1.5 h-3.5 w-3.5" />
                        Sistem Informasi Kepegawaian
                    </Badge>
                </div>
                {/* Decorative background element */}
                <div className="absolute -right-4 -top-12 h-32 w-32 rounded-full bg-accent/5 blur-3xl" />
                <div className="absolute -bottom-10 right-20 h-24 w-24 rounded-full bg-primary/5 blur-2xl" />
            </div>
        </BlurFade>
    );
}
