<?php

namespace App\Console\Commands;

use App\Models\Verse;
use App\Services\OllamaService;
use App\Services\VectorStoreService;
use App\Jobs\VectorizeVerseJob;
use Illuminate\Console\Command;

class VectorizeVerses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bible:vectorize {--bible-version=BSB} {--chunk=100} {--queue : Dispatch to queue instead of running sequentially}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Vectorize Bible verses and store in ChromaDB';

    /**
     * Execute the console command.
     */
    public function handle(OllamaService $ollama, VectorStoreService $vectorStore)
    {
        $version = $this->option('bible-version');
        $chunkSize = (int) $this->option('chunk');
        $useQueue = $this->option('queue');

        $this->info("Fetching verses for {$version}...");

        $total = Verse::where('version', $version)->count();

        if ($total === 0) {
            $this->error("No verses found for version: {$version}");
            return;
        }

        $this->info("Total verses to process: {$total}");

        if ($useQueue) {
            $this->info("Dispatching jobs to queue for {$version}...");
            
            Verse::where('version', $version)
                ->chunk($chunkSize, function ($verses) use ($version) {
                    $batchVerses = [];
                    $batchMetadatas = [];
                    $batchIds = [];

                    foreach ($verses as $v) {
                        $batchVerses[] = $v->text;
                        $batchMetadatas[] = [
                            'book' => $v->book,
                            'chapter' => $v->chapter,
                            'verse' => $v->verse,
                            'version' => $v->version,
                            'full_reference' => $v->full_reference,
                        ];
                        $batchIds[] = (string) $v->_id;
                    }

                    VectorizeVerseJob::dispatch($version, $batchVerses, $batchMetadatas, $batchIds);
                });

            $this->info("All jobs for {$version} dispatched successfully!");
            return;
        }

        $this->info("Starting sequential vectorization for {$version}...");
        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();

        Verse::where('version', $version)
            ->chunk($chunkSize, function ($verses) use ($ollama, $vectorStore, $progressBar, $version) {
                $batchVerses = [];
                $batchMetadatas = [];
                $batchIds = [];

                foreach ($verses as $v) {
                    $batchVerses[] = $v->text;
                    $batchMetadatas[] = [
                        'book' => $v->book,
                        'chapter' => $v->chapter,
                        'verse' => $v->verse,
                        'version' => $v->version,
                        'full_reference' => $v->full_reference,
                    ];
                    $batchIds[] = (string) $v->_id;
                }

                if (!empty($batchVerses)) {
                    try {
                        $embeddings = $ollama->getEmbeddings($batchVerses);
                        if ($embeddings) {
                            $vectorStore->addDocuments('bible_verses', $batchVerses, $batchMetadatas, $batchIds, $embeddings);
                        }
                    } catch (\Exception $e) {
                        $this->error("\nError adding to ChromaDB: " . $e->getMessage());
                    }
                    $progressBar->advance(count($batchVerses));
                }
            });

        $progressBar->finish();
        $this->info("\nVectorization complete for {$version}!");
    }
}
