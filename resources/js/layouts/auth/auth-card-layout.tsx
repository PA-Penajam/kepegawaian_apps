import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';
import { BlurFade } from '@/components/ui/blur-fade';

export default function AuthCardLayout({
    children,
    title,
    description,
}: PropsWithChildren<{
    name?: string;
    title?: string;
    description?: string;
}>) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-gradient-to-br from-background via-muted to-background p-6 md:p-10">
            <div className="flex w-full max-w-md flex-col gap-6">
                <BlurFade delay={0.1} duration={0.4}>
                    <Link
                        href={home()}
                        className="flex items-center gap-2 self-center font-medium"
                    >
                        <div className="flex items-center justify-center">
                            <AppLogoIcon className="size-16" />
                        </div>
                    </Link>
                </BlurFade>

                <BlurFade delay={0.25} duration={0.4}>
                    <Card className="rounded-xl">
                        <CardHeader className="px-10 pt-8 pb-0 text-center">
                            <CardTitle className="text-xl">{title}</CardTitle>
                            <CardDescription>{description}</CardDescription>
                        </CardHeader>
                        <CardContent className="px-10 py-8">
                            {children}
                        </CardContent>
                    </Card>
                </BlurFade>
            </div>
        </div>
    );
}