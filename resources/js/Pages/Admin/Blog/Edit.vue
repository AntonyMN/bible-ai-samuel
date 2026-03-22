<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    post: Object,
});

const form = useForm({
    title: props.post.title,
    content: props.post.content,
    status: props.post.status,
});

const submit = () => {
    form.patch(route('admin.blog.update', props.post.id));
};
</script>

<template>
    <Head title="Admin - Edit Blog Post" />
    <div class="p-8 max-w-5xl mx-auto">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-stone-900 font-['Gentium_Book_Plus']">Edit Post</h1>
            <Link :href="route('admin.blog.index')" class="text-stone-500 hover:text-stone-800 font-bold text-sm">
                &larr; Back to List
            </Link>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <div class="bg-white p-8 rounded-[32px] shadow-sm border border-stone-200 space-y-6">
                <!-- Title -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-widest text-stone-500 mb-2">Title</label>
                    <input v-model="form.title" type="text" class="w-full bg-stone-50 border-stone-200 rounded-2xl p-4 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all font-bold text-lg" required />
                </div>

                <!-- Status -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-widest text-stone-500 mb-2">Status</label>
                    <select v-model="form.status" class="w-full bg-stone-50 border-stone-200 rounded-2xl p-4 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all">
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                    </select>
                </div>

                <!-- Content -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-widest text-stone-500 mb-2">Content (Markdown)</label>
                    <textarea v-model="form.content" rows="20" class="w-full bg-stone-50 border-stone-200 rounded-2xl p-4 focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all font-serif leading-relaxed" required></textarea>
                </div>
            </div>

            <div class="flex justify-end space-x-4">
                <button type="submit" :disabled="form.processing" class="bg-purple-700 text-white px-10 py-4 rounded-full font-bold hover:bg-purple-800 transition shadow-xl disabled:opacity-50">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</template>
