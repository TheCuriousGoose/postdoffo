<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Notifications\WorkspaceMemberAddedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inviting_an_existing_user_notifies_them(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->postJson(route('api.members.store', $workspace), [
                'email' => $invitee->email,
                'role' => 'editor',
            ])
            ->assertOk();

        $this->assertSame(1, $invitee->fresh()->notifications()->count());
        $this->assertSame(
            WorkspaceMemberAddedNotification::class,
            $invitee->fresh()->notifications()->first()->type,
        );
    }

    public function test_notifications_index_lists_notifications_with_unread_count(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)->postJson(route('api.members.store', $workspace), [
            'email' => $invitee->email,
            'role' => 'editor',
        ])->assertOk();

        $this->actingAs($invitee)
            ->getJson(route('api.notifications.index'))
            ->assertOk()
            ->assertJsonCount(1, 'notifications')
            ->assertJsonPath('unread_count', 1);
    }

    public function test_user_can_mark_a_notification_as_read(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)->postJson(route('api.members.store', $workspace), [
            'email' => $invitee->email,
            'role' => 'editor',
        ])->assertOk();

        $notification = $invitee->fresh()->notifications()->sole();

        $this->actingAs($invitee)
            ->postJson(route('api.notifications.read', $notification->id))
            ->assertNoContent();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $stranger = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)->postJson(route('api.members.store', $workspace), [
            'email' => $invitee->email,
            'role' => 'editor',
        ])->assertOk();

        $notification = $invitee->fresh()->notifications()->sole();

        $this->actingAs($stranger)
            ->postJson(route('api.notifications.read', $notification->id))
            ->assertForbidden();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $workspaceA = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspaceB = Workspace::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)->postJson(route('api.members.store', $workspaceA), [
            'email' => $invitee->email,
            'role' => 'editor',
        ])->assertOk();
        $this->actingAs($owner)->postJson(route('api.members.store', $workspaceB), [
            'email' => $invitee->email,
            'role' => 'viewer',
        ])->assertOk();

        $this->actingAs($invitee)
            ->postJson(route('api.notifications.read-all'))
            ->assertNoContent();

        $this->assertSame(0, $invitee->fresh()->unreadNotifications()->count());
    }
}
