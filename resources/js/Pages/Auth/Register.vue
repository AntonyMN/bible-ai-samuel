<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Register" />

        <form @submit.prevent="submit">
            <div class="relative group">
                <InputLabel for="name" value="Full Name" class="text-stone-700 font-medium ml-1" />
                <div class="mt-1 relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-user text-stone-400 group-focus-within:text-amber-600 transition-colors"></i>
                    </div>
                    <TextInput
                        id="name"
                        type="text"
                        class="block w-full pl-10 bg-stone-50 border-stone-200 focus:bg-white focus:ring-amber-500 focus:border-amber-500 transition-all rounded-xl shadow-sm"
                        v-model="form.name"
                        required
                        autofocus
                        autocomplete="name"
                        placeholder="John Doe"
                    />
                </div>
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div class="mt-5 relative group">
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
                        autocomplete="username"
                        placeholder="your@email.com"
                    />
                </div>
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-5 relative group">
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
                        autocomplete="new-password"
                        placeholder="••••••••"
                    />
                </div>
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-5 relative group">
                <InputLabel for="password_confirmation" value="Confirm Password" class="text-stone-700 font-medium ml-1" />
                <div class="mt-1 relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-shield-alt text-stone-400 group-focus-within:text-amber-600 transition-colors"></i>
                    </div>
                    <TextInput
                        id="password_confirmation"
                        type="password"
                        class="block w-full pl-10 bg-stone-50 border-stone-200 focus:bg-white focus:ring-amber-500 focus:border-amber-500 transition-all rounded-xl shadow-sm"
                        v-model="form.password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="••••••••"
                    />
                </div>
                <InputError
                    class="mt-2"
                    :message="form.errors.password_confirmation"
                />
            </div>

            <div class="mt-8 flex flex-col space-y-4">
                <PrimaryButton
                    class="w-full justify-center py-3 bg-amber-600 hover:bg-amber-700 text-white shadow-md hover:shadow-lg transform hover:-translate-y-0.5 active:translate-y-0 transition-all duration-200 rounded-xl font-bold uppercase tracking-widest text-xs"
                    :class="{ 'opacity-50 pointer-events-none': form.processing }"
                    :disabled="form.processing"
                >
                    <i class="fas fa-user-plus mr-2"></i>
                    Begin the Journey
                </PrimaryButton>

                <div class="text-center">
                    <Link
                        :href="route('login')"
                        class="text-xs text-stone-500 hover:text-amber-700 transition-colors font-medium border-b border-transparent hover:border-amber-200"
                    >
                        Already registered? Go to Login
                    </Link>
                </div>
            </div>
        </form>
    </GuestLayout>
</template>
