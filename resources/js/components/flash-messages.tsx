import { usePage } from '@inertiajs/react';
import { AlertCircle, AlertTriangle, CheckCircle2, Info, X } from 'lucide-react';
import { AnimatePresence, motion, useReducedMotion } from 'motion/react';
import React, { useEffect, useRef, useState } from 'react';

type FlashType = 'success' | 'error' | 'warning' | 'info';

interface FlashMessageItem {
    id: number;
    type: FlashType;
    text: string;
}

export function FlashMessages() {
    const { flash } = usePage<{
        flash?: {
            success?: string;
            error?: string;
            warning?: string;
            info?: string;
        };
    }>().props;

    const [messages, setMessages] = useState<FlashMessageItem[]>([]);
    const shouldReduceMotion = useReducedMotion();
    const lastAddedRef = useRef<{ time: number; text: string } | null>(null);

    useEffect(() => {
        if (flash?.success) {
            addMessage('success', flash.success);
        }
        if (flash?.error) {
            addMessage('error', flash.error);
        }
        if (flash?.warning) {
            addMessage('warning', flash.warning);
        }
        if (flash?.info) {
            addMessage('info', flash.info);
        }
    }, [flash]);

    const addMessage = (type: FlashType, text: string) => {
        const now = Date.now();

        // Cegah duplikasi pesan identik dalam 500ms (misal React Strict Mode)
        if (
            lastAddedRef.current &&
            lastAddedRef.current.text === text &&
            now - lastAddedRef.current.time < 500
        ) {
            return;
        }

        lastAddedRef.current = { time: now, text };

        const id = now + Math.random();
        setMessages((prev) => [...prev, { id, type, text }]);

        // Auto-dismiss setelah 5 detik
        setTimeout(() => {
            setMessages((prev) => prev.filter((m) => m.id !== id));
        }, 5000);
    };

    const removeMessage = (id: number) => {
        setMessages((prev) => prev.filter((m) => m.id !== id));
    };

    const getIcon = (type: FlashType) => {
        switch (type) {
            case 'success':
                return (
                    <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-emerald-500/15 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400">
                        <CheckCircle2 className="size-4.5" />
                    </div>
                );
            case 'error':
                return (
                    <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-destructive/15 text-destructive dark:bg-destructive/20">
                        <AlertCircle className="size-4.5" />
                    </div>
                );
            case 'warning':
                return (
                    <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-amber-500/15 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400">
                        <AlertTriangle className="size-4.5" />
                    </div>
                );
            case 'info':
            default:
                return (
                    <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/15 text-primary dark:bg-primary/20">
                        <Info className="size-4.5" />
                    </div>
                );
        }
    };

    return (
        <div
            aria-live="polite"
            role="region"
            aria-label="Pemberitahuan Sistem"
            className="pointer-events-none fixed right-4 bottom-5 z-50 flex w-[380px] max-w-[calc(100vw-2rem)] flex-col gap-2.5 sm:right-6 sm:bottom-6"
        >
            <AnimatePresence>
                {messages.map((message) => (
                    <motion.div
                        key={message.id}
                        layout
                        initial={
                            shouldReduceMotion
                                ? { opacity: 0 }
                                : { opacity: 0, y: 24, scale: 0.96 }
                        }
                        animate={
                            shouldReduceMotion
                                ? { opacity: 1 }
                                : { opacity: 1, y: 0, scale: 1 }
                        }
                        exit={
                            shouldReduceMotion
                                ? { opacity: 0 }
                                : { opacity: 0, scale: 0.92, transition: { duration: 0.2 } }
                        }
                        className="pointer-events-auto flex items-start gap-3 rounded-xl border border-border/80 bg-card p-3.5 shadow-lg backdrop-blur-md transition-all dark:border-white/10 dark:bg-card"
                    >
                        {getIcon(message.type)}
                        <div className="min-w-0 flex-1 pt-0.5">
                            <p className="text-xs leading-relaxed font-medium text-foreground break-words sm:text-sm">
                                {message.text}
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={() => removeMessage(message.id)}
                            aria-label="Tutup pemberitahuan"
                            className="rounded-md p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                        >
                            <X className="size-4" />
                        </button>
                    </motion.div>
                ))}
            </AnimatePresence>
        </div>
    );
}
