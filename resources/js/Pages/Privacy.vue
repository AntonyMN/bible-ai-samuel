<script setup>
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Privacy Policy - Samuel AI" />

    <div class="min-h-screen bg-stone-50 font-['Outfit']">
        <!-- Header -->
        <nav class="bg-white/80 backdrop-blur-md border-b border-stone-200 sticky top-0 z-50">
            <div class="max-w-6xl mx-auto px-6 h-20 flex items-center justify-between">
                <Link :href="route('landing')" class="flex items-center space-x-2">
                    <div class="w-10 h-10 bg-purple-700 rounded-2xl flex items-center justify-center text-white shadow-lg">
                        <i class="fas fa-bible text-xl"></i>
                    </div>
                    <span class="text-2xl font-bold text-stone-800 font-['Gentium_Book_Plus']">Samuel</span>
                </Link>
                <Link :href="route('landing')" class="text-sm font-bold text-purple-700 hover:text-purple-800 transition">
                    Back to Landing
                </Link>
            </div>
        </nav>

        <!-- Content -->
        <main class="max-w-4xl mx-auto px-6 py-16 md:py-24">
            <div class="bg-white rounded-[40px] shadow-sm border border-stone-200 p-8 md:p-16">
                <header class="mb-12 text-center">
                    <h1 class="text-4xl md:text-5xl font-bold text-stone-900 mb-4 font-['Gentium_Book_Plus'] italic">Privacy Policy</h1>
                    <p class="text-stone-400 uppercase tracking-widest text-sm font-bold">Effective Date: March 21, 2026</p>
                </header>

                <div class="space-y-12 text-stone-600 leading-relaxed">
                    <section>
                        <h2 class="text-2xl font-bold text-stone-800 mb-4 font-['Gentium_Book_Plus']">1. Our Commitment to You</h2>
                        <p>
                            Peace be with you. At Samuel AI, we treat your privacy with the same respect and care we would show a brother or sister in Christ. We believe your digital journey through the Word should be sacred and secure.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-2xl font-bold text-stone-800 mb-4 font-['Gentium_Book_Plus']">2. Information We Collect</h2>
                        <div class="space-y-4">
                            <p>To provide you with a personalized spiritual companion, we collection the following:</p>
                            <ul class="list-disc pl-6 space-y-2">
                                <li><strong>Account Data:</strong> Your name and email address when you register.</li>
                                <li><strong>Conversation History:</strong> To help you pick up where you left off in your reflection.</li>
                                <li><strong>Usage Data:</strong> Technical information like IP addresses and browser types to ensure the light stays on and the service is stable.</li>
                            </ul>
                        </div>
                    </section>

                    <section>
                        <h2 class="text-2xl font-bold text-stone-800 mb-4 font-['Gentium_Book_Plus']">3. How Your Data Is Used</h2>
                        <p>
                            Your conversations are processed by our specialized AI models to provide biblical encouragement, scriptural context, and gentle guidance. We do <strong>not</strong> sell your data to third parties, nor do we use your personal reflections for advertising.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-2xl font-bold text-stone-800 mb-4 font-['Gentium_Book_Plus']">4. Security of the Word</h2>
                        <p>
                            We implement industry-standard security measures to protect your information. However, please remember that no method of transmission over the internet is 100% secure. We encourage you to use a strong, unique password for your Samuel AI account.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-2xl font-bold text-stone-800 mb-4 font-['Gentium_Book_Plus']">5. Cookies & Session</h2>
                        <p>
                            We use essential cookies to keep you logged in and to remember your preferences (like your favorite Bible version). These are necessary for the sanctuary to function correctly.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-2xl font-bold text-stone-800 mb-4 font-['Gentium_Book_Plus']">6. Contact Us</h2>
                        <p>
                            If you have any questions about this policy or your data, please reach out to us. We are here to walk with you.
                        </p>
                    </section>
                </div>

                <footer class="mt-16 pt-8 border-t border-stone-100 text-center">
                    <p class="text-stone-400 italic font-['Gentium_Book_Plus']">"The Lord preserve thy going out and thy coming in from this time forth, and even for evermore." — Psalm 121:8</p>
                </footer>
            </div>
        </main>

        <!-- Simple Footer -->
        <footer class="py-12 bg-white border-t border-stone-200">
            <div class="max-w-6xl mx-auto px-6 text-center text-stone-400 text-sm">
                © 2026 Samuel AI. All rights reserved.
            </div>
        </footer>
    </div>
</template>
