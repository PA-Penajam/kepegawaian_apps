import { usePage } from '@inertiajs/react';
import { Check, X, AlertCircle } from 'lucide-react';
import { AnimatePresence, motion } from 'motion/react';
import React, { useEffect, useState, useRef } from 'react';

export function FlashMessages() {
    const { flash } = usePage<any>().props;
    const [messages, setMessages] = useState<any[]>([]);

    useEffect(() => {
        if (flash?.success) {
            addMessage('success', flash.success);
        }

        if (flash?.error) {
            addMessage('error', flash.error);
        }
    }, [flash]);

    const lastAddedRef = React.useRef<{ time: number; text: string } | null>(null);

    const addMessage = (type: string, text: string) => {
        const now = Date.now();

        if (
            lastAddedRef.current &&
            lastAddedRef.current.text === text &&
            now - lastAddedRef.current.time < 500
        ) {
            return; // Cegah duplikasi karena React Strict Mode
        }

        lastAddedRef.current = { time: now, text };

        const id = now + Math.random();
        setMessages((prev) => [...prev, { id, type, text }]);
        setTimeout(() => {
            setMessages((prev) => prev.filter((m) => m.id !== id));
        }, 4000); // Tampil selama 4 detik
    };

    const removeMessage = (id: number) => {
        setMessages((prev) => prev.filter((m) => m.id !== id));
    };

    return (
        <div className="fixed bottom-6 right-6 z-50 flex flex-col gap-3 pointer-events-none">
            <AnimatePresence>
                {messages.map((message) => (
                    <motion.div
                        key={message.id}
                        initial={{ opacity: 0, y: 50, scale: 0.9 }}
                        animate={{ opacity: 1, y: 0, scale: 1 }}
                        exit={{ opacity: 0, scale: 0.9, transition: { duration: 0.2 } }}
                        className={`pointer-events-auto flex items-center gap-3 border-2 border-black p-4 shadow-[4px_4px_0_rgba(0,0,0,1)] bg-background w-[350px] max-w-[90vw]`}
                    >
                        {message.type === 'success' ? (
                            <div className="bg-primary border-2 border-black rounded-full p-1.5 text-primary-foreground">
                                <Check className="w-5 h-5 stroke-[3]" />
                            </div>
                        ) : (
                            <div className="bg-destructive border-2 border-black rounded-full p-1.5 text-destructive-foreground">
                                <AlertCircle className="w-5 h-5 stroke-[3]" />
                            </div>
                        )}
                        <p className="text-sm font-bold flex-1 leading-snug text-foreground">{message.text}</p>
                        <button
                            onClick={() => removeMessage(message.id)}
                            className="text-foreground/50 hover:text-foreground transition-colors p-1"
                        >
                            <X className="w-4 h-4 stroke-[3]" />
                        </button>
                    </motion.div>
                ))}
            </AnimatePresence>
        </div>
    );
}
