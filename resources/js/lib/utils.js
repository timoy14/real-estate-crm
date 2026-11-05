import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs) {
    return twMerge(clsx(inputs));
}

export function toUrl(href) {
    return typeof href === 'string' ? href : href?.url;
}

/** Add `value` to `list` (once) when `on` is true, otherwise remove it; for checkbox groups. */
export function toggleIn(list, value, on) {
    return on === true
        ? [...new Set([...list, value])]
        : list.filter((x) => x !== value);
}
