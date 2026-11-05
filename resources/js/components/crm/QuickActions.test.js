import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';

vi.mock('@inertiajs/vue3', () => ({
    useForm: (data) =>
        reactive({
            ...data,
            errors: {},
            processing: false,
            post: vi.fn(),
            transform: () => ({ post: vi.fn() }),
        }),
}));

import QuickActions from './QuickActions.vue';

describe('QuickActions', () => {
    it('focuses the body textarea when the log activity sheet opens', async () => {
        const wrapper = mount(QuickActions, {
            attachTo: document.body,
            props: { linkOptions: { contacts: [], properties: [], deals: [] } },
            global: { stubs: { ScheduleViewingForm: true, TaskForm: true } },
        });

        await wrapper.find('[data-test="log-activity"]').trigger('click');
        await flushPromises();
        await new Promise((r) => setTimeout(r, 0));
        await flushPromises();

        expect(document.activeElement?.id).toBe('activity-body');
        wrapper.unmount();
    });
});
