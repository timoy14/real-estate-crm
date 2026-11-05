<script setup>
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';

/**
 * A Cancel / confirm AlertDialog. AlertDialogAction closes the dialog before
 * `confirm` is emitted, so a caller's target must outlive the close (clear it
 * in the request's onFinish, not when the dialog closes).
 */
defineProps({
    title: String,
    description: String,
    confirmLabel: { type: String, default: 'Delete' },
    confirmTest: String,
});

const open = defineModel('open', { type: Boolean, required: true });
const emit = defineEmits(['confirm']);
</script>

<template>
    <AlertDialog v-model:open="open">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ title }}</AlertDialogTitle>
                <AlertDialogDescription>{{
                    description
                }}</AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction
                    :data-test="confirmTest"
                    @click="emit('confirm')"
                    >{{ confirmLabel }}</AlertDialogAction
                >
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
