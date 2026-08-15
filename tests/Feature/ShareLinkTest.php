<?php

namespace EasyAI\LaravelAI\Tests\Feature;

use EasyAI\LaravelAI\Chat\Models\ChatMessage;
use EasyAI\LaravelAI\Chat\Models\ChatSession;
use EasyAI\LaravelAI\Chat\Support\ChatIdentity;
use EasyAI\LaravelAI\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The public share-link feature (AIChatController::shareLink()/unshareLink()/
 * publicShare()) — a session stays exactly as private as it's always been
 * until its owner explicitly generates a link, at which point GET
 * ai-chat/s/{token} must render it with genuinely no auth/ownership check
 * at all (that route is registered outside config('ai.chat.middleware') in
 * ChatServiceProvider specifically so AI_CHAT_MIDDLEWARE=auth can't lock
 * it out — not re-verified here, that's route-registration wiring rather
 * than request-scoped behavior).
 */
class ShareLinkTest extends TestCase
{
    use RefreshDatabase;

    private function withGuestCookie(string $token): array
    {
        return [ChatIdentity::COOKIE_NAME => $token];
    }

    public function test_generating_a_share_link_requires_ownership(): void
    {
        $session = ChatSession::create(['title' => 'Not yours', 'guest_token' => str_repeat('a', 40)]);

        $response = $this->withCredentials()->withCookies($this->withGuestCookie(str_repeat('b', 40)))
            ->postJson("/ai-chat/api/sessions/{$session->id}/share");

        $response->assertStatus(403);
        $this->assertDatabaseHas('ai_chat_sessions', ['id' => $session->id, 'share_token' => null]);
    }

    public function test_the_owner_can_generate_a_share_link_and_it_is_idempotent(): void
    {
        $session = ChatSession::create(['title' => 'Mine', 'guest_token' => str_repeat('a', 40)]);

        $response = $this->withCredentials()->withCookies($this->withGuestCookie(str_repeat('a', 40)))
            ->postJson("/ai-chat/api/sessions/{$session->id}/share");

        $response->assertOk();
        $url = $response->json('url');
        $this->assertNotEmpty($url);

        $token = $session->fresh()->share_token;
        $this->assertNotNull($token);
        $this->assertStringContainsString($token, $url);

        // A second click returns the exact same link, not a fresh one.
        $again = $this->withCredentials()->withCookies($this->withGuestCookie(str_repeat('a', 40)))
            ->postJson("/ai-chat/api/sessions/{$session->id}/share");
        $this->assertSame($token, $session->fresh()->share_token);
        $this->assertSame($url, $again->json('url'));
    }

    public function test_the_public_route_renders_the_conversation_with_no_auth_at_all(): void
    {
        $session = ChatSession::create(['title' => 'Shared chat', 'share_token' => str_repeat('x', 40)]);
        ChatMessage::create(['chat_session_id' => $session->id, 'role' => 'user', 'content' => 'What is Laravel?']);
        ChatMessage::create(['chat_session_id' => $session->id, 'role' => 'assistant', 'content' => 'A PHP framework.']);

        // A bare, unauthenticated GET — no cookies, no session, nothing.
        $response = $this->get('/ai-chat/s/' . str_repeat('x', 40));

        $response->assertOk();
        $response->assertSee('Shared chat');
        $response->assertSee('What is Laravel?', false);
        $response->assertSee('A PHP framework.', false);
    }

    public function test_an_unknown_share_token_404s(): void
    {
        $this->get('/ai-chat/s/' . str_repeat('z', 40))->assertNotFound();
    }

    public function test_revoking_clears_the_token_and_the_old_link_404s(): void
    {
        $session = ChatSession::create(['title' => 'Mine', 'guest_token' => str_repeat('a', 40), 'share_token' => str_repeat('x', 40)]);

        $response = $this->withCredentials()->withCookies($this->withGuestCookie(str_repeat('a', 40)))
            ->deleteJson("/ai-chat/api/sessions/{$session->id}/share");

        $response->assertOk();
        $this->assertDatabaseHas('ai_chat_sessions', ['id' => $session->id, 'share_token' => null]);
        $this->get('/ai-chat/s/' . str_repeat('x', 40))->assertNotFound();
    }

    public function test_revoking_a_share_link_requires_ownership_too(): void
    {
        $session = ChatSession::create(['title' => 'Not yours', 'guest_token' => str_repeat('a', 40), 'share_token' => str_repeat('x', 40)]);

        $response = $this->withCredentials()->withCookies($this->withGuestCookie(str_repeat('b', 40)))
            ->deleteJson("/ai-chat/api/sessions/{$session->id}/share");

        $response->assertStatus(403);
        $this->assertDatabaseHas('ai_chat_sessions', ['id' => $session->id, 'share_token' => str_repeat('x', 40)]);
    }

    public function test_the_public_view_has_no_send_box_export_or_feedback_controls(): void
    {
        $session = ChatSession::create(['title' => 'Shared chat', 'share_token' => str_repeat('x', 40)]);
        ChatMessage::create(['chat_session_id' => $session->id, 'role' => 'assistant', 'content' => 'Hello!']);

        $html = $this->get('/ai-chat/s/' . str_repeat('x', 40))->assertOk()->getContent();

        // No interactive/authenticated chat-page machinery leaked into the public page.
        $this->assertStringNotContainsString('sendFeedback(', $html);
        $this->assertStringNotContainsString('exportServerSide(', $html);
        $this->assertStringNotContainsString('id="input"', $html);
        $this->assertStringNotContainsString('csrf-token', $html);
    }
}
