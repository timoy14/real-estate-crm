import { usePage } from '@inertiajs/vue3';
import { route as ziggyRoute } from 'ziggy-js';

// Relative URLs, so links work whatever APP_URL is.
// In the browser, @routes defines the global Ziggy. During SSR there is no such
// global, so fall back to the routes shared as the `ziggy` Inertia prop.
export function route(name, params) {
    return ziggyRoute(
        name,
        params,
        false,
        globalThis.Ziggy ?? usePage().props.ziggy,
    );
}
