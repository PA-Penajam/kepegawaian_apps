import { Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';
import { BlurFade } from '@/components/ui/blur-fade';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-gradient-to-br from-background via-muted to-background p-6 md:p-10">
            <div className="w-full max-w-sm">
                <div className="flex flex-col gap-8 rounded-xl border-2 border-foreground bg-card p-8 drop-shadow-[8px_8px_0_rgba(0,0,0,1)]">
                    <BlurFade delay={0.1} duration={0.4}>
                        <div className="flex flex-col items-center gap-4">
                            <Link
                                href={home()}
                                className="flex flex-col items-center gap-2 font-medium"
                            >
                                <div className="mb-2 flex items-center justify-center">
                                    <AppLogoIcon className="size-20" />
                                </div>
                                <span className="sr-only">{title}</span>
                            </Link>

                            <div className="space-y-2 text-center">
                                <h1 className="text-xl font-black">
                                    {title}
                                </h1>
                                <p className="text-center text-sm font-medium text-muted-foreground">
                                    {description}
                                </p>
                            </div>
                        </div>
                    </BlurFade>
                    <BlurFade delay={0.25} duration={0.4}>
                        {children}
                    </BlurFade>
                </div>
            </div>
        </div>
    );
}