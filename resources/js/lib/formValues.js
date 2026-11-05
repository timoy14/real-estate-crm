/** Select value for "nothing picked" (a Select cannot hold an empty string). */
export const NONE = '__none';

/** An optional id as a Select value. */
export const idOrNone = (id) => (id ? String(id) : NONE);

/** A Select value back to an id, or null for NONE. */
export const idOrNull = (v) => (v === NONE ? null : Number(v));

/** A Select value back to a string, or null for NONE. */
export const noneToNull = (v) => (v === NONE ? null : v);

/** A number as text-input value ('' when empty). */
export const numText = (n) => (n === null || n === undefined ? '' : String(n));

/** A numeric text input back to a number, or null when blank. */
export const numOrNull = (v) => (v === '' ? null : Number(v));

/** Comma-separated text to its trimmed, non-empty items. */
export const splitList = (s) =>
    s
        .split(',')
        .map((x) => x.trim())
        .filter(Boolean);
