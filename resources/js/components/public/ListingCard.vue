<script setup>
import { Link } from '@inertiajs/vue3';
import { Bath, BedDouble, Ruler } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { isRent, money } from '@/lib/format';

defineProps({
    listing: { type: Object, required: true },
});
</script>

<template>
    <Link
        :href="`/properties/${listing.slug}`"
        class="block min-w-0"
        data-test="listing-card"
    >
        <Card class="h-full gap-0 overflow-hidden py-0">
            <div class="relative aspect-[4/3] w-full overflow-hidden bg-muted">
                <img
                    :src="listing.cover_url"
                    :alt="listing.title"
                    loading="lazy"
                    class="size-full object-cover"
                />
                <Badge class="absolute top-3 left-3">
                    {{
                        listing.listing_type === 'rent'
                            ? 'For rent'
                            : 'For sale'
                    }}
                </Badge>
            </div>
            <CardContent class="flex flex-col gap-2 p-4">
                <p class="text-lg font-semibold">
                    {{ money(listing.price)
                    }}<span
                        v-if="isRent(listing)"
                        class="text-sm font-normal text-muted-foreground"
                        >/mo</span
                    >
                </p>
                <h3 class="truncate font-medium">{{ listing.title }}</h3>
                <p class="truncate text-sm text-muted-foreground">
                    {{
                        [listing.area, listing.city].filter(Boolean).join(', ')
                    }}
                </p>
                <div
                    class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground"
                >
                    <span
                        v-if="listing.bedrooms !== null"
                        class="flex items-center gap-1"
                    >
                        <BedDouble class="size-4" /> {{ listing.bedrooms }}
                    </span>
                    <span
                        v-if="listing.bathrooms !== null"
                        class="flex items-center gap-1"
                    >
                        <Bath class="size-4" /> {{ listing.bathrooms }}
                    </span>
                    <span
                        v-if="listing.floor_area_sqft"
                        class="flex items-center gap-1"
                    >
                        <Ruler class="size-4" />
                        {{ listing.floor_area_sqft.toLocaleString('en-US') }}
                        sqft
                    </span>
                </div>
            </CardContent>
        </Card>
    </Link>
</template>
