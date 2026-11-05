import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';

const patch = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    router: { patch: (...a) => patch(...a), get: vi.fn() },
    Head: { render: () => null },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

import Board from './Board.vue';

const deal = (id, stageId) => ({
    id,
    title: `Deal ${id}`,
    pipeline_id: 1,
    stage_id: stageId,
    stage: null,
    position: 0,
    status: 'open',
    value: 1000,
    commission_percent: 3,
    estimated_commission: 30,
    expected_close_date: null,
    contact: { id: 9, name: 'Maria' },
    property: null,
    owner: { id: 1, name: 'Anna', initials: 'A' },
});
const stage = (id, deals, extra = {}) => ({
    id,
    name: `S${id}`,
    position: id,
    probability: 10,
    is_won: false,
    is_lost: false,
    count: deals.length,
    total_value: 0,
    deals,
    ...extra,
});

const DealFormStub = {
    name: 'DealForm',
    props: ['options', 'agents', 'pipelineSlug', 'contactId', 'propertyId'],
    template: '<div />',
};

function mountBoard() {
    return mount(Board, {
        attachTo: document.body,
        props: {
            pipeline: {
                id: 1,
                name: 'Sales',
                slug: 'sales',
                listing_type: 'sale',
            },
            pipelines: [
                { id: 1, name: 'Sales', slug: 'sales', listing_type: 'sale' },
            ],
            view: 'board',
            stages: [stage(1, [deal(11, 1)]), stage(2, [])],
            form: { pipelines: [], contacts: [], properties: [] },
        },
        global: {
            stubs: {
                DealForm: DealFormStub,
                VueDraggable: { template: '<div><slot /></div>' },
            },
        },
    });
}

afterEach(() => {
    document.body.innerHTML = '';
    patch.mockClear();
    window.history.replaceState(null, '', '/');
});

/** Simulate dragging deal 11 from stage 1 to stage 2 and return the visit options. */
function dragAcross(vm) {
    vm.startDrag();
    const [from, to] = vm.columns;
    to.deals.push(from.deals.pop());
    vm.dropped(to, { item: { dataset: { dealId: '11' } }, newIndex: 0 });
    return patch.mock.calls[0][2];
}

describe('Deals/Board', () => {
    it.each([
        ['onHttpException', { status: 500 }],
        ['onNetworkError', new Error('offline')],
    ])(
        'restores the columns when the move fails (%s)',
        async (callback, arg) => {
            const wrapper = mountBoard();
            const vm = wrapper.vm;
            const options = dragAcross(vm);
            expect(vm.columns[1].deals.map((d) => d.id)).toEqual([11]);

            const result = options[callback](arg);
            await flushPromises();

            expect(vm.columns[0].deals.map((d) => d.id)).toEqual([11]);
            expect(vm.columns[1].deals).toEqual([]);
            // Inertia's default handling (error page / rejection) is kept.
            expect(result).not.toBe(false);
            wrapper.unmount();
        },
    );

    it('passes contact_id and property_id from the URL into the new deal form', async () => {
        window.history.replaceState(
            null,
            '',
            '/app/deals?new=1&contact_id=5&property_id=8',
        );
        const wrapper = mountBoard();
        await flushPromises();

        const form = wrapper.findComponent(DealFormStub);
        expect(form.exists()).toBe(true);
        expect(form.props('contactId')).toBe(5);
        expect(form.props('propertyId')).toBe(8);
        wrapper.unmount();
    });
});
