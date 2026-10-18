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
use Filagora\Forums\Enums\PollStatus;
use Filagora\Forums\Models\Forum;
use Filagora\Forums\Models\ForumGroup;
use Filagora\Forums\Models\Poll;
use Filagora\Forums\Models\PollOption;
use Filagora\Forums\Models\PollQuestion;
use Filagora\Forums\Models\Post;
use Filagora\Forums\Models\PostAttachment;
use Filagora\Forums\Models\PostMention;
use Filagora\Forums\Models\PostReport;
use Filagora\Forums\Models\Reaction;
use Filagora\Forums\Models\SiteNotification;
use Filagora\Forums\Models\Thread;
use Filagora\Forums\Models\ThreadRead;
use Filagora\Forums\Models\ThreadSubscription;
use Filagora\Logs\Models\EmailLog;
use Filagora\Memberships\Models\BillingEventLog;
use Filagora\Memberships\Models\MembershipPlan;
use Filagora\Memberships\Services\Memberships;
use Filagora\Messages\Models\PrivateConversation;
use Filagora\Messages\Models\PrivateConversationParticipant;
use Filagora\Messages\Models\PrivateMessage;
use Filagora\Messages\Services\Mailbox;
use Filagora\Push\Services\PushSubscriptions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

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
            ['name' => 'Demo Admin', 'username' => 'admin', 'password' => 'password'],
        );
        $admin->forceFill(['email_verified_at' => now()])->save();

        UserEntitlementGrant::query()->firstOrCreate(
            ['user_id' => $admin->id, 'entitlement_key' => 'filagora.*', 'grant_type' => 'system'],
            ['reason' => 'Demo staff'],
        );

        $member = User::query()->updateOrCreate(
            ['email' => 'member@example.test'],
            ['name' => 'Demo Member', 'username' => 'member', 'password' => 'password'],
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
        $this->seedMemberships($member);
    }

    /**
     * A forum with an approved thread (one reply of the member's, reported), and a thread
     * waiting for approval, so the moderation queue and reports screens have something to show.
     */
    /**
     * A poll on the welcome thread, a like on its first post, and the member following it, so the
     * add-on tables have rows (a digest row appears when the daily digest is queued).
     */
    private function seedForumExtras(Thread $welcome, User $admin, User $member): void
    {
        if (! Poll::query()->where('thread_id', $welcome->id)->exists()) {
            $poll = Poll::query()->create(['thread_id' => $welcome->id, 'created_by_id' => $admin->id, 'status' => PollStatus::OPEN]);
            $question = PollQuestion::query()->create(['poll_id' => $poll->id, 'prompt' => 'What should the demo show next?', 'sort_order' => 0]);

            foreach (['Polls', 'Private messages', 'Search'] as $position => $label) {
                PollOption::query()->create(['poll_question_id' => $question->id, 'label' => $label, 'sort_order' => $position]);
            }
        }

        $first = $welcome->posts()->orderBy('id')->first();

        if ($first !== null) {
            Reaction::query()->firstOrCreate(['post_id' => $first->id, 'user_id' => $member->id, 'type' => 'like']);
        }

        ThreadSubscription::query()->firstOrCreate(['thread_id' => $welcome->id, 'user_id' => $member->id]);
    }

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

        $this->seedForumExtras($welcome, $admin, $member);
        $this->seedMoreForumExtras($welcome, $admin, $member);
        $this->seedMessages($admin, $member);

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

    /**
     * One private conversation from the admin to the member, unread, written straight to the tables
     * so seeding sends no email.
     */
    private function seedMessages(User $admin, User $member): void
    {
        if (PrivateConversation::query()->exists()) {
            return;
        }

        $conversation = PrivateConversation::query()->create(['created_by_id' => $admin->id, 'subject' => 'Welcome to the demo']);
        PrivateMessage::query()->create(['conversation_id' => $conversation->id, 'sender_id' => $admin->id, 'body' => 'Reply to this to try private messages.', 'content_format' => 'markdown']);
        $inboxes = app(Mailbox::class)->ensureSystemFolders([$admin->id, $member->id]);

        PrivateConversationParticipant::query()->create(['conversation_id' => $conversation->id, 'user_id' => $admin->id, 'folder_id' => $inboxes[$admin->id], 'is_read' => true, 'last_read_at' => now()]);
        PrivateConversationParticipant::query()->create(['conversation_id' => $conversation->id, 'user_id' => $member->id, 'folder_id' => $inboxes[$member->id], 'is_read' => false]);
    }

    /**
     * A mention of the member in the admin's welcome post, a stored file on it, the admin's read
     * marker for the thread, and a browser subscribed to push for the member. Written straight to
     * the tables (and the storage disk), so seeding sends no email or push.
     */
    private function seedMoreForumExtras(Thread $welcome, User $admin, User $member): void
    {
        $first = $welcome->posts()->orderBy('id')->first();

        if ($first === null) {
            return;
        }

        $first->forceFill(['body' => 'Say hello, @member.'])->saveQuietly();
        PostMention::query()->firstOrCreate(['post_id' => $first->id, 'user_id' => $member->id], ['notified_at' => now()]);

        if (! PostAttachment::query()->where('post_id', $first->id)->exists()) {
            Storage::disk('local')->put('attachments/demo/welcome.txt', 'Welcome to the demo.');
            PostAttachment::query()->create([
                'post_id' => $first->id,
                'thread_id' => $welcome->id,
                'uploader_id' => $admin->id,
                'original_filename' => 'welcome.txt',
                'extension' => 'txt',
                'mime_type' => 'text/plain',
                'filesize_bytes' => 20,
                'storage_disk' => 'local',
                'storage_path' => 'attachments/demo/welcome.txt',
            ]);
        }

        ThreadRead::query()->firstOrCreate(['user_id' => $admin->id, 'thread_id' => $welcome->id], ['last_read_at' => now()]);

        if (! app(PushSubscriptions::class)->hasAny($member)) {
            app(PushSubscriptions::class)->store($member, 'https://fcm.googleapis.com/fcm/send/demo-member', 'demo-public-key', 'demo-auth-token', null, 'Demo browser');
        }
    }

    /**
     * A free plan the member holds, a paid PayPal plan with a price in two currencies (not
     * connected to a PayPal account, so it cannot be bought), and a failed billing event, so
     * the plans screen, the billing log and its navigation badge have something to show.
     */
    private function seedMemberships(User $member): void
    {
        $free = MembershipPlan::query()->updateOrCreate(
            ['code' => 'supporter-free'],
            ['name' => 'Supporter (free)', 'description' => 'Free membership.', 'is_active' => true, 'is_free' => true, 'entitlements' => ['demo.supporter'], 'feature_list' => ['The supporter badge'], 'sort_order' => 1],
        );

        $gold = MembershipPlan::query()->updateOrCreate(
            ['code' => 'supporter-gold'],
            ['name' => 'Supporter (gold)', 'description' => 'Paid membership, billed monthly.', 'is_active' => true, 'is_free' => false, 'billing_interval' => 'monthly', 'provider' => 'paypal', 'entitlements' => ['demo.supporter', 'demo.gold'], 'feature_list' => ['Everything in free', 'A gold badge'], 'sort_order' => 2],
        );

        foreach (['USD' => ['5.00', true], 'EUR' => ['4.50', false]] as $currency => [$amount, $default]) {
            $gold->prices()->updateOrCreate(['currency' => $currency], ['amount' => $amount, 'is_default' => $default]);
        }

        app(Memberships::class)->activateLocal($member, $free);

        BillingEventLog::query()->firstOrCreate(
            ['provider' => 'paypal', 'event_id' => 'WH-DEMO-FAILED'],
            [
                'event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED',
                'processing_status' => 'failed',
                'error_message' => 'PayPal webhook signature verification failed.',
                'payload' => ['id' => 'WH-DEMO-FAILED', 'event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED', 'resource' => ['id' => 'I-DEMO', 'status' => 'ACTIVE']],
            ],
        );
    }
}
