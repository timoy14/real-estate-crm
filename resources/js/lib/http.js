/** JSON requests outside Inertia (the bell, the assistant), with Laravel's CSRF cookie. */

export function xsrfToken() {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return m ? decodeURIComponent(m[1]) : '';
}

/** Send a JSON request and return the raw response (the caller checks `ok`/`status`). */
export function sendJson(url, method, body) {
    return fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            ...(body === undefined
                ? {}
                : { 'Content-Type': 'application/json' }),
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        ...(body === undefined ? {} : { body: JSON.stringify(body) }),
    });
}

/** Send a JSON request and parse the reply; a non-2xx status throws. */
export async function fetchJson(url, method = 'GET', body) {
    const res = await sendJson(url, method, body);

    if (!res.ok) {
        throw new Error(String(res.status));
    }

    return res.json();
}
