<script setup>
import { ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);
</script>

<template>
    <div class="min-h-screen bg-stone-50 font-['Outfit']">
        <nav class="bg-white border-b border-stone-200 shadow-sm bg-gradient-to-r from-purple-50 to-white">
            <!-- Primary Navigation Menu -->
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-20 justify-between items-center">
                    <div class="flex items-center space-x-6">
                        <!-- Logo -->
                        <div class="flex shrink-0 items-center">
                            <Link :href="route('chat.index')" class="flex items-center space-x-3 group">
                                <div class="w-10 h-10 bg-purple-700 rounded-full flex items-center justify-center text-white shadow-md transform group-hover:rotate-12 transition-transform">
                                    <i class="fas fa-bible text-xl"></i>
                                </div>
                                <span class="text-2xl font-['Gentium_Book_Plus'] font-bold text-stone-800 tracking-tight">Samuel</span>
                            </Link>
                        </div>

                        <!-- Navigation Links -->
                        <div class="hidden space-x-4 sm:flex">
                            <NavLink
                                :href="route('chat.index')"
                                :active="route().current('chat.index')"
                                class="text-sm font-bold uppercase tracking-widest"
                            >
                                <i class="fas fa-comment-dots mr-2"></i> Chat
                            </NavLink>
                            <NavLink
                                :href="route('memories.index')"
                                :active="route().current('memories.index')"
                                class="text-sm font-bold uppercase tracking-widest"
                            >
                                <i class="fas fa-heart mr-2 text-purple-600"></i> My Life
                            </NavLink>
                        </div>
                    </div>

                    <div class="hidden sm:ms-6 sm:flex sm:items-center space-x-4">
                        <!-- Settings Dropdown -->
                        <div class="relative">
                            <Dropdown align="right" width="48">
                                <template #trigger>
                                    <span class="inline-flex rounded-md">
                                        <button
                                            type="button"
                                            class="inline-flex items-center space-x-2 rounded-full border border-stone-200 bg-white px-4 py-2 text-sm font-bold text-stone-600 shadow-sm transition hover:border-purple-300 hover:text-purple-800 focus:outline-none"
                                        >
                                            <div class="w-6 h-6 bg-purple-100 rounded-full flex items-center justify-center text-purple-700">
                                                <i class="fas fa-user text-xs"></i>
                                            </div>
                                            <span>{{ $page.props.auth.user.name }}</span>

                                            <i class="fas fa-chevron-down text-[10px] text-stone-400"></i>
                                        </button>
                                    </span>
                                </template>

                                <template #content>
                                    <div class="px-4 py-2 border-b border-stone-50 mb-1">
                                        <p class="text-[10px] font-bold text-stone-400 uppercase tracking-widest">Logged in as</p>
                                        <p class="text-xs font-medium text-stone-800 truncate">{{ $page.props.auth.user.email }}</p>
                                    </div>
                                    <DropdownLink :href="route('profile.edit')" class="flex items-center space-x-3 text-sm">
                                        <i class="fas fa-id-card w-4"></i> Profile
                                    </DropdownLink>
                                    <DropdownLink
                                        :href="route('logout')"
                                        method="post"
                                        as="button"
                                        class="flex items-center space-x-3 text-sm text-red-600 hover:text-red-700"
                                    >
                                        <i class="fas fa-sign-out-alt w-4"></i> Log Out
                                    </DropdownLink>
                                </template>
                            </Dropdown>
                        </div>
                    </div>

                    <!-- Hamburger -->
                    <div class="-me-2 flex items-center sm:hidden">
                        <button
                            @click="showingNavigationDropdown = !showingNavigationDropdown"
                            class="inline-flex items-center justify-center rounded-lg p-2 text-stone-400 hover:bg-stone-100 hover:text-stone-500 focus:outline-none transition"
                        >
                            <i :class="['fas fa-lg', showingNavigationDropdown ? 'fa-times' : 'fa-bars']"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Responsive Navigation Menu -->
            <div
                :class="{
                    block: showingNavigationDropdown,
                    hidden: !showingNavigationDropdown,
                }"
                class="sm:hidden bg-white border-t border-stone-100 animate-in slide-in-from-top"
            >
                <div class="space-y-1 pb-3 pt-2 px-4">
                    <ResponsiveNavLink
                        :href="route('chat.index')"
                        :active="route().current('chat.index')"
                    >
                        <i class="fas fa-comment-dots mr-2"></i> Chat
                    </ResponsiveNavLink>
                    <ResponsiveNavLink
                        :href="route('memories.index')"
                        :active="route().current('memories.index')"
                    >
                        <i class="fas fa-heart mr-2 text-purple-600"></i> My Life
                    </ResponsiveNavLink>
                </div>

                <!-- Responsive Settings Options -->
                <div class="border-t border-stone-100 pb-4 pt-4 px-4 bg-stone-50/50">
                    <div class="flex items-center space-x-3 mb-3">
                        <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center text-purple-700">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <div class="text-sm font-bold text-stone-800">{{ $page.props.auth.user.name }}</div>
                            <div class="text-xs text-stone-500">{{ $page.props.auth.user.email }}</div>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <ResponsiveNavLink :href="route('profile.edit')">
                            <i class="fas fa-id-card mr-2"></i> Profile
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="text-red-600"
                        >
                            <i class="fas fa-sign-out-alt mr-2"></i> Log Out
                        </ResponsiveNavLink>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page Heading -->
        <header class="bg-white border-b border-stone-100" v-if="$slots.header">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <slot name="header" />
            </div>
        </header>

        <!-- Page Content -->
        <main class="py-12 bg-[radial-gradient(#e5e7eb_1px,transparent_1px)] [background-size:20px_20px]">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <slot />
            </div>
        </main>
    </div>
</template>
