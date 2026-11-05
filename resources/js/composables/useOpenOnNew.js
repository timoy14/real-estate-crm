import { onMounted, watch } from 'vue';

/**
 * The New menu and record pages link here with ?new=1 to open the create/edit sheet
 * on load; closing it drops the query so a reload does not reopen it.
 */
export function useOpenOnNew(isOpen, { cleanUrl, open, when }) {
    onMounted(() => {
        const params = new URLSearchParams(window.location.search);

        if ((when?.() ?? true) && params.get('new') === '1') {
            if (open) {
                open(params);
            } else {
                isOpen.value = true;
            }
        }
    });

    watch(isOpen, (value) => {
        if (!value && window.location.search.includes('new=')) {
            window.history.replaceState(window.history.state, '', cleanUrl());
        }
    });
}
