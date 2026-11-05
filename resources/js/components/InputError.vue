<script setup>
defineProps({
    message: String,
});
</script>

<template>
    <div v-show="message">
        <p class="text-sm text-destructive">
            {{ message }}
        </p>
    </div>
</template>
