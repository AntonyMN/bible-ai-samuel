<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useForm, Link, Head } from '@inertiajs/vue3';
import { marked } from 'marked';
import Swal from 'sweetalert2';

const vFocus = {
    mounted: (el) => el.focus()
};

const props = defineProps({
    auth: Object,
    initialMessages: Array,
    conversations: Array,
    availableModels: Array,
    userPreferences: Object,
});

const messages = ref(props.initialMessages || []);
const sidebarConversations = ref(props.conversations || []);
const activeConversationId = ref(null);
const newMessage = ref('');
const isTyping = ref(false);
const showUserDropdown = ref(false);
const selectedModel = ref(props.availableModels && props.availableModels.length > 0 ? props.availableModels[0] : 'llama3.2:3b');
const chatContainer = ref(null);

// TTS State
const audioPlayer = ref(null);
const isSpeaking = ref(false);
const isPaused = ref(false);
const currentlySpeakingMessageIndex = ref(-1);
const selectedBibleVersion = ref(props.userPreferences?.bible_version || 'BSB');

// TTS Search & Highlighting
const currentHighlightIndex = ref(-1);

// Auth Modal state
const showAuthModal = ref(false);
const authView = ref('signin'); // 'signin' or 'signup'
const authForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

// Title Editing State
const editingTitleId = ref(null);
const editingTitleValue = ref('');

const sortedSidebarConversations = computed(() => {
    return [...sidebarConversations.value].sort((a, b) => {
        return new Date(b.updated_at) - new Date(a.updated_at);
    });
});

const pageTitle = computed(() => {
    if (activeConversationId.value) {
        const conv = sidebarConversations.value.find(c => c.id === activeConversationId.value);
        return conv ? `Samuel - ${conv.title}` : 'Samuel - Chat';
    }
    return 'Samuel - Chat';
});

const sendMessage = () => {
    if (newMessage.value.trim() === '') return;
    
    // Optimistic UI
    messages.value.push({
        role: 'user',
        content: newMessage.value,
    });
    
    const userMsg = newMessage.value;
    newMessage.value = '';
    isTyping.value = true;
    
    // Send to backend
    axios.post(route('chat.send'), {
        message: userMsg,
        conversation_id: activeConversationId.value,
        model: selectedModel.value,
        bible_version: selectedBibleVersion.value,
        history: messages.value.slice(-10).map(m => ({ role: m.role, content: m.content })),
    }).then(response => {
        isTyping.value = false;
        const aiMsg = response.data.message;
        
        // If not already added by Echo
        const exists = messages.value.some(m => m.content === aiMsg.content && m.role === 'assistant');
        if (!exists) {
            messages.value.push(aiMsg);
        }
        
        // Handle new conversation metadata for auth users
        if (response.data.conversation_id && !activeConversationId.value) {
            activeConversationId.value = response.data.conversation_id;
            
            // Add to sidebar if not present
            const sidebarExists = sidebarConversations.value.some(c => c.id === response.data.conversation_id);
            if (!sidebarExists) {
                sidebarConversations.value.unshift({
                    id: response.data.conversation_id,
                    title: response.data.conversation_title || 'New Conversation',
                    updated_at: new Date().toISOString()
                });
            }
            
            // Update URL without reload if possible (Inertia history)
            window.history.pushState({}, '', route('chat.index') + '?conversation_id=' + response.data.conversation_id);
        }

        // Update active sidebar timestamp
        const conv = sidebarConversations.value.find(c => c.id === activeConversationId.value);
        if (conv) {
            conv.updated_at = new Date().toISOString();
        }
    }).catch(error => {
        isTyping.value = false;
        if (error.response?.status === 403) {
            // Save state for continuation
            sessionStorage.setItem('pending_chat_message', userMsg);
            sessionStorage.setItem('pending_chat_history', JSON.stringify(messages.value.slice(0, -1)));
            showAuthModal.value = true;
        } else {
            console.error(error);
            // Mark last message as failed for retry
            if (messages.value.length > 0 && messages.value[messages.value.length - 1].role === 'user') {
                messages.value[messages.value.length - 1].failed = true;
            }
        }
    });
};

const resendMessage = (content, index) => {
    // Remove failed flag and re-send
    messages.value.splice(index, 1);
    newMessage.value = content;
    sendMessage();
};

const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text).then(() => {
        // Maybe show toast? Using simplified for now
    });
};

const sanitizeForTts = (text) => {
    // Remove Markdown symbols but keep text
    return text
        .replace(/\*\*/g, '') // Bold
        .replace(/\*/g, '')   // Italic
        .replace(/__/g, '')   // Bold
        .replace(/_/g, '')    // Italic
        .replace(/#/g, '')    // Headers
        .replace(/\[([^\]]+)\]\([^\)]+\)/g, '$1') // Links (keep text)
        .replace(/`([^`]+)`/g, '$1') // Code
        .trim();
};

const readOutLoud = (text, index) => {
    if (isSpeaking.value && !isPaused.value && currentlySpeakingMessageIndex.value === index) {
        if (audioPlayer.value) audioPlayer.value.pause();
        isPaused.value = true;
        return;
    }
    
    if (isPaused.value && currentlySpeakingMessageIndex.value === index) {
        if (audioPlayer.value) audioPlayer.value.play();
        isPaused.value = false;
        return;
    }
    
    // Stop previous
    if (audioPlayer.value) {
        audioPlayer.value.pause();
        audioPlayer.value = null;
    }

    currentlySpeakingMessageIndex.value = index;
    const cleanText = sanitizeForTts(text);
    
    axios.post('https://api.chatwithsamuel.org/tts', { text: cleanText })
        .then(response => {
            const url = response.data.url;
            audioPlayer.value = new Audio(url);
            
            audioPlayer.value.onplay = () => {
                isSpeaking.value = true;
                isPaused.value = false;
            };
            
            audioPlayer.value.onpause = () => {
                isPaused.value = true;
            };
            
            audioPlayer.value.onended = () => {
                isSpeaking.value = false;
                isPaused.value = false;
                currentlySpeakingMessageIndex.value = -1;
            };
            
            audioPlayer.value.play();
        })
        .catch(error => {
            console.error('TTS Error:', error);
            isSpeaking.value = false;
            isPaused.value = false;
            currentlySpeakingMessageIndex.value = -1;
            Swal.fire({
                title: 'Grace and Peace',
                text: 'Samuel encountered trouble speaking just now. Please try again in a moment.',
                icon: 'error',
                confirmButtonColor: '#7e22ce', // purple-700
                background: '#fafaf9', // stone-50
            });
        });
};

const stopSpeech = () => {
    if (audioPlayer.value) {
        audioPlayer.value.pause();
        audioPlayer.value = null;
    }
    isSpeaking.value = false;
    isPaused.value = false;
    currentlySpeakingMessageIndex.value = -1;
};

const updateBibleVersionPreference = () => {
    if (props.auth.user) {
        axios.post(route('user.bible-version'), {
            bible_version: selectedBibleVersion.value,
        });
    }
};

watch(selectedBibleVersion, () => {
    updateBibleVersionPreference();
});

const startNewChat = () => {
    activeConversationId.value = null;
    messages.value = [];
};

const loadConversation = (id) => {
    activeConversationId.value = id;
    axios.get(route('chat.show', id)).then(response => {
        messages.value = response.data.messages;
    });
};

const updateConversationTitle = (id) => {
    if (!editingTitleValue.value.trim()) {
        editingTitleId.value = null;
        return;
    }
    
    axios.patch(route('chat.update-title', id), {
        title: editingTitleValue.value
    }).then(() => {
        const conv = sidebarConversations.value.find(c => c.id === id);
        if (conv) conv.title = editingTitleValue.value;
        editingTitleId.value = null;
    });
};

const scrollToBottom = () => {
    setTimeout(() => {
        if (chatContainer.value) {
            chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
        }
    }, 100);
};

watch(messages, () => {
    scrollToBottom();
}, { deep: true });

watch(activeConversationId, () => {
    scrollToBottom();
});

const startEditingTitle = (conv) => {
    editingTitleId.value = conv.id;
    editingTitleValue.value = conv.title;
};

const parseMarkdown = (content) => {
    return marked.parse(content, { breaks: true });
};

const handleLogout = () => {
    axios.post(route('logout')).then(() => {
        window.location.href = route('landing');
    });
};

const submitAuth = () => {
    const url = authView.value === 'signin' ? route('login') : route('register');
    authForm.post(url, {
        onSuccess: () => {
            showAuthModal.value = false;
            authForm.reset();
        },
    });
};

const closeAuthModal = () => {
    showAuthModal.value = false;
    // If they close it, we might want to remove the optimistic user message that triggered it
    if (messages.value.length > 0 && messages.value[messages.value.length - 1].role === 'user' && !messages.value[messages.value.length - 1].id) {
        messages.value.pop();
    }
};

onMounted(() => {
    window.Echo.channel('chat')
        .listen('MessageSent', (e) => {
            // Update sidebar timestamp if conversation exists
            if (e.conversation_id) {
                const conv = sidebarConversations.value.find(c => c.id === e.conversation_id);
                if (conv) {
                    conv.updated_at = new Date().toISOString();
                }
            }

            // Only push if it's the active conversation
            if (e.conversation_id === activeConversationId.value) {
                if (e.message.role === 'assistant') {
                    messages.value.push(e.message);
                }
            } else {
                // If user is querying but navigating, we can update the sidebar or a hidden state
                // For now, let's just ensure it doesn't break the active view
            }
        });

    // Check for pending history/message to continue
    const pendingMsg = sessionStorage.getItem('pending_chat_message');
    const pendingHistory = sessionStorage.getItem('pending_chat_history');

    if (props.auth.user && pendingMsg) {
        if (pendingHistory) {
            messages.value = JSON.parse(pendingHistory);
        }
        newMessage.value = pendingMsg;
        sessionStorage.removeItem('pending_chat_message');
        sessionStorage.removeItem('pending_chat_history');
        sendMessage();
    }
});
</script>

<template>
    <Head :title="pageTitle" />
    <div class="flex h-screen bg-stone-50 overflow-hidden">
        <aside v-if="auth.user" class="hidden md:flex flex-col w-64 bg-stone-100 border-r border-stone-200 shadow-inner">
            <div class="p-4 border-b border-stone-200 bg-white/50">
                <button 
                    class="w-full py-2 bg-purple-700 text-white rounded-lg text-sm font-medium hover:bg-purple-800 transition shadow-sm"
                    @click="startNewChat"
                >
                    + New Conversation
                </button>
            </div>
            <div class="flex-1 overflow-y-auto p-2 space-y-1">
                <div 
                    v-for="conv in sortedSidebarConversations" 
                    :key="conv.id"
                    :class="['p-3 rounded-lg text-sm cursor-pointer transition flex group flex-col relative', activeConversationId === conv.id ? 'bg-white border border-stone-200 shadow-sm' : 'hover:bg-stone-200 text-stone-600']"
                    @click="loadConversation(conv.id)"
                >
                    <div v-if="editingTitleId === conv.id" class="flex items-center space-x-2 w-full" @click.stop>
                        <input 
                            v-model="editingTitleValue" 
                            type="text" 
                            class="flex-1 bg-stone-50 border border-stone-200 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-amber-500 outline-none"
                            @keyup.enter="updateConversationTitle(conv.id)"
                            @blur="updateConversationTitle(conv.id)"
                            ref="titleInput"
                            v-focus
                        >
                    </div>
                    <div v-else class="flex justify-between items-start w-full">
                        <span class="font-medium truncate flex-1">{{ conv.title }}</span>
                        <button 
                            v-if="activeConversationId === conv.id"
                            @click.stop="startEditingTitle(conv)"
                            class="opacity-0 group-hover:opacity-100 p-1 text-stone-400 hover:text-purple-700 transition"
                        >
                            <i class="fas fa-pen text-[10px]"></i>
                        </button>
                    </div>
                    <span class="text-[10px] text-stone-400 mt-1">{{ new Date(conv.updated_at).toLocaleDateString() }}</span>
                </div>
            </div>
        </aside>

        <!-- Main Content (Header + Chat + Input) -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Header -->
            <header class="bg-white border-b border-stone-200 p-4 shadow-sm flex justify-between items-center bg-gradient-to-r from-purple-50 to-white font-['Outfit']">
            <div class="flex items-center space-x-2">
                <div class="w-12 h-12 rounded-full overflow-hidden shadow-lg transform hover:rotate-12 transition-transform duration-300">
                    <img src="/images/logo.png" alt="Samuel Logo" class="w-full h-full object-cover">
                </div>
                <div>
                    <h1 class="text-3xl font-['Gentium_Book_Plus'] font-bold text-stone-800 tracking-tight">Samuel</h1>
                    <p class="text-sm text-purple-800 italic">"Your faithful brother, Samuel" <span class="text-[9px] not-italic text-stone-300 ml-1">v1.1.2</span></p>
                </div>
            </div>
            
            <div class="flex items-center space-x-3">
                <!-- Agent Selector -->
                <div v-if="availableModels.length > 0" class="hidden sm:flex items-center space-x-2 bg-stone-100/80 px-4 py-2 rounded-full border border-stone-200 hover:border-purple-300 transition-colors h-10 shadow-sm">
                    <i class="fas fa-robot text-xs text-purple-700"></i>
                    <select 
                        v-model="selectedModel" 
                        class="bg-transparent border-none text-xs font-bold text-stone-600 focus:ring-0 cursor-pointer p-0 pr-6 leading-tight h-full"
                    >
                        <option v-for="model in availableModels" :key="model" :value="model">
                            {{ model }}
                        </option>
                    </select>
                </div>

                <!-- Bible Version Selector -->
                <div class="hidden sm:flex items-center space-x-2 bg-stone-100/80 px-4 py-2 rounded-full border border-stone-200 hover:border-purple-300 transition-colors h-10 shadow-sm">
                    <i class="fas fa-book-open text-xs text-purple-700"></i>
                    <select 
                        v-model="selectedBibleVersion" 
                        class="bg-transparent border-none text-xs font-bold text-stone-600 focus:ring-0 cursor-pointer p-0 pr-6 leading-tight h-full"
                    >
                        <option value="BSB">BSB</option>
                        <option value="KJV">KJV</option>
                        <option value="ASV">ASV</option>
                        <option value="WEB">WEB</option>
                    </select>
                </div>

                <a 
                    href="https://ko-fi.com/Y8Y21W7RKD" 
                    target="_blank" 
                    class="hidden sm:flex items-center space-x-2 bg-pink-50/50 px-4 py-2 rounded-full border border-pink-100 hover:bg-pink-100 hover:border-pink-200 transition-all group h-10 shadow-sm"
                    title="Keep Samuel Online"
                >
                    <i class="fas fa-heart text-xs text-pink-600 group-hover:scale-110 transition-transform"></i>
                    <span class="text-xs font-bold text-pink-700">Keep Samuel Online</span>
                </a>
                <div v-if="auth.user" class="relative">
                    <button 
                        @click="showUserDropdown = !showUserDropdown"
                        class="flex items-center space-x-2 p-1 rounded-full hover:bg-stone-100 transition-colors border border-transparent focus:border-purple-200 outline-none"
                    >
                        <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center text-purple-700 shadow-inner">
                            <i class="fas fa-user text-sm"></i>
                        </div>
                        <i class="fas fa-chevron-down text-[10px] text-stone-400"></i>
                    </button>
                    
                    <!-- Dropdown Menu -->
                    <div 
                        v-if="showUserDropdown" 
                        class="absolute right-0 mt-2 w-48 bg-white border border-stone-100 rounded-xl shadow-xl z-50 py-2 transform origin-top-right overflow-hidden"
                    >
                        <div class="px-4 py-2 border-b border-stone-50 mb-1">
                            <p class="text-xs font-bold text-stone-400 uppercase tracking-widest">Logged in as</p>
                            <p class="text-sm font-medium text-stone-800 truncate">{{ auth.user.name }}</p>
                        </div>
                        <Link 
                            :href="route('profile.edit')" 
                            class="flex items-center space-x-3 px-4 py-2 text-sm text-stone-600 hover:bg-purple-50 hover:text-purple-800 transition-colors"
                        >
                            <i class="fas fa-id-card w-4"></i>
                            <span>Profile</span>
                        </Link>
                        <Link 
                            v-if="auth.user.is_admin"
                            :href="route('admin.dashboard')" 
                            class="flex items-center space-x-3 px-4 py-2 text-sm text-blue-600 hover:bg-blue-50 transition-colors"
                        >
                            <i class="fas fa-chart-line w-4"></i>
                            <span>Admin Dashboard</span>
                        </Link>
                        <hr class="border-stone-50 my-1">
                        <button 
                            @click="handleLogout"
                            class="w-full flex items-center space-x-3 px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors"
                        >
                            <i class="fas fa-sign-out-alt w-4"></i>
                            <span>Logout</span>
                        </button>
                    </div>
                </div>
                <div v-else class="flex items-center space-x-2">
                    <a :href="route('login')" class="flex items-center px-4 h-10 text-sm font-medium text-purple-800 hover:bg-purple-50 rounded-full transition-colors">Login</a>
                    <a :href="route('register')" class="flex items-center px-5 h-10 bg-purple-700 text-white rounded-full text-sm font-bold hover:bg-purple-800 transition shadow-sm hover:shadow-md">Sign Up</a>
                </div>
            </div>
        </header>

        <!-- Chat Area -->
        <main ref="chatContainer" class="flex-1 overflow-y-auto p-4 space-y-6 max-w-4xl mx-auto w-full bg-[radial-gradient(#e5e7eb_1px,transparent_1px)] [background-size:20px_20px]">
            <div v-if="messages.length === 0" class="flex flex-col items-center justify-center h-full text-stone-400 space-y-4">
                <div class="w-24 h-24 bg-stone-100 rounded-full flex items-center justify-center border-2 border-stone-200 border-dashed">
                    <i class="fas fa-hand-holding-heart text-4xl text-stone-300"></i>
                </div>
                <div class="text-center space-y-2">
                    <p class="font-serif italic text-2xl text-stone-600">Peace be with you, {{ auth.user ? auth.user.name : 'friend' }}.</p>
                    <p class="text-sm text-stone-400 max-w-sm">I am Samuel, your brother in faith. How may I encourage you through the Word today?</p>
                </div>
                <div class="grid grid-cols-2 gap-3 max-w-md w-full mt-4">
                    <button class="text-sm p-4 bg-white border border-stone-200 rounded-2xl text-stone-600 hover:bg-purple-50 hover:border-purple-200 text-left transition shadow-sm group" @click="newMessage = 'Give me comfort in my time of trouble'">
                        <i class="fas fa-heart text-purple-200 group-hover:text-purple-700 mr-2"></i>
                        "Give me comfort..."
                    </button>
                    <button class="text-sm p-4 bg-white border border-stone-200 rounded-2xl text-stone-600 hover:bg-purple-50 hover:border-purple-200 text-left transition shadow-sm group" @click="newMessage = 'What does the bible say about wisdom?'">
                        <i class="fas fa-lightbulb text-purple-200 group-hover:text-purple-700 mr-2"></i>
                        "What about wisdom?"
                    </button>
                </div>
            </div>

            <div v-for="(msg, index) in messages" :key="index" :class="['flex', msg.role === 'user' ? 'justify-end' : 'justify-start']">
                <div :class="['max-w-[85%] rounded-3xl px-6 py-4 shadow-md relative group transition-all duration-300', msg.role === 'user' ? 'bg-purple-700 text-white rounded-tr-none hover:bg-purple-800' : 'bg-white text-stone-800 border border-stone-100 rounded-tl-none hover:border-purple-100']">
                    <!-- Message Content -->
                    <div v-if="msg.role === 'assistant'" class="markdown-content text-base leading-relaxed relative">
                        <div v-html="parseMarkdown(msg.content)"></div>
                        
                    </div>
                    <p v-else :class="['text-lg leading-relaxed font-medium', msg.failed ? 'opacity-70' : '']">{{ msg.content }}</p>
                    
                    <!-- Failed Label & Retry -->
                    <div v-if="msg.failed && msg.role === 'user'" class="mt-3 flex items-center justify-between bg-black/5 p-2 rounded-xl border border-white/10">
                        <p class="text-[10px] text-red-100 italic flex items-center">
                            <i class="fas fa-exclamation-circle mr-1 text-[8px]"></i> Delivery failed
                        </p>
                        <button 
                            @click="resendMessage(msg.content, index)" 
                            class="bg-white text-purple-800 text-[10px] font-bold px-3 py-1 rounded-lg hover:bg-purple-50 transition shadow-sm flex items-center space-x-1"
                        >
                            <i class="fas fa-sync-alt text-[8px]"></i>
                            <span>Retry Now</span>
                        </button>
                    </div>
                    <p v-else-if="msg.failed" class="text-[10px] text-red-400 mt-1 italic flex items-center">
                        <i class="fas fa-exclamation-circle mr-1 text-[8px]"></i> Delivery failed
                    </p>

                    <!-- New Systematic Footnotes are already part of msg.content -->

                    <!-- Action Buttons Overlay -->
                    <div :class="['absolute top-2 opacity-0 group-hover:opacity-100 transition-opacity flex space-x-1', msg.role === 'user' ? '-left-6' : '-right-10 flex-col space-y-1 items-start top-1']">
                        <template v-if="msg.role === 'user'">
                            <button v-if="msg.failed" @click="resendMessage(msg.content, index)" class="p-1.5 bg-white border border-stone-200 rounded-full text-purple-700 hover:text-purple-800 shadow-sm transition transform hover:rotate-45" title="Retry">
                                <i class="fas fa-sync-alt text-[10px]"></i>
                            </button>
                        </template>
                        <template v-else>
                            <button @click="copyToClipboard(msg.content)" class="p-1.5 bg-white border border-stone-200 rounded-lg text-stone-400 hover:text-purple-700 shadow-sm transition" title="Copy Content">
                                <i class="fas fa-copy text-[10px]"></i>
                            </button>
                            <button @click="readOutLoud(msg.content, index)" class="p-1.5 bg-white border border-stone-200 rounded-lg text-stone-400 hover:text-purple-700 shadow-sm transition" :title="isSpeaking && !isPaused && currentlySpeakingMessageIndex === index ? 'Pause' : 'Read Out Loud'">
                                <i :class="['fas text-[10px]', isSpeaking && !isPaused && currentlySpeakingMessageIndex === index ? 'fa-pause' : 'fa-volume-up']"></i>
                            </button>
                            <button v-if="isSpeaking && currentlySpeakingMessageIndex === index" @click="stopSpeech" class="p-1.5 bg-white border border-stone-200 rounded-lg text-stone-400 hover:text-red-600 shadow-sm transition" title="Stop">
                                <i class="fas fa-stop text-[10px]"></i>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
            <div v-if="isTyping" class="flex justify-start">
                <div class="bg-white text-stone-500 border border-stone-100 rounded-3xl rounded-tl-none px-6 py-4 shadow-md flex items-center space-x-3 transition-all animate-pulse">
                    <i class="fas fa-circle-notch fa-spin text-sm text-purple-700"></i>
                    <span class="text-sm font-['Gentium_Book_Plus'] italic tracking-wide">Samuel is searching the scriptures for you...</span>
                </div>
            </div>
        </main>

        <!-- Input Area -->
        <footer class="bg-white border-t border-stone-200 p-4 shadow-inner">
            <div class="max-w-4xl mx-auto relative">
                <input 
                    v-model="newMessage" 
                    type="text" 
                    class="w-full pl-6 pr-14 py-4 bg-stone-50 border border-stone-200 rounded-full focus:ring-4 focus:ring-purple-500/10 focus:border-purple-700 outline-none transition-all text-base shadow-sm font-['Outfit']"
                    placeholder="Ask a biblical question..."
                    @keyup.enter="sendMessage"
                >
                <button 
                    class="absolute right-2 top-2 p-2 bg-purple-700 text-white rounded-full hover:bg-purple-800 transition-all duration-300 shadow-md disabled:opacity-30 flex items-center justify-center w-12 h-12 transform hover:scale-105"
                    :disabled="!newMessage.trim() || isTyping"
                    @click="sendMessage"
                >
                    <i v-if="!isTyping" class="fas fa-paper-plane text-lg"></i>
                    <i v-else class="fas fa-spinner fa-spin text-lg"></i>
                </button>
            </div>
            <div class="mt-2 text-center">
                <p class="text-[10px] text-stone-400 font-['Gentium_Book_Plus']">A spiritual companion powered by the light of the word.</p>
            </div>
        </footer>

    </div>

    <!-- Authentication Modal (Glassmorphism) -->
    <div v-if="showAuthModal" class="fixed inset-0 bg-stone-900/60 backdrop-blur-md z-[100] flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-white/90 backdrop-blur-xl rounded-3xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all border border-white/20 relative">
            <!-- Close Button -->
            <button @click="closeAuthModal" class="absolute top-4 right-4 text-stone-400 hover:text-stone-600 transition p-2 bg-stone-100/50 rounded-full">
                <i class="fas fa-times"></i>
            </button>

            <!-- Modal Header -->
            <div class="bg-gradient-to-br from-amber-600 to-amber-700 p-8 text-center text-white relative overflow-hidden">
                <div class="absolute top-0 left-0 w-full h-full opacity-10 pointer-events-none">
                    <i class="fas fa-bible text-[120px] -rotate-12 transform -translate-x-10 translate-y-10"></i>
                </div>
                <div class="relative z-10">
                    <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center mx-auto mb-4 backdrop-blur-md border border-white/30 shadow-inner">
                        <i class="fas fa-lock text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-serif font-bold italic tracking-tight">Continue Your Journey</h3>
                    <p class="text-amber-100 text-sm mt-2 font-serif italic">"Ask, and it shall be given you; seek, and ye shall find" — Matthew 7:7</p>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex border-b border-stone-100 bg-stone-50/50">
                <button 
                    @click="authView = 'signin'" 
                    :class="['flex-1 py-4 text-sm font-bold transition-all', authView === 'signin' ? 'text-amber-700 border-b-2 border-amber-600 bg-white' : 'text-stone-400 hover:text-stone-600']"
                >
                    Sign In
                </button>
                <button 
                    @click="authView = 'signup'" 
                    :class="['flex-1 py-4 text-sm font-bold transition-all', authView === 'signup' ? 'text-amber-700 border-b-2 border-amber-600 bg-white' : 'text-stone-400 hover:text-stone-600']"
                >
                    Create Account
                </button>
            </div>

            <!-- Form -->
            <div class="p-8 space-y-6">
                <form @submit.prevent="submitAuth" class="space-y-4">
                    <div v-if="authView === 'signup'" class="space-y-1">
                        <label class="block text-[10px] font-bold text-stone-400 uppercase tracking-widest ml-1">Full Name</label>
                        <div class="relative group">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-stone-300 group-focus-within:text-amber-500 transition-colors">
                                <i class="fas fa-user-circle"></i>
                            </span>
                            <input 
                                v-model="authForm.name" 
                                type="text"
                                placeholder="Enter your name"
                                class="w-full pl-11 pr-4 py-3 bg-stone-50 border border-stone-100 rounded-xl focus:ring-4 focus:ring-amber-500/10 focus:border-amber-500 outline-none transition-all text-sm shadow-inner"
                                required
                            >
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="block text-[10px] font-bold text-stone-400 uppercase tracking-widest ml-1">Email Address</label>
                        <div class="relative group">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-stone-300 group-focus-within:text-amber-500 transition-colors">
                                <i class="fas fa-envelope"></i>
                            </span>
                            <input 
                                v-model="authForm.email" 
                                type="email"
                                placeholder="you@example.com"
                                class="w-full pl-11 pr-4 py-3 bg-stone-50 border border-stone-100 rounded-xl focus:ring-4 focus:ring-amber-500/10 focus:border-amber-500 outline-none transition-all text-sm shadow-inner"
                                required
                            >
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="block text-[10px] font-bold text-stone-400 uppercase tracking-widest ml-1">Password</label>
                        <div class="relative group">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-stone-300 group-focus-within:text-amber-500 transition-colors">
                                <i class="fas fa-key"></i>
                            </span>
                            <input 
                                v-model="authForm.password" 
                                type="password"
                                placeholder="••••••••"
                                class="w-full pl-11 pr-4 py-3 bg-stone-50 border border-stone-100 rounded-xl focus:ring-4 focus:ring-amber-500/10 focus:border-amber-500 outline-none transition-all text-sm shadow-inner"
                                required
                            >
                        </div>
                    </div>

                    <div v-if="authView === 'signup'" class="space-y-1">
                        <label class="block text-[10px] font-bold text-stone-400 uppercase tracking-widest ml-1">Confirm Password</label>
                        <div class="relative group">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-stone-300 group-focus-within:text-amber-500 transition-colors">
                                <i class="fas fa-check-double"></i>
                            </span>
                            <input 
                                v-model="authForm.password_confirmation" 
                                type="password"
                                placeholder="••••••••"
                                class="w-full pl-11 pr-4 py-3 bg-stone-50 border border-stone-100 rounded-xl focus:ring-4 focus:ring-amber-500/10 focus:border-amber-500 outline-none transition-all text-sm shadow-inner"
                                required
                            >
                        </div>
                    </div>

                    <div v-if="authForm.errors.email || authForm.errors.password || authForm.errors.name" class="p-3 bg-red-50 border border-red-100 rounded-xl text-xs text-red-600 animate-shake">
                        <p v-if="authForm.errors.email">{{ authForm.errors.email }}</p>
                        <p v-if="authForm.errors.password">{{ authForm.errors.password }}</p>
                        <p v-if="authForm.errors.name">{{ authForm.errors.name }}</p>
                    </div>

                    <button 
                        type="submit"
                        class="w-full bg-amber-600 text-white py-4 rounded-xl font-bold hover:bg-amber-700 transition shadow-lg transform active:scale-[0.98] mt-2 flex items-center justify-center space-x-2"
                        :disabled="authForm.processing"
                    >
                        <i v-if="authForm.processing" class="fas fa-circle-notch fa-spin"></i>
                        <span>{{ authView === 'signin' ? 'Sign In' : 'Create Account' }}</span>
                    </button>
                </form>

                <div class="relative">
                    <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-stone-100"></div></div>
                    <div class="relative flex justify-center text-[10px] uppercase tracking-widest text-stone-400 font-bold bg-white px-2">Peace be with you</div>
                </div>

                <p class="text-center text-xs text-stone-500">
                    {{ authView === 'signin' ? "Don't have an account?" : "Already have an account?" }}
                    <button @click="authView = authView === 'signin' ? 'signup' : 'signin'" class="text-amber-600 font-bold hover:underline">
                        {{ authView === 'signin' ? 'Sign Up' : 'Log In' }}
                    </button>
                </p>
            </div>
        </div>
    </div>
</div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Crimson+Pro:ital,wght@0,400;0,700;1,400&family=Inter:wght@400;500;600&display=swap');

body {
    font-family: 'Inter', sans-serif;
}

h1, .font-serif {
    font-family: 'Crimson Pro', serif;
}

.markdown-content p {
    margin-bottom: 1rem;
}
.markdown-content p:last-child {
    margin-bottom: 0;
}
.markdown-content ul, .markdown-content ol {
    margin-bottom: 1rem;
    padding-left: 1.5rem;
}
.markdown-content li {
    margin-bottom: 0.5rem;
}
.markdown-content ul {
    list-style-type: disc;
}
.markdown-content ol {
    list-style-type: decimal;
}
.markdown-content strong {
    font-weight: 600;
    color: #92400e; /* amber-800 for emphasis */
}
.markdown-content h1, .markdown-content h2, .markdown-content h3 {
    font-family: 'Crimson Pro', serif;
    font-weight: 700;
    margin-top: 1rem;
    margin-bottom: 0.5rem;
    color: #78350f; /* amber-900 */
}
</style>
