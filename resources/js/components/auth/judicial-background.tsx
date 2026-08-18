import { motion, useReducedMotion } from 'motion/react';

export default function JudicialAuthBackground() {
    const shouldReduceMotion = useReducedMotion();

    return (
        <div
            aria-hidden="true"
            className="pointer-events-none absolute inset-0 z-0 overflow-hidden select-none"
        >
            {/* Latar Belakang Kanvas Hijau Zamrud Tua Resmi (Deep Forest Green Peradilan) */}
            <div className="absolute inset-0 bg-[#072016] dark:bg-[#030e09]" />

            {/* Lapisan Gradien Atmosferik Mewah */}
            <div className="absolute inset-0 bg-gradient-to-br from-[#0c3122] via-[#072016] to-[#04140d] dark:from-[#082218] dark:via-[#030e09] dark:to-[#020a06]" />

            {/* Sinar Emas Hangat di Sudut Kanan Atas */}
            <div className="absolute -top-32 -right-32 h-[38rem] w-[38rem] rounded-full bg-[radial-gradient(circle,rgba(245,158,11,0.22)_0%,rgba(217,119,6,0.08)_45%,transparent_70%)] blur-2xl" />

            {/* Pendaran Hijau Zamrud di Sudut Kiri Bawah */}
            <div className="absolute -bottom-36 -left-36 h-[42rem] w-[42rem] rounded-full bg-[radial-gradient(circle,rgba(16,185,129,0.2)_0%,rgba(5,150,105,0.08)_50%,transparent_75%)] blur-3xl" />

            {/* Efek Pendaran Tengah (Breathing Glow) di Belakang Kartu Login */}
            <motion.div
                initial={{ opacity: 0.7, scale: 1 }}
                animate={
                    shouldReduceMotion
                        ? { opacity: 0.75 }
                        : {
                              opacity: [0.7, 0.95, 0.7],
                              scale: [1, 1.08, 1],
                          }
                }
                transition={{
                    duration: 7,
                    repeat: Infinity,
                    ease: 'easeInOut',
                }}
                className="absolute inset-0 flex items-center justify-center"
            >
                <div className="h-[34rem] w-[34rem] max-w-[95vw] rounded-full bg-[radial-gradient(circle,rgba(16,185,129,0.25)_0%,rgba(245,158,11,0.18)_45%,transparent_70%)] blur-2xl" />
            </motion.div>

            {/* Pola Geometris Watermark Peradilan Beraksen Emas Halus */}
            <svg
                className="absolute inset-0 h-full w-full text-amber-300/[0.09] dark:text-amber-400/[0.07]"
                xmlns="http://www.w3.org/2000/svg"
                width="100%"
                height="100%"
                fill="none"
            >
                <defs>
                    <pattern
                        id="judicial-watermark-pattern"
                        x="0"
                        y="0"
                        width="160"
                        height="160"
                        patternUnits="userSpaceOnUse"
                    >
                        {/* Motif Lingkaran Konsentris */}
                        <circle cx="80" cy="80" r="60" stroke="currentColor" strokeWidth="1.2" strokeDasharray="4 4" />
                        <circle cx="80" cy="80" r="42" stroke="currentColor" strokeWidth="1" />
                        <circle cx="80" cy="80" r="24" stroke="currentColor" strokeWidth="0.8" strokeDasharray="2 2" />

                        {/* Garis Sumbu Geometris */}
                        <line x1="80" y1="10" x2="80" y2="150" stroke="currentColor" strokeWidth="1" />
                        <line x1="10" y1="80" x2="150" y2="80" stroke="currentColor" strokeWidth="1" />

                        {/* Siluet Timbangan Keadilan */}
                        <path d="M60 62 L100 62" stroke="currentColor" strokeWidth="2" />
                        <path d="M60 62 L50 76 M60 62 L70 76" stroke="currentColor" strokeWidth="1.2" />
                        <path d="M100 62 L90 76 M100 62 L110 76" stroke="currentColor" strokeWidth="1.2" />
                        <path d="M46 76 Q60 84 74 76" stroke="currentColor" strokeWidth="1.5" />
                        <path d="M86 76 Q100 84 114 76" stroke="currentColor" strokeWidth="1.5" />

                        {/* Aksen Sudut Simetris */}
                        <circle cx="0" cy="0" r="16" stroke="currentColor" strokeWidth="1" />
                        <circle cx="160" cy="0" r="16" stroke="currentColor" strokeWidth="1" />
                        <circle cx="0" cy="160" r="16" stroke="currentColor" strokeWidth="1" />
                        <circle cx="160" cy="160" r="16" stroke="currentColor" strokeWidth="1" />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#judicial-watermark-pattern)" />
            </svg>

            {/* Vignette Gelap pada Tepi Layar */}
            <div className="absolute inset-0 bg-[radial-gradient(circle_at_center,transparent_30%,rgba(0,0,0,0.45)_100%)]" />
        </div>
    );
}
