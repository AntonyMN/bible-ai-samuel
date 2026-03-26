<?php

namespace App\Console\Commands;

use App\Models\Verse;
use App\Services\AiServiceInterface;
use App\Services\VectorStoreService;
use Illuminate\Console\Command;

class IngestBibleData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bible:ingest {--bible-version= : The version to ingest (KJV, ASV, BBE)} {--dry-run} {--skip-embedding : Skip vectorization (embedding) part to avoid GPU costs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ingest Bible data from JSON sources into MongoDB and ChromaDB';

    protected $sources = [
        'BSB' => 'en-bsb',
        'KJV' => 'en-kjv',
        'ASV' => 'en-asv',
        'WEB' => 'en-web',
    ];

    protected $books = [
        'genesis', 'exodus', 'leviticus', 'numbers', 'deuteronomy', 'joshua', 'judges', 'ruth', '1samuel', '2samuel',
        '1kings', '2kings', '1chronicles', '2chronicles', 'ezra', 'nehemiah', 'esther', 'job', 'psalms', 'proverbs',
        'ecclesiastes', 'songofsolomon', 'isaiah', 'jeremiah', 'lamentations', 'ezekiel', 'daniel', 'hosea', 'joel',
        'amos', 'obadiah', 'jonah', 'micah', 'nahum', 'habakkuk', 'zephaniah', 'haggai', 'zechariah', 'malachi',
        'matthew', 'mark', 'luke', 'john', 'acts', 'romans', '1corinthians', '2corinthians', 'galatians', 'ephesians',
        'philippians', 'colossians', '1thessalonians', '2thessalonians', '1timothy', '2timothy', 'titus', 'philemon',
        'hebrews', 'james', '1peter', '2peter', '1john', '2john', '3john', 'jude', 'revelation'
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $aiService = app(AiServiceInterface::class);
        $vectorStore = app(VectorStoreService::class);
        
        $this->info("Handle started for wldeh/bible-api source");
        $targetVersion = $this->option('bible-version');
        $dryRun = $this->option('dry-run');

        if (!$dryRun) {
            try {
                $vectorStore->createCollection('bible_verses');
                $this->info("ChromaDB collection created/verified.");
            } catch (\Exception $e) {
                $this->warn("ChromaDB connection failed. Vectorization may be skipped: " . $e->getMessage());
            }
        }

        $versionsToIngest = $targetVersion ? [$targetVersion => $this->sources[$targetVersion]] : $this->sources;

        foreach ($versionsToIngest as $internalVersion => $apiId) {
            $this->info("Processing Version: {$internalVersion} (API ID: {$apiId})");

            foreach ($this->books as $bookSlug) {
                // We'll iterate chapters until 404
                $chapter = 1;
                $hasMoreChapters = true;

                while ($hasMoreChapters) {
                    $chapterUrl = "https://raw.githubusercontent.com/wldeh/bible-api/main/bibles/{$apiId}/books/{$bookSlug}/chapters/{$chapter}.json";
                    
                    try {
                        $response = \Illuminate\Support\Facades\Http::get($chapterUrl);
                        
                        if ($response->status() === 404) {
                            $hasMoreChapters = false;
                            continue;
                        }

                        if (!$response->successful()) {
                            $this->error("Failed to fetch {$bookSlug} Ch {$chapter}. Status: " . $response->status());
                            $hasMoreChapters = false;
                            continue;
                        }

                        $data = $response->json();
                        if (!isset($data['data'])) {
                            $hasMoreChapters = false;
                            continue;
                        }

                        $this->info("Ingesting {$internalVersion} - {$bookSlug} Chapter {$chapter}...");

                        $batchVerses = [];
                        $batchEmbeddings = [];
                        $batchMetadatas = [];
                        $batchIds = [];
                        $textsToEmbed = [];

                        foreach ($data['data'] as $verseData) {
                            $bookName = $verseData['book'];
                            $chapterNum = (int)$verseData['chapter'];
                            $verseNum = (int)$verseData['verse'];
                            $text = $verseData['text'];
                            $fullReference = "{$bookName} {$chapterNum}:{$verseNum}";

                            if ($dryRun) {
                                $this->line("Would ingest: [{$internalVersion}] {$fullReference}: " . substr($text, 0, 50) . "...");
                                continue;
                            }

                            // Store in MongoDB
                            Verse::updateOrCreate([
                                'version' => $internalVersion,
                                'book' => $bookName,
                                'chapter' => $chapterNum,
                                'verse' => $verseNum,
                            ], [
                                'text' => $text,
                                'full_reference' => $fullReference,
                            ]);

                            // Prepare for Vectorization (Primary Version or Explicit)
                            if (!$this->option('skip-embedding') && ($internalVersion === 'BSB' || $targetVersion)) {
                                try {
                                    $embedding = $aiService->embed("{$fullReference} ({$internalVersion}): {$text}");
                                    
                                    if (!empty($embedding)) {
                                        $batchVerses[] = "{$fullReference} ({$internalVersion}): {$text}";
                                        $batchEmbeddings[] = $embedding;
                                        $batchMetadatas[] = [
                                            'book' => $bookName,
                                            'chapter' => $chapterNum,
                                            'verse' => $verseNum,
                                            'reference' => $fullReference,
                                            'version' => $internalVersion,
                                        ];
                                        $batchIds[] = "{$internalVersion}_{$bookName}_{$chapterNum}_{$verseNum}";
                                    }
                                } catch (\Exception $e) {
                                    // Skip embedding if AI service fails
                                }
                            }
                        }

                        if (!$dryRun && !empty($batchVerses)) {
                            try {
                                $vectorStore->addDocuments('bible_verses', $batchVerses, $batchMetadatas, $batchIds, $batchEmbeddings);
                            } catch (\Exception $e) {
                                // Quiet fail for Chroma
                            }
                        }

                        $chapter++;

                    } catch (\Exception $e) {
                        $this->error("Error in {$bookSlug} Ch {$chapter}: " . $e->getMessage());
                        $hasMoreChapters = false;
                    }
                }
            }
        }

        $this->info("Ingestion completed!");
        return 0;
    }
}
