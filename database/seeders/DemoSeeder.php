<?php

namespace Database\Seeders;

use App\Models\User;
use Filagora\Cms\Models\CmsPage;
use Filagora\Compliance\Models\CookieConsentCookie;
use Filagora\Compliance\Models\CookieConsentGroup;
use Filagora\Compliance\Models\CookiePolicyVersion;
use Filagora\Compliance\Models\PrivacyPolicyVersion;
use Filagora\Compliance\Models\PrivacyRequest;
use Filagora\Core\Models\Setting;
use Filagora\Entitlements\Enums\EntitlementRequestStatus;
use Filagora\Entitlements\Models\EntitlementDefinition;
use Filagora\Entitlements\Models\EntitlementRequest;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filagora\Forums\Models\Forum;
use Filagora\Forums\Models\ForumGroup;
use Filagora\Forums\Models\Post;
use Filagora\Forums\Models\PostReport;
use Filagora\Forums\Models\SiteNotification;
use Filagora\Forums\Models\Thread;
use Filagora\Logs\Models\EmailLog;
use Illuminate\Database\Seeder;

/**
 * A staff user who holds every `filagora.*` entitlement through one wildcard grant, a
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
            ['user_id' => $admin->id, 'entitlement_key' => 'filagora.*', 'grant_type' => 'system'],
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
        $this->seedEmailLog($member);
        $this->seedPages();
        $this->seedAnnouncement();
        $this->seedCompliance($member);
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

    /**
     * Email logging switched on, with one logged message so the Email log screen has a row.
     */
    private function seedEmailLog(User $member): void
    {
        Setting::query()->updateOrCreate(['key' => 'mail_log_sending'], ['value' => '1', 'value_type' => 'boolean']);

        EmailLog::query()->firstOrCreate(
            ['correlation_id' => 'demo-welcome'],
            [
                'status' => 'sent',
                'mailer' => 'log',
                'to' => [$member->email],
                'subject' => 'Welcome to the demo',
                'sent_at' => now(),
            ],
        );
    }

    /**
     * A published page with a hero block, and a draft page, so the Content screens have rows.
     */
    private function seedPages(): void
    {
        $welcome = CmsPage::query()->firstOrCreate(
            ['slug' => 'welcome'],
            ['title' => 'Welcome', 'status' => 'published', 'published_at' => now()],
        );

        $welcome->blocks()->firstOrCreate(
            ['type' => 'hero', 'name' => 'Welcome hero'],
            ['is_published' => true, 'sort_order' => 1, 'data' => ['title' => 'Welcome to the demo', 'body' => 'Edit me under Content → Pages.', 'cta_label' => 'Browse the forum', 'cta_url' => '/forums']],
        );

        CmsPage::query()->firstOrCreate(
            ['slug' => 'about'],
            ['title' => 'About', 'status' => 'draft'],
        );
    }

    /**
     * A published banner for signed-in users, so the Announcements screen has a row.
     */
    private function seedAnnouncement(): void
    {
        SiteNotification::query()->firstOrCreate(
            ['title' => 'Welcome to the demo'],
            [
                'body' => 'This banner is an **announcement**. Edit it under Forum → Announcements.',
                'status' => 'published',
                'audience' => 'authenticated_only',
                'severity' => 'info',
            ],
        );
    }

    /**
     * Cookie consent groups, one version of each policy, and a data export request waiting for
     * staff, so the Privacy and compliance screens have something to show.
     */
    private function seedCompliance(User $member): void
    {
        $necessary = CookieConsentGroup::query()->firstOrCreate(
            ['group_key' => 'necessary'],
            ['label' => 'Necessary', 'description' => 'Needed for the site to work.', 'is_required' => true, 'sort_order' => 1],
        );
        $analytics = CookieConsentGroup::query()->firstOrCreate(
            ['group_key' => 'analytics'],
            ['label' => 'Analytics', 'description' => 'Helps us see which pages are used.', 'sort_order' => 2],
        );

        CookieConsentCookie::query()->firstOrCreate(['cookie_name' => 'demo_session'], ['group_id' => $necessary->id, 'label' => 'Session', 'provider' => 'This site', 'retention_period' => 'Session']);
        CookieConsentCookie::query()->firstOrCreate(['cookie_name' => 'demo_stats'], ['group_id' => $analytics->id, 'label' => 'Statistics', 'provider' => 'This site', 'retention_period' => '1 year']);

        CookiePolicyVersion::query()->firstOrCreate(['version' => '2026.01.01.1'], ['label' => 'Cookie policy', 'is_active' => true]);
        PrivacyPolicyVersion::query()->firstOrCreate(['version' => '2026.01'], ['label' => 'Privacy policy', 'summary' => 'How the demo treats your data.', 'is_active' => true]);

        PrivacyRequest::query()->firstOrCreate(
            ['user_id' => $member->id, 'type' => 'export'],
            ['status' => 'received', 'request_details' => 'Please send me a copy of my data.', 'requested_at' => now()],
        );
    }
}
