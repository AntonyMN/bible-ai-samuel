<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    posts: Object,
});

const isScrolled = ref(false);

if (typeof window !== 'undefined') {
    window.addEventListener('scroll', () => {
        isScrolled.value = window.scrollY > 20;
    });
}
</script>

<template>
    <Head title="Samuel's Journal - Spiritual Reflections" />
    <div class="min-h-screen bg-stone-50 text-stone-900 font-['Outfit'] selection:bg-purple-200 selection:text-purple-900">
        <!-- Navigation (Simplified for Blog) -->
        <nav 
            :class="['fixed w-full z-50 transition-all duration-500 px-6 py-4 flex justify-between items-center', 
                isScrolled ? 'bg-white/80 backdrop-blur-md shadow-sm py-3' : 'bg-transparent'
            ]"
        >
            <Link :href="route('landing')" class="flex items-center space-x-2">
                <div class="w-10 h-10 bg-purple-700 rounded-full flex items-center justify-center text-white shadow-lg">
                    <i class="fas fa-bible text-xl"></i>
                </div>
                <span class="text-2xl font-bold tracking-tight text-stone-800 font-['Gentium_Book_Plus']">Samuel</span>
            </Link>
            <div class="flex items-center space-x-6 text-sm font-medium text-stone-600">
                <Link :href="route('chat.index')" class="hover:text-purple-700 transition">Chat with Samuel</Link>
            </div>
        </nav>

        <!-- Header -->
        <header class="pt-32 pb-16 px-6 relative overflow-hidden">
            <div class="absolute inset-0 z-0">
                <div class="absolute top-20 right-[-10%] w-[500px] h-[500px] bg-purple-200/20 rounded-full blur-3xl"></div>
                <div class="absolute bottom-20 left-[-10%] w-[400px] h-[400px] bg-stone-200/30 rounded-full blur-3xl"></div>
            </div>
            
            <div class="max-w-6xl mx-auto relative z-10 text-center">
                <h1 class="text-5xl md:text-7xl font-bold text-stone-900 leading-tight mb-6 font-['Gentium_Book_Plus']">
                    Samuel's <span class="italic text-purple-700">Journal</span>
                </h1>
                <p class="text-xl text-stone-600 max-w-2xl mx-auto leading-relaxed">
                    Spiritual reflections, historical insights, and encouraging words from the heart of your faithful companion.
                </p>
            </div>
        </header>

        <!-- Blog Grid -->
        <main class="max-w-6xl mx-auto px-6 pb-24 relative z-10">
            <div class="grid md:grid-cols-3 gap-8">
                <article v-for="post in posts.data" :key="post.id" class="group bg-white/40 backdrop-blur-xl border border-white/60 rounded-[40px] shadow-sm hover:shadow-xl transition-all overflow-hidden flex flex-col">
                    <div class="aspect-video overflow-hidden relative">
                        <img :src="post.image_url" :alt="post.title" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" />
                        <div class="absolute top-4 left-4">
                            <span class="bg-purple-700/80 backdrop-blur-md text-white text-[10px] font-bold uppercase tracking-widest px-3 py-1 rounded-full border border-white/20">
                                {{ post.topic }}
                            </span>
                        </div>
                    </div>
                    <div class="p-8 flex-1 flex flex-col">
                        <h2 class="text-2xl font-bold mb-4 text-stone-800 font-['Gentium_Book_Plus'] group-hover:text-purple-700 transition-colors">
                            {{ post.title }}
                        </h2>
                        <p class="text-stone-600 line-clamp-3 mb-6 flex-1">
                            {{ post.meta_description }}
                        </p>
                        <div class="flex items-center justify-between mt-auto pt-6 border-t border-stone-200/40">
                            <span class="text-xs text-stone-400 font-bold uppercase tracking-widest">
                                {{ new Date(post.published_at).toLocaleDateString() }}
                            </span>
                            <Link :href="route('blog.show', post.slug)" class="text-purple-700 font-bold text-sm hover:underline">
                                Read More &rarr;
                            </Link>
                        </div>
                    </div>
                </article>
            </div>

            <!-- Pagination (if needed) -->
            <div v-if="posts.links.length > 3" class="mt-16 flex justify-center space-x-2">
                    <div v-for="link in posts.links" :key="link.label">
                        <Link 
                            :href="link.url || '#'" 
                            :class="['px-4 py-2 rounded-full text-sm font-bold transition-all', 
                                link.active ? 'bg-purple-700 text-white shadow-lg' : 'bg-white/60 text-stone-600 hover:bg-white shadow-sm',
                                !link.url ? 'opacity-50 cursor-not-allowed hidden' : ''
                            ]"
                        >
                            <span v-html="link.label"></span>
                        </Link>
                    </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="py-12 border-t border-stone-200 bg-white px-6">
            <div class="max-w-6xl mx-auto text-center">
                <p class="text-sm text-stone-400">
                    © 2026 Samuel. Walking in the light of the Word.
                </p>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.line-clamp-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
