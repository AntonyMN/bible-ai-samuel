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
                    if (str_contains($m['name'], 'nomic-embed-text')) continue;
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
                ->map(function($conv) {
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
        
        // Guest limit check
        if (!Auth::check()) {
            $count = session()->get('guest_messages_count', 0);
            if ($count >= 5) {
                return response()->json(['error' => 'Limit reached. Please login to continue.'], 403);
            }
            session()->put('guest_messages_count', $count + 1);
        }

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
        
        $systemPrompt = "You are 'Samuel', a warm, humble, and encouraging Christian brother. 
        Your goal is to offer advice, comfort, admonitions, and commentary based STRICTLY on the Holy Bible.
        
        CRITICAL RULES:
        1. Tone: Friendly, conversational, and brotherly. Address the user as '{$userName}'.
        2. Version Adherence: You must ONLY use the '{$bibleVersion}' version for any scripture you quote or allude to.
        3. STRICT Context: You have been provided with specific Bible verses in the 'Context' section below. You MUST use these verses as your primary source of truth. 
        4. No Hallucinated Versions: Do NOT use NIV, NKJV, or any other version unless it is explicitly specified as the 'Current Bible Version Preference'.
        5. Citations: Provide citations (Book Chapter:Verse Version) at the end of your response or as footnotes.
        
        Current Bible Version Preference: {$bibleVersion}
        
        Context:
        {$context}";

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
        $aiContent = "I am sorry, I couldn't find an answer in the word right now. Please try again or rephrase your question.";
        
        try {
            $response = $ollama->chat($messages, $model);
            if (isset($response['message']['content'])) {
                $aiContent = $response['message']['content'];
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Ollama Chat failed: " . $e->getMessage());
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

        $user = Auth::user();
        if ($user) {
            $user->update([
                'bible_version' => $request->bible_version,
            ]);
        }

        return response()->json(['success' => true]);
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
            $chapter = (int)$match[2];
            $verseStart = (int)$match[3];
            $verseEnd = isset($match[4]) ? (int)$match[4] : $verseStart;

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
