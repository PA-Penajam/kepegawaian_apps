import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 40 42" xmlns="http://www.w3.org/2000/svg">
            <path
                fill="currentColor"
                d="M20 2 L38 12 L38 40 L2 40 L2 12 Z M20 6 L6 14 L6 36 L14 36 L14 26 L18 26 L18 36 L22 36 L22 26 L26 26 L26 36 L34 36 L34 14 Z M12 16 L16 16 L16 20 L12 20 Z M20 16 L24 16 L24 20 L20 20 Z M28 16 L32 16 L32 20 L28 20 Z"
            />
        </svg>
    );
}
