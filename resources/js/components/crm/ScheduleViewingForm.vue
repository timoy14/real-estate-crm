<script setup>
import { useForm } from '@inertiajs/vue3';
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
import { Textarea } from '@/components/ui/textarea';
import { route } from '@/lib/route';

const props = defineProps({
    contactId: Number,
    propertyId: Number,
    dealId: Number,
    linkOptions: { type: Object, required: true },
});

const emit = defineEmits(['saved']);

const form = useForm({
    property_id: props.propertyId ? String(props.propertyId) : '',
    contact_id: props.contactId ? String(props.contactId) : '',
    scheduled_at: '',
    duration_minutes: '30',
    body: '',
});

function submit() {
    form.transform((d) => ({
        type: 'viewing',
        property_id: d.property_id ? Number(d.property_id) : null,
        contact_id: d.contact_id ? Number(d.contact_id) : null,
        deal_id: props.dealId ?? null,
        scheduled_at: d.scheduled_at || null,
        duration_minutes:
            d.duration_minutes === '' ? null : Number(d.duration_minutes),
        body: d.body || null,
    })).post(route('app.activities.store'), {
        preserveScroll: true,
        onSuccess: () => emit('saved'),
    });
}
</script>

<template>
    <form
        class="flex flex-col gap-4 px-4 pb-4"
        data-test="schedule-viewing-form"
        @submit.prevent="submit"
    >
        <div v-if="!propertyId" class="grid gap-2">
            <Label for="viewing-property-id">Property</Label>
            <Select v-model="form.property_id">
                <SelectTrigger
                    id="viewing-property-id"
                    class="w-full"
                    data-test="viewing-property"
                    ><SelectValue placeholder="Pick a property"
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="p in linkOptions.properties"
                        :key="p.id"
                        :value="String(p.id)"
                        >{{ p.title }}</SelectItem
                    >
                </SelectContent>
            </Select>
            <InputError :message="form.errors.property_id" />
        </div>

        <div v-if="!contactId" class="grid gap-2">
            <Label for="viewing-contact-id">Client</Label>
            <Select v-model="form.contact_id">
                <SelectTrigger
                    id="viewing-contact-id"
                    class="w-full"
                    data-test="viewing-contact"
                    ><SelectValue placeholder="Pick a client"
                /></SelectTrigger>
                <SelectContent>
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

        <div class="grid grid-cols-2 gap-3">
            <div class="grid gap-2">
                <Label for="viewing-when">When</Label>
                <Input
                    id="viewing-when"
                    v-model="form.scheduled_at"
                    type="datetime-local"
                    data-test="viewing-when"
                />
                <InputError :message="form.errors.scheduled_at" />
            </div>
            <div class="grid gap-2">
                <Label for="viewing-duration">Minutes</Label>
                <Input
                    id="viewing-duration"
                    v-model="form.duration_minutes"
                    type="number"
                    min="1"
                />
                <InputError :message="form.errors.duration_minutes" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="viewing-notes">Notes (optional)</Label>
            <Textarea id="viewing-notes" v-model="form.body" rows="3" />
        </div>

        <Button
            type="submit"
            :disabled="form.processing"
            data-test="viewing-save"
            >Schedule viewing</Button
        >
    </form>
</template>
