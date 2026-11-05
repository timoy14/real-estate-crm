import { mount } from '@vue/test-utils';
import { nextTick, reactive } from 'vue';
import { describe, expect, it, vi } from 'vitest';

const post = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    useForm: (data) =>
        reactive({
            ...data,
            errors: {},
            processing: false,
            post,
            reset: vi.fn(),
        }),
}));

import InquiryForm from './InquiryForm.vue';

const mountForm = () =>
    mount(InquiryForm, {
        props: {
            propertySlug: 'brickell-loft',
            propertyTitle: 'Brickell Loft',
            agentName: 'Anna Reyes',
        },
    });

describe('InquiryForm', () => {
    it('blocks submit with an error when both email and phone are empty', async () => {
        post.mockClear();
        const wrapper = mountForm();

        await wrapper.find('#inq-name').setValue('Maria');
        await wrapper.find('form').trigger('submit');

        expect(post).not.toHaveBeenCalled();
        expect(wrapper.find('[data-test="contact-error"]').text()).toContain(
            'email or a phone number',
        );
    });

    it('prefills the message with the property title', () => {
        const wrapper = mountForm();

        expect(wrapper.find('#inq-message').element.value).toBe(
            "Hi, I'm interested in Brickell Loft. Please contact me.",
        );
    });

    it('shows a friendly message instead of the error modal when rate limited (429)', async () => {
        post.mockClear();
        const wrapper = mountForm();
        await wrapper.find('#inq-name').setValue('Maria');
        await wrapper.find('#inq-email').setValue('maria@example.com');
        wrapper.vm.form.consent = true;
        await wrapper.find('form').trigger('submit');

        expect(post).toHaveBeenCalledTimes(1);
        const options = post.mock.calls[0][1];
        expect(options.onHttpException({ status: 429 })).toBe(false);
        await nextTick();

        expect(wrapper.text()).toContain(
            'Too many messages. Please wait a minute and try again.',
        );
        // Other HTTP errors keep Inertia's default handling.
        expect(options.onHttpException({ status: 500 })).not.toBe(false);
    });
});
