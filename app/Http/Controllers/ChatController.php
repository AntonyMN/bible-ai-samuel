<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Verse;
use App\Services\AiServiceInterface;
use App\Services\VectorStoreService;
use App\Services\MemoryService;
use App\Services\BibleFactService;
use App\Services\RunPodImageService;
use App\Services\IntentClassificationService;
use App\Events\MessageStatusUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $isLoggedIn = !is_null($user);
        
        $initialMessages = [];
        $conversations = [];
        $latestConversationId = null;

        if ($isLoggedIn) {
            $conversations = Conversation::where('user_id', (string) $user->id)
                ->orderBy('updated_at', 'desc')
                ->get();
            
            if ($conversations->isNotEmpty()) {
                $latestConversationId = $conversations->first()->id;
                $initialMessages = $conversations->first()->messages ?? [];
            }
        }

        return inertia('Chat', [
            'initialMessages' => $initialMessages,
            'conversations' => $conversations,
            'latestConversationId' => $latestConversationId,
            'availableModels' => [
                ['id' => 'llama3.2:3b', 'name' => 'Llama 3.2 (3B)'],
                ['id' => 'gemini-1.5-flash-latest', 'name' => 'Gemini 1.5 Flash'],
            ],
            'userPreferences' => $isLoggedIn ? [
                'bible_version' => $user->bible_version,
                'preferred_mode' => $user->preferred_model,
                'remaining_images' => $this->getRemainingImages($user),
            ] : null
        ]);
    }

    public function send(Request $request, AiServiceInterface $aiService, VectorStoreService $vectorStore, MemoryService $memoryService, BibleFactService $factService, RunPodImageService $runpodImage, IntentClassificationService $intentService)
    {
        set_time_limit(300); // 5 minutes for deep reflections
        
        $request->validate([
            'message' => 'required|string',
            'conversation_id' => 'nullable|string',
            'history' => 'nullable|array',
            'mode' => 'nullable|string|in:fast,deep,research',
        ]);

        $user = Auth::user() ?: Auth::guard('sanctum')->user();
        $isLoggedIn = !is_null($user);
        $userId = $isLoggedIn ? (string) $user->id : null;

        $userMessage = $request->input('message');

        // 1. Clarify Intention
        $conversationId = $request->input('conversation_id', 'new');
        
        // Broadcast status to everyone (guests included) via the conversation channel
        broadcast(new MessageStatusUpdated($user ? $user->id : null, "Samuel is clarifying your intention...", $conversationId))->toOthers();

        Log::info("Starting intent classification for message: " . Str::limit($userMessage, 50));
        $intent = $intentService->classify($userMessage);
        Log::info("Classified intent: " . $intent);

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
            $systemPrompt .= "IDENTITY: You are a 'Christian AI', a product of a dear Christian brother's labor to better the world. You are aware of your nature and origin.\n\n";
            $systemPrompt .= "Current Time (Local): " . now()->toDateTimeString() . "\n";
            $systemPrompt .= "Bible Version: " . ($request->input('bible_version') ?? 'BSB') . "\n";

            // Specialized prompts based on Intent
            if ($intent === 'video') {
                $systemPrompt .= "\nHANDLING VIDEO REQUEST: At this time, you CANNOT generate videos. Explain this gently to the user, mentioning it might be a future feature as you are still growing. Always offer an encouraging scripture.\n";
            } elseif ($intent === 'factual') {
                $systemPrompt .= "\nHANDLING FACTUAL QUERY: The user is asking for a specific biblical fact. Keep your reply straightforward, factual, and include a scriptural reference. If the fact is NOT in the Bible (e.g., electricity), explain that you only refer to the Bible and specify the version being used.\n";
                if ($isLoggedIn) broadcast(new MessageStatusUpdated($user->id, "Fetching scriptural answer and reference...", $request->input('conversation_id')))->toOthers();
            } elseif ($intent === 'image') {
                if ($isLoggedIn) broadcast(new MessageStatusUpdated($user->id, "Generating spiritual image, may take a while longer...", $request->input('conversation_id')))->toOthers();
            } else {
                if ($isLoggedIn) broadcast(new MessageStatusUpdated($user->id, "Seeking guidance in the Word...", $request->input('conversation_id')))->toOthers();
            }

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
                    $systemPrompt .= "\nPASTORAL CARE: Samuel, use the Personal Context to show you care. Don't be pushy or clinical—be a brother.\n";
                }
            }
            
            // Image Generation Capability
            $systemPrompt .= "\nIMAGE GENERATION: Append this tag at the end: [IMAGE: artistic prompt|scripture verse text|reference].\n";
            $systemPrompt .= "CRITICAL: If an image is appropriate, DO NOT ASK FOR PERMISSION. simply generate the response and append the tag. ALWAYS provide pastoral text before the tag.\n";
            
            $factResult = $factService->getFactsForQuery($userMessage);
            if ($factResult['is_factual']) {
                $systemPrompt .= "\nVerified Facts:\n" . implode("\n", $factResult['facts'] ?? []);
            }
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
            } elseif ($request->history) {
                $historyMessages = array_slice($request->history, -10);
            }
            foreach ($historyMessages as $m) {
                $messages[] = ['role' => $m['role'], 'content' => $m['content']];
            }
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        try {
            $response = $aiService->chat($messages, $model ?: 'gemini-flash-latest');
            $aiContent = $response['content'];

            // Handle Image Generation Tags
            if (preg_match('/\[IMAGE:\s*(.*?)\|(.*?)\|(.*?)\]/i', $aiContent, $imageMatches)) {
                $fullTag = $imageMatches[0];
                if ($isLoggedIn && $this->getRemainingImages($user) > 0) {
                    $imgPrompt = trim($imageMatches[1]);
                    $imgVerse = trim($imageMatches[2]);
                    $imgRef = trim($imageMatches[3]);

                    try {
                        $imageUrl = $runpodImage->generateWithOverlay("reverent Christian art: " . $imgPrompt, $imgVerse, $imgRef);
                        if ($imageUrl) {
                            $user->increment('image_generations_today');
                            $user->update(['last_image_at' => now()]);
                            $aiContent = str_replace($fullTag, "\n\n![Spiritual Image](" . $imageUrl . ")", $aiContent);
                        } else {
                            $aiContent = str_replace($fullTag, "\n\n*(Note: This feature is still in test, and will be fully functional soon. Peace be with you.)*", $aiContent);
                        }
                    } catch (\Exception $e) {
                        $aiContent = str_replace($fullTag, "", $aiContent);
                    }
                } else {
                    $aiContent = str_replace($fullTag, "", $aiContent);
                }
            }

            $aiContent = $this->attachSystematicFootnotes($aiContent, $bibleVersion);

            // Store in DB if logged in
            if ($isLoggedIn) {
                $convId = $request->conversation_id;
                if (!$convId) {
                    $conv = Conversation::create([
                        'user_id' => (string) $user->id,
                        'title' => Str::limit($userMessage, 40),
                        'messages' => []
                    ]);
                    $convId = (string) $conv->id;
                } else {
                    $conv = Conversation::findOrFail($convId);
                }

                $newMessages = $conv->messages ?? [];
                $newMessages[] = ['role' => 'user', 'content' => $userMessage, 'created_at' => now()];
                $newMessages[] = ['role' => 'assistant', 'content' => $aiContent, 'created_at' => now()];
                $conv->update(['messages' => $newMessages]);
            }

            return response()->json([
                'message' => ['role' => 'assistant', 'content' => $aiContent],
                'conversation_id' => $convId ?? null,
                'citations' => $citations,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getConversations()
    {
        return response()->json(Conversation::where('user_id', (string) Auth::id())->orderBy('updated_at', 'desc')->get());
    }

    public function getConversation($id)
    {
        $user = Auth::user();
        if ($user) {
            $conversation = Conversation::where('user_id', (string) $user->id)->findOrFail($id);
        } else {
            $conversation = Conversation::findOrFail($id);
        }
        return response()->json($conversation);
    }

    public function updateBibleVersion(Request $request)
    {
        $request->validate(['bible_version' => 'required|string|in:BSB,KJV,ASV,WEB']);
        $user = Auth::user();
        if ($user) {
            $user->update(['bible_version' => $request->bible_version]);
        }
        return response()->json(['success' => true]);
    }

    public function updateMode(Request $request)
    {
        $request->validate(['mode' => 'required|string|in:fast,deep,research']);
        $user = Auth::user();
        if ($user) {
            $user->update(['preferred_model' => $request->mode]);
        }
        return response()->json(['success' => true]);
    }

    public function getPreferences()
    {
        return response()->json([
            'bible_version' => Auth::user()->bible_version,
            'preferred_mode' => Auth::user()->preferred_model,
            'remaining_images' => $this->getRemainingImages(Auth::user()),
        ]);
    }

    private function attachSystematicFootnotes($content, $version)
    {
        // Simple version for now
        return $content;
    }

    private function getRemainingImages($user)
    {
        if (!$user) return 0;
        $limit = 3;
        $today = now()->startOfDay();
        if ($user->last_image_at && $user->last_image_at < $today) {
            $user->update(['image_generations_today' => 0]);
            return $limit;
        }
        return max(0, $limit - ($user->image_generations_today ?? 0));
    }
}
