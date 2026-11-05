<script setup>
import { router } from '@inertiajs/vue3';
import { GripVertical, ImagePlus, Trash2, Upload, X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { route } from '@/lib/route';

const MAX = 12;
const ACCEPT = ['image/jpeg', 'image/png', 'image/webp'];

const props = defineProps({
    slug: { type: String, required: true },
    photos: { type: Array, required: true },
    errors: Object,
});

const items = ref([...props.photos]);
watch(
    () => props.photos,
    (p) => (items.value = [...p]),
);

const pending = ref([]);
const dragging = ref(false);
const uploading = ref(false);
const localError = ref('');
const input = ref(null);

const room = computed(() => MAX - items.value.length - pending.value.length);
const uploadError = computed(
    () =>
        localError.value ||
        Object.entries(props.errors ?? {}).find(([k]) =>
            k.startsWith('photos'),
        )?.[1] ||
        '',
);

function addFiles(list) {
    localError.value = '';
    const files = Array.from(list ?? []);
    const valid = files.filter(
        (f) => ACCEPT.includes(f.type) && f.size <= 5 * 1024 * 1024,
    );
    if (valid.length !== files.length) {
        localError.value =
            'Only JPG, PNG or WebP images up to 5 MB are accepted.';
    }
    const accepted = valid.slice(0, Math.max(room.value, 0));
    if (accepted.length < valid.length) {
        localError.value = `A property can have at most ${MAX} photos.`;
    }
    pending.value.push(
        ...accepted.map((file) => ({
            file,
            preview: URL.createObjectURL(file),
        })),
    );
}

function removePending(i) {
    URL.revokeObjectURL(pending.value[i].preview);
    pending.value.splice(i, 1);
}

function onDrop(e) {
    dragging.value = false;
    addFiles(e.dataTransfer?.files ?? null);
}

function upload() {
    if (!pending.value.length) return;
    uploading.value = true;
    router.post(
        route('app.properties.photos.store', props.slug),
        { photos: pending.value.map((p) => p.file) },
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                pending.value.forEach((p) => URL.revokeObjectURL(p.preview));
                pending.value = [];
            },
            onFinish: () => (uploading.value = false),
        },
    );
}

function remove(photo) {
    router.delete(
        route('app.properties.photos.destroy', [props.slug, photo.id]),
        { preserveScroll: true },
    );
}

function saveOrder() {
    router.patch(
        route('app.properties.photos.order', props.slug),
        { ids: items.value.map((p) => p.id) },
        { preserveScroll: true },
    );
}

onBeforeUnmount(() =>
    pending.value.forEach((p) => URL.revokeObjectURL(p.preview)),
);
</script>

<template>
    <div class="flex flex-col gap-3" data-test="photo-uploader">
        <div
            class="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
            :class="dragging ? 'bg-accent' : ''"
            data-test="photo-dropzone"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
        >
            <ImagePlus />
            <p>Drag photos here, or</p>
            <Button
                type="button"
                variant="outline"
                size="sm"
                :disabled="room <= 0"
                @click="input?.click()"
            >
                Choose files
            </Button>
            <input
                ref="input"
                type="file"
                multiple
                accept="image/jpeg,image/png,image/webp"
                class="hidden"
                data-test="photo-input"
                @change="
                    (e) => {
                        addFiles(e.target.files);
                        e.target.value = '';
                    }
                "
            />
            <p>
                {{ items.length }} of {{ MAX }} photos. JPG, PNG or WebP, up to
                5 MB each.
            </p>
        </div>
        <InputError :message="uploadError" />

        <div v-if="pending.length" class="flex flex-col gap-2">
            <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                <div
                    v-for="(p, i) in pending"
                    :key="p.preview"
                    class="relative"
                >
                    <img
                        :src="p.preview"
                        alt="Selected photo preview"
                        class="aspect-[4/3] w-full rounded-md object-cover"
                    />
                    <Button
                        type="button"
                        size="icon"
                        variant="secondary"
                        class="absolute top-1 right-1 size-6"
                        aria-label="Remove selected photo"
                        @click="removePending(i)"
                    >
                        <X />
                    </Button>
                </div>
            </div>
            <Button
                type="button"
                :disabled="uploading"
                data-test="photo-upload"
                @click="upload"
            >
                <Upload />
                Upload {{ pending.length }} photo{{
                    pending.length === 1 ? '' : 's'
                }}
            </Button>
        </div>

        <VueDraggable
            v-if="items.length"
            v-model="items"
            :animation="150"
            handle=".photo-handle"
            class="grid grid-cols-3 gap-2 sm:grid-cols-4"
            data-test="photo-list"
            @end="saveOrder"
        >
            <div v-for="(p, i) in items" :key="p.id" class="relative">
                <img
                    :src="p.url"
                    :alt="`Photo ${i + 1}`"
                    loading="lazy"
                    class="aspect-[4/3] w-full rounded-md object-cover"
                />
                <Button
                    type="button"
                    size="icon"
                    variant="secondary"
                    class="photo-handle absolute bottom-1 left-1 size-6 cursor-grab"
                    aria-label="Drag to reorder"
                >
                    <GripVertical />
                </Button>
                <Button
                    type="button"
                    size="icon"
                    variant="secondary"
                    class="absolute top-1 right-1 size-6"
                    aria-label="Delete photo"
                    data-test="photo-delete"
                    @click="remove(p)"
                >
                    <Trash2 />
                </Button>
            </div>
        </VueDraggable>
    </div>
</template>
