import PropTypes from 'prop-types';
import { useId } from 'react';

// La marca: las tres esquinas de un código QR y, donde iría la cuarta, la
// marca de ingreso admitido. Es el mismo dibujo que public/favicon.svg.
export default function Logo({ className = 'h-9 w-9' }) {
    const id = useId();
    return (
        <svg
            viewBox="0 0 32 32"
            className={`shrink-0 ${className}`}
            role="img"
            aria-label="Control de ingreso"
        >
            <defs>
                <linearGradient id={id} x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stopColor="#3b82f6" />
                    <stop offset="1" stopColor="#1e40af" />
                </linearGradient>
            </defs>
            <rect width="32" height="32" rx="8" fill={`url(#${id})`} />
            <g fill="none" stroke="#fff" strokeWidth="1.9">
                <rect x="6" y="6" width="8" height="8" rx="2.2" />
                <rect x="18" y="6" width="8" height="8" rx="2.2" />
                <rect x="6" y="18" width="8" height="8" rx="2.2" />
            </g>
            <g fill="#fff">
                <rect x="8.9" y="8.9" width="2.2" height="2.2" rx="0.6" />
                <rect x="20.9" y="8.9" width="2.2" height="2.2" rx="0.6" />
                <rect x="8.9" y="20.9" width="2.2" height="2.2" rx="0.6" />
            </g>
            <path
                d="M17.6 22.2l3 3 5.6-6.6"
                fill="none"
                stroke="#fff"
                strokeWidth="2.5"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

Logo.propTypes = { className: PropTypes.string };
