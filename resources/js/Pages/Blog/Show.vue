<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { marked } from 'marked';

const props = defineProps({
    post: Object,
});

const parsedContent = computed(() => {
    return marked.parse(props.post.content || '');
});

const isScrolled = ref(false);

if (typeof window !== 'undefined') {
    window.addEventListener('scroll', () => {
        isScrolled.value = window.scrollY > 20;
    });
}
</script>

<template>
    <Head :title="post.title + ' - Samuel\'s Journal'" />
    <div class="min-h-screen bg-stone-50 text-stone-900 font-['Outfit'] selection:bg-purple-200 selection:text-purple-900">
        <!-- Navigation -->
        <nav 
            :class="['fixed w-full z-50 transition-all duration-500 px-6 py-4 flex justify-between items-center', 
                isScrolled ? 'bg-white/80 backdrop-blur-md shadow-sm py-3' : 'bg-transparent'
            ]"
        >
            <Link :href="route('blog.index')" class="flex items-center space-x-2">
                <div class="w-10 h-10 bg-purple-700 rounded-full flex items-center justify-center text-white shadow-lg">
                    <i class="fas fa-bible text-xl"></i>
                </div>
                <span class="text-2xl font-bold tracking-tight text-stone-800 font-['Gentium_Book_Plus']">Samuel</span>
            </Link>
            <div class="flex items-center space-x-6 text-sm font-medium text-stone-600">
                <Link :href="route('chat.index')" class="hover:text-purple-700 transition">Chat with Samuel</Link>
            </div>
        </nav>

        <!-- Hero / Featured Image -->
        <div class="pt-24 md:pt-32 pb-16 px-6">
            <div class="max-w-4xl mx-auto">
                <div class="mb-8 flex items-center justify-center space-x-4">
                    <span class="bg-purple-100 text-purple-800 text-[10px] font-bold uppercase tracking-widest px-4 py-1.5 rounded-full border border-purple-200">
                        {{ post.topic }}
                    </span>
                    <span class="text-xs text-stone-400 font-bold uppercase tracking-widest">
                        {{ new Date(post.published_at).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) }}
                    </span>
                </div>
                
                <h1 class="text-4xl md:text-6xl font-bold text-stone-900 leading-tight mb-12 text-center font-['Gentium_Book_Plus']">
                    {{ post.title }}
                </h1>

                <div class="relative rounded-[40px] overflow-hidden shadow-2xl mb-16 aspect-[21/9]">
                    <img :src="post.image_url" :alt="post.title" class="w-full h-full object-cover" />
                    <div class="absolute inset-0 bg-gradient-to-t from-stone-900/40 to-transparent"></div>
                </div>

                <!-- Articles Content -->
                <article class="prose prose-stone prose-lg max-w-none bg-white/60 backdrop-blur-xl p-8 md:p-16 rounded-[40px] border border-white/80 shadow-sm mb-24">
                    <div class="post-content font-serif leading-relaxed text-stone-800" v-html="parsedContent"></div>
                    
                    <div class="mt-16 pt-8 border-t border-stone-200 flex flex-col items-center">
                        <div class="w-16 h-16 bg-purple-700 rounded-full flex items-center justify-center text-white shadow-lg mb-4">
                            <i class="fas fa-bible text-2xl"></i>
                        </div>
                        <p class="text-stone-500 italic font-['Gentium_Book_Plus']">Peace be with you always.</p>
                        <p class="text-stone-800 mt-2 font-bold">— Samuel Blythe</p>
                    </div>
                </article>
                
                <!-- Simple Comment Suggestion -->
                <div class="bg-purple-50 rounded-[40px] p-12 text-center border border-purple-100 shadow-sm">
                    <h3 class="text-2xl font-bold mb-4 font-['Gentium_Book_Plus'] text-stone-800">Discuss with Samuel</h3>
                    <p class="text-stone-600 mb-8">Have questions about this reflection? Chat with Samuel directly to explore the Word deeper.</p>
                    <Link :href="route('chat.index')" class="inline-flex items-center space-x-2 bg-purple-700 text-white px-8 py-4 rounded-full hover:bg-purple-800 transition shadow-lg text-lg font-bold">
                        <i class="fas fa-comment"></i>
                        <span>Ask Samuel a Question</span>
                    </Link>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="py-12 border-t border-stone-200 bg-white px-6 mt-12">
            <div class="max-w-6xl mx-auto text-center">
                <p class="text-sm text-stone-400">
                    © 2026 Samuel. A spiritual companion in the light of the Word.
                </p>
            </div>
        </footer>
    </div>
</template>

<style>
/* Markdown Content Styling */
.post-content h2 { @apply text-3xl font-bold mt-12 mb-6 text-stone-900 font-['Gentium_Book_Plus']; }
.post-content h3 { @apply text-2xl font-bold mt-8 mb-4 text-stone-800 font-['Gentium_Book_Plus']; }
.post-content p { @apply mb-6; }
.post-content b, .post-content strong { @apply text-purple-800 font-bold; }
.post-content ul { @apply list-disc list-inside mb-6; }
.post-content li { @apply mb-2; }
.post-content hr { @apply my-12 border-stone-200; }
</style>
