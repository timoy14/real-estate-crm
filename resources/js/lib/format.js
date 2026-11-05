const peso = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    maximumFractionDigits: 0,
});

export function money(n) {
    return n === null || n === undefined ? '—' : peso.format(n);
}

export const isRent = (p) => p.listing_type === 'rent';

/** Price as plain text, with "/mo" for rentals. */
export function priceText(p) {
    return money(p.price) + (isRent(p) ? '/mo' : '');
}

/** "under_offer" → "Under offer" (or "under offer" with capitalize = false). */
export function humanize(value, capitalize = true) {
    const text = value.replace(/_/g, ' ');

    return capitalize ? text.replace(/^./, (c) => c.toUpperCase()) : text;
}

/** The app timezone (config/app.php): every CRM date is shown in it. */
export const TZ = 'America/New_York';

const dateOnly = /^(\d{4})-(\d{2})-(\d{2})$/;

export function date(iso, withTime = false) {
    const m = dateOnly.exec(iso);
    // A date-only string is a calendar day, not an instant: build it as local
    // and format it without a zone shift.
    const d = m ? new Date(+m[1], +m[2] - 1, +m[3]) : new Date(iso);

    if (Number.isNaN(d.getTime())) {
        return '—';
    }

    const opts =
        withTime && !m
            ? { dateStyle: 'medium', timeStyle: 'short', timeZone: TZ }
            : m
              ? { dateStyle: 'medium' }
              : { dateStyle: 'medium', timeZone: TZ };

    return new Intl.DateTimeFormat('en-US', opts).format(d);
}

const rtf = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

const UNITS = [
    ['year', 31536000],
    ['month', 2592000],
    ['week', 604800],
    ['day', 86400],
    ['hour', 3600],
    ['minute', 60],
];

function nyDay(ms) {
    const p = new Intl.DateTimeFormat('en-US', {
        timeZone: TZ,
        year: 'numeric',
        month: 'numeric',
        day: 'numeric',
    }).formatToParts(ms);
    const get = (t) => +p.find((x) => x.type === t).value;

    return Math.round(
        Date.UTC(get('year'), get('month') - 1, get('day')) / 86400000,
    );
}

export function relative(iso) {
    const t = new Date(iso).getTime();

    if (Number.isNaN(t)) {
        return '—';
    }

    const seconds = Math.round((t - Date.now()) / 1000);

    if (Math.abs(seconds) >= 86400 && Math.abs(seconds) < 604800) {
        const days = nyDay(t) - nyDay(Date.now());

        return rtf.format(days, 'day');
    }

    for (const [unit, size] of UNITS) {
        if (Math.abs(seconds) >= size) {
            return rtf.format(Math.round(seconds / size), unit);
        }
    }

    return 'just now';
}
