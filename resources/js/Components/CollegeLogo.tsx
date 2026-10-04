import { BRANDING } from '@/branding';

interface CollegeLogoProps {
    /** Diameter of the shield in pixels. */
    size?: number;
    /**
     * 'icon' renders just the circular shield monogram; 'full' adds the
     * stacked wordmark underneath it.
     */
    variant?: 'full' | 'icon';
    className?: string;
}

/**
 * The Future Vision College mark, drawn as inline SVG so it scales to any size
 * without a raster asset or an extra network request.
 *
 * Geometry lives in a 100x100 user-space box centred on (50, 50); every
 * caller-supplied size only changes the rendered viewport, never the drawing,
 * so the mark is pixel-identical everywhere it appears.
 */
export default function CollegeLogo({
    size = 40,
    variant = 'icon',
    className = '',
}: CollegeLogoProps) {
    const isFull = variant === 'full';

    // The wordmark needs room below the shield, so 'full' gets a taller box.
    // The shield itself stays exactly `size` wide in both variants.
    const height = isFull ? size * 1.42 : size;

    return (
        <svg
            width={size}
            height={height}
            viewBox={isFull ? '0 0 100 142' : '0 0 100 100'}
            className={className}
            role="img"
            aria-label={BRANDING.name}
            xmlns="http://www.w3.org/2000/svg"
        >
            <title>{BRANDING.name}</title>

            {/* Shield disc: navy field with a thin gold ring at the edge. */}
            <circle
                cx="50"
                cy="50"
                r="48"
                fill="#0F2B5B"
                stroke="#C9A227"
                strokeWidth="2"
            />
            {/* Inner hairline keeps the disc from reading as a flat blob. */}
            <circle
                cx="50"
                cy="50"
                r="42.5"
                fill="none"
                stroke="#C9A227"
                strokeWidth="0.75"
                opacity="0.45"
            />

            {/* Subtle four-point star above the monogram. */}
            <path
                d="M50 26 L51.9 31.1 L57 33 L51.9 34.9 L50 40 L48.1 34.9 L43 33 L48.1 31.1 Z"
                fill="#E5C458"
            />

            {/* Monogram. Georgia matches the serif stack in tailwind.config.js. */}
            <text
                x="50"
                y="68"
                textAnchor="middle"
                fontFamily="Georgia, Cambria, 'Times New Roman', serif"
                fontSize="24"
                fontWeight="bold"
                letterSpacing="0.5"
                fill="#C9A227"
            >
                {BRANDING.shortName}
            </text>

            {isFull && (
                <text
                    x="50"
                    y="122"
                    textAnchor="middle"
                    fontFamily="Figtree, ui-sans-serif, system-ui, sans-serif"
                    fontSize="9.5"
                    fontWeight="600"
                    letterSpacing="1.6"
                    fill="#0F2B5B"
                >
                    {BRANDING.name.split(' ').slice(0, -1).join(' ').toUpperCase()}
                </text>
            )}
            {isFull && (
                <text
                    x="50"
                    y="136"
                    textAnchor="middle"
                    fontFamily="Figtree, ui-sans-serif, system-ui, sans-serif"
                    fontSize="9.5"
                    fontWeight="600"
                    letterSpacing="1.6"
                    fill="#0F2B5B"
                >
                    {BRANDING.name.split(' ').slice(-1)[0].toUpperCase()}
                </text>
            )}
        </svg>
    );
}