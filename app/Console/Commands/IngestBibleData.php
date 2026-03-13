<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class IngestBibleData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bible:ingest {--bible-version= : The version to ingest (KJV, ASV, BBE)} {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ingest Bible data from JSON sources into MongoDB and ChromaDB';

    protected $sources = [
        'KJV' => 'https://raw.githubusercontent.com/thiagobodruk/bible/master/json/en_kjv.json',
        'ASV' => 'https://raw.githubusercontent.com/thiagobodruk/bible/master/json/en_asv.json',
        'BBE' => 'https://raw.githubusercontent.com/thiagobodruk/bible/master/json/en_bbe.json',
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $ollama = app(\App\Services\OllamaService::class);
        $vectorStore = app(\App\Services\VectorStoreService::class);
        
        $this->info("Handle started");
        $targetVersion = $this->option('bible-version');
        $dryRun = $this->option('dry-run');

        if (!$dryRun) {
            try {
                $vectorStore->createCollection('bible_verses');
                $this->info("ChromaDB collection created/verified.");
            } catch (\Exception $e) {
                $this->warn("ChromaDB connection failed. Vectorization will be skipped: " . $e->getMessage());
            }
        }

        $versionsToIngest = $targetVersion ? [$targetVersion => $this->sources[$targetVersion]] : $this->sources;

        foreach ($versionsToIngest as $version => $url) {
            $this->info("Fetching {$version} from {$url}...");
            try {
                $response = \Illuminate\Support\Facades\Http::get($url);
                
                if (!$response->successful()) {
                    $this->error("Failed to fetch {$version}. Status: " . $response->status());
                    continue;
                }
            } catch (\Exception $e) {
                $this->error("HTTP error fetching {$version}: " . $e->getMessage());
                continue;
            }

            $this->info("Response received. Length: " . strlen($response->body()));
            
            $body = $response->body();
            // Remove BOM if present
            $body = preg_replace('/^[\x00-\x1F\x80-\xFF]/', '', $body);
            // More robust BOM removal
            $body = ltrim($body, "\xEF\xBB\xBF");
            
            $data = json_decode($body, true);

            if (is_null($data)) {
                $this->error("JSON decoding failed for {$version}");
                continue;
            }

            foreach ($data as $bookData) {
                $bookName = $bookData['name'];
                $this->info("Processing {$version} - {$bookName}");

                $verseGlobalCount = 0;
                foreach ($bookData['chapters'] as $chapterIndex => $verses) {
                    $chapterNum = $chapterIndex + 1;
                    
                    $batchVerses = [];
                    $batchEmbeddings = [];
                    $batchMetadatas = [];
                    $batchIds = [];

                    foreach ($verses as $verseIndex => $text) {
                        $verseNum = $verseIndex + 1;
                        $fullReference = "{$bookName} {$chapterNum}:{$verseNum}";

                        if ($dryRun) {
                            $this->line("Would ingest: [{$version}] {$fullReference}: " . substr($text, 0, 50) . "...");
                            continue;
                        }

                        // Store in MongoDB
                        \App\Models\Verse::updateOrCreate([
                            'version' => $version,
                            'book' => $bookName,
                            'chapter' => $chapterNum,
                            'verse' => $verseNum,
                        ], [
                            'text' => $text,
                            'full_reference' => $fullReference,
                        ]);

                        $verseGlobalCount++;
                        if ($verseGlobalCount % 100 === 0) {
                            $this->info("Stored $verseGlobalCount verses...");
                        }

                        // For demo purposes, we vectorize only KJV or if explicitly selected
                        if ($version === 'KJV' || $targetVersion) {
                            try {
                                $embedding = $ollama->embed("{$fullReference} ({$version}): {$text}");
                                
                                if (!empty($embedding)) {
                                    $batchVerses[] = "{$fullReference} ({$version}): {$text}";
                                    $batchEmbeddings[] = $embedding;
                                    $batchMetadatas[] = [
                                        'version' => $version,
                                        'book' => $bookName,
                                        'chapter' => $chapterNum,
                                        'verse' => $verseNum,
                                        'reference' => $fullReference,
                                    ];
                                    $batchIds[] = "{$version}_{$bookName}_{$chapterNum}_{$verseNum}";
                                }
                            } catch (\Exception $e) {
                                // Skip embedding if ollama/chroma fails
                            }
                        }
                    }

                    if (!$dryRun && !empty($batchVerses)) {
                        try {
                            $vectorStore->addDocuments('bible_verses', $batchVerses, $batchMetadatas, $batchIds, $batchEmbeddings);
                        } catch (\Exception $e) {
                            $this->warn("Failed to add to ChromaDB: " . $e->getMessage());
                        }
                    }
                }
            }
        }

        $this->info("Ingestion completed!");
        return 0;
    }
}
