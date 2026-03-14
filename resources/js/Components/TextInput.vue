<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="rounded-xl border-stone-200 shadow-sm focus:border-purple-500 focus:ring-purple-500 bg-white text-stone-800 placeholder-stone-400 transition duration-200"
        v-model="model"
        ref="input"
    />
</template>
