<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-stone-50 pt-6 sm:justify-center sm:pt-0"
    >
        <div class="mb-8 text-center">
            <Link href="/" class="flex flex-col items-center group">
                <div class="w-16 h-16 bg-amber-600 rounded-full flex items-center justify-center text-white shadow-lg group-hover:bg-amber-700 transition-all duration-300 transform group-hover:scale-110">
                    <i class="fas fa-bible text-3xl"></i>
                </div>
                <h1 class="mt-4 text-3xl font-serif font-bold text-stone-800 tracking-tight">Samuel.ai</h1>
                <p class="text-xs text-amber-700 italic mt-1">"Your faithful brother, Samuel"</p>
            </Link>
        </div>

        <div
            class="mt-2 w-full overflow-hidden bg-white px-8 py-8 shadow-[0_20px_50px_rgba(0,0,0,0.05)] sm:max-w-md sm:rounded-2xl border border-stone-100"
        >
            <slot />
        </div>
        
        <div class="mt-8 text-center text-stone-400 text-[10px] font-serif uppercase tracking-widest">
            Spiritually Inspired &copy; 2026 
        </div>
    </div>
</template>
