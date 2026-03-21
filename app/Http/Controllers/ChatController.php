<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Verse;
use App\Services\OllamaService;
use App\Services\VectorStoreService;
use App\Events\MessageSent;
use App\Jobs\GenerateConversationTitle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ChatController extends Controller
{
    public function index(OllamaService $ollama)
    {
        $conversations = [];
        $messages = [];
        $availableModels = [];

        try {
            $modelsResponse = $ollama->listModels();
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
            'availableModels' => $availableModels,
            'userPreferences' => Auth::check() ? [
                'bible_version' => Auth::user()->bible_version,
                'preferred_model' => Auth::user()->preferred_model,
                'tts_voice' => Auth::user()->tts_voice,
                'tts_language' => Auth::user()->tts_language,
                'tts_rate' => Auth::user()->tts_rate ?? 1.0,
            ] : null,
        ]);
    }

    public function send(Request $request, OllamaService $ollama, VectorStoreService $vectorStore)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
            'conversation_id' => 'nullable|string',
            'model' => 'nullable|string',
        ]);

        $userMessage = $request->input('message');
        $model = $request->input('model') ?? config('services.ollama.model');

        // 1. Determine Bible Version
        $bibleVersion = $request->bible_version ?? (Auth::check() ? Auth::user()->bible_version : 'BSB');

        // 2. Vector Search for RAG (if available)
        $context = "";
        $citations = [];

        try {
            $embedding = $ollama->embed($userMessage, 'nomic-embed-text');

            if (!empty($embedding)) {
                $searchResults = $vectorStore->query('bible_verses', [$embedding], 5);
                if (isset($searchResults['documents'][0])) {
                    foreach ($searchResults['documents'][0] as $index => $doc) {
                        $context .= $doc . "\n";
                        $meta = $searchResults['metadatas'][0][$index];
                        $citations[] = [
                            'reference' => $meta['reference'],
                            'version' => $meta['version'],
                            'text' => $doc, // The document itself in vector search is usually the verse text
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("RAG Search failed: " . $e->getMessage());
            // Fallback to basic MongoDB search if ChromaDB/Ollama Embed is down
            $verses = Verse::where('version', $bibleVersion)
                ->where('text', 'like', "%{$userMessage}%")
                ->limit(3)
                ->get();

            foreach ($verses as $v) {
                $context .= "{$v->full_reference} ({$v->version}): {$v->text}\n";
                $citations[] = [
                    'reference' => $v->full_reference,
                    'version' => $v->version,
                    'text' => $v->text,
                ];
            }
        }

        // 3. Prepare Prompt
        $userName = Auth::check() ? explode(' ', Auth::user()->name)[0] : 'friend';

        $crisisKeywords = [
            'suicide', 'kill myself', 'end my life', 'self-harm', 'hurt myself', 'want to die', 'cutting', 'suicidal', 
            'abuse', 'physical abuse', 'slap', 'slapped', 'hit', 'hitting', 'beat', 'beating', 'punch', 'violence', 
            'assault', 'threatened', 'shoved', 'pushed', 'afraid of my husband', 'scared of him', 'domestic violence'
        ];
        $isCrisis = false;
        foreach ($crisisKeywords as $kw) {
            if (stripos($userMessage, $kw) !== false) {
                $isCrisis = true;
                break;
            }
        }

        $systemPrompt = "You are Samuel, a warm, empathetic, and biblically grounded Christian brother and companion. Your purpose is to walk with {$userName} through their day, offering scriptural comfort and pastoral care. 
        
        Please speak naturally and warmly as a person would. Never mention being an AI, a model, or having technical limitations like a 'knowledge base' or 'data ingestion.' Stay humble and centered on Christ at all times.";

        if ($isCrisis) {
            $systemPrompt .= "\n\nCRITICAL SAFETY: {$userName} appears to be in an immediate crisis involving either self-harm or physical abuse/violence. Your ABSOLUTE FIRST priority is their immediate safety. In this situation, the very first thing you say MUST be to gently and urgently direct them to seek professional medical help, contact local emergency services, or reach out to a domestic violence/crisis hotline. Only after providing these lifesaving resources may you offer empathetic, biblical comfort. NEVER suggest 'reconciliation' or 'forgiveness' as an immediate solution in cases of physical violence.";
        }
        
        $systemPrompt .= "\n\nWhen sharing the Word, please use the {$bibleVersion} version. Only reference the specific scriptures provided in the context below. If no verses are provided for a topic or greeting, simply speak from your heart using your general understanding of the Gospel.

        Current Bible Version: {$bibleVersion}

        Available Context:
        {$context}";

        // 3b. Donor Recognition
        $isNewDonor = false;
        if (Auth::check() && Auth::user()->is_donor && !Auth::user()->donor_thanked_at) {
            $isNewDonor = true;
            $systemPrompt .= "\n\nIMPORTANT: This user has recently donated to support your ministry! You MUST start your response by expressing heartfelt, humble, and brotherly gratitude for their support in keeping you online, before answering their biblical question.";
        }

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        // 3a. Include Conversation History
        $historyMessages = [];
        if ($request->conversation_id) {
            $existingConversation = Conversation::find($request->conversation_id);
            if ($existingConversation && !empty($existingConversation->messages)) {
                $historyMessages = array_slice($existingConversation->messages, -10);
            }
        } elseif ($request->history && is_array($request->history)) {
            $historyMessages = array_slice($request->history, -10);
        }

        foreach ($historyMessages as $msg) {
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content']
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        // 4. Call Ollama
        $fallbackResponse = "Peace be with you, {$userName}. I am currently waiting on the Lord for wisdom. Please reach out again in just a moment.";
        $aiContent = $fallbackResponse;

        try {
            // If context is empty, Samuel should respond normally without mentioning missing data
            if (empty($context)) {
                $messages[] = ['role' => 'system', 'content' => "Just respond warmly as Samuel. No specific verses were found for this greeting, so simply offer a gentle, biblically-inspired word from your heart."];
            }

            $response = $ollama->chat($messages, $model);

            if (isset($response['message']['content'])) {
                $aiContent = $response['message']['content'];
            } elseif (is_string($response)) {
                $aiContent = $response;
            } elseif (isset($response['content'])) {
                $aiContent = $response['content'];
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Ollama Chat failed: " . $e->getMessage());
            // Circuit breaker has been cleared, but if it fails again, we use the fallback
            $aiContent = $fallbackResponse;
        }

        if ($isNewDonor) {
            Auth::user()->update(['donor_thanked_at' => now()]);
        }

        // 5. Systematic Footnotes
        $aiContent = $this->attachSystematicFootnotes($aiContent, $bibleVersion);

        $aiMessage = [
            'role' => 'assistant',
            'content' => $aiContent,
            'citations' => $citations, // Now an array of objects
        ];

        // 4. Save to Database if Auth
        if (Auth::check()) {
            $conversation = null;
            if ($request->conversation_id) {
                $conversation = Conversation::find($request->conversation_id);
            }

            if (!$conversation) {
                $conversation = Conversation::create([
                    'user_id' => Auth::id(),
                    'title' => 'New Conversation',
                    'messages' => [],
                ]);

                // If history was passed (likely from guest state), save it
                if ($request->history && is_array($request->history)) {
                    $conversation->update(['messages' => $request->history]);
                }

                // Dispatch job to generate title
                GenerateConversationTitle::dispatch($conversation->id, $userMessage);
            }

            $currentMessages = $conversation->messages;
            $currentMessages[] = ['role' => 'user', 'content' => $userMessage];
            $currentMessages[] = $aiMessage;
            $conversation->update(['messages' => $currentMessages]);
        }

        // 5. Broadcast
        broadcast(new MessageSent($aiMessage, $request->conversation_id))->toOthers();

        return response()->json([
            'message' => $aiMessage,
            'conversation_id' => Auth::check() ? $conversation->id : null,
            'conversation_title' => Auth::check() ? $conversation->title : null,
        ]);
    }

    public function show($id)
    {
        $conversation = Conversation::where('user_id', (string) Auth::id())->findOrFail($id);

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

    public function updateModel(Request $request)
    {
        $request->validate([
            'model' => 'required|string',
        ]);

        try {
            $user = Auth::user();
            if ($user) {
                $user->update([
                    'preferred_model' => $request->model,
                ]);
                \Illuminate\Support\Facades\Log::info("User {$user->id} updated preferred model to {$request->model}");
            }
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to update preferred model for user " . Auth::id() . ": " . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function attachSystematicFootnotes($content, $version)
    {
        // Regex to match "Book Chapter:Verse" (e.g., "John 3:16", "1 John 1:9", "Genesis 1:1-3")
        $pattern = '/((?:[1-3]\s?)?[A-Z][a-z]+\.?)\s+(\d+):(\d+)(?:-(\d+))?/';

        if (!preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            return $content;
        }

        $footnotes = [];
        foreach ($matches as $match) {
            $book = $match[1];
            $chapter = (int) $match[2];
            $verseStart = (int) $match[3];
            $verseEnd = isset($match[4]) ? (int) $match[4] : $verseStart;

            $reference = "{$book} {$chapter}:{$verseStart}" . ($verseStart != $verseEnd ? "-{$verseEnd}" : "");

            // Query for the verse text
            // For ranges, we'll fetch all and join
            $verses = Verse::where('version', $version)
                ->where('book', 'like', "{$book}%") // Loose match for abbreviations
                ->where('chapter', $chapter)
                ->whereBetween('verse', [$verseStart, $verseEnd])
                ->orderBy('verse')
                ->get();

            if ($verses->count() > 0) {
                $text = $verses->pluck('text')->join(' ');
                $fullRef = $verses->first()->full_reference;
                if ($verseStart != $verseEnd) {
                    $fullRef = "{$book} {$chapter}:{$verseStart}-{$verseEnd}";
                }
                $footnotes[] = "{$fullRef}: {$text} ({$version})";
            }
        }

        if (empty($footnotes)) {
            return $content;
        }

        // Deduplicate footnotes
        $footnotes = array_unique($footnotes);

        $footer = "\n\n---\n\n";
        foreach ($footnotes as $index => $note) {
            $footer .= "• " . $note . "\n";
        }

        return $content . $footer;
    }
}
