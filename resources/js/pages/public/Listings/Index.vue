<script setup>
import { Link } from '@inertiajs/vue3';
import ListingCard from '@/components/public/ListingCard.vue';
import ListingFilters from '@/components/public/ListingFilters.vue';
import { Button } from '@/components/ui/button';

defineProps({
    listings: { type: Object, required: true },
    filters: { type: Object, required: true },
    areas: { type: Array, required: true },
    meta: { type: Object, required: true },
});
</script>

<template>
    <div class="flex flex-col gap-8">
        <section class="-mx-4 -mt-8 bg-accent px-4 py-10 sm:py-14">
            <div class="mx-auto flex max-w-6xl flex-col gap-6">
                <div class="flex flex-col gap-2">
                    <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">
                        Find your next home
                    </h1>
                    <p class="text-muted-foreground">
                        Homes and apartments for sale and rent from Skyline
                        Realty.
                    </p>
                </div>
                <ListingFilters :filters="filters" :areas="areas" />
            </div>
        </section>

        <section class="flex flex-col gap-4">
            <p class="text-sm text-muted-foreground" data-test="result-count">
                {{ listings.meta.total }}
                {{ listings.meta.total === 1 ? 'property' : 'properties' }}
            </p>

            <div
                v-if="listings.data.length"
                class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3"
            >
                <ListingCard
                    v-for="l in listings.data"
                    :key="l.slug"
                    :listing="l"
                />
            </div>
            <p v-else class="py-12 text-center text-muted-foreground">
                No properties match your search. Try changing the filters.
            </p>

            <nav
                v-if="listings.meta.last_page > 1"
                class="flex items-center justify-between gap-4"
                aria-label="Pagination"
            >
                <Button v-if="listings.links.prev" variant="outline" as-child>
                    <Link :href="listings.links.prev" preserve-scroll
                        >Previous</Link
                    >
                </Button>
                <span v-else />
                <span class="text-sm text-muted-foreground">
                    Page {{ listings.meta.current_page }} of
                    {{ listings.meta.last_page }}
                </span>
                <Button v-if="listings.links.next" variant="outline" as-child>
                    <Link :href="listings.links.next" preserve-scroll
                        >Next</Link
                    >
                </Button>
                <span v-else />
            </nav>
        </section>
    </div>
</template>
