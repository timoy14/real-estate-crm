<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
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
import { idOrNull, NONE, numOrNull } from '@/lib/formValues';
import { route } from '@/lib/route';

const props = defineProps({
    options: { type: Object, required: true },
    deal: Object,
    agents: Array,
    // Pre-selected pipeline slug for new deals.
    pipelineSlug: String,
    contactId: Number,
    propertyId: Number,
});

const emit = defineEmits(['saved']);

const defaultPipeline = computed(
    () =>
        props.options.pipelines.find((p) => p.id === props.deal?.pipeline_id) ??
        props.options.pipelines.find((p) => p.slug === props.pipelineSlug) ??
        props.options.pipelines[0],
);

const form = useForm({
    title: props.deal?.title ?? '',
    pipeline_id: String(defaultPipeline.value?.id ?? ''),
    stage_id: String(
        props.deal?.stage_id ?? defaultPipeline.value?.stages[0]?.id ?? '',
    ),
    contact_id: props.deal
        ? String(props.deal.contact?.id ?? '')
        : props.contactId
          ? String(props.contactId)
          : '',
    property_id: props.deal?.property
        ? String(props.deal.property.id)
        : props.propertyId
          ? String(props.propertyId)
          : NONE,
    value:
        props.deal?.value !== null && props.deal?.value !== undefined
            ? String(props.deal.value)
            : '',
    commission_percent: props.deal ? String(props.deal.commission_percent) : '',
    expected_close_date: props.deal?.expected_close_date ?? '',
    owner_id: String(props.deal?.owner.id ?? ''),
});

const stages = computed(
    () =>
        props.options.pipelines.find((p) => String(p.id) === form.pipeline_id)
            ?.stages ?? [],
);

// Changing pipeline resets the stage to that pipeline's first.
watch(
    () => form.pipeline_id,
    () => {
        if (!stages.value.some((s) => String(s.id) === form.stage_id)) {
            form.stage_id = String(stages.value[0]?.id ?? '');
        }
    },
);

const isAdmin = computed(() => !!props.agents);

function submit() {
    form.transform((d) => ({
        title: d.title,
        ...(props.deal
            ? {}
            : {
                  pipeline_id: Number(d.pipeline_id),
                  stage_id: Number(d.stage_id),
              }),
        contact_id: Number(d.contact_id),
        property_id: idOrNull(d.property_id),
        value: numOrNull(d.value),
        commission_percent: numOrNull(d.commission_percent),
        expected_close_date: d.expected_close_date || null,
        owner_id: isAdmin.value && d.owner_id ? Number(d.owner_id) : undefined,
    })).submit(
        props.deal ? 'patch' : 'post',
        props.deal
            ? route('app.deals.update', props.deal.id)
            : route('app.deals.store'),
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
        data-test="deal-form"
        @submit.prevent="submit"
    >
        <div class="grid gap-2">
            <Label for="deal-title">Title</Label>
            <Input id="deal-title" v-model="form.title" autocomplete="off" />
            <InputError :message="form.errors.title" />
        </div>

        <template v-if="!deal">
            <div class="grid grid-cols-2 gap-3">
                <div class="grid gap-2">
                    <Label for="deal-pipeline-id">Pipeline</Label>
                    <Select v-model="form.pipeline_id">
                        <SelectTrigger id="deal-pipeline-id" class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="p in options.pipelines"
                                :key="p.id"
                                :value="String(p.id)"
                            >
                                {{ p.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-2">
                    <Label for="deal-stage-id">Stage</Label>
                    <Select v-model="form.stage_id">
                        <SelectTrigger id="deal-stage-id" class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="s in stages"
                                :key="s.id"
                                :value="String(s.id)"
                                >{{ s.name }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.stage_id" />
                </div>
            </div>
        </template>

        <div class="grid gap-2">
            <Label for="deal-contact-id">Client</Label>
            <Select v-model="form.contact_id">
                <SelectTrigger
                    id="deal-contact-id"
                    class="w-full"
                    data-test="deal-contact"
                    ><SelectValue placeholder="Pick a client"
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="c in options.contacts"
                        :key="c.id"
                        :value="String(c.id)"
                        >{{ c.name }}</SelectItem
                    >
                </SelectContent>
            </Select>
            <InputError :message="form.errors.contact_id" />
        </div>

        <div class="grid gap-2">
            <Label for="deal-property-id">Property</Label>
            <Select v-model="form.property_id">
                <SelectTrigger id="deal-property-id" class="w-full"
                    ><SelectValue placeholder="None"
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem :value="NONE">None</SelectItem>
                    <SelectItem
                        v-for="p in options.properties"
                        :key="p.id"
                        :value="String(p.id)"
                        >{{ p.title }}</SelectItem
                    >
                </SelectContent>
            </Select>
            <InputError :message="form.errors.property_id" />
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div class="grid gap-2">
                <Label for="deal-value">Value (PHP)</Label>
                <Input
                    id="deal-value"
                    v-model="form.value"
                    type="number"
                    min="0"
                />
                <InputError :message="form.errors.value" />
            </div>
            <div class="grid gap-2">
                <Label for="deal-commission">Commission %</Label>
                <Input
                    id="deal-commission"
                    v-model="form.commission_percent"
                    type="number"
                    min="0"
                    max="100"
                    step="0.01"
                    placeholder="Default"
                />
                <InputError :message="form.errors.commission_percent" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="deal-close">Expected close date</Label>
            <Input
                id="deal-close"
                v-model="form.expected_close_date"
                type="date"
            />
            <InputError :message="form.errors.expected_close_date" />
        </div>

        <div v-if="agents" class="grid gap-2">
            <Label for="deal-owner-id">Owner</Label>
            <Select v-model="form.owner_id">
                <SelectTrigger id="deal-owner-id" class="w-full"
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

        <Button type="submit" :disabled="form.processing" data-test="deal-save">
            {{ deal ? 'Save changes' : 'Create deal' }}
        </Button>
    </form>
</template>
