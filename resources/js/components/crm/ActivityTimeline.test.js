import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';

const patch = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    router: { patch: (...a) => patch(...a) },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

import ActivityTimeline from './ActivityTimeline.vue';

const viewing = {
    id: 4,
    type: 'viewing',
    subject: null,
    body: null,
    occurred_at: null,
    scheduled_at: '2026-11-10T14:00:00-05:00',
    duration_minutes: 30,
    call_outcome: null,
    viewing_status: 'scheduled',
    feedback: null,
    created_at: null,
    author: { id: 1, name: 'Anna' },
    contact: null,
    property: null,
    deal: null,
    can: { update: true },
};

afterEach(() => {
    document.body.innerHTML = '';
    patch.mockClear();
});

describe('ActivityTimeline complete-viewing dialog', () => {
    it('guards against double submit and shows server errors', async () => {
        const wrapper = mount(ActivityTimeline, {
            attachTo: document.body,
            props: { activities: [viewing] },
        });
        expect(wrapper.find('[data-test="viewing-status"]').text()).toBe(
            'Scheduled',
        );

        await wrapper.find('[data-test="complete-viewing"]').trigger('click');
        await flushPromises();
        const confirm = () =>
            document.body.querySelector(
                '[data-test="confirm-complete-viewing"]',
            );
        confirm().click();
        await flushPromises();
        confirm().click();
        await flushPromises();
        expect(patch).toHaveBeenCalledTimes(1);
        expect(confirm().disabled).toBe(true);

        const options = patch.mock.calls[0][2];
        options.onError({ feedback: 'The feedback is too long.' });
        options.onFinish();
        await flushPromises();

        expect(document.body.textContent).toContain(
            'The feedback is too long.',
        );
        expect(confirm().disabled).toBe(false);
        wrapper.unmount();
    });
});
