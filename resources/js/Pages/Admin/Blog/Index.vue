<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    posts: Object,
});

const deleteForm = useForm({});

const deletePost = (id) => {
    if (confirm('Are you sure you want to delete this post?')) {
        deleteForm.delete(route('admin.blog.destroy', id));
    }
};
</script>

<template>
    <Head title="Admin - Blog Management" />
    <div class="p-8 max-w-7xl mx-auto">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-stone-900 font-['Gentium_Book_Plus']">Blog Management</h1>
            <div class="flex space-x-4">
                 <Link :href="route('admin.dashboard')" class="bg-stone-200 text-stone-700 px-6 py-2 rounded-full font-bold hover:bg-stone-300 transition">
                    &larr; Back to Dashboard
                </Link>
            </div>
        </div>

        <div class="bg-white rounded-[32px] shadow-sm border border-stone-200 overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-stone-50 border-bottom border-stone-200">
                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-widest text-stone-500">Title</th>
                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-widest text-stone-500">Topic</th>
                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-widest text-stone-500">Status</th>
                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-widest text-stone-500">Published At</th>
                        <th class="px-6 py-4 text-xs font-bold uppercase tracking-widest text-stone-500 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    <tr v-for="post in posts.data" :key="post.id" class="hover:bg-stone-50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="font-bold text-stone-800">{{ post.title }}</div>
                            <div class="text-xs text-stone-400">{{ post.slug }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-xs bg-purple-100 text-purple-700 px-2 py-1 rounded-md font-bold uppercase">
                                {{ post.topic }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <span :class="['text-[10px] font-bold uppercase px-2 py-1 rounded-full border', 
                                post.status === 'published' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-amber-50 text-amber-700 border-amber-200'
                            ]">
                                {{ post.status }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-stone-600">
                            {{ post.published_at ? new Date(post.published_at).toLocaleString() : 'N/A' }}
                        </td>
                        <td class="px-6 py-4 text-right space-x-2">
                            <Link :href="route('admin.blog.edit', post.id)" class="text-purple-600 hover:text-purple-800 font-bold text-sm">Edit</Link>
                            <button @click="deletePost(post.id)" class="text-red-600 hover:text-red-800 font-bold text-sm">Delete</button>
                        </td>
                    </tr>
                    <tr v-if="posts.data.length === 0">
                        <td colspan="5" class="px-6 py-12 text-center text-stone-400 italic">No blog posts found. Run the seeder to generate some!</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="posts.links.length > 3" class="mt-8 flex justify-center space-x-2">
             <div v-for="link in posts.links" :key="link.label">
                <Link 
                    :href="link.url || '#'" 
                    :class="['px-4 py-2 rounded-full text-xs font-bold transition-all', 
                        link.active ? 'bg-stone-900 text-white shadow-lg' : 'bg-white text-stone-600 hover:bg-stone-50 shadow-sm',
                        !link.url ? 'opacity-50 cursor-not-allowed hidden' : ''
                    ]"
                >
                    <span v-html="link.label"></span>
                </Link>
             </div>
        </div>
    </div>
</template>
