import { AlertCircle, Eye, EyeOff } from 'lucide-react';
import { AnimatePresence, motion, useReducedMotion } from 'motion/react';
import type { ComponentProps, KeyboardEvent, Ref } from 'react';
import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

export default function PasswordInput({
    className,
    ref,
    onKeyDown,
    onKeyUp,
    ...props
}: Omit<ComponentProps<'input'>, 'type'> & { ref?: Ref<HTMLInputElement> }) {
    const [showPassword, setShowPassword] = useState(false);
    const [capsLockActive, setCapsLockActive] = useState(false);
    const shouldReduceMotion = useReducedMotion();

    const handleKeyActivity = (e: KeyboardEvent<HTMLInputElement>) => {
        setCapsLockActive(e.getModifierState('CapsLock'));
    };

    return (
        <div className="relative">
            <Input
                type={showPassword ? 'text' : 'password'}
                className={cn('pr-10', className)}
                ref={ref}
                onKeyDown={(e) => {
                    handleKeyActivity(e);
                    onKeyDown?.(e);
                }}
                onKeyUp={(e) => {
                    handleKeyActivity(e);
                    onKeyUp?.(e);
                }}
                onBlur={() => setCapsLockActive(false)}
                {...props}
            />
            <button
                type="button"
                onClick={() => setShowPassword((prev) => !prev)}
                className="absolute inset-y-0 right-0 flex items-center rounded-r-md px-3 text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-[2px] focus-visible:ring-ring focus-visible:outline-none"
                aria-label={showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'}
                tabIndex={-1}
            >
                <AnimatePresence mode="wait" initial={false}>
                    <motion.span
                        key={showPassword ? 'hide' : 'show'}
                        initial={shouldReduceMotion ? { opacity: 0 } : { opacity: 0, scale: 0.8 }}
                        animate={shouldReduceMotion ? { opacity: 1 } : { opacity: 1, scale: 1 }}
                        exit={shouldReduceMotion ? { opacity: 0 } : { opacity: 0, scale: 0.8 }}
                        transition={{ duration: 0.15, ease: 'easeOut' }}
                        className="flex items-center justify-center"
                    >
                        {showPassword ? (
                            <EyeOff className="size-4" />
                        ) : (
                            <Eye className="size-4" />
                        )}
                    </motion.span>
                </AnimatePresence>
            </button>

            <AnimatePresence>
                {capsLockActive && (
                    <motion.div
                        initial={shouldReduceMotion ? { opacity: 0 } : { opacity: 0, y: -2 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, y: -2 }}
                        transition={{ duration: 0.15 }}
                        className="mt-1 flex items-center gap-1 text-xs font-medium text-amber-600 dark:text-amber-400"
                        role="status"
                        aria-live="polite"
                    >
                        <AlertCircle className="size-3 shrink-0" />
                        <span>Tombol Caps Lock sedang aktif</span>
                    </motion.div>
                )}
            </AnimatePresence>
        </div>
    );
}
