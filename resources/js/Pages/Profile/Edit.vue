<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head title="Profile Settings" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-3xl font-['Gentium_Book_Plus'] font-bold text-stone-800 tracking-tight">
                Profile Sanctuary
            </h2>
            <p class="text-stone-500 text-sm mt-1">Refine your presence in the sanctuary.</p>
        </template>

        <div class="space-y-8 pb-12">
            <div class="bg-white border border-stone-200 shadow-sm rounded-2xl p-6 md:p-10">
                <UpdateProfileInformationForm
                    :must-verify-email="mustVerifyEmail"
                    :status="status"
                    class="max-w-2xl"
                />
            </div>

            <div class="bg-white border border-stone-200 shadow-sm rounded-2xl p-6 md:p-10">
                <UpdatePasswordForm class="max-w-2xl" />
            </div>

            <div class="bg-white border border-rose-100 shadow-sm rounded-2xl p-6 md:p-10">
                <DeleteUserForm class="max-w-2xl" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
