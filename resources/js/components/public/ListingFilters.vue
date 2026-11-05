<script setup>
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
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

const props = defineProps({
    filters: { type: Object, required: true },
    areas: { type: Array, required: true },
});

const ANY = '__any';
const PROPERTY_TYPES = [
    ['house', 'House'],
    ['apartment', 'Apartment'],
    ['condo', 'Condo'],
    ['land', 'Land'],
    ['commercial', 'Commercial'],
];

const type = ref(props.filters.type ?? ANY);
const propertyType = ref(props.filters.property_type ?? ANY);
const area = ref(props.filters.area ?? ANY);
const beds = ref(props.filters.beds?.toString() ?? ANY);
const sort = ref(props.filters.sort);
const minPrice = ref(props.filters.min_price?.toString() ?? '');
const maxPrice = ref(props.filters.max_price?.toString() ?? '');

const clean = (v) => (v === ANY || v === '' ? undefined : v);

function search() {
    router.get(
        '/',
        {
            type: clean(type.value),
            property_type: clean(propertyType.value),
            area: clean(area.value),
            beds: clean(beds.value),
            min_price: clean(minPrice.value),
            max_price: clean(maxPrice.value),
            sort: sort.value === 'newest' ? undefined : sort.value,
        },
        { preserveScroll: true },
    );
}
</script>

<template>
    <form
        class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4"
        data-test="listing-filters"
        @submit.prevent="search"
    >
        <div class="flex flex-col gap-1.5">
            <Label for="listing-type">Buy or rent</Label>
            <Select v-model="type">
                <SelectTrigger id="listing-type" class="w-full"
                    ><SelectValue
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem :value="ANY">Sale or rent</SelectItem>
                    <SelectItem value="sale">For sale</SelectItem>
                    <SelectItem value="rent">For rent</SelectItem>
                </SelectContent>
            </Select>
        </div>
        <div class="flex flex-col gap-1.5">
            <Label for="listing-propertyType">Property type</Label>
            <Select v-model="propertyType">
                <SelectTrigger id="listing-propertyType" class="w-full"
                    ><SelectValue
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem :value="ANY">Any type</SelectItem>
                    <SelectItem
                        v-for="[v, l] in PROPERTY_TYPES"
                        :key="v"
                        :value="v"
                        >{{ l }}</SelectItem
                    >
                </SelectContent>
            </Select>
        </div>
        <div class="flex flex-col gap-1.5">
            <Label for="listing-area">Area</Label>
            <Select v-model="area">
                <SelectTrigger id="listing-area" class="w-full"
                    ><SelectValue
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem :value="ANY">Any area</SelectItem>
                    <SelectItem v-for="a in areas" :key="a" :value="a">{{
                        a
                    }}</SelectItem>
                </SelectContent>
            </Select>
        </div>
        <div class="flex flex-col gap-1.5">
            <Label for="listing-beds">Bedrooms</Label>
            <Select v-model="beds">
                <SelectTrigger id="listing-beds" class="w-full"
                    ><SelectValue
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem :value="ANY">Any</SelectItem>
                    <SelectItem
                        v-for="n in [1, 2, 3, 4, 5]"
                        :key="n"
                        :value="String(n)"
                        >{{ n }}+</SelectItem
                    >
                </SelectContent>
            </Select>
        </div>
        <div class="flex flex-col gap-1.5">
            <Label for="min_price">Min price</Label>
            <Input
                id="min_price"
                v-model="minPrice"
                type="number"
                min="0"
                inputmode="numeric"
                placeholder="No min"
            />
        </div>
        <div class="flex flex-col gap-1.5">
            <Label for="max_price">Max price</Label>
            <Input
                id="max_price"
                v-model="maxPrice"
                type="number"
                min="0"
                inputmode="numeric"
                placeholder="No max"
            />
        </div>
        <div class="flex flex-col gap-1.5">
            <Label for="listing-sort">Sort by</Label>
            <Select v-model="sort">
                <SelectTrigger id="listing-sort" class="w-full"
                    ><SelectValue
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem value="newest">Newest</SelectItem>
                    <SelectItem value="price_asc"
                        >Price: low to high</SelectItem
                    >
                    <SelectItem value="price_desc"
                        >Price: high to low</SelectItem
                    >
                </SelectContent>
            </Select>
        </div>
        <div class="flex items-end">
            <Button type="submit" class="w-full">Search</Button>
        </div>
    </form>
</template>
