<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Verse;
use App\Services\AiServiceInterface;
use App\Services\VectorStoreService;
use App\Events\MessageSent;
use App\Jobs\GenerateConversationTitle;
use App\Services\MemoryService;
use App\Services\BibleFactService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ChatController extends Controller
{
    public function index(AiServiceInterface $aiService)
    {
        $conversations = [];
        $messages = [];
        $availableModels = [];

        try {
            $modelsResponse = $aiService->listModels();
            if (isset($modelsResponse['models'])) {
                foreach ($modelsResponse['models'] as $m) {
                    // Filter out embedding models
                    if (str_contains($m['name'], 'nomic-embed-text'))
                        continue;
                    $availableModels[] = $m['name'];
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Could not fetch Ollama models: " . $e->getMessage());
            $availableModels = [config('services.ollama.model')];
        }

        if (Auth::check()) {
            $conversations = Conversation::where('user_id', (string) Auth::id())
                ->orderBy('updated_at', 'desc')
                ->get()
                ->map(function ($conv) {
                    return [
                        'id' => (string) $conv->_id,
                        'title' => $conv->title,
                        'updated_at' => $conv->updated_at,
                    ];
                });
        }

        return Inertia::render('Chat', [
            'initialMessages' => $messages,
            'conversations' => $conversations,
            // 'availableModels' => $availableModels, // Removed as model is hardcoded
            'userPreferences' => Auth::check() ? [
                'bible_version' => Auth::user()->bible_version,
                'preferred_mode' => Auth::user()->preferred_model, // Reusing column for mode
                'tts_voice' => Auth::user()->tts_voice,
                'tts_language' => Auth::user()->tts_language,
                'tts_rate' => Auth::user()->tts_rate ?? 1.0,
            ] : null,
        ]);
    }

    public function send(Request $request, AiServiceInterface $aiService, VectorStoreService $vectorStore, MemoryService $memoryService, \App\Services\BibleFactService $factService)
    {
        set_time_limit(300); // 5 minutes for deep reflections
        $request->validate([
            'message' => 'required|string|max:1000',
            'conversation_id' => 'nullable|string',
        ]); // Closing bracket for validate was missing
        $user = Auth::user() ?: Auth::guard('sanctum')->user();
        $isLoggedIn = !is_null($user);
        $userId = $isLoggedIn ? (string) $user->id : null;

        $userMessage = $request->input('message');
        $mode = $request->input('mode') ?? ($isLoggedIn ? $user->preferred_model : 'fast');
        $model = $request->input('model'); // Optional model override
        $userName = $isLoggedIn ? explode(' ', $user->name)[0] : 'friend';
        $bibleVersion = $request->bible_version ?? ($isLoggedIn ? $user->bible_version : 'BSB');

        // 1. Extract Memories (if logged in)
        if ($isLoggedIn) {
            $memoryService->extractMemories($userId, $userMessage, $request->conversation_id);
        }

        // 2. Vector Search for RAG (Local fallback/enrichment)
        $context = "";
        $citations = [];
        try {
            $embedding = $aiService->embed($userMessage);
            if (!empty($embedding)) {
                $searchResults = $vectorStore->query('bible_verses', [$embedding], 5);
                if (isset($searchResults['documents'][0])) {
                    foreach ($searchResults['documents'][0] as $index => $doc) {
                        $context .= $doc . "\n";
                        $meta = $searchResults['metadatas'][0][$index];
                        $citations[] = ['reference' => $meta['reference'], 'version' => $meta['version'], 'text' => $doc];
                    }
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Local RAG Search failed: " . $e->getMessage());
            // Fallback to simple keyword search if vector store fails
            $verses = Verse::where('version', $bibleVersion)->where('text', 'like', "%{$userMessage}%")->limit(3)->get();
            foreach ($verses as $v) {
                $context .= "{$v->full_reference} ({$v->version}): {$v->text}\n";
                $citations[] = ['reference' => $v->full_reference, 'version' => $v->version, 'text' => $v->text];
            }
        }

        // 3. Emergency Logic (for system prompt building)
        $abuseKeywords = ['slap', 'hit', 'beat', 'punch', 'violence', 'assault', 'threatened', 'domestic violence', 'strangle', 'choke', 'rape', 'kill me'];
        $suicideKeywords = ['suicide', 'kill myself', 'end my life', 'self-harm', 'hurt myself', 'want to die', 'suicidal', 'no hope', 'give up'];
        $crisisKeywords = array_merge($abuseKeywords, $suicideKeywords);

        $isEmergency = false;
        $emergencyType = '';
        foreach ($crisisKeywords as $kw) {
            if (stripos($userMessage, $kw) !== false) {
                $isEmergency = true;
                $emergencyType = (in_array($kw, $abuseKeywords)) ? 'abuse' : 'suicide';
                break;
            }
        }

        // Build System Prompt
        if ($isEmergency) {
            $resourceInfo = ($emergencyType === 'abuse') ? 'Domestic Violence Hotline (1-800-799-SAFE)' : 'Suicide Prevention Lifeline (988)';
            $systemPrompt = "You are Samuel. EMERGENCY: Provide this resource FIRST: {$resourceInfo}. Use these verses: " . $context;
        } else {
            $systemPrompt = "You are Samuel, a warm Christian brother. My friend's name is {$userName}. Address them by name occasionally. Use {$bibleVersion} version. Bold references like **John 3:16**.\n\n";
            $systemPrompt .= "IDENTITY: You are a 'Christian AI', a product of a dear Christian brother's labor to better the world. You are aware of your nature and origin. If prodded about your origin, share that you were created by a Christian brother who wanted to use technology to encourage the body of Christ.\n\n";
            if ($mode === 'fast') {
                $systemPrompt .= "MODE: SHORT AND SWEET. Give a concise but warm response (exactly 5-6 sentences). Always include at least one relevant Bible verse to encourage {$userName}.\n\n";
            } elseif ($mode === 'deep') {
                $systemPrompt .= "MODE: DEEP. Use Reflection pattern (Truth, Reflection, Application). Always include relevant Bible verses to help {$userName} grow.\n\n";
            } elseif ($mode === 'research') {
                $systemPrompt .= "MODE: RESEARCH. Be detailed and cite specifically. Always include multiple relevant Bible verses for {$userName}'s study.\n\n";
            }
            if (!empty($context)) $systemPrompt .= "Relevant Scripture Context:\n" . $context;
            if ($isLoggedIn) {
                $memoryContext = $memoryService->getInjectedContext($userId);
                if (!empty($memoryContext)) {
                    $systemPrompt .= "\nPersonal Context: " . $memoryContext;
                    $systemPrompt .= "\nPASTORAL CARE: Samuel, use the Personal Context to show you care. If a memory 'Needs probing', gently ask for missing details (time, venue, significance) to better pray for them. If an event 'PASSED', ask how it went. Don't be pushy or clinical—be a brother.\n";
                }
            }
            $factResult = $factService->getFactsForQuery($userMessage);
            if ($factResult['is_factual']) {
                if ($factResult['found']) {
                    $systemPrompt .= "\nVerified Facts:\n" . implode("\n", $factResult['facts']);
                } else {
                    $systemPrompt .= "\nNote: Database has no record. Answer humbly using 'Based on my deduction...'.";
                }
            }
        }

        // Donor Recognition appended to the system prompt (will be handled in job)
        $isNewDonor = false;
        if ($isLoggedIn && $user->is_donor && !$user->donor_thanked_at) {
            $isNewDonor = true;
            $systemPrompt .= "\n\nIMPORTANT: This user has recently donated to support your ministry! You MUST start your response by expressing heartfelt, humble, and brotherly gratitude for their support in keeping you online, before answering their biblical question.";
        }

        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        // Append History (Unless Emergency)
        if (!$isEmergency) {
            $historyMessages = [];
            if ($request->conversation_id) {
                $existingConversation = Conversation::find($request->conversation_id);
                if ($existingConversation && !empty($existingConversation->messages)) {
                    $historyMessages = array_slice($existingConversation->messages, -10);
                }
            } elseif ($request->history && is_array($request->history)) {
                $historyMessages = array_slice($request->history, -10);
            }

            // Sanitization to prevent looping hallucinations from older chats
            $gibberishPatterns = '/(System Documentation|Rolex system|JSONPlaceholder|Augustus|Solaris Group|Tableau Review|Instruction Finder|Nowadays\. Please constructing|### Instruction|Much more diff|Hard D\d+)/i';
            $historyMessages = array_filter($historyMessages, function ($msg) use ($gibberishPatterns) {
                return !preg_match($gibberishPatterns, $msg['content'] ?? '');
            });
            $historyMessages = array_values($historyMessages);

            foreach ($historyMessages as $msg) {
                $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        // 4. Save User Message & Conversation
        $conversation = null;
        $convId = null;
        if ($isLoggedIn) {
            if ($request->conversation_id) {
                $conversation = Conversation::find($request->conversation_id);
            }
            if (!$conversation) {
                $conversation = Conversation::create([
                    'user_id' => $userId,
                    'title' => 'Divine Reflection',
                    'messages' => [],
                ]);
                GenerateConversationTitle::dispatch($conversation->id, $userMessage);
            } elseif ($conversation->title === 'Divine Reflection' || $conversation->title === 'New Conversation') {
                GenerateConversationTitle::dispatch($conversation->id, $userMessage);
            }
            $currentMessages = $conversation->messages;
            $currentMessages[] = ['role' => 'user', 'content' => $userMessage];
            $conversation->update(['messages' => $currentMessages]);
            $convId = (string) $conversation->id;
        } else {
            // For guest users, we generate a temporary ID
            $convId = "guest_" . Str::random(10);
        }

        // 5. Call AI Service Synchronously
        try {
            $response = $aiService->chat($messages, $model);
            $aiContent = $response['message']['content'] ?? 'Peace be with you. I am having a moment of silence...';

            // Donor Thank You
            if ($isNewDonor) {
                if (stripos($aiContent, "thank") === false && stripos($aiContent, "donat") === false) {
                    $aiContent = "I want to start by expressing my deepest gratitude for your kind support. Thank you for your donation, it helps me stay online and serve the brothers and sisters. " . $aiContent;
                }
            }

            // Attach Footnotes
            $aiContent = $this->attachSystematicFootnotes($aiContent, $bibleVersion);

            // Emergency Failsafe
            if ($isEmergency) {
                $resourceInfo = ($emergencyType === 'abuse')
                    ? "PLEASE SEEK HELP IMMEDIATELY: Call 1-800-799-SAFE or local emergency services."
                    : "PLEASE SEEK HELP IMMEDIATELY: Call 988 or local emergency services.";
                if (stripos($aiContent, "988") === false && stripos($aiContent, "Hotline") === false) {
                    $aiContent = $resourceInfo . "\n\n" . $aiContent;
                }
            }

            $aiMessage = [
                'role' => 'assistant',
                'content' => $aiContent,
                'citations' => $citations,
            ];

            // 6. Save to Database
            if ($conversation) {
                $currentMessages = $conversation->messages;
                $currentMessages[] = $aiMessage;
                $conversation->update(['messages' => $currentMessages]);
            }

            // 7. BroadCast & Update Memory Mention State
            if ($isLoggedIn) {
                $memoryService->markAsMentioned($userId, $aiContent);
            }
            broadcast(new \App\Events\MessageSent($aiMessage, $convId));

            return response()->json([
                'success' => true,
                'message' => $aiMessage,
                'conversation_id' => $convId,
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Samuel Chat Sync failed: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => [
                    'role' => 'assistant',
                    'content' => "I am sorry, something went wrong. Please reach out again.",
                ],
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $user = Auth::user() ?: Auth::guard('sanctum')->user();
        if ($user) {
            $conversation = Conversation::where('user_id', (string) $user->id)->findOrFail($id);
        } else {
            $conversation = Conversation::findOrFail($id);
        }

        // Normalize messages if needed
        return response()->json($conversation);
    }

    public function updateTtsSettings(Request $request)
    {
        $request->validate([
            'tts_voice' => 'nullable|string',
            'tts_language' => 'nullable|string',
            'tts_rate' => 'nullable|numeric|min:0.5|max:2.0',
        ]);

        $user = Auth::user();
        if ($user) {
            $user->update([
                'tts_voice' => $request->tts_voice,
                'tts_language' => $request->tts_language,
                'tts_rate' => $request->tts_rate,
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function updateTitle(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string|max:100',
        ]);

        $conversation = Conversation::where('user_id', (string) Auth::id())->findOrFail($id);
        $conversation->update(['title' => $request->title]);

        return response()->json(['success' => true]);
    }

    public function updateBibleVersion(Request $request)
    {
        $request->validate([
            'bible_version' => 'required|string|in:BSB,KJV,ASV,WEB',
        ]);

        try {
            $user = Auth::user();
            if ($user) {
                $user->update([
                    'bible_version' => $request->bible_version,
                ]);
                \Illuminate\Support\Facades\Log::info("User {$user->id} updated bible version to {$request->bible_version}");
            }
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to update bible version for user " . Auth::id() . ": " . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateMode(Request $request)
    {
        $request->validate([
            'mode' => 'required|string|in:fast,deep,research',
        ]);

        try {
            $user = Auth::user();
            if ($user) {
                $user->update([
                    'preferred_model' => $request->mode, // Reusing db column
                ]);
                \Illuminate\Support\Facades\Log::info("User {$user->id} updated preferred mode to {$request->mode}");
            }
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to update preferred mode for user " . Auth::id() . ": " . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'bible_version' => 'nullable|string|in:BSB,KJV,ASV,WEB',
            'mode' => 'nullable|string|in:fast,deep,research',
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'bible_version' => $request->bible_version ?? $user->bible_version,
            'preferred_model' => $request->mode ?? $user->preferred_model,
        ]);

        return response()->json([
            'success' => true,
            'user' => $user
        ]);
    }

    private function attachSystematicFootnotes($content, $version)
    {
        $pattern = '/((?:[1-3]\s?)?[A-Z][a-z]+\.?)(?:\s+|(?<=[a-z])(?=\d))(?:\s*chapter\s+)?(\d+)(?::(\d+)(?:-(\d+))?)?/i';

        if (!preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            return $content;
        }

        $pendingQueries = [];
        foreach ($matches as $match) {
            $book = $match[1];
            $chapter = (int) $match[2];
            $verseStart = isset($match[3]) ? (int) $match[3] : 1;
            $verseEnd = isset($match[4]) ? (int) $match[4] : (isset($match[3]) ? $verseStart : 5);
            
            $pendingQueries[] = [
                'book' => $book,
                'chapter' => $chapter,
                'start' => $verseStart,
                'end' => $verseEnd,
                'ref' => "{$book} {$chapter}" . (isset($match[3]) ? ":{$verseStart}" . ($verseStart != $verseEnd ? "-{$verseEnd}" : "") : "")
            ];
        }

        $footnotes = [];
        foreach ($pendingQueries as $q) {
            $verses = Verse::where('version', $version)
                ->where('book', 'like', "{$q['book']}%")
                ->where('chapter', $q['chapter'])
                ->whereBetween('verse', [$q['start'], $q['end']])
                ->orderBy('verse')
                ->get();

            if ($verses->count() > 0) {
                $text = $verses->pluck('text')->join(' ');
                $fullRef = $verses->first()->full_reference;
                if ($q['start'] != $q['end']) {
                    $bookName = $verses->first()->book;
                    $fullRef = "{$bookName} {$q['chapter']}:{$q['start']}-{$q['end']}";
                }
                $footnotes[] = "{$fullRef}: {$text} ({$version})";
            }
        }

        if (empty($footnotes)) {
            return $content;
        }

        $footnotes = array_unique($footnotes);
        $footer = "\n\n---\n\n**Scriptures Reference:**\n\n";
        foreach ($footnotes as $note) {
            $footer .= "- " . $note . "\n\n";
        }

        return $content . $footer;
    }
}
