import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';

const del = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    router: { delete: (...a) => del(...a) },
    Head: { render: () => null },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ props: {} }),
}));

import Show from './Show.vue';

const wishlist = {
    id: 7,
    listing_type: 'sale',
    property_types: [],
    min_budget: null,
    max_budget: null,
    min_bedrooms: null,
    min_bathrooms: null,
    areas: [],
    must_have_features: [],
    move_in_date: null,
    notes: null,
};

function mountShow() {
    return mount(Show, {
        attachTo: document.body,
        props: {
            client: {
                id: 3,
                name: 'Maria Lopez',
                first_name: 'Maria',
                last_name: 'Lopez',
                email: null,
                phone: null,
                roles: [],
                status: 'active',
                source: null,
                tags: [],
                owner: { id: 1, name: 'Anna' },
                updated_at: null,
                notes: null,
                created_at: null,
            },
            wishlists: [wishlist],
            can: { update: true, delete: true },
            options: {
                roles: [],
                statuses: [],
                sources: [],
                listing_types: [],
                property_types: [],
                features: [],
            },
            matches: [],
            deals: [],
            activities: [],
            tasks: [],
            link_options: { contacts: [], properties: [], deals: [] },
        },
        global: {
            stubs: {
                AskAiPanel: true,
                ActivityTimeline: true,
                QuickActions: true,
                TaskList: true,
                ClientForm: true,
                DealList: true,
                MatchList: true,
                WishlistForm: true,
            },
        },
    });
}

afterEach(() => {
    document.body.innerHTML = '';
    del.mockClear();
});

describe('Clients/Show confirm dialogs', () => {
    it('confirming a wishlist delete sends the delete request', async () => {
        const wrapper = mountShow();

        await wrapper.find('[data-test="delete-wishlist"]').trigger('click');
        await flushPromises();
        document.body
            .querySelector('[data-test="confirm-delete-wishlist"]')
            .click();
        await flushPromises();

        expect(del).toHaveBeenCalledTimes(1);
        expect(del.mock.calls[0][0]).toBe('/app/wishlists/7');
        wrapper.unmount();
    });

    it('confirming the client delete sends the delete request', async () => {
        const wrapper = mountShow();

        await wrapper.find('[data-test="delete-client"]').trigger('click');
        await flushPromises();
        document.body
            .querySelector('[data-test="confirm-delete-client"]')
            .click();
        await flushPromises();

        expect(del).toHaveBeenCalledTimes(1);
        expect(del.mock.calls[0][0]).toBe('/app/clients/3');
        wrapper.unmount();
    });

    it('the New deal link carries the client so the deal form is pre-filled', async () => {
        const wrapper = mountShow();
        const tab = wrapper
            .findAll('button[role="tab"]')
            .find((b) => b.text() === 'Deals');
        await tab.trigger('mousedown', { button: 0 });
        await flushPromises();
        expect(wrapper.find('[data-test="add-deal"]').attributes('href')).toBe(
            '/app/deals?new=1&contact_id=3',
        );
        wrapper.unmount();
    });
});
