<script setup>
import { useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { numOrNull, splitList } from '@/lib/formValues';
import { toggleIn } from '@/lib/utils';
import { route } from '@/lib/route';

const props = defineProps({
    clientId: { type: Number, required: true },
    options: { type: Object, required: true },
    wishlist: Object,
});

const emit = defineEmits(['saved']);

const w = props.wishlist;
const form = useForm({
    listing_type: w?.listing_type ?? 'sale',
    property_types: [...(w?.property_types ?? [])],
    min_budget: w?.min_budget?.toString() ?? '',
    max_budget: w?.max_budget?.toString() ?? '',
    min_bedrooms: w?.min_bedrooms?.toString() ?? '',
    min_bathrooms: w?.min_bathrooms?.toString() ?? '',
    areas: (w?.areas ?? []).join(', '),
    must_have_features: [...(w?.must_have_features ?? [])],
    move_in_date: w?.move_in_date ?? '',
    notes: w?.notes ?? '',
});

function toggleType(value, on) {
    form.property_types = toggleIn(form.property_types, value, on);
}

function toggleFeature(value, on) {
    form.must_have_features = toggleIn(form.must_have_features, value, on);
}

function submit() {
    form.transform((d) => ({
        ...d,
        min_budget: numOrNull(d.min_budget),
        max_budget: numOrNull(d.max_budget),
        min_bedrooms: numOrNull(d.min_bedrooms),
        min_bathrooms: numOrNull(d.min_bathrooms),
        areas: splitList(d.areas),
        move_in_date: d.move_in_date || null,
    })).submit(
        w ? 'patch' : 'post',
        w
            ? route('app.clients.wishlists.update', w.id)
            : route('app.clients.wishlists.store', props.clientId),
        { preserveScroll: true, onSuccess: () => emit('saved') },
    );
}
</script>

<template>
    <form
        class="flex flex-col gap-4"
        data-test="wishlist-form"
        @submit.prevent="submit"
    >
        <div class="grid gap-2">
            <Label for="wishlist-listing-type">Looking to</Label>
            <Select v-model="form.listing_type">
                <SelectTrigger id="wishlist-listing-type" class="w-full"
                    ><SelectValue
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="t in options.listing_types"
                        :key="t.value"
                        :value="t.value"
                    >
                        {{ t.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <InputError :message="form.errors.listing_type" />
        </div>

        <div class="grid gap-2">
            <Label>Property types</Label>
            <div class="grid grid-cols-3 gap-2">
                <Label
                    v-for="t in options.property_types"
                    :key="t.value"
                    class="flex items-center gap-2 font-normal"
                >
                    <Checkbox
                        :model-value="form.property_types.includes(t.value)"
                        @update:model-value="(v) => toggleType(t.value, v)"
                    />
                    {{ t.label }}
                </Label>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div class="grid gap-2">
                <Label for="min_budget">Min budget</Label>
                <Input
                    id="min_budget"
                    v-model="form.min_budget"
                    type="number"
                    min="0"
                />
                <InputError :message="form.errors.min_budget" />
            </div>
            <div class="grid gap-2">
                <Label for="max_budget">Max budget</Label>
                <Input
                    id="max_budget"
                    v-model="form.max_budget"
                    type="number"
                    min="0"
                />
                <InputError :message="form.errors.max_budget" />
            </div>
            <div class="grid gap-2">
                <Label for="min_bedrooms">Min bedrooms</Label>
                <Input
                    id="min_bedrooms"
                    v-model="form.min_bedrooms"
                    type="number"
                    min="0"
                />
                <InputError :message="form.errors.min_bedrooms" />
            </div>
            <div class="grid gap-2">
                <Label for="min_bathrooms">Min bathrooms</Label>
                <Input
                    id="min_bathrooms"
                    v-model="form.min_bathrooms"
                    type="number"
                    min="0"
                />
                <InputError :message="form.errors.min_bathrooms" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="areas">Areas (comma separated)</Label>
            <Input id="areas" v-model="form.areas" />
        </div>
        <div class="grid gap-2">
            <Label>Must-have features</Label>
            <div class="grid grid-cols-2 gap-2">
                <Label
                    v-for="f in options.features"
                    :key="f.value"
                    class="flex items-center gap-2 font-normal"
                >
                    <Checkbox
                        :model-value="form.must_have_features.includes(f.value)"
                        @update:model-value="(v) => toggleFeature(f.value, v)"
                    />
                    {{ f.label }}
                </Label>
            </div>
            <InputError :message="form.errors.must_have_features" />
        </div>
        <div class="grid gap-2">
            <Label for="move_in_date">Move-in date</Label>
            <Input id="move_in_date" v-model="form.move_in_date" type="date" />
            <InputError :message="form.errors.move_in_date" />
        </div>
        <div class="grid gap-2">
            <Label for="wl_notes">Notes</Label>
            <Textarea id="wl_notes" v-model="form.notes" rows="3" />
        </div>

        <Button
            type="submit"
            :disabled="form.processing"
            data-test="wishlist-save"
        >
            {{ wishlist ? 'Save wishlist' : 'Add wishlist' }}
        </Button>
    </form>
</template>
