import { AlertTriangle, Check, Copy, Home, RefreshCw } from 'lucide-react';
import React, { Component   } from 'react';
import type {ErrorInfo, ReactNode} from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';

interface Props {
    children: ReactNode;
    fallback?: ReactNode;
}

interface State {
    hasError: boolean;
    error: Error | null;
    errorInfo: ErrorInfo | null;
    copied: boolean;
}

export class ErrorBoundary extends Component<Props, State> {
    public state: State = {
        hasError: false,
        error: null,
        errorInfo: null,
        copied: false,
    };

    public static getDerivedStateFromError(error: Error): Partial<State> {
        return { hasError: true, error };
    }

    public componentDidCatch(error: Error, errorInfo: ErrorInfo): void {
        this.setState({ errorInfo });
        // Log ke console untuk keperluan investigasi teknis
        console.error('SIMPEG React Error Boundary mendeteksi kendala:', error, errorInfo);
    }

    private handleCopyError = (): void => {
        const errorDetails = `[SIMPEG PA Penajam - Error Report]
Pesan: ${this.state.error?.message}
Stack: ${this.state.error?.stack}
Komponen: ${this.state.errorInfo?.componentStack}
Waktu: ${new Date().toISOString()}
URL: ${window.location.href}`;

        navigator.clipboard.writeText(errorDetails).then(() => {
            this.setState({ copied: true });
            setTimeout(() => this.setState({ copied: false }), 3000);
        });
    };

    private handleReload = (): void => {
        window.location.reload();
    };

    private handleReset = (): void => {
        this.setState({ hasError: false, error: null, errorInfo: null });
        window.location.href = '/dashboard';
    };

    public render(): ReactNode {
        if (this.state.hasError) {
            if (this.props.fallback) {
                return this.props.fallback;
            }

            return (
                <div className="flex min-h-screen w-full flex-col items-center justify-center bg-background px-4 py-8 text-foreground sm:px-6">
                    <div className="w-full max-w-lg rounded-2xl border border-border bg-card p-6 shadow-xl sm:p-8">
                        {/* Header Lambang */}
                        <div className="flex flex-col items-center gap-3 text-center">
                            <AppLogoIcon className="size-14" />
                            <div className="inline-flex items-center gap-1.5 rounded-full border border-destructive/20 bg-destructive/10 px-3 py-1 text-xs font-semibold text-destructive">
                                <AlertTriangle className="size-3.5" />
                                Kendala Antarmuka Aplikasi
                            </div>
                            <h1 className="text-xl font-bold tracking-tight text-foreground sm:text-2xl">
                                Terjadi Kesalahan Tampilan
                            </h1>
                            <p className="text-xs leading-relaxed text-muted-foreground sm:text-sm">
                                Aplikasi mengalami kendala saat memuat komponen ini. Data Anda aman dan tidak terpengaruh di sistem server.
                            </p>
                        </div>

                        {/* Ringkasan Kesalahan */}
                        {this.state.error && (
                            <div className="mt-5 rounded-xl border border-destructive/20 bg-destructive/5 p-3.5 text-xs text-destructive">
                                <p className="font-semibold">Pesan Kesalahan:</p>
                                <p className="mt-1 font-mono text-xs leading-relaxed break-all">
                                    {this.state.error.message || 'Unknown render exception'}
                                </p>
                            </div>
                        )}

                        {/* Tombol Tindakan */}
                        <div className="mt-6 flex flex-col gap-2.5 sm:flex-row">
                            <Button
                                variant="default"
                                className="w-full gap-2 font-medium"
                                onClick={this.handleReload}
                            >
                                <RefreshCw className="size-4" />
                                Muat Ulang Halaman
                            </Button>
                            <Button
                                variant="outline"
                                className="w-full gap-2 font-medium"
                                onClick={this.handleReset}
                            >
                                <Home className="size-4" />
                                Ke Dasbor
                            </Button>
                        </div>

                        {/* Rincian Teknis & Tombol Salin */}
                        <div className="mt-4 flex items-center justify-between border-t border-border pt-4 text-xs text-muted-foreground">
                            <span>Laporkan ke Kepegawaian & Ortala</span>
                            <button
                                type="button"
                                onClick={this.handleCopyError}
                                className="inline-flex items-center gap-1.5 rounded-md px-2 py-1 font-medium text-foreground transition-colors hover:bg-muted"
                            >
                                {this.state.copied ? (
                                    <>
                                        <Check className="size-3.5 text-emerald-600 dark:text-emerald-400" />
                                        <span className="text-emerald-600 dark:text-emerald-400">Tersalin</span>
                                    </>
                                ) : (
                                    <>
                                        <Copy className="size-3.5" />
                                        <span>Salin Log</span>
                                    </>
                                )}
                            </button>
                        </div>
                    </div>
                </div>
            );
        }

        return this.props.children;
    }
}
