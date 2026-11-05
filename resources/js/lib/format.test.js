import { describe, expect, it } from 'vitest';
import { date, money, relative } from './format';

describe('money', () => {
    it('formats whole dollars', () => {
        expect(money(400000)).toBe('₱400,000');
    });

    it('renders null as an em dash', () => {
        expect(money(null)).toBe('—');
    });
});

describe('date', () => {
    it('shows a date-only string as that calendar day', () => {
        expect(date('2026-11-01')).toBe('Nov 1, 2026');
    });

    it('formats a datetime in America/New_York', () => {
        expect(date('2026-11-01T15:30:00Z', true)).toBe(
            'Nov 1, 2026, 10:30 AM',
        );
        expect(date('2026-11-02T03:00:00Z')).toBe('Nov 1, 2026');
    });
});

describe('relative', () => {
    it('renders recent times', () => {
        expect(relative(new Date().toISOString())).toBe('just now');
        expect(
            relative(new Date(Date.now() - 3 * 86400000).toISOString()),
        ).toBe('3 days ago');
    });
});
