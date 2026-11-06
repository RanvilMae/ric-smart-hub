<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({ inheritAttrs: false });

defineProps<{ className?: HTMLAttributes['class'] }>();
</script>

<template>
    <img
        src="/images/ric-logo.png"
        alt="Rural Improvement Clubs Philippines"
        :class="['rounded-full object-contain', className]"
        v-bind="$attrs"
    />
</template>