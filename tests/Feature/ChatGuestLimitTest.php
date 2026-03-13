<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ChatGuestLimitTest extends TestCase
{
    /**
     * Test that guests are limited to 5 messages.
     */
    public function test_guest_is_limited_to_5_messages(): void
    {
        // Simulate 5 guest messages
        for ($i = 0; $i < 5; $i++) {
            $response = $this->post(route('chat.send'), [
                'message' => 'Hello ' . $i,
            ]);
            $response->assertStatus(200);
        }

        // The 6th message should fail with 403
        $response = $this->post(route('chat.send'), [
            'message' => 'Hello 6',
        ]);
        $response->assertStatus(403);
        $response->assertJson(['error' => 'Limit reached. Please login to continue.']);
    }

    /**
     * Test that history passed in request is saved to new conversation for auth user.
     */
    public function test_history_is_imported_for_new_conversation(): void
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $history = [
            ['role' => 'user', 'content' => 'Guest msg 1'],
            ['role' => 'assistant', 'content' => 'AI response 1'],
        ];

        $response = $this->post(route('chat.send'), [
            'message' => 'Follow up msg',
            'history' => $history,
        ]);

        $response->assertStatus(200);
        $conversationId = $response->json('conversation_id');
        $this->assertNotNull($conversationId);

        $conversation = \App\Models\Conversation::find($conversationId);
        
        // Should contain imported history + new user msg + new AI msg
        $this->assertCount(4, $conversation->messages);
        $this->assertEquals('Guest msg 1', $conversation->messages[0]['content']);
        $this->assertEquals('Follow up msg', $conversation->messages[2]['content']);
    }
}
