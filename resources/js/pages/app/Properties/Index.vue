<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { Bath, BedDouble, LayoutGrid, Plus, Ruler, Table2 } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import Pager from '@/components/crm/Pager.vue';
import PropertyForm from '@/components/crm/PropertyForm.vue';
import StatusBadge from '@/components/crm/StatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useOpenOnNew } from '@/composables/useOpenOnNew';
import { priceText } from '@/lib/format';
import { optionLabel } from '@/lib/options';
import { route } from '@/lib/route';

const props = defineProps({
    properties: { type: Object, required: true },
    filters: { type: Object, required: true },
    areas: { type: Array, required: true },
    options: { type: Object, required: true },
    contacts: { type: Array, required: true },
    agents: Array,
});

defineOptions({
    layout: { breadcrumbs: [{ title: 'Properties', href: '/app/properties' }] },
});

const ALL = '__all';
const search = ref(props.filters.search);
const minPrice = ref(props.filters.min_price?.toString() ?? '');
const maxPrice = ref(props.filters.max_price?.toString() ?? '');

function visit(extra = {}) {
    const merged = {
        search: search.value,
        listing_type: props.filters.listing_type,
        property_type: props.filters.property_type,
        status: props.filters.status,
        min_price: minPrice.value,
        max_price: maxPrice.value,
        min_bedrooms: props.filters.min_bedrooms,
        area: props.filters.area,
        view: props.filters.view,
        ...extra,
    };
    const query = Object.fromEntries(
        Object.entries(merged).filter(
            ([, v]) => v !== null && v !== '' && v !== ALL,
        ),
    );
    router.get(route('app.properties.index'), query, {
        preserveState: true,
        replace: true,
    });
}

watchDebounced([search, minPrice, maxPrice], () => visit(), { debounce: 300 });

const sheetOpen = ref(false);
useOpenOnNew(sheetOpen, { cleanUrl: () => route('app.properties.index') });

const label = (opts, v) => optionLabel(opts, v) ?? v;
</script>

<template>
    <Head title="Properties" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center justify-between gap-3">
            <Heading
                title="Properties"
                :description="`${properties.total} total`"
            />
            <Button data-test="new-property" @click="sheetOpen = true">
                <Plus />
                New property
            </Button>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <Input
                v-model="search"
                class="w-64"
                aria-label="Search properties"
                placeholder="Search title or address"
                data-test="property-search"
            />

            <Select
                :model-value="filters.listing_type ?? ALL"
                @update:model-value="(v) => visit({ listing_type: String(v) })"
            >
                <SelectTrigger class="w-32" aria-label="Filter by listing type"
                    ><SelectValue placeholder="Listing"
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem :value="ALL">Sale or rent</SelectItem>
                    <SelectItem
                        v-for="o in options.listing_types"
                        :key="o.value"
                        :value="o.value"
                        >{{ o.label }}</SelectItem
                    >
                </SelectContent>
            </Select>

            <Select
                :model-value="filters.property_type ?? ALL"
                @update:model-value="(v) => visit({ property_type: String(v) })"
            >
                <SelectTrigger class="w-36" aria-label="Filter by property type"
                    ><SelectValue placeholder="Type"
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem :value="ALL">All types</SelectItem>
                    <SelectItem
                        v-for="o in options.property_types"
                        :key="o.value"
                        :value="o.value"
                        >{{ o.label }}</SelectItem
                    >
                </SelectContent>
            </Select>

            <Select
                :model-value="filters.status ?? ALL"
                @update:model-value="(v) => visit({ status: String(v) })"
            >
                <SelectTrigger class="w-36" aria-label="Filter by status"
                    ><SelectValue placeholder="Status"
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem :value="ALL">All statuses</SelectItem>
                    <SelectItem
                        v-for="o in options.statuses"
                        :key="o.value"
                        :value="o.value"
                        >{{ o.label }}</SelectItem
                    >
                </SelectContent>
            </Select>

            <Select
                :model-value="filters.area ?? ALL"
                @update:model-value="(v) => visit({ area: String(v) })"
            >
                <SelectTrigger class="w-40" aria-label="Filter by neighborhood"
                    ><SelectValue placeholder="Neighborhood"
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem :value="ALL">All neighborhoods</SelectItem>
                    <SelectItem v-for="a in areas" :key="a" :value="a">{{
                        a
                    }}</SelectItem>
                </SelectContent>
            </Select>

            <Select
                :model-value="
                    filters.min_bedrooms !== null
                        ? String(filters.min_bedrooms)
                        : ALL
                "
                @update:model-value="(v) => visit({ min_bedrooms: String(v) })"
            >
                <SelectTrigger class="w-32" aria-label="Minimum bedrooms"
                    ><SelectValue placeholder="Beds"
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem :value="ALL">Any beds</SelectItem>
                    <SelectItem
                        v-for="n in [1, 2, 3, 4, 5]"
                        :key="n"
                        :value="String(n)"
                        >{{ n }}+ beds</SelectItem
                    >
                </SelectContent>
            </Select>

            <Input
                v-model="minPrice"
                class="w-28"
                type="number"
                min="0"
                aria-label="Minimum price"
                placeholder="Min price"
            />
            <Input
                v-model="maxPrice"
                class="w-28"
                type="number"
                min="0"
                aria-label="Maximum price"
                placeholder="Max price"
            />

            <div class="ml-auto flex gap-1">
                <Button
                    :variant="filters.view === 'grid' ? 'secondary' : 'outline'"
                    size="icon"
                    aria-label="Grid view"
                    data-test="view-grid"
                    @click="visit({ view: 'grid' })"
                >
                    <LayoutGrid />
                </Button>
                <Button
                    :variant="
                        filters.view === 'table' ? 'secondary' : 'outline'
                    "
                    size="icon"
                    aria-label="Table view"
                    data-test="view-table"
                    @click="visit({ view: 'table' })"
                >
                    <Table2 />
                </Button>
            </div>
        </div>

        <p
            v-if="properties.data.length === 0"
            class="py-12 text-center text-sm text-muted-foreground"
        >
            No properties match these filters.
        </p>

        <div
            v-else-if="filters.view === 'grid'"
            class="grid grid-cols-[minmax(0,1fr)] gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
            data-test="property-grid"
        >
            <Link
                v-for="p in properties.data"
                :key="p.id"
                :href="route('app.properties.show', p.slug)"
                class="block"
            >
                <Card class="h-full overflow-hidden py-0">
                    <img
                        :src="p.cover_url"
                        :alt="p.title"
                        loading="lazy"
                        class="aspect-[4/3] w-full object-cover"
                    />
                    <CardContent class="flex flex-col gap-2 pb-4">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-lg font-semibold">{{
                                priceText(p)
                            }}</span>
                            <div class="flex gap-1">
                                <Badge variant="outline">{{
                                    p.listing_type === 'rent' ? 'Rent' : 'Sale'
                                }}</Badge>
                                <StatusBadge
                                    :status="p.status"
                                    :label="label(options.statuses, p.status)"
                                />
                            </div>
                        </div>
                        <div class="truncate font-medium">{{ p.title }}</div>
                        <div class="truncate text-sm text-muted-foreground">
                            {{ p.area }}, {{ p.city }}
                        </div>
                        <div class="flex gap-4 text-sm text-muted-foreground">
                            <span
                                v-if="p.bedrooms !== null"
                                class="flex items-center gap-1"
                                ><BedDouble class="size-4" />{{
                                    p.bedrooms
                                }}</span
                            >
                            <span
                                v-if="p.bathrooms !== null"
                                class="flex items-center gap-1"
                                ><Bath class="size-4" />{{ p.bathrooms }}</span
                            >
                            <span
                                v-if="p.floor_area_sqft"
                                class="flex items-center gap-1"
                                ><Ruler class="size-4" />{{
                                    p.floor_area_sqft.toLocaleString('en-US')
                                }}
                                sqft</span
                            >
                        </div>
                    </CardContent>
                </Card>
            </Link>
        </div>

        <div v-else class="rounded-md border" data-test="property-table">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Property</TableHead>
                        <TableHead>Price</TableHead>
                        <TableHead>Type</TableHead>
                        <TableHead>Beds / baths</TableHead>
                        <TableHead>Area</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Agent</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="p in properties.data" :key="p.id">
                        <TableCell>
                            <Link
                                :href="route('app.properties.show', p.slug)"
                                class="flex items-center gap-3 font-medium hover:underline"
                            >
                                <img
                                    :src="p.cover_url"
                                    alt=""
                                    loading="lazy"
                                    class="size-10 rounded-md object-cover"
                                />
                                {{ p.title }}
                            </Link>
                        </TableCell>
                        <TableCell>{{ priceText(p) }}</TableCell>
                        <TableCell>
                            {{ label(options.property_types, p.property_type) }}
                            <Badge variant="outline">{{
                                p.listing_type === 'rent' ? 'Rent' : 'Sale'
                            }}</Badge>
                        </TableCell>
                        <TableCell
                            >{{ p.bedrooms ?? '-' }} /
                            {{ p.bathrooms ?? '-' }}</TableCell
                        >
                        <TableCell>{{ p.area }}, {{ p.city }}</TableCell>
                        <TableCell
                            ><StatusBadge
                                :status="p.status"
                                :label="label(options.statuses, p.status)"
                        /></TableCell>
                        <TableCell>{{ p.listing_agent?.name }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Pager :page="properties" />
    </div>

    <Sheet v-model:open="sheetOpen">
        <SheetContent class="overflow-y-auto">
            <SheetHeader>
                <SheetTitle>New property</SheetTitle>
                <SheetDescription
                    >Add a listing. You will be the listing
                    agent.</SheetDescription
                >
            </SheetHeader>
            <PropertyForm
                v-if="sheetOpen"
                :options="options"
                :contacts="contacts"
                :agents="agents"
                @saved="sheetOpen = false"
            />
        </SheetContent>
    </Sheet>
</template>
