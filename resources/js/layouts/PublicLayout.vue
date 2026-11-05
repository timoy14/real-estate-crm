<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { route } from '@/lib/route';

const name = usePage().props.name;
</script>

<template>
    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <header class="border-b">
            <div
                class="mx-auto flex h-16 w-full max-w-6xl items-center justify-between px-4"
            >
                <Link href="/" class="text-lg font-semibold text-primary">
                    {{ name }}
                </Link>
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="route('login')">Staff login</Link>
                </Button>
            </div>
        </header>

        <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
            <slot />
        </main>

        <footer>
            <Separator />
            <div
                class="mx-auto w-full max-w-6xl px-4 py-6 text-sm text-muted-foreground"
            >
                &copy; {{ new Date().getFullYear() }} {{ name }}. All rights
                reserved.
            </div>
        </footer>
    </div>
</template>
