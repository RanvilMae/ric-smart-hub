<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="min-h-svh bg-muted/40 px-4 py-10 md:px-10">
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-8">
            <div class="flex flex-col items-center gap-4 text-center">
                <Link :href="home()">
                    <AppLogoIcon class="size-16" />
                </Link>
                <div class="space-y-2">
                    <h1 class="text-xl font-semibold">{{ title }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ description }}
                    </p>
                </div>
            </div>
            <slot />
        </div>
    </div>
</template>