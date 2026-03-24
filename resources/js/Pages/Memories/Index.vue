<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    memories: Array,
});

const form = useForm({
    content: '',
    category: 'plan',
    importance: 3,
});

const isAdding = ref(false);

const submit = () => {
    form.post(route('memories.store'), {
        onSuccess: () => {
            form.reset();
            isAdding.value = false;
        },
    });
};

const deleteMemory = (id) => {
    if (confirm('Are you sure you want Samuel to forget this?')) {
        useForm({}).delete(route('memories.destroy', id));
    }
};

const toggleCompleted = (memory) => {
    useForm({
        is_completed: !memory.is_completed
    }).patch(route('memories.update', memory._id));
};

const getCategoryIcon = (category) => {
    switch (category) {
        case 'plan': return 'fa-calendar-check';
        case 'struggle': return 'fa-hand-holding-heart';
        case 'event': return 'fa-star';
        case 'preference': return 'fa-thumbs-up';
        default: return 'fa-brain';
    }
};

const getCategoryColor = (category) => {
    switch (category) {
        case 'plan': return 'text-blue-600 bg-blue-50';
        case 'struggle': return 'text-rose-600 bg-rose-50';
        case 'event': return 'text-amber-600 bg-amber-50';
        case 'preference': return 'text-emerald-600 bg-emerald-50';
        default: return 'text-purple-600 bg-purple-50';
    }
};
</script>

<template>
    <Head title="My Life with Samuel" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-3xl font-gentium font-bold text-stone-800">My Life with Samuel</h2>
                    <p class="mt-2 text-stone-500 max-w-2xl">
                        Samuel remembers the important things you share so he can walk beside you with better understanding and prayer.
                    </p>
                </div>
                <button 
                    @click="isAdding = !isAdding"
                    class="bg-purple-700 hover:bg-purple-800 text-white px-6 py-2.5 rounded-full font-bold shadow-md transition-all flex items-center space-x-2"
                >
                    <i class="fas" :class="isAdding ? 'fa-times' : 'fa-plus'"></i>
                    <span>{{ isAdding ? 'Cancel' : 'Add Memory' }}</span>
                </button>
            </div>
        </template>

        <div class="space-y-8">
            <!-- Add Memory Form -->
            <div v-if="isAdding" class="bg-white p-8 rounded-3xl shadow-sm border border-purple-100 max-w-2xl mx-auto transform transition-all animate-in fade-in zoom-in duration-300">
                <h3 class="text-xl font-bold text-stone-800 mb-6 flex items-center">
                    <i class="fas fa-pen-nib text-purple-600 mr-3"></i> What should Samuel remember?
                </h3>
                <form @submit.prevent="submit" class="space-y-6">
                    <div>
                        <textarea
                            v-model="form.content"
                            class="w-full border-stone-200 rounded-2xl focus:border-purple-500 focus:ring-purple-500 bg-stone-50 p-4 text-stone-800 italic"
                            rows="3"
                            placeholder="e.g., I'm starting a new job next Monday..."
                            required
                        ></textarea>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-stone-600 mb-2 uppercase tracking-wider">Category</label>
                            <select v-model="form.category" class="w-full border-stone-200 rounded-xl focus:border-purple-500 focus:ring-purple-500">
                                <option value="plan">Plan / Goal</option>
                                <option value="struggle">Prayer Request / Struggle</option>
                                <option value="event">Important Event</option>
                                <option value="preference">Preference / Likes</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-stone-600 mb-2 uppercase tracking-wider">Importance</label>
                            <div class="flex items-center space-x-2 h-10">
                                <template v-for="i in 5" :key="i">
                                    <button 
                                        type="button"
                                        @click="form.importance = i"
                                        class="w-8 h-8 rounded-full transition-all flex items-center justify-center"
                                        :class="i <= form.importance ? 'bg-purple-600 text-white' : 'bg-stone-100 text-stone-400 hover:bg-stone-200'"
                                    >
                                        <i class="fas fa-star text-tiny"></i>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            class="bg-purple-700 hover:bg-purple-800 text-white px-8 py-3 rounded-full font-bold shadow-lg disabled:opacity-50"
                            :disabled="form.processing"
                        >
                            Save for Samuel
                        </button>
                    </div>
                </form>
            </div>

            <!-- Memories Grid -->
            <div v-if="memories.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div 
                    v-for="memory in memories" 
                    :key="memory._id"
                    class="bg-white p-6 rounded-3xl shadow-sm border border-stone-100 flex flex-col justify-between group hover:shadow-md transition-all relative overflow-hidden"
                    :class="{'opacity-60 bg-stone-50': memory.is_completed}"
                >
                    <!-- Background Decoration -->
                    <div class="absolute -right-4 -top-4 w-20 h-20 opacity-[0.03] transform rotate-12 transition-transform group-hover:scale-110">
                        <i class="fas fa-brain text-8xl"></i>
                    </div>

                    <div>
                        <div class="flex justify-between items-start mb-4">
                            <span 
                                class="px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest border border-current"
                                :class="getCategoryColor(memory.category)"
                            >
                                <i class="fas mr-1" :class="getCategoryIcon(memory.category)"></i>
                                {{ memory.category }}
                            </span>
                            <div class="flex space-x-1">
                                <i v-for="i in memory.importance" :key="i" class="fas fa-star text-tiny text-amber-400"></i>
                            </div>
                        </div>

                        <p class="text-stone-800 font-medium leading-relaxed mb-6" :class="{'line-through text-stone-400': memory.is_completed}">
                            "{{ memory.content }}"
                        </p>
                    </div>

                    <div class="flex items-center justify-between mt-4 pt-4 border-t border-stone-50">
                        <span class="text-[10px] font-bold text-stone-400 uppercase tracking-widest">
                            {{ new Date(memory.created_at).toLocaleDateString() }}
                        </span>
                        
                        <div class="flex space-x-2">
                            <button 
                                @click="toggleCompleted(memory)"
                                class="p-2 rounded-full transition-colors"
                                :class="memory.is_completed ? 'text-emerald-600 bg-emerald-50' : 'text-stone-400 hover:text-emerald-600 hover:bg-emerald-50'"
                                :title="memory.is_completed ? 'Mark as active' : 'Mark as completed'"
                            >
                                <i class="fas" :class="memory.is_completed ? 'fa-check-circle' : 'fa-circle'"></i>
                            </button>
                            <button 
                                @click="deleteMemory(memory._id)"
                                class="p-2 text-stone-400 hover:text-red-600 hover:bg-red-50 rounded-full transition-colors"
                                title="Forget this"
                            >
                                <i class="fas fa-trash-alt text-xs"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="text-center py-20 bg-white rounded-3xl border-2 border-dashed border-stone-200">
                <div class="w-20 h-20 bg-stone-50 rounded-full flex items-center justify-center mx-auto mb-6 text-stone-300">
                    <i class="fas fa-brain text-3xl"></i>
                </div>
                <h3 class="text-xl font-bold text-stone-800 mb-2">No memories yet</h3>
                <p class="text-stone-500 max-w-sm mx-auto mb-8">
                    When you share your life with Samuel, he'll keep track of the important things here. You can also add them manually.
                </p>
                <button 
                    @click="isAdding = true"
                    class="text-purple-700 font-bold hover:underline"
                >
                    + Add your first memory
                </button>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.font-gentium {
    font-family: 'Gentium Book Plus', serif;
}
.font-outfit {
    font-family: 'Outfit', sans-serif;
}
.text-tiny {
    font-size: 10px;
}
</style>
