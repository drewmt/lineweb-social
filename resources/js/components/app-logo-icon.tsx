import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 48 48"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
        >
            <path
                d="M11.5 24.5c0-7.18 5.71-13 12.75-13s12.75 5.82 12.75 13c0 6.46-4.63 11.82-10.69 12.83L20.5 41v-3.82c-5.2-1.63-9-6.7-9-12.68Z"
                stroke="currentColor"
                strokeWidth="3.2"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <circle cx="18.2" cy="24.2" r="2.2" fill="currentColor" />
            <circle cx="24.4" cy="20.4" r="2.2" fill="currentColor" />
            <circle cx="30.6" cy="25.6" r="2.2" fill="currentColor" />
            <path
                d="m20.1 23 2.5-1.5m3.6.7 2.5 2"
                stroke="currentColor"
                strokeWidth="1.9"
                strokeLinecap="round"
            />
        </svg>
    );
}
