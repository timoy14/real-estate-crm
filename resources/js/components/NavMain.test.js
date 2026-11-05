import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { defineComponent, h, ref } from 'vue';

const setOpenMobile = vi.fn();
const isMobile = ref(true);

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ url: '/app/clients/5' }),
    Link: defineComponent({
        props: ['href'],
        setup:
            (props, { slots, attrs }) =>
            () =>
                h('a', { href: props.href, ...attrs }, slots.default?.()),
    }),
}));

vi.mock('@/components/ui/sidebar', () => {
    const pass = (tag) =>
        defineComponent({
            inheritAttrs: false,
            props: ['isActive', 'tooltip', 'asChild'],
            setup:
                (props, { slots, attrs }) =>
                () =>
                    h(
                        tag,
                        {
                            ...attrs,
                            'data-active': props.isActive ? 'true' : undefined,
                        },
                        slots.default?.(),
                    ),
        });

    return {
        SidebarGroup: pass('div'),
        SidebarGroupLabel: pass('div'),
        SidebarMenu: pass('ul'),
        SidebarMenuItem: pass('li'),
        SidebarMenuButton: pass('div'),
        SidebarMenuBadge: pass('span'),
        useSidebar: () => ({ isMobile, setOpenMobile }),
    };
});

import NavMain from './NavMain.vue';

const items = [
    { title: 'My Day', href: '/app/my-day' },
    { title: 'Clients', href: '/app/clients' },
];

describe('NavMain', () => {
    it('highlights the parent item on a detail page', () => {
        const wrapper = mount(NavMain, { props: { items } });
        const buttons = wrapper.findAll('[data-active]');
        expect(buttons.map((b) => b.text())).toEqual(['Clients']);
    });

    it('closes the mobile drawer when a nav link is tapped', async () => {
        setOpenMobile.mockClear();
        const wrapper = mount(NavMain, { props: { items } });
        await wrapper.findAll('a')[0].trigger('click');
        expect(setOpenMobile).toHaveBeenCalledWith(false);
    });
});
