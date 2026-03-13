<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Log in" />

        <div v-if="status" class="mb-4 text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div class="relative group">
                <InputLabel for="email" value="Email" class="text-stone-700 font-medium ml-1" />
                <div class="mt-1 relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-envelope text-stone-400 group-focus-within:text-amber-600 transition-colors"></i>
                    </div>
                    <TextInput
                        id="email"
                        type="email"
                        class="block w-full pl-10 bg-stone-50 border-stone-200 focus:bg-white focus:ring-amber-500 focus:border-amber-500 transition-all rounded-xl shadow-sm"
                        v-model="form.email"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="your@email.com"
                    />
                </div>
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-6 relative group">
                <InputLabel for="password" value="Password" class="text-stone-700 font-medium ml-1" />
                <div class="mt-1 relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-lock text-stone-400 group-focus-within:text-amber-600 transition-colors"></i>
                    </div>
                    <TextInput
                        id="password"
                        type="password"
                        class="block w-full pl-10 bg-stone-50 border-stone-200 focus:bg-white focus:ring-amber-500 focus:border-amber-500 transition-all rounded-xl shadow-sm"
                        v-model="form.password"
                        required
                        autocomplete="current-password"
                        placeholder="••••••••"
                    />
                </div>
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-6 block">
                <label class="flex items-center group cursor-pointer">
                    <Checkbox name="remember" v-model:checked="form.remember" class="border-stone-300 text-amber-600 focus:ring-amber-500" />
                    <span class="ms-3 text-sm text-stone-600 group-hover:text-stone-800 transition-colors">Remember my spiritual journey</span>
                </label>
            </div>

            <div class="mt-8 flex flex-col space-y-4">
                <PrimaryButton
                    class="w-full justify-center py-3 bg-amber-600 hover:bg-amber-700 text-white shadow-md hover:shadow-lg transform hover:-translate-y-0.5 active:translate-y-0 transition-all duration-200 rounded-xl font-bold uppercase tracking-widest text-xs"
                    :class="{ 'opacity-50 pointer-events-none': form.processing }"
                    :disabled="form.processing"
                >
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    Enter the Sanctuary
                </PrimaryButton>

                <div class="flex items-center justify-between">
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-xs text-stone-500 hover:text-amber-700 transition-colors font-medium border-b border-transparent hover:border-amber-200"
                    >
                        Forgotten your key?
                    </Link>

                    <Link
                        :href="route('register')"
                        class="text-xs text-amber-700 hover:text-amber-800 transition-colors font-bold uppercase tracking-wider"
                    >
                        New Seeker? Register
                    </Link>
                </div>
            </div>
        </form>
    </GuestLayout>
</template>
