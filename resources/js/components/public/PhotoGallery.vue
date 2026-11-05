<script setup>
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Carousel,
    CarouselContent,
    CarouselItem,
    CarouselNext,
    CarouselPrevious,
} from '@/components/ui/carousel';

const props = defineProps({
    photos: { type: Array, required: true },
    cover: { type: String, required: true },
    title: { type: String, required: true },
});

const active = ref(0);
const api = ref(null);

function onInit(a) {
    if (!a) {
        return;
    }

    api.value = a;
    a.on('select', () => (active.value = a.selectedScrollSnap()));
}

function go(i) {
    active.value = i;
    api.value?.scrollTo(i);
}
const slides = computed(() =>
    props.photos.length ? props.photos : [props.cover],
);
</script>

<template>
    <div class="flex min-w-0 flex-col gap-3" data-test="photo-gallery">
        <Carousel class="w-full" @init-api="onInit">
            <CarouselContent>
                <CarouselItem v-for="(src, i) in slides" :key="src + i">
                    <div
                        class="aspect-[4/3] w-full overflow-hidden rounded-lg bg-muted sm:aspect-[16/10]"
                    >
                        <img
                            :src="src"
                            :alt="`${title} photo ${i + 1}`"
                            :loading="i === 0 ? 'eager' : 'lazy'"
                            class="size-full object-cover"
                        />
                    </div>
                </CarouselItem>
            </CarouselContent>
            <template v-if="slides.length > 1">
                <CarouselPrevious class="left-2" />
                <CarouselNext class="right-2" />
            </template>
        </Carousel>
        <div v-if="slides.length > 1" class="flex gap-2 overflow-x-auto pb-1">
            <Button
                v-for="(src, i) in slides"
                :key="'t' + src + i"
                type="button"
                size="icon"
                :variant="active === i ? 'default' : 'outline'"
                class="size-16 shrink-0 overflow-hidden p-0"
                :aria-label="`Show photo ${i + 1}`"
                @click="go(i)"
            >
                <img
                    :src="src"
                    alt=""
                    loading="lazy"
                    class="size-full object-cover"
                />
            </Button>
        </div>
    </div>
</template>
