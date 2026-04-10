<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Memory;
use App\Services\MemoryService;
use App\Services\AiServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class MemoryEnhancementTest extends TestCase
{
    // MongoDB doesn't support standard RefreshDatabase easily without proper config, 
    // but we can manually clean up.
    
    protected function setUp(): void
    {
        parent::setUp();
        Memory::truncate();
    }

    public function test_memory_extraction_with_dates()
    {
        $user = User::factory()->create();
        $aiService = Mockery::mock(AiServiceInterface::class);
        
        // Mock AI response for extraction
        $aiService->shouldReceive('chat')
            ->once()
            ->andReturn([
                'message' => [
                    'content' => json_encode([
                        [
                            'content' => 'Attending a wedding',
                            'category' => 'events',
                            'importance' => 4,
                            'occurs_at' => '2026-04-11T12:00:00',
                            'is_one_off' => true,
                            'significance' => 'Best friend is getting married'
                        ]
                    ])
                ]
            ]);

        $service = new MemoryService($aiService);
        $service->extractMemories($user->id, "I have a wedding this Saturday");

        $this->assertEquals(1, Memory::where('user_id', $user->id)
            ->where('content', 'like', '%Attending a wedding%')
            ->count());
        
        $memory = Memory::where('user_id', $user->id)->first();
        $this->assertEquals('2026-04-11 12:00:00', $memory->occurs_at->format('Y-m-d H:i:s'));
    }

    public function test_context_injection_filtering()
    {
        $user = User::factory()->create();
        
        // Create an old memory mentioned recently
        Memory::create([
            'user_id' => $user->id,
            'content' => 'Old Memory',
            'category' => 'other',
            'importance' => 3,
            'last_mentioned_at' => now(),
            'is_completed' => false
        ]);

        // Create a new memory
        Memory::create([
            'user_id' => $user->id,
            'content' => 'New Memory',
            'category' => 'events',
            'importance' => 5,
            'probe_status' => 'none',
            'is_completed' => false
        ]);

        $service = new MemoryService(Mockery::mock(AiServiceInterface::class));
        $context = $service->getInjectedContext($user->id);

        $this->assertStringContainsString('New Memory', $context);
        $this->assertStringNotContainsString('Old Memory', $context);
        $this->assertStringContainsString('Needs probing', $context);
    }

    public function test_mention_tracking()
    {
        $user = User::factory()->create();
        $memory = Memory::create([
            'user_id' => $user->id,
            'content' => 'Wedding',
            'category' => 'events',
            'importance' => 5,
            'probe_status' => 'none',
            'is_completed' => false
        ]);

        $service = new MemoryService(Mockery::mock(AiServiceInterface::class));
        
        $aiResponse = "That's wonderful! I'll pray for your Wedding. When is it happening?";
        $service->markAsMentioned($user->id, $aiResponse);

        $memory->refresh();
        $this->assertEquals(1, $memory->mention_count);
        $this->assertNotNull($memory->last_mentioned_at);
        $this->assertEquals('probed', $memory->probe_status);
    }
}
