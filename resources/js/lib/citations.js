const PATTERN = /\[(contact|property|deal|activity|wishlist|inquiry):(\d+)\]/g;

/**
 * Splits assistant text into text and link segments. Only refs the server resolved
 * (and so checked for visibility) become links; any other [type:id] stays plain text.
 */
export function renderCitations(text, refs) {
    const known = new Map(refs.map((r) => [r.ref, r]));
    const segments = [];

    const pushText = (t) => {
        if (t === '') {
            return;
        }

        const last = segments[segments.length - 1];

        if (last && last.kind === 'text') {
            last.text += t;
        } else {
            segments.push({ kind: 'text', text: t });
        }
    };

    let cursor = 0;

    for (const match of text.matchAll(PATTERN)) {
        const start = match.index ?? 0;
        const key = `${match[1]}:${match[2]}`;
        const ref = known.get(key);

        pushText(text.slice(cursor, start));

        if (ref) {
            segments.push({
                kind: 'link',
                ref: ref.ref,
                label: ref.label,
                url: ref.url,
            });
        } else {
            pushText(match[0]);
        }

        cursor = start + match[0].length;
    }

    pushText(text.slice(cursor));

    return segments;
}
