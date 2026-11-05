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
import { idOrNone, idOrNull, NONE, numOrNull, numText } from '@/lib/formValues';
import { toggleIn } from '@/lib/utils';
import { route } from '@/lib/route';

const props = defineProps({
    options: { type: Object, required: true },
    property: Object,
    contacts: Array,
    agents: Array,
});

const emit = defineEmits(['saved']);

const form = useForm({
    title: props.property?.title ?? '',
    address: props.property?.address ?? '',
    area: props.property?.area ?? '',
    city: props.property?.city ?? '',
    listing_type: props.property?.listing_type ?? 'sale',
    property_type: props.property?.property_type ?? 'house',
    price: numText(props.property?.price),
    bedrooms: numText(props.property?.bedrooms),
    bathrooms: numText(props.property?.bathrooms),
    floor_area_sqft: numText(props.property?.floor_area_sqft),
    lot_area_sqft: numText(props.property?.lot_area_sqft),
    year_built: numText(props.property?.year_built),
    latitude: numText(props.property?.latitude),
    longitude: numText(props.property?.longitude),
    features: [...(props.property?.features ?? [])],
    description: props.property?.description ?? '',
    status: props.property?.status ?? 'draft',
    is_published: props.property?.is_published ?? false,
    hide_exact_address: props.property?.hide_exact_address ?? false,
    owner_contact_id: idOrNone(props.property?.owner_contact_id),
    listing_agent_id: String(props.property?.listing_agent?.id ?? ''),
});

const showMore = ref(false);
const isAdmin = computed(() => !!props.agents);

function toggleFeature(value, on) {
    form.features = toggleIn(form.features, value, on);
}

function submit() {
    form.transform((d) => ({
        ...d,
        price: numOrNull(d.price),
        bedrooms: numOrNull(d.bedrooms),
        bathrooms: numOrNull(d.bathrooms),
        floor_area_sqft: numOrNull(d.floor_area_sqft),
        lot_area_sqft: numOrNull(d.lot_area_sqft),
        year_built: numOrNull(d.year_built),
        latitude: numOrNull(d.latitude),
        longitude: numOrNull(d.longitude),
        owner_contact_id: idOrNull(d.owner_contact_id),
        listing_agent_id:
            isAdmin.value && d.listing_agent_id
                ? Number(d.listing_agent_id)
                : undefined,
    })).submit(
        props.property ? 'patch' : 'post',
        props.property
            ? route('app.properties.update', props.property.slug)
            : route('app.properties.store'),
        { preserveScroll: true, onSuccess: () => emit('saved') },
    );
}
</script>

<template>
    <form
        class="flex flex-col gap-4 px-4 pb-4"
        data-test="property-form"
        @submit.prevent="submit"
    >
        <div class="grid gap-2">
            <Label for="p_title">Title</Label>
            <Input id="p_title" v-model="form.title" autocomplete="off" />
            <InputError :message="form.errors.title" />
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div class="grid gap-2">
                <Label for="property-listing-type">Listing type</Label>
                <Select v-model="form.listing_type">
                    <SelectTrigger id="property-listing-type" class="w-full"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="o in options.listing_types"
                            :key="o.value"
                            :value="o.value"
                            >{{ o.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.listing_type" />
            </div>
            <div class="grid gap-2">
                <Label for="property-property-type">Property type</Label>
                <Select v-model="form.property_type">
                    <SelectTrigger id="property-property-type" class="w-full"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="o in options.property_types"
                            :key="o.value"
                            :value="o.value"
                            >{{ o.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.property_type" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="p_price">{{
                form.listing_type === 'rent'
                    ? 'Monthly rent (PHP)'
                    : 'Price (PHP)'
            }}</Label>
            <Input
                id="p_price"
                v-model="form.price"
                type="number"
                min="0"
                inputmode="numeric"
            />
            <InputError :message="form.errors.price" />
        </div>

        <div class="grid gap-2">
            <Label for="p_address">Address</Label>
            <Input id="p_address" v-model="form.address" autocomplete="off" />
            <InputError :message="form.errors.address" />
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div class="grid gap-2">
                <Label for="p_area">Neighborhood</Label>
                <Input id="p_area" v-model="form.area" autocomplete="off" />
                <InputError :message="form.errors.area" />
            </div>
            <div class="grid gap-2">
                <Label for="p_city">City</Label>
                <Input id="p_city" v-model="form.city" autocomplete="off" />
                <InputError :message="form.errors.city" />
            </div>
        </div>

        <div class="grid grid-cols-3 gap-3">
            <div class="grid gap-2">
                <Label for="p_beds">Beds</Label>
                <Input
                    id="p_beds"
                    v-model="form.bedrooms"
                    type="number"
                    min="0"
                />
                <InputError :message="form.errors.bedrooms" />
            </div>
            <div class="grid gap-2">
                <Label for="p_baths">Baths</Label>
                <Input
                    id="p_baths"
                    v-model="form.bathrooms"
                    type="number"
                    min="0"
                />
                <InputError :message="form.errors.bathrooms" />
            </div>
            <div class="grid gap-2">
                <Label for="p_area_sqft">Area (sqft)</Label>
                <Input
                    id="p_area_sqft"
                    v-model="form.floor_area_sqft"
                    type="number"
                    min="0"
                />
                <InputError :message="form.errors.floor_area_sqft" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label>Features</Label>
            <div class="grid grid-cols-2 gap-2">
                <Label
                    v-for="f in options.features"
                    :key="f.value"
                    class="flex items-center gap-2 font-normal"
                >
                    <Checkbox
                        :model-value="form.features.includes(f.value)"
                        @update:model-value="(v) => toggleFeature(f.value, v)"
                    />
                    {{ f.label }}
                </Label>
            </div>
            <InputError :message="form.errors.features" />
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
                    <Label for="property-status">Status</Label>
                    <Select v-model="form.status">
                        <SelectTrigger id="property-status" class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="o in options.statuses"
                                :key="o.value"
                                :value="o.value"
                                >{{ o.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.status" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-2">
                        <Label for="p_lot">Lot (sqft)</Label>
                        <Input
                            id="p_lot"
                            v-model="form.lot_area_sqft"
                            type="number"
                            min="0"
                        />
                        <InputError :message="form.errors.lot_area_sqft" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="p_year">Year built</Label>
                        <Input
                            id="p_year"
                            v-model="form.year_built"
                            type="number"
                        />
                        <InputError :message="form.errors.year_built" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="p_lat">Latitude</Label>
                        <Input
                            id="p_lat"
                            v-model="form.latitude"
                            type="number"
                            step="any"
                        />
                        <InputError :message="form.errors.latitude" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="p_lng">Longitude</Label>
                        <Input
                            id="p_lng"
                            v-model="form.longitude"
                            type="number"
                            step="any"
                        />
                        <InputError :message="form.errors.longitude" />
                    </div>
                </div>
                <Label class="flex items-center gap-2 font-normal">
                    <Checkbox v-model="form.is_published" />
                    Published on the public site
                </Label>
                <Label class="flex items-center gap-2 font-normal">
                    <Checkbox v-model="form.hide_exact_address" />
                    Hide exact address publicly
                </Label>
                <div v-if="contacts" class="grid gap-2">
                    <Label for="property-owner-contact-id"
                        >Owner (seller or landlord)</Label
                    >
                    <Select v-model="form.owner_contact_id">
                        <SelectTrigger
                            id="property-owner-contact-id"
                            class="w-full"
                            ><SelectValue placeholder="None"
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="NONE">None</SelectItem>
                            <SelectItem
                                v-for="c in contacts"
                                :key="c.id"
                                :value="String(c.id)"
                                >{{ c.name }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.owner_contact_id" />
                </div>
                <div v-if="agents" class="grid gap-2">
                    <Label for="property-listing-agent-id">Listing agent</Label>
                    <Select v-model="form.listing_agent_id">
                        <SelectTrigger
                            id="property-listing-agent-id"
                            class="w-full"
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
                <div class="grid gap-2">
                    <Label for="p_desc">Description</Label>
                    <Textarea id="p_desc" v-model="form.description" rows="5" />
                    <InputError :message="form.errors.description" />
                </div>
            </CollapsibleContent>
        </Collapsible>

        <Button
            type="submit"
            :disabled="form.processing"
            data-test="property-save"
        >
            {{ property ? 'Save changes' : 'Create property' }}
        </Button>
    </form>
</template>
