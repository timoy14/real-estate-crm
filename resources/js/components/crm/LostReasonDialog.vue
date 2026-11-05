<script setup>
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

/** Asks for an optional lost reason. Closing without confirming emits `cancel`. */
const open = defineModel('open', { type: Boolean, required: true });
const emit = defineEmits(['confirm', 'cancel']);

const reason = ref('');
let confirmed = false;

watch(open, (isOpen) => {
    if (isOpen) {
        reason.value = '';
        confirmed = false;
    } else if (!confirmed) {
        emit('cancel');
    }
});

function confirm() {
    confirmed = true;
    open.value = false;
    emit('confirm', reason.value.trim() || undefined);
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Mark deal as lost</DialogTitle>
                <DialogDescription
                    >Add a reason if you like. You can leave it
                    blank.</DialogDescription
                >
            </DialogHeader>
            <div class="grid gap-2">
                <Label for="lost-reason">Lost reason</Label>
                <Textarea
                    id="lost-reason"
                    v-model="reason"
                    rows="3"
                    data-test="lost-reason"
                />
            </div>
            <DialogFooter>
                <Button variant="outline" @click="open = false">Cancel</Button>
                <Button data-test="confirm-lost" @click="confirm"
                    >Mark as lost</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
