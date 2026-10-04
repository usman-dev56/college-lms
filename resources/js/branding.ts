/**
 * Single source of truth for the college's public-facing identity.
 *
 * Every user-facing surface (landing page, guest and authenticated layouts,
 * admissions pages) reads its copy from here rather than hardcoding strings,
 * so a rename or a new phone number is a one-line change instead of a sweep
 * across the frontend.
 *
 * The contact details are placeholders pending confirmation.
 */
export const BRANDING = {
    name: 'Future Vision College',
    shortName: 'FVC',
    tagline: 'Where Vision Meets Excellence',
    established: '2026',
    address: 'Main Road, Chiniot, Punjab, Pakistan',
    phone: '+92 47 1234567',
    email: 'info@futurevision.edu.pk',
} as const;