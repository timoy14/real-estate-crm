<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { idOrNone, idOrNull, NONE } from '@/lib/formValues';
import { route } from '@/lib/route';

const props = defineProps({
    task: Object,
    contactId: Number,
    propertyId: Number,
    dealId: Number,
    linkOptions: Object,
    // Show the client / property / deal pickers (the Tasks page).
    showLinks: Boolean,
    agents: Array,
});

const emit = defineEmits(['saved']);

const form = useForm({
    title: props.task?.title ?? '',
    // The server sends ISO with the app-timezone offset, so the first 16 characters are the local time.
    due_at: props.task?.due_at ? props.task.due_at.slice(0, 16) : '',
    priority: props.task?.priority ?? 'normal',
    owner_id: props.task ? String(props.task.owner.id) : '',
    contact_id: idOrNone(props.task?.contact?.id ?? props.contactId),
    property_id: idOrNone(props.task?.property?.id ?? props.propertyId),
    deal_id: idOrNone(props.task?.deal?.id ?? props.dealId),
});

const isAdmin = computed(() => !!props.agents);

function submit() {
    form.transform((d) => ({
        title: d.title,
        due_at: d.due_at || null,
        priority: d.priority,
        owner_id: isAdmin.value && d.owner_id ? Number(d.owner_id) : undefined,
        contact_id: idOrNull(d.contact_id),
        property_id: idOrNull(d.property_id),
        deal_id: idOrNull(d.deal_id),
    })).submit(
        props.task ? 'patch' : 'post',
        props.task
            ? route('app.tasks.update', props.task.id)
            : route('app.tasks.store'),
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
        data-test="task-form"
        @submit.prevent="submit"
    >
        <div class="grid gap-2">
            <Label for="task-title">Title</Label>
            <Input
                id="task-title"
                v-model="form.title"
                autocomplete="off"
                data-test="task-title"
            />
            <InputError :message="form.errors.title" />
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div class="grid gap-2">
                <Label for="task-due">Due</Label>
                <Input
                    id="task-due"
                    v-model="form.due_at"
                    type="datetime-local"
                />
                <InputError :message="form.errors.due_at" />
            </div>
            <div class="grid gap-2">
                <Label for="task-priority">Priority</Label>
                <Select v-model="form.priority">
                    <SelectTrigger id="task-priority" class="w-full"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="low">Low</SelectItem>
                        <SelectItem value="normal">Normal</SelectItem>
                        <SelectItem value="high">High</SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <div v-if="agents" class="grid gap-2">
            <Label for="task-owner-id">Assignee</Label>
            <Select v-model="form.owner_id">
                <SelectTrigger id="task-owner-id" class="w-full"
                    ><SelectValue placeholder="Me"
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="a in agents"
                        :key="a.id"
                        :value="String(a.id)"
                        >{{ a.name }}</SelectItem
                    >
                </SelectContent>
            </Select>
        </div>

        <template v-if="showLinks && linkOptions">
            <div class="grid gap-2">
                <Label for="task-contact-id">Client</Label>
                <Select v-model="form.contact_id">
                    <SelectTrigger id="task-contact-id" class="w-full"
                        ><SelectValue placeholder="None"
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="NONE">None</SelectItem>
                        <SelectItem
                            v-for="c in linkOptions.contacts"
                            :key="c.id"
                            :value="String(c.id)"
                            >{{ c.name }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.contact_id" />
            </div>
            <div class="grid gap-2">
                <Label for="task-property-id">Property</Label>
                <Select v-model="form.property_id">
                    <SelectTrigger id="task-property-id" class="w-full"
                        ><SelectValue placeholder="None"
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="NONE">None</SelectItem>
                        <SelectItem
                            v-for="p in linkOptions.properties"
                            :key="p.id"
                            :value="String(p.id)"
                            >{{ p.title }}</SelectItem
                        >
                    </SelectContent>
                </Select>
            </div>
            <div class="grid gap-2">
                <Label for="task-deal-id">Deal</Label>
                <Select v-model="form.deal_id">
                    <SelectTrigger id="task-deal-id" class="w-full"
                        ><SelectValue placeholder="None"
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="NONE">None</SelectItem>
                        <SelectItem
                            v-for="d in linkOptions.deals"
                            :key="d.id"
                            :value="String(d.id)"
                            >{{ d.title }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.deal_id" />
            </div>
        </template>

        <Button type="submit" :disabled="form.processing" data-test="task-save">
            {{ task ? 'Save changes' : 'Add task' }}
        </Button>
    </form>
</template>
