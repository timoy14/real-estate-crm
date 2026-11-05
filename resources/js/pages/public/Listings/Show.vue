<script setup>
import { Link } from '@inertiajs/vue3';
import { Bath, BedDouble, Calendar, Check, Ruler } from '@lucide/vue';
import { computed } from 'vue';
import InquiryForm from '@/components/public/InquiryForm.vue';
import PhotoGallery from '@/components/public/PhotoGallery.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { humanize, isRent, money } from '@/lib/format';

const props = defineProps({
    listing: { type: Object, required: true },
    meta: { type: Object, required: true },
});

const num = (n) => n.toLocaleString('en-US');

const facts = computed(() => {
    const l = props.listing;

    return [
        { icon: BedDouble, label: 'Bedrooms', value: l.bedrooms },
        { icon: Bath, label: 'Bathrooms', value: l.bathrooms },
        {
            icon: Ruler,
            label: 'Floor area',
            value: l.floor_area_sqft ? `${num(l.floor_area_sqft)} sqft` : null,
        },
        {
            icon: Ruler,
            label: 'Lot area',
            value: l.lot_area_sqft ? `${num(l.lot_area_sqft)} sqft` : null,
        },
        { icon: Calendar, label: 'Year built', value: l.year_built },
    ].filter((f) => f.value !== null && f.value !== undefined);
});

const initials = computed(() =>
    (props.listing.agent?.name ?? '')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0].toUpperCase())
        .join(''),
);

// OpenStreetMap embed, only when exact coordinates are public.
const mapSrc = computed(() => {
    const { latitude: lat, longitude: lon } = props.listing;

    if (lat === null || lon === null) {
        return null;
    }

    const d = 0.005;
    const bbox = [lon - d, lat - d, lon + d, lat + d].join(',');

    return `https://www.openstreetmap.org/export/embed.html?bbox=${bbox}&layer=mapnik&marker=${lat},${lon}`;
});
</script>

<template>
    <div class="flex flex-col gap-6">
        <Button variant="link" class="w-fit px-0" as-child>
            <Link href="/">&larr; All listings</Link>
        </Button>

        <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
            <div class="flex min-w-0 flex-col gap-8 lg:col-span-2">
                <PhotoGallery
                    :photos="listing.photos"
                    :cover="listing.cover_url"
                    :title="listing.title"
                />

                <div class="flex flex-col gap-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge>{{
                            listing.listing_type === 'rent'
                                ? 'For rent'
                                : 'For sale'
                        }}</Badge>
                        <Badge variant="secondary">{{
                            humanize(listing.property_type)
                        }}</Badge>
                    </div>
                    <p class="text-3xl font-bold">
                        {{ money(listing.price)
                        }}<span
                            v-if="isRent(listing)"
                            class="text-base font-normal text-muted-foreground"
                            >/mo</span
                        >
                    </p>
                    <h1 class="text-xl font-semibold sm:text-2xl">
                        {{ listing.title }}
                    </h1>
                    <p class="text-muted-foreground">
                        {{
                            listing.address ??
                            [listing.area, listing.city]
                                .filter(Boolean)
                                .join(', ')
                        }}
                        <template
                            v-if="
                                listing.address &&
                                (listing.area || listing.city)
                            "
                        >
                            &middot;
                            {{
                                [listing.area, listing.city]
                                    .filter(Boolean)
                                    .join(', ')
                            }}
                        </template>
                    </p>
                </div>

                <dl
                    v-if="facts.length"
                    class="grid grid-cols-2 gap-4 sm:grid-cols-3"
                >
                    <div
                        v-for="f in facts"
                        :key="f.label"
                        class="flex items-center gap-3"
                    >
                        <component
                            :is="f.icon"
                            class="size-5 shrink-0 text-muted-foreground"
                        />
                        <div class="min-w-0">
                            <dt class="text-xs text-muted-foreground">
                                {{ f.label }}
                            </dt>
                            <dd class="font-medium">{{ f.value }}</dd>
                        </div>
                    </div>
                </dl>

                <section
                    v-if="listing.features.length"
                    class="flex flex-col gap-3"
                >
                    <h2 class="text-lg font-semibold">Features</h2>
                    <ul class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <li
                            v-for="f in listing.features"
                            :key="f"
                            class="flex items-center gap-2"
                        >
                            <Check class="size-4 text-primary" />
                            {{ humanize(f) }}
                        </li>
                    </ul>
                </section>

                <section v-if="listing.description" class="flex flex-col gap-3">
                    <h2 class="text-lg font-semibold">About this property</h2>
                    <p class="break-words whitespace-pre-line">
                        {{ listing.description }}
                    </p>
                </section>

                <section v-if="mapSrc" class="flex flex-col gap-3">
                    <h2 class="text-lg font-semibold">Location</h2>
                    <iframe
                        :src="mapSrc"
                        title="Map"
                        loading="lazy"
                        referrerpolicy="no-referrer"
                        class="h-64 w-full rounded-lg border"
                    />
                </section>
            </div>

            <aside
                class="flex min-w-0 flex-col gap-4 lg:sticky lg:top-6 lg:self-start"
            >
                <Card v-if="listing.agent">
                    <CardContent class="flex items-center gap-3">
                        <Avatar>
                            <AvatarImage
                                v-if="listing.agent.avatar_url"
                                :src="listing.agent.avatar_url"
                                :alt="listing.agent.name"
                            />
                            <AvatarFallback>{{ initials }}</AvatarFallback>
                        </Avatar>
                        <div class="min-w-0">
                            <p class="truncate font-medium">
                                {{ listing.agent.name }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                Listing agent
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <InquiryForm
                    :property-slug="listing.slug"
                    :property-title="listing.title"
                    :agent-name="listing.agent?.name"
                />
            </aside>
        </div>
    </div>
</template>
