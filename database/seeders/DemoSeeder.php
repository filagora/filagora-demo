<?php

namespace Database\Seeders;

use App\Models\User;
use ForumKit\Entitlements\Enums\EntitlementRequestStatus;
use ForumKit\Entitlements\Models\EntitlementDefinition;
use ForumKit\Entitlements\Models\EntitlementRequest;
use ForumKit\Entitlements\Models\UserEntitlementGrant;
use ForumKit\Forums\Models\Forum;
use ForumKit\Forums\Models\ForumGroup;
use ForumKit\Forums\Models\Post;
use ForumKit\Forums\Models\PostReport;
use ForumKit\Forums\Models\Thread;
use Illuminate\Database\Seeder;

/**
 * A staff user who holds every `forumkit.*` entitlement through one wildcard grant, a
 * plain member, and a pending request from that member, so every screen has something
 * to show. Local use only: the passwords are public.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@example.test'],
            ['name' => 'Demo Admin', 'password' => 'password'],
        );
        $admin->forceFill(['email_verified_at' => now()])->save();

        UserEntitlementGrant::query()->firstOrCreate(
            ['user_id' => $admin->id, 'entitlement_key' => 'forumkit.*', 'grant_type' => 'system'],
            ['reason' => 'Demo staff'],
        );

        $member = User::query()->updateOrCreate(
            ['email' => 'member@example.test'],
            ['name' => 'Demo Member', 'password' => 'password'],
        );
        $member->forceFill(['email_verified_at' => now()])->save();

        EntitlementDefinition::query()->firstOrCreate(
            ['key' => 'demo.early-access'],
            ['label' => 'Early access', 'is_requestable' => true, 'supports_wildcard_children' => false, 'namespace' => 'demo'],
        );

        EntitlementRequest::query()->firstOrCreate(
            ['user_id' => $member->id, 'entitlement_key' => 'demo.early-access'],
            ['status' => EntitlementRequestStatus::PENDING, 'request_reason' => 'I would like to try it.', 'requested_at' => now()],
        );

        $this->seedForum($admin, $member);
    }

    /**
     * A forum with an approved thread (one reply of the member's, reported), and a thread
     * waiting for approval, so the moderation queue and reports screens have something to show.
     */
    private function seedForum(User $admin, User $member): void
    {
        $group = ForumGroup::query()->firstOrCreate(['slug' => 'community'], ['name' => 'Community']);
        $forum = Forum::query()->firstOrCreate(
            ['slug' => 'general'],
            ['forum_group_id' => $group->id, 'name' => 'General discussion', 'description' => 'Anything goes.'],
        );

        $welcome = Thread::query()->firstOrCreate(
            ['slug' => 'welcome'],
            ['forum_id' => $forum->id, 'author_id' => $admin->id, 'title' => 'Welcome to the demo forum'],
        );

        if ($welcome->posts()->doesntExist()) {
            Post::query()->create(['thread_id' => $welcome->id, 'author_id' => $admin->id, 'body' => 'Say hello.', 'content_format' => 'markdown']);
            $reply = Post::query()->create(['thread_id' => $welcome->id, 'author_id' => $member->id, 'body' => 'Buy cheap watches here!', 'content_format' => 'markdown']);
            PostReport::query()->create(['post_id' => $reply->id, 'thread_id' => $welcome->id, 'reporter_id' => $admin->id, 'reason' => 'Looks like spam.']);
        }

        $pending = Thread::query()->firstOrCreate(
            ['slug' => 'first-post'],
            ['forum_id' => $forum->id, 'author_id' => $member->id, 'title' => 'My first post', 'moderation_status' => 'pending'],
        );

        if ($pending->posts()->doesntExist()) {
            Post::query()->create(['thread_id' => $pending->id, 'author_id' => $member->id, 'body' => 'Hi all, new here.', 'content_format' => 'markdown', 'moderation_status' => 'pending']);
        }
    }
}
