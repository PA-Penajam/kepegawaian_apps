import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 64 64"
            fill="none"
            {...props}
        >
            <defs>
                <linearGradient id="shieldGrad" x1="8" y1="4" x2="56" y2="60" gradientUnits="userSpaceOnUse">
                    <stop offset="0%" stopColor="var(--color-primary, #1e4a38)" />
                    <stop offset="100%" stopColor="#0d281e" />
                </linearGradient>
                <linearGradient id="goldGrad" x1="16" y1="12" x2="48" y2="48" gradientUnits="userSpaceOnUse">
                    <stop offset="0%" stopColor="#f7d070" />
                    <stop offset="50%" stopColor="#d4a337" />
                    <stop offset="100%" stopColor="#9a6e1a" />
                </linearGradient>
            </defs>

            {/* Shield Base */}
            <path
                d="M32 4L10 12V28C10 44 19.5 54.5 32 60C44.5 54.5 54 44 54 28V12L32 4Z"
                fill="url(#shieldGrad)"
                stroke="url(#goldGrad)"
                strokeWidth="2.5"
                strokeLinejoin="round"
            />

            {/* Inner Border */}
            <path
                d="M32 8.5L14 15V27.5C14 41 21.8 50 32 55C42.2 50 50 41 50 27.5V15L32 8.5Z"
                stroke="url(#goldGrad)"
                strokeWidth="1"
                strokeOpacity="0.4"
                fill="none"
            />

            {/* Central Pillar of Justice */}
            <path
                d="M32 16V46"
                stroke="url(#goldGrad)"
                strokeWidth="2.5"
                strokeLinecap="round"
            />
            {/* Top Star of Excellence */}
            <circle cx="32" cy="14" r="2" fill="#f7d070" />

            {/* Balance Crossbeam */}
            <path
                d="M20 22C24 20.5 40 20.5 44 22"
                stroke="url(#goldGrad)"
                strokeWidth="2"
                strokeLinecap="round"
            />

            {/* Left Balance Scale */}
            <path d="M20 22L16 31" stroke="url(#goldGrad)" strokeWidth="1.2" strokeLinecap="round" />
            <path d="M20 22L24 31" stroke="url(#goldGrad)" strokeWidth="1.2" strokeLinecap="round" />
            <path
                d="M14 31C14 34 26 34 26 31H14Z"
                fill="url(#goldGrad)"
            />

            {/* Right Balance Scale */}
            <path d="M44 22L40 31" stroke="url(#goldGrad)" strokeWidth="1.2" strokeLinecap="round" />
            <path d="M44 22L48 31" stroke="url(#goldGrad)" strokeWidth="1.2" strokeLinecap="round" />
            <path
                d="M38 31C38 34 50 34 50 31H38Z"
                fill="url(#goldGrad)"
            />

            {/* Base Pedestal */}
            <path
                d="M24 46H40"
                stroke="url(#goldGrad)"
                strokeWidth="3"
                strokeLinecap="round"
            />
            <path
                d="M27 43H37"
                stroke="url(#goldGrad)"
                strokeWidth="2"
                strokeLinecap="round"
            />
        </svg>
    );
}
