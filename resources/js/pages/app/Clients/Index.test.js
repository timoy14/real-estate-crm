import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';

const post = vi.fn();
const get = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    router: {
        post: (...a) => post(...a),
        get: (...a) => get(...a),
    },
    Head: { render: () => null },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

import Index from './Index.vue';

const opt = (v) => ({ value: v, label: v });
const row = (id) => ({
    id,
    name: `Client ${id}`,
    email: null,
    phone: null,
    roles: [],
    status: 'active',
    source: null,
    tags: [],
    owner: { id: 1, name: 'Anna' },
    updated_at: null,
});

function mountIndex() {
    return mount(Index, {
        attachTo: document.body,
        props: {
            clients: {
                data: [row(1), row(2)],
                current_page: 1,
                last_page: 1,
                total: 2,
                prev_page_url: null,
                next_page_url: null,
            },
            filters: {
                search: '',
                role: null,
                status: null,
                tag: null,
                owner_id: null,
                sort: 'updated_at',
                direction: 'desc',
            },
            options: {
                roles: [],
                statuses: [opt('active')],
                sources: [],
                listing_types: [],
                property_types: [],
                features: [],
            },
            tags: [],
        },
        global: { stubs: { ClientForm: true } },
    });
}

afterEach(() => {
    document.body.innerHTML = '';
    post.mockClear();
});

describe('Clients/Index bulk actions', () => {
    it('confirming a bulk delete posts the selected ids', async () => {
        const wrapper = mountIndex();
        wrapper.vm.selected = [1, 2];
        await flushPromises();

        await wrapper.find('[data-test="bulk-delete"]').trigger('click');
        await flushPromises();
        const confirm = document.body.querySelector(
            '[data-test="bulk-confirm"]',
        );
        expect(confirm).not.toBeNull();
        confirm.click();
        await flushPromises();

        expect(post).toHaveBeenCalledTimes(1);
        expect(post.mock.calls[0][1]).toEqual({
            ids: [1, 2],
            action: 'delete',
        });
        wrapper.unmount();
    });
});
