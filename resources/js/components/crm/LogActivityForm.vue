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
import { idOrNone, idOrNull, NONE, numOrNull } from '@/lib/formValues';
import { route } from '@/lib/route';

const props = defineProps({
    contactId: Number,
    propertyId: Number,
    dealId: Number,
    linkOptions: Object,
});

const emit = defineEmits(['saved']);

const types = [
    { value: 'call', label: 'Call' },
    { value: 'email', label: 'Email' },
    { value: 'meeting', label: 'Meeting' },
    { value: 'note', label: 'Note' },
];
const outcomes = [
    { value: 'connected', label: 'Connected' },
    { value: 'no_answer', label: 'No answer' },
    { value: 'voicemail', label: 'Voicemail' },
    { value: 'busy', label: 'Busy' },
];

// Only type + body are needed; everything else is optional.
const form = useForm({
    type: 'call',
    body: '',
    subject: '',
    call_outcome: NONE,
    duration_minutes: '',
    contact_id: idOrNone(props.contactId),
});

function submit() {
    form.transform((d) => ({
        type: d.type,
        body: d.body || null,
        subject: d.subject || null,
        call_outcome:
            d.type === 'call' && d.call_outcome !== NONE
                ? d.call_outcome
                : null,
        duration_minutes: numOrNull(d.duration_minutes),
        contact_id: idOrNull(d.contact_id),
        property_id: props.propertyId ?? null,
        deal_id: props.dealId ?? null,
    })).post(route('app.activities.store'), {
        preserveScroll: true,
        onSuccess: () => emit('saved'),
    });
}
</script>

<template>
    <form
        class="flex flex-col gap-4 px-4 pb-4"
        data-test="log-activity-form"
        @submit.prevent="submit"
    >
        <div class="grid gap-2">
            <Label for="activity-type">Type</Label>
            <Select v-model="form.type">
                <SelectTrigger
                    id="activity-type"
                    class="w-full"
                    data-test="activity-type"
                    ><SelectValue
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="t in types"
                        :key="t.value"
                        :value="t.value"
                        >{{ t.label }}</SelectItem
                    >
                </SelectContent>
            </Select>
            <InputError :message="form.errors.type" />
        </div>

        <div v-if="!contactId && !dealId && linkOptions" class="grid gap-2">
            <Label for="activity-contact-id">Client</Label>
            <Select v-model="form.contact_id">
                <SelectTrigger id="activity-contact-id" class="w-full"
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
            <Label for="activity-body">What happened?</Label>
            <Textarea
                id="activity-body"
                v-model="form.body"
                rows="4"
                autofocus
                data-test="activity-body"
            />
            <InputError :message="form.errors.body" />
        </div>

        <div v-if="form.type === 'call'" class="grid grid-cols-2 gap-3">
            <div class="grid gap-2">
                <Label for="activity-call-outcome">Outcome</Label>
                <Select v-model="form.call_outcome">
                    <SelectTrigger id="activity-call-outcome" class="w-full"
                        ><SelectValue placeholder="Optional"
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="NONE">Not set</SelectItem>
                        <SelectItem
                            v-for="o in outcomes"
                            :key="o.value"
                            :value="o.value"
                            >{{ o.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.call_outcome" />
            </div>
            <div class="grid gap-2">
                <Label for="activity-duration">Minutes</Label>
                <Input
                    id="activity-duration"
                    v-model="form.duration_minutes"
                    type="number"
                    min="1"
                />
                <InputError :message="form.errors.duration_minutes" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="activity-subject">Subject (optional)</Label>
            <Input
                id="activity-subject"
                v-model="form.subject"
                autocomplete="off"
            />
            <InputError :message="form.errors.subject" />
        </div>

        <InputError :message="form.errors.deal_id" />

        <Button
            type="submit"
            :disabled="form.processing"
            data-test="activity-save"
            >Log activity</Button
        >
    </form>
</template>
