<?php

namespace App\Jobs;

use App\Services\VectorStoreService;
use App\Services\AiServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class VectorizeVerseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 600;

    protected $bibleVersion;
    protected $verses;
    protected $metadatas;
    protected $ids;

    /**
     * Create a new job instance.
     */
    public function __construct(string $bibleVersion, array $verses, array $metadatas, array $ids)
    {
        $this->bibleVersion = $bibleVersion;
        $this->verses = $verses;
        $this->metadatas = $metadatas;
        $this->ids = $ids;
    }

    /**
     * Execute the job.
     */
    public function handle(AiServiceInterface $aiService, VectorStoreService $vectorStore): void
    {
        try {
            Log::info("Vectorizing batch of " . count($this->ids) . " verses for {$this->bibleVersion}");

            $embeddings = $aiService->getEmbeddings($this->verses);

            if (empty($embeddings)) {
                throw new \Exception("Failed to generate embeddings for batch in {$this->bibleVersion}");
            }

            $vectorStore->addDocuments('bible_verses', $this->verses, $this->metadatas, $this->ids, $embeddings);

            Log::info("Successfully vectorized and stored batch for {$this->bibleVersion}");
        } catch (\Exception $e) {
            Log::error("Error in VectorizeVerseJob for {$this->bibleVersion}: " . $e->getMessage());
            throw $e;
        }
    }
}
