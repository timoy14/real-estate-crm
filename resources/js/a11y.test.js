import { describe, expect, it } from 'vitest';

// Source-level check (F-I9): every Select trigger has an accessible name, either an
// id that a <Label for> points at, or an aria-label. Placeholder-only filter inputs
// on the list pages carry an aria-label.
const sources = import.meta.glob(
    [
        './components/crm/*.vue',
        './components/public/*.vue',
        './pages/app/**/*.vue',
    ],
    {
        query: '?raw',
        import: 'default',
        eager: true,
    },
);
const read = (path) => sources[`./${path}`] ?? '';

const FORMS = [
    'components/crm/ClientForm.vue',
    'components/crm/DealForm.vue',
    'components/crm/LogActivityForm.vue',
    'components/crm/ScheduleViewingForm.vue',
    'components/crm/TaskForm.vue',
    'components/crm/PropertyForm.vue',
    'components/crm/WishlistForm.vue',
    'components/public/ListingFilters.vue',
];
const LISTS = [
    'pages/app/Clients/Index.vue',
    'pages/app/Properties/Index.vue',
    'pages/app/Inquiries/Index.vue',
];

function triggers(src) {
    return [...src.matchAll(/<SelectTrigger\b[^>]*>/g)].map((m) => m[0]);
}

describe('accessible names for selects and filters', () => {
    it.each([...FORMS, ...LISTS])(
        '%s: every SelectTrigger is labelled',
        (file) => {
            const src = read(file);
            const tags = triggers(src);
            expect(tags.length).toBeGreaterThan(0);

            for (const tag of tags) {
                const id = tag.match(/\s(?::)?id="([^"]+)"/)?.[1];
                const hasAria = /\s(?::)?aria-label=/.test(tag);
                if (hasAria) {
                    continue;
                }
                expect(id, `${file}: ${tag}`).toBeTruthy();
                // The matching Label uses the same (possibly bound) expression.
                expect(
                    src.includes(`for="${id}"`),
                    `${file}: no <Label for="${id}">`,
                ).toBe(true);
            }
        },
    );

    it.each(LISTS)(
        '%s: placeholder-only inputs carry an aria-label',
        (file) => {
            const src = read(file);
            for (const tag of src.matchAll(
                /<Input\b[^>]*placeholder=[^>]*>/g,
            )) {
                if (/\sid="/.test(tag[0])) {
                    continue;
                }
                expect(
                    /\s(?::)?aria-label=/.test(tag[0]),
                    `${file}: ${tag[0]}`,
                ).toBe(true);
            }
        },
    );
});
