<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const isScrolled = ref(false);
const isMenuOpen = ref(false);

onMounted(() => {
    window.addEventListener('scroll', () => {
        isScrolled.value = window.scrollY > 20;
    });
});
</script>

<template>
    <Head title="Samuel - Your Faith Companion" />
    <div class="min-h-screen bg-stone-50 text-stone-900 font-['Outfit'] selection:bg-purple-200 selection:text-purple-900">
        <!-- Navigation -->
        <nav 
            :class="['fixed w-full z-50 transition-all duration-500 px-6 py-4 flex justify-between items-center', 
                isScrolled ? 'bg-white/80 backdrop-blur-md shadow-sm py-3' : 'bg-transparent'
            ]"
        >
            <div class="flex items-center space-x-2">
                <div class="w-10 h-10 bg-purple-700 rounded-full flex items-center justify-center text-white shadow-lg">
                    <i class="fas fa-bible text-xl"></i>
                </div>
                <span class="text-2xl font-bold tracking-tight text-stone-800 font-['Gentium_Book_Plus']">Samuel</span>
            </div>

            <!-- Hamburger Button -->
            <button @click="isMenuOpen = !isMenuOpen" class="md:hidden text-stone-600 focus:outline-none z-50 p-2">
                <i :class="['fas', isMenuOpen ? 'fa-times' : 'fa-bars', 'text-2xl']"></i>
            </button>

            <!-- Desktop Menu -->
            <div class="hidden md:flex items-center space-x-4 md:space-x-8 text-sm font-medium text-stone-600">
                <a href="#about" class="hover:text-purple-700 transition">About Samuel</a>
                <a href="#features" class="hover:text-purple-700 transition">Features</a>
                <a href="#versions" class="hover:text-purple-700 transition">Versions</a>
                <a href="https://ko-fi.com/Y8Y21W7RKD" target="_blank" class="flex items-center space-x-2 bg-pink-600 text-white px-5 py-2 rounded-full hover:bg-pink-700 transition shadow-md font-bold text-sm tracking-wide">
                    <i class="fas fa-heart"></i>
                    <span>Keep Samuel Online</span>
                </a>
                <a href="https://chat.chatwithsamuel.org" class="bg-purple-700 text-white px-6 py-2 rounded-full hover:bg-purple-800 transition shadow-md hover:shadow-lg">Start Chatting</a>
            </div>

            <!-- Mobile Menu Overlay -->
            <transition
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="opacity-0 translate-x-10"
                enter-to-class="opacity-100 translate-x-0"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="opacity-100 translate-x-0"
                leave-to-class="opacity-0 translate-x-10"
            >
                <div v-if="isMenuOpen" class="fixed inset-0 z-40 bg-white/95 backdrop-blur-xl flex flex-col items-center justify-center space-y-8 md:hidden px-6 text-center">
                    <a href="#about" @click="isMenuOpen = false" class="text-2xl font-bold text-stone-800 hover:text-purple-700 transition">About Samuel</a>
                    <a href="#features" @click="isMenuOpen = false" class="text-2xl font-bold text-stone-800 hover:text-purple-700 transition">Features</a>
                    <a href="#versions" @click="isMenuOpen = false" class="text-2xl font-bold text-stone-800 hover:text-purple-700 transition">Versions</a>
                    <hr class="w-12 border-stone-200">
                    <a href="https://ko-fi.com/Y8Y21W7RKD" target="_blank" class="flex items-center space-x-2 bg-pink-600 text-white px-8 py-3 rounded-full hover:bg-pink-700 transition shadow-lg font-bold text-lg">
                        <i class="fas fa-heart"></i>
                        <span>Keep Samuel Online</span>
                    </a>
                    <a href="https://chat.chatwithsamuel.org" class="bg-purple-700 text-white px-10 py-4 rounded-full hover:bg-purple-800 transition shadow-lg text-lg font-bold">Start Chatting</a>
                </div>
            </transition>
        </nav>

        <!-- Hero Section -->
        <header class="relative pt-32 pb-20 px-6 overflow-hidden">
            <div class="absolute inset-0 z-0">
                <div class="absolute top-20 right-[-10%] w-[500px] h-[500px] bg-purple-200/20 rounded-full blur-3xl"></div>
                <div class="absolute bottom-20 left-[-10%] w-[400px] h-[400px] bg-stone-200/30 rounded-full blur-3xl"></div>
            </div>
            
            <div class="max-w-6xl mx-auto relative z-10 text-center">
                <div class="inline-flex items-center space-x-2 bg-purple-100/50 px-4 py-1.5 rounded-full text-purple-800 text-xs font-bold uppercase tracking-widest mb-8 border border-purple-200 animate-fade-in">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-purple-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-purple-500"></span>
                    </span>
                    <span>Now with RunPod Fast Inference</span>
                </div>
                
                <h1 class="text-6xl md:text-8xl font-bold text-stone-900 leading-tight mb-8">
                    Walk in Faith with <br/>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-purple-700 to-purple-900 italic font-['Gentium_Book_Plus']">Samuel</span>
                </h1>
                
                <p class="text-xl md:text-2xl text-stone-600 max-w-2xl mx-auto mb-12 leading-relaxed">
                    A premium Biblical companion designed to provide spiritual guidance, historical context, and deep scriptural insights through his faithful persona.
                </p>
                
                <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                    <a href="https://chat.chatwithsamuel.org" class="w-full sm:w-auto px-10 py-5 bg-purple-700 text-white rounded-full text-lg font-bold hover:bg-purple-800 transition-all shadow-xl hover:shadow-purple-700/20 transform hover:-translate-y-1">
                        Begin Your Journey
                    </a>
                    <a href="/download/samuel-ai.apk" class="w-full sm:w-auto px-10 py-5 bg-stone-900 text-white rounded-full text-lg font-bold hover:bg-black transition-all shadow-xl transform hover:-translate-y-1 flex items-center justify-center space-x-2">
                        <i class="fab fa-android text-2xl"></i>
                        <span>Download Android</span>
                    </a>
                </div>
            </div>
            
            <!-- Floating Elements -->
            <div class="mt-20 max-w-5xl mx-auto bg-white/40 backdrop-blur-xl border border-white/60 rounded-[40px] shadow-2xl overflow-hidden animate-slide-up">
                <div class="p-2">
                    <div class="bg-stone-900 rounded-[32px] overflow-hidden aspect-video relative group">
                        <div class="absolute inset-0 bg-gradient-to-t from-stone-900/80 to-transparent"></div>
                        <div class="absolute bottom-10 left-10 text-white text-left">
                            <p class="text-stone-400 text-sm uppercase tracking-widest mb-2">Modern Experience</p>
                            <h3 class="text-2xl font-bold font-['Gentium_Book_Plus']">Reverent Design & Glassmorphism</h3>
                        </div>
                        <!-- Mockup Inner (Simplified representation) -->
                        <div class="absolute inset-0 flex items-center justify-center overflow-hidden">
                             <video 
                                src="/images/Video_Creation_For_Website_Landing_Page.mp4" 
                                class="w-full h-full object-cover opacity-60 group-hover:scale-105 transition-transform duration-700" 
                                autoplay 
                                loop 
                                muted 
                                playsinline
                             ></video>

                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- About Samuel -->
        <section id="about" class="py-24 px-6 bg-white">
            <div class="max-w-6xl mx-auto">
                <div class="grid md:grid-cols-2 gap-16 items-center">
                    <div class="space-y-6">
                        <div class="w-16 h-1 bg-purple-700 mb-8"></div>
                        <h2 class="text-4xl md:text-5xl font-bold text-stone-900 font-['Gentium_Book_Plus']">Who is Samuel?</h2>
                        <p class="text-lg text-stone-600 leading-relaxed">
                            Samuel is more than just an AI. He is a warm, humble, and encouraging Christian brother designed to walk alongside you in your faith journey. Unlike generic assistants, Samuel interacts with deep reverence for the Word, providing comfort, wisdom, and clarity using the light of scripture.
                        </p>
                        <ul class="space-y-4">
                            <li class="flex items-start space-x-3">
                                <i class="fas fa-check-circle text-purple-700 mt-1"></i>
                                <span class="text-stone-700"><b>Personalized Guidance</b>: Tailored spiritual encouragement based on your needs.</span>
                            </li>
                            <li class="flex items-start space-x-3">
                                <i class="fas fa-check-circle text-purple-700 mt-1"></i>
                                <span class="text-stone-700"><b>Historical Context</b>: Insights into the social and temporal settings of the Bible.</span>
                            </li>
                            <li class="flex items-start space-x-3">
                                <i class="fas fa-check-circle text-purple-700 mt-1"></i>
                                <span class="text-stone-700"><b>Multilingual Support</b>: Access wisdom in English, Spanish, French, and more.</span>
                            </li>
                        </ul>
                    </div>
                    <div class="relative">
                        <div class="aspect-square bg-purple-50 rounded-[60px] transform rotate-3 absolute inset-0 -z-10"></div>
                        <div class="aspect-square bg-stone-100 rounded-[60px] overflow-hidden border border-stone-200 shadow-inner">
                             <img src="/images/sam_two.png" class="w-full h-full object-cover" alt="Samuel" />
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features / Response Modes -->
        <section id="features" class="py-24 px-6 bg-stone-100/50">
            <div class="max-w-6xl mx-auto text-center mb-16">
                <h2 class="text-4xl font-bold mb-4 font-['Gentium_Book_Plus']">Tailored Response Modes</h2>
                <p class="text-stone-600 max-w-xl mx-auto">Samuel adapts his wisdom to your needs, whether you seek a quick word of encouragement or a deep theological study.</p>
            </div>
            
            <div class="grid md:grid-cols-3 gap-8 max-w-6xl mx-auto">
                <!-- Short and Sweet -->
                <div class="bg-white p-8 rounded-[40px] shadow-sm border border-stone-200 hover:shadow-xl transition-all group">
                    <div class="w-12 h-12 bg-stone-100 rounded-2xl flex items-center justify-center text-stone-600 mb-6 group-hover:bg-purple-700 group-hover:text-white transition-colors">
                        <i class="fas fa-comment-dots"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-2 font-['Gentium_Book_Plus']">Short and Sweet</h3>
                    <p class="text-xs font-bold text-purple-700 uppercase tracking-widest mb-4">Encouraging & Quick</p>
                    <p class="text-stone-600 mb-6">Concise, 5-6 sentence responses focused on immediate comfort, daily strength, and rapid scriptural reminders.</p>
                    <div class="text-sm text-stone-400 italic">Ideal for: Busy mornings, quick check-ins, or a word of peace.</div>
                </div>

                <!-- Deep Reflection -->
                <div class="bg-white p-8 rounded-[40px] shadow-sm border border-stone-200 hover:shadow-xl transition-all group">
                    <div class="w-12 h-12 bg-stone-100 rounded-2xl flex items-center justify-center text-stone-600 mb-6 group-hover:bg-purple-700 group-hover:text-white transition-colors">
                        <i class="fas fa-scroll"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-2 font-['Gentium_Book_Plus']">Deep Reflection</h3>
                    <p class="text-xs font-bold text-purple-700 uppercase tracking-widest mb-4">Spiritual Growth</p>
                    <p class="text-stone-600 mb-6">A balanced approach using the Truth, Reflection, and Application pattern to help you internalize the Word.</p>
                    <div class="text-sm text-stone-400 italic">Ideal for: Personal devotion, searching for meaning, and prayerful study.</div>
                </div>

                <!-- Research Mode -->
                <div class="bg-white p-8 rounded-[40px] shadow-sm border border-stone-200 hover:shadow-xl transition-all group">
                    <div class="w-12 h-12 bg-stone-100 rounded-2xl flex items-center justify-center text-stone-600 mb-6 group-hover:bg-purple-700 group-hover:text-white transition-colors">
                        <i class="fas fa-microscope"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-2 font-['Gentium_Book_Plus']">Research Mode</h3>
                    <p class="text-xs font-bold text-purple-700 uppercase tracking-widest mb-4">Scholarly Depth</p>
                    <p class="text-stone-600 mb-6">In-depth analysis with extensive cross-references, historical context, and original language insights for serious students.</p>
                    <div class="text-sm text-stone-400 italic">Ideal for: Sermon prep, theological research, and deep dive study.</div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="py-12 border-t border-stone-200 bg-white px-6">
            <div class="max-w-6xl mx-auto flex flex-col md:flex-row justify-between items-center gap-8">
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 bg-purple-700 rounded-full flex items-center justify-center text-white">
                        <i class="fas fa-bible"></i>
                    </div>
                    <span class="text-xl font-bold text-stone-800 font-['Gentium_Book_Plus']">Samuel</span>
                </div>
                <div class="text-sm text-stone-400">
                    © 2026 Samuel. A spiritual companion in the light of the Word.
                    <Link :href="route('privacy')" class="ml-4 hover:text-purple-700 transition underline decoration-stone-200 underline-offset-4">Privacy Policy</Link>
                </div>
                <div class="flex space-x-6 text-stone-400">
                    <a href="#" class="hover:text-purple-700 transition"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="hover:text-purple-700 transition"><i class="fab fa-facebook-p"></i></a>
                    <a href="#" class="hover:text-purple-700 transition"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
@keyframes fade-in {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
@keyframes slide-up {
    from { opacity: 0; transform: translateY(40px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in { animation: fade-in 0.8s ease-out forwards; }
.animate-slide-up { animation: slide-up 1.2s cubic-bezier(0.22, 1, 0.36, 1) forwards; }

nav.bg-transparent { border-bottom: 1px solid transparent; }
nav.bg-white\/80 { border-bottom: 1px solid rgba(231, 229, 228, 0.5); }
</style>
