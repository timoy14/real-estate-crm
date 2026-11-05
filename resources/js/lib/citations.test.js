import { describe, expect, it } from 'vitest';
import { renderCitations } from './citations';

const refs = [
    { ref: 'contact:12', label: 'Maria Lopez', url: '/app/clients/12' },
];

describe('renderCitations', () => {
    it('makes a link segment for a known ref', () => {
        expect(renderCitations('Call her [contact:12] today', refs)).toEqual([
            { kind: 'text', text: 'Call her ' },
            {
                kind: 'link',
                ref: 'contact:12',
                label: 'Maria Lopez',
                url: '/app/clients/12',
            },
            { kind: 'text', text: ' today' },
        ]);
    });

    it('keeps an unknown ref as plain text', () => {
        expect(
            renderCitations('See [contact:99] and [contact:12]', refs),
        ).toEqual([
            { kind: 'text', text: 'See [contact:99] and ' },
            {
                kind: 'link',
                ref: 'contact:12',
                label: 'Maria Lopez',
                url: '/app/clients/12',
            },
        ]);
    });

    it('returns one text segment when there are no refs', () => {
        expect(renderCitations('Nothing to cite.', refs)).toEqual([
            { kind: 'text', text: 'Nothing to cite.' },
        ]);
        expect(renderCitations('[contact:5] only', [])).toEqual([
            { kind: 'text', text: '[contact:5] only' },
        ]);
    });

    it('returns no segments for empty text', () => {
        expect(renderCitations('', refs)).toEqual([]);
    });
});
