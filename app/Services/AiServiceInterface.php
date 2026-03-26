<?php

namespace App\Services;

interface AiServiceInterface
{
    /**
     * Chat with the AI
     * 
     * @param array $messages Standard messages array [['role' => '...', 'content' => '...']]
     * @param string|null $model Optional model override
     * @param array|null $stop Optional stop sequences
     * @return array
     */
    public function chat(array $messages, $model = null, $stop = null);

    /**
     * Get embedding for a single text
     */
    public function embed(string $text, $model = null);

    /**
     * Get embeddings for multiple texts
     */
    public function getEmbeddings(array $texts, $model = null);

    /**
     * List available models
     */
    public function listModels();
}
