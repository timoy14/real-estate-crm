import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';

const patch = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    router: { patch: (...a) => patch(...a), delete: vi.fn() },
    Head: { render: () => null },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

import Show from './Show.vue';

const stages = [
    { id: 1, name: 'Inquiry', is_won: false, is_lost: false, count: 1 },
    { id: 2, name: 'Offer', is_won: false, is_lost: false, count: 4 },
    { id: 6, name: 'Lost', is_won: false, is_lost: true, count: 2 },
];

function mountShow(contact = { id: 9, name: 'Maria' }) {
    return mount(Show, {
        attachTo: document.body,
        props: {
            deal: {
                id: 5,
                title: 'Brickell condo',
                pipeline_id: 1,
                stage_id: 1,
                stage: { id: 1, name: 'Inquiry' },
                position: 0,
                status: 'open',
                value: 1000,
                commission_percent: 3,
                estimated_commission: 30,
                expected_close_date: null,
                contact,
                property: null,
                owner: { id: 1, name: 'Anna', initials: 'A' },
                lost_reason: null,
                closed_at: null,
                created_at: null,
                pipeline: {
                    id: 1,
                    name: 'Sales',
                    slug: 'sales',
                    listing_type: 'sale',
                },
                stage_probability: 10,
            },
            can: { update: true, delete: true },
            stages,
            form: { pipelines: [], contacts: [], properties: [] },
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
                DealForm: true,
            },
        },
    });
}

afterEach(() => {
    document.body.innerHTML = '';
    patch.mockClear();
});

describe('Deals/Show stage picker', () => {
    it('moves the deal to the end of the chosen stage', async () => {
        const wrapper = mountShow();
        wrapper.vm.changeStage('2');
        await flushPromises();

        expect(patch).toHaveBeenCalledTimes(1);
        expect(patch.mock.calls[0][0]).toBe('/app/deals/5/move');
        expect(patch.mock.calls[0][1]).toEqual({ stage_id: 2, position: 4 });
        wrapper.unmount();
    });

    it('asks for a lost reason before moving to a lost stage', async () => {
        const wrapper = mountShow();
        wrapper.vm.changeStage('6');
        await flushPromises();
        expect(patch).not.toHaveBeenCalled();

        const reason = document.body.querySelector('[data-test="lost-reason"]');
        reason.value = 'Bought elsewhere';
        reason.dispatchEvent(new Event('input'));
        await flushPromises(); // the passive v-model emits on the next tick
        document.body.querySelector('[data-test="confirm-lost"]').click();
        await flushPromises();

        expect(patch.mock.calls[0][1]).toEqual({
            stage_id: 6,
            position: 2,
            lost_reason: 'Bought elsewhere',
        });
        wrapper.unmount();
    });

    it('has a labelled stage picker and renders without a client', () => {
        const wrapper = mountShow(null);
        expect(wrapper.find('label[for="deal-stage-select"]').exists()).toBe(
            true,
        );
        expect(wrapper.find('#deal-stage-select').exists()).toBe(true);
        expect(wrapper.text()).toContain('Open');
        wrapper.unmount();
    });
});
