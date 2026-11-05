<script setup>
import { useForm } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { NONE, noneToNull, splitList } from '@/lib/formValues';
import { toggleIn } from '@/lib/utils';
import { route } from '@/lib/route';

const props = defineProps({
    options: { type: Object, required: true },
    client: Object,
    agents: Array,
});

const emit = defineEmits(['saved']);

const form = useForm({
    first_name: props.client?.first_name ?? '',
    last_name: props.client?.last_name ?? '',
    email: props.client?.email ?? '',
    phone: props.client?.phone ?? '',
    roles: [...(props.client?.roles ?? [])],
    source: props.client?.source ?? NONE,
    status: props.client?.status ?? 'new_lead',
    tags: (props.client?.tags ?? []).join(', '),
    notes: props.client?.notes ?? '',
    owner_id: String(props.client?.owner.id ?? ''),
});

const showMore = ref(false);
const isAdmin = computed(() => !!props.agents);

function toggleRole(value, on) {
    form.roles = toggleIn(form.roles, value, on);
}

function submit() {
    form.transform((d) => ({
        ...d,
        source: noneToNull(d.source),
        email: d.email || null,
        phone: d.phone || null,
        tags: splitList(d.tags),
        owner_id: isAdmin.value && d.owner_id ? Number(d.owner_id) : undefined,
    })).submit(
        props.client ? 'patch' : 'post',
        props.client
            ? route('app.clients.update', props.client.id)
            : route('app.clients.store'),
        {
            preserveScroll: true,
            onSuccess: () => emit('saved'),
        },
    );
}
</script>

<template>
    <form
        class="flex flex-col gap-4 px-4 pb-4"
        data-test="client-form"
        @submit.prevent="submit"
    >
        <div class="grid grid-cols-2 gap-3">
            <div class="grid gap-2">
                <Label for="first_name">First name</Label>
                <Input
                    id="first_name"
                    v-model="form.first_name"
                    autocomplete="off"
                />
                <InputError :message="form.errors.first_name" />
            </div>
            <div class="grid gap-2">
                <Label for="last_name">Last name</Label>
                <Input
                    id="last_name"
                    v-model="form.last_name"
                    autocomplete="off"
                />
                <InputError :message="form.errors.last_name" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="email">Email</Label>
            <Input
                id="email"
                v-model="form.email"
                type="email"
                autocomplete="off"
            />
            <InputError :message="form.errors.email" />
        </div>

        <div class="grid gap-2">
            <Label for="phone">Phone</Label>
            <Input id="phone" v-model="form.phone" autocomplete="off" />
            <InputError :message="form.errors.phone" />
        </div>

        <div class="grid gap-2">
            <Label>Roles</Label>
            <div class="grid grid-cols-2 gap-2">
                <Label
                    v-for="r in options.roles"
                    :key="r.value"
                    class="flex items-center gap-2 font-normal"
                >
                    <Checkbox
                        :model-value="form.roles.includes(r.value)"
                        @update:model-value="(v) => toggleRole(r.value, v)"
                    />
                    {{ r.label }}
                </Label>
            </div>
            <InputError :message="form.errors.roles" />
        </div>

        <div class="grid gap-2">
            <Label for="client-source">Source</Label>
            <Select v-model="form.source">
                <SelectTrigger id="client-source" class="w-full">
                    <SelectValue placeholder="Unknown" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="NONE">Unknown</SelectItem>
                    <SelectItem
                        v-for="s in options.sources"
                        :key="s.value"
                        :value="s.value"
                    >
                        {{ s.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <Collapsible v-model:open="showMore" class="grid gap-4">
            <CollapsibleTrigger as-child>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="justify-start"
                >
                    <ChevronDown />
                    More
                </Button>
            </CollapsibleTrigger>
            <CollapsibleContent class="grid gap-4">
                <div class="grid gap-2">
                    <Label for="client-status">Status</Label>
                    <Select v-model="form.status">
                        <SelectTrigger id="client-status" class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="s in options.statuses"
                                :key="s.value"
                                :value="s.value"
                            >
                                {{ s.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.status" />
                </div>
                <div class="grid gap-2">
                    <Label for="tags">Tags (comma separated)</Label>
                    <Input
                        id="tags"
                        v-model="form.tags"
                        placeholder="vip, first-time"
                    />
                </div>
                <div v-if="agents" class="grid gap-2">
                    <Label for="client-owner-id">Owner</Label>
                    <Select v-model="form.owner_id">
                        <SelectTrigger id="client-owner-id" class="w-full"
                            ><SelectValue placeholder="Me"
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="a in agents"
                                :key="a.id"
                                :value="String(a.id)"
                            >
                                {{ a.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-2">
                    <Label for="notes">Notes</Label>
                    <Textarea id="notes" v-model="form.notes" rows="4" />
                </div>
            </CollapsibleContent>
        </Collapsible>

        <Button
            type="submit"
            :disabled="form.processing"
            data-test="client-save"
        >
            {{ client ? 'Save changes' : 'Create client' }}
        </Button>
    </form>
</template>
