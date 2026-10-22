<?php

use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Filagora\Cms\Filament\Resources\CmsBlocks\CmsBlockResource;
use Filagora\Cms\Filament\Resources\CmsMediaAssets\CmsMediaAssetResource;
use Filagora\Cms\Filament\Resources\CmsNavigations\CmsNavigationResource;
use Filagora\Cms\Filament\Resources\CmsPages\CmsPageResource;
use Filagora\Cms\Models\CmsPage;
use Filagora\Cms\Search\CmsSearch;
use Filagora\Compliance\Filament\Resources\CookieConsentGroups\CookieConsentGroupResource;
use Filagora\Compliance\Filament\Resources\CookiePolicyVersions\CookiePolicyVersionResource;
use Filagora\Compliance\Filament\Resources\PrivacyOperationAudits\PrivacyOperationAuditResource;
use Filagora\Compliance\Filament\Resources\PrivacyPolicyVersions\PrivacyPolicyVersionResource;
use Filagora\Compliance\Filament\Resources\PrivacyRequests\Pages\ViewPrivacyRequest;
use Filagora\Compliance\Filament\Resources\PrivacyRequests\PrivacyRequestResource;
use Filagora\Compliance\Models\PrivacyRequest;
use Filagora\Compliance\Services\CookieConsent;
use Filagora\Compliance\Services\PolicyAcceptance;
use Filagora\Core\Filament\Resources\Settings\SettingResource;
use Filagora\Entitlements\Enums\EntitlementRequestStatus;
use Filagora\Entitlements\Filament\RelationManagers\UserEntitlementRequestsRelationManager;
use Filagora\Entitlements\Filament\RelationManagers\UserEntitlementsRelationManager;
use Filagora\Entitlements\Filament\Resources\DefaultEntitlementGrants\DefaultEntitlementGrantResource;
use Filagora\Entitlements\Filament\Resources\EntitlementDefinitions\EntitlementDefinitionResource;
use Filagora\Entitlements\Filament\Resources\EntitlementRequests\EntitlementRequestResource;
use Filagora\Entitlements\Filament\Resources\EntitlementRequests\Pages\ViewEntitlementRequest;
use Filagora\Entitlements\Filament\Resources\UserEntitlementGrants\UserEntitlementGrantResource;
use Filagora\Entitlements\Models\EntitlementRequest;
use Filagora\Entitlements\Services\EntitlementResolver;
use Filagora\Forums\Contracts\SubscriptionPreferences;
use Filagora\Forums\Filament\Pages\ModerationQueue;
use Filagora\Forums\Filament\Resources\ForumGroups\ForumGroupResource;
use Filagora\Forums\Filament\Resources\Forums\ForumResource;
use Filagora\Forums\Filament\Resources\PostReports\PostReportResource;
use Filagora\Forums\Filament\Resources\Posts\PostResource;
use Filagora\Forums\Filament\Resources\SiteNotifications\SiteNotificationResource;
use Filagora\Forums\Filament\Resources\Threads\ThreadResource;
use Filagora\Forums\Models\Poll;
use Filagora\Forums\Models\PostAttachment;
use Filagora\Forums\Models\PostMention;
use Filagora\Forums\Models\PostReport;
use Filagora\Forums\Models\Reaction;
use Filagora\Forums\Models\SiteNotification;
use Filagora\Forums\Models\Thread;
use Filagora\Forums\Search\ForumSearch;
use Filagora\Forums\Search\ForumSearchCriteria;
use Filagora\Forums\Services\ActiveAnnouncements;
use Filagora\Forums\Services\AnnouncementDismissals;
use Filagora\Forums\Services\Attachments;
use Filagora\Forums\Services\Mentions;
use Filagora\Forums\Services\Polls;
use Filagora\Forums\Services\Reactions;
use Filagora\Forums\Services\ReadTracking;
use Filagora\Forums\Services\Subscriptions;
use Filagora\Logs\Filament\Resources\EmailLogs\EmailLogResource;
use Filagora\Logs\Models\EmailLog;
use Filagora\Memberships\Filament\RelationManagers\UserMembershipsRelationManager;
use Filagora\Memberships\Filament\Resources\BillingEvents\BillingEventResource;
use Filagora\Memberships\Filament\Resources\MembershipPlans\MembershipPlanResource;
use Filagora\Memberships\Models\MembershipPlan;
use Filagora\Memberships\Models\UserMembership;
use Filagora\Memberships\Services\Memberships;
use Filagora\Messages\Contracts\MessagingPolicy;
use Filagora\Messages\Exceptions\MessageRefused;
use Filagora\Messages\Models\PrivateConversation;
use Filagora\Messages\Services\IgnoredUsers;
use Filagora\Messages\Services\Mailbox;
use Filagora\Messages\Services\Messages;
use Filagora\Notifications\Bridges\ForumsBridge;
use Filagora\Notifications\Bridges\MessagesBridge;
use Filagora\Notifications\Bridges\PushBridge;
use Filagora\Notifications\Enums\NotificationChannel;
use Filagora\Notifications\Filament\RelationManagers\UserNotificationPreferencesRelationManager;
use Filagora\Notifications\Models\NotificationPreference;
use Filagora\Notifications\Services\Preferences;
use Filagora\Notifications\Services\Unsubscribe;
use Filagora\Push\Contracts\PushPreferences;
use Filagora\Push\Enums\PushCategory;
use Filagora\Push\Exceptions\PushRefused;
use Filagora\Push\Notifications\WebPushAlert;
use Filagora\Push\Services\PushNotifier;
use Filagora\Push\Services\PushSubscriptions;
use Filagora\Search\Engines\ArrayEngine;
use Filagora\Search\Support\SearchEngines;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

beforeEach(function () {
    Storage::fake('local');
    seed(DemoSeeder::class);

    $this->admin = User::where('email', 'admin@example.test')->sole();
    $this->member = User::where('email', 'member@example.test')->sole();
});

it('passes the Filagora doctor on a migrated install', function () {
    artisan('filagora:doctor')->expectsOutputToContain('All checks passed.')->assertExitCode(0);
});

it('sends guests to the sign-in page', function () {
    get('/admin')->assertRedirect('/admin/login');
});

it('leaves the site root free for the host app', function () {
    get('/')->assertNotFound();
});

it('serves the panel from the configured path', function () {
    expect(Filament::getPanel('admin')->getPath())->toBe(config('demo.panel_path'));
});

it('shows the sign-in page', function () {
    get('/admin/login')->assertOk();
});

it('opens the dashboard and every Filagora screen for staff', function () {
    actingAs($this->admin);

    foreach ([
        '/admin',
        SettingResource::getUrl('index'),
        EntitlementDefinitionResource::getUrl('index'),
        UserEntitlementGrantResource::getUrl('index'),
        DefaultEntitlementGrantResource::getUrl('index'),
        EntitlementRequestResource::getUrl('index'),
        UserResource::getUrl('index'),
        ForumGroupResource::getUrl('index'),
        ForumResource::getUrl('index'),
        ThreadResource::getUrl('index'),
        PostResource::getUrl('index'),
        PostReportResource::getUrl('index'),
        ModerationQueue::getUrl(),
        EmailLogResource::getUrl('index'),
        MembershipPlanResource::getUrl('index'),
        BillingEventResource::getUrl('index'),
        CmsPageResource::getUrl('index'),
        CmsNavigationResource::getUrl('index'),
        CmsBlockResource::getUrl('index'),
        CmsMediaAssetResource::getUrl('index'),
        SiteNotificationResource::getUrl('index'),
        PrivacyRequestResource::getUrl('index'),
        CookieConsentGroupResource::getUrl('index'),
        CookiePolicyVersionResource::getUrl('index'),
        PrivacyPolicyVersionResource::getUrl('index'),
        PrivacyOperationAuditResource::getUrl('index'),
    ] as $url) {
        get($url)->assertOk();
    }
});

it('keeps staff screens closed to a member without entitlements', function () {
    actingAs($this->member);

    get('/admin')->assertOk();

    foreach ([
        SettingResource::getUrl('index'),
        EntitlementDefinitionResource::getUrl('index'),
        UserEntitlementGrantResource::getUrl('index'),
        DefaultEntitlementGrantResource::getUrl('index'),
        EntitlementRequestResource::getUrl('index'),
        UserResource::getUrl('index'),
        ForumResource::getUrl('index'),
        ThreadResource::getUrl('index'),
        PostReportResource::getUrl('index'),
        ModerationQueue::getUrl(),
        EmailLogResource::getUrl('index'),
        MembershipPlanResource::getUrl('index'),
        BillingEventResource::getUrl('index'),
        CmsPageResource::getUrl('index'),
        CmsNavigationResource::getUrl('index'),
        CmsBlockResource::getUrl('index'),
        CmsMediaAssetResource::getUrl('index'),
        SiteNotificationResource::getUrl('index'),
        PrivacyRequestResource::getUrl('index'),
        CookieConsentGroupResource::getUrl('index'),
        CookiePolicyVersionResource::getUrl('index'),
        PrivacyPolicyVersionResource::getUrl('index'),
        PrivacyOperationAuditResource::getUrl('index'),
    ] as $url) {
        get($url)->assertForbidden();
    }
});

it('lists the staff entitlements as one wildcard grant, and the member\'s as what their membership brings', function () {
    expect(app(EntitlementResolver::class)->entitlementsFor($this->admin))->toBe(['filagora.*'])
        ->and(app(EntitlementResolver::class)->entitlementsFor($this->member))->toBe(['demo.supporter']);
});

it('shows both entitlement relation managers on the user screen', function () {
    actingAs($this->admin);
    Filament::setCurrentPanel('admin');

    get(UserResource::getUrl('edit', ['record' => $this->admin]))
        ->assertOk()
        ->assertSeeLivewire(UserEntitlementsRelationManager::class)
        ->assertSee('Entitlement grants')
        ->assertSee('Entitlement requests');

    $managerArguments = ['ownerRecord' => $this->admin, 'pageClass' => EditUser::class];

    Livewire::test(UserEntitlementsRelationManager::class, $managerArguments)
        ->assertSuccessful()
        ->assertSee('filagora.*');

    Livewire::test(UserEntitlementRequestsRelationManager::class, ['ownerRecord' => $this->member, 'pageClass' => EditUser::class])
        ->assertSuccessful()
        ->assertSee('demo.early-access');
});

it('lets staff approve a member request, and the member then holds the entitlement', function () {
    actingAs($this->admin);
    Filament::setCurrentPanel('admin');
    $request = EntitlementRequest::where('user_id', $this->member->id)->sole();

    expect(app(EntitlementResolver::class)->hasEntitlement($this->member, 'demo.early-access'))->toBeFalse();

    Livewire::test(ViewEntitlementRequest::class, ['record' => $request->getKey()])
        ->callAction('approve', ['review_notes' => 'Welcome aboard'])
        ->assertNotified();

    expect($request->refresh()->status)->toBe(EntitlementRequestStatus::APPROVED)
        ->and($request->reviewed_by_id)->toBe($this->admin->id)
        ->and(app(EntitlementResolver::class)->hasEntitlement($this->member, 'demo.early-access'))->toBeTrue();
});

it('lets staff approve the queued thread and resolve the reported reply', function () {
    actingAs($this->admin);
    Filament::setCurrentPanel('admin');
    $pending = Thread::where('slug', 'first-post')->sole();
    $report = PostReport::sole();

    Livewire::test(ModerationQueue::class)
        ->assertSee('My first post')
        ->assertSee('Buy cheap watches here!')
        ->callAction('approveThread', [], ['id' => $pending->id])
        ->callAction('resolveReport', ['outcome' => 'remove', 'note' => 'Spam'], ['id' => $report->post_id]);

    expect($pending->refresh()->isApproved())->toBeTrue()
        ->and($report->refresh()->post->is_deleted)->toBeTrue();
});

it('seeds idempotently', function () {
    seed(DemoSeeder::class);

    expect(User::count())->toBe(2)
        ->and(EntitlementRequest::count())->toBe(1)
        ->and(Thread::count())->toBe(2)
        ->and(PostReport::count())->toBe(1)
        ->and(EmailLog::count())->toBe(1)
        ->and(CmsPage::count())->toBe(2)
        ->and(SiteNotification::count())->toBe(1)
        ->and(PrivacyRequest::count())->toBe(1)
        ->and(Poll::count())->toBe(1)
        ->and(Reaction::count())->toBe(1)
        ->and(PrivateConversation::count())->toBe(1);
});

it('shows the seeded banner to a signed-in member until they dismiss it, and never to a guest', function () {
    $banners = app(ActiveAnnouncements::class);
    $dismissals = app(AnnouncementDismissals::class);
    $banner = SiteNotification::sole();

    expect($banners->forViewer(null)->count())->toBe(0)
        ->and($banners->forViewer($this->member)->pluck('id')->all())->toBe([$banner->id])
        ->and($dismissals->dismiss($this->member, $banner->id))->toBeTrue()
        ->and($dismissals->dismissedIdsFor($this->member))->toBe([$banner->id]);
});

it('lets staff generate the seeded export, and the file holds what every package registered', function () {
    Storage::fake('local');
    actingAs($this->admin);
    Filament::setCurrentPanel('admin');
    $request = PrivacyRequest::sole();

    Livewire::test(ViewPrivacyRequest::class, ['record' => $request->getKey()])->callAction('export')->assertNotified();

    $file = json_decode(Storage::disk('local')->get($request->refresh()->export_path), true);

    expect($file)->toHaveKeys(['generated_at', 'user', 'forums', 'email_logs', 'entitlements', 'compliance', 'notifications'])
        ->and(array_column($file['notifications']['preferences'], 'category'))->toContain('forum.thread_updates_digest', 'messages.received', 'forum.mention')
        ->and($file['user']['email'])->toBe($this->member->email)
        ->and($file['forums']['threads'])->not->toBeEmpty();
});

it('asks the member for cookie consent and policy acceptance, and remembers their answer', function () {
    $consent = app(CookieConsent::class);
    $policy = app(PolicyAcceptance::class);

    expect(array_column($consent->enabledGroups(), 'key'))->toBe(['necessary', 'analytics'])
        ->and($policy->hasAcknowledgedActivePolicy($this->member))->toBeFalse();

    $saved = $consent->save($this->member, ['groups' => ['analytics' => true]], '127.0.0.1', 'browser');
    $policy->accept($this->member, '127.0.0.1', 'browser');

    expect($saved->consent['groups'])->toBe(['necessary' => true, 'analytics' => true])
        ->and($consent->requiresReconsent($saved))->toBeFalse()
        ->and($policy->hasAcknowledgedActivePolicy($this->member))->toBeTrue();
});

it('lets the member vote once in the seeded poll and see the result, react to the first post and follow the thread', function () {
    $poll = Poll::sole();
    $poll->load('questions.options');
    $thread = $poll->thread;
    $first = $thread->posts()->orderBy('id')->first();
    $polls = app(Polls::class);

    expect($polls->canSeeResults($poll, $this->member))->toBeFalse();

    $polls->vote($this->member, $poll, [$poll->questions[0]->id => [$poll->questions[0]->options[1]->id]]);

    expect($polls->canSeeResults($poll, $this->member))->toBeTrue()
        ->and($polls->results($poll)['questions'][0]['options'][1]['votes'])->toBe(1)
        ->and(app(Reactions::class)->toggle($this->admin, $first))->toBeTrue()
        ->and(app(Reactions::class)->counts([$first->id])[$first->id]['like'])->toBe(2)
        ->and(app(Subscriptions::class)->isSubscribed($this->member, $thread))->toBeTrue();
});

it('lets the member read the seeded message, reply to it and trash the conversation', function () {
    $conversation = PrivateConversation::sole();
    $mailbox = app(Mailbox::class);
    $messages = app(Messages::class);

    expect($mailbox->unreadCount($this->member))->toBe(1);

    $messages->open($this->member, $conversation);
    $messages->reply($this->member, $conversation, 'Thanks!');

    expect($mailbox->unreadCount($this->member))->toBe(0)
        ->and($mailbox->unreadCount($this->admin))->toBe(1)
        ->and($conversation->messages()->count())->toBe(2)
        ->and($mailbox->trash($this->member, [$conversation->id]))->toBe(1)
        ->and($mailbox->unreadCount($this->member))->toBe(0);
});

it('stops messages both ways once the member ignores the admin, and lets them through again after', function () {
    $ignored = app(IgnoredUsers::class);
    $messages = app(Messages::class);
    $ignored->ignore($this->member, $this->admin);

    expect(fn () => $messages->send($this->admin, [$this->member], 'Hello', 'Are you there?'))->toThrow(MessageRefused::class)
        ->and(fn () => $messages->send($this->member, [$this->admin], 'Hello', 'Are you there?'))->toThrow(MessageRefused::class)
        ->and(PrivateConversation::count())->toBe(1);

    $ignored->unignore($this->member, $this->admin);

    // Sent to the admin: the member's seeded push subscription is a placeholder, so a message to the member would try to push to it.
    expect($messages->send($this->member, [$this->admin], 'Hello', 'Are you there?'))->toBeInstanceOf(PrivateConversation::class);
});

it('finds the seeded thread and page by their words, on the database engine', function () {
    config(['filagora-forums.search.fulltext' => false]);
    $threads = app(ForumSearch::class)->threads(new ForumSearchCriteria('welcome'), null)->getCollection();
    $pages = app(CmsSearch::class)->pages('welcome')->getCollection();

    expect($threads->pluck('title')->all())->toBe(['Welcome to the demo forum'])
        ->and($pages->pluck('title')->all())->toBe(['Welcome'])
        ->and(app(CmsSearch::class)->pages('browse the forum')->getCollection()->pluck('title')->all())->toBe(['Welcome']);
});

it('gives the same answers from the array engine once the indexes are built', function () {
    config(['filagora-search.engine' => 'array']);
    app(SearchEngines::class)->extend('array', fn () => $engine ??= new ArrayEngine);

    artisan('filagora:search:reindex', ['--sync-settings' => true])->assertExitCode(0);
    artisan('filagora:search:check-health')->expectsOutput('All search indexes are healthy.')->assertExitCode(0);

    expect(app(ForumSearch::class)->threads(new ForumSearchCriteria('welcome'), null)->getCollection()->pluck('title')->all())->toBe(['Welcome to the demo forum'])
        ->and(app(CmsSearch::class)->pages('welcome')->getCollection()->pluck('title')->all())->toBe(['Welcome']);
});

it('has the member mentioned in the welcome post, once', function () {
    $post = Thread::query()->where('title', 'Welcome to the demo forum')->sole()->posts()->orderBy('id')->first();
    $mentions = app(Mentions::class);

    expect($mentions->usernames($post->body))->toBe(['member'])
        ->and(PostMention::where('post_id', $post->id)->pluck('user_id')->all())->toBe([$this->member->id])
        ->and($mentions->sync($post))->toHaveCount(0);
});

it('tracks what each person has read', function () {
    $thread = Thread::query()->where('title', 'Welcome to the demo forum')->sole();
    $reads = app(ReadTracking::class);

    expect($reads->isUnread($this->admin, $thread))->toBeFalse()
        ->and($reads->isUnread($this->member, $thread))->toBeTrue()
        ->and($reads->unreadThreads($this->member)->pluck('id')->all())->toContain($thread->id);

    $reads->markThreadRead($this->member, $thread);

    expect($reads->isUnread($this->member, $thread))->toBeFalse()
        ->and($reads->unreadThreads($this->member)->pluck('id')->all())->not->toContain($thread->id);
});

it('serves the seeded file to a guest, and keeps a waiting upload to its uploader', function () {
    $files = app(Attachments::class);
    $seeded = PostAttachment::sole();

    ob_start();
    $files->stream($seeded)->sendContent();

    expect(ob_get_clean())->toBe('Welcome to the demo.')->and($files->canView($seeded, null))->toBeTrue();

    $waiting = $files->storeInline($this->member, UploadedFile::fake()->image('mine.png', 50, 50));

    expect($files->canView($waiting, $this->member))->toBeTrue()->and($files->canView($waiting, $this->admin))->toBeFalse()->and($files->canView($waiting, null))->toBeFalse();
});

it('pushes to the member\'s subscribed browser and refuses an endpoint on an internal host', function () {
    Notification::fake();
    $push = app(PushSubscriptions::class);

    expect($push->hasAny($this->member))->toBeTrue()->and($push->hasAny($this->admin))->toBeFalse()
        ->and(app(PushNotifier::class)->send($this->member, PushCategory::MENTION, 'Demo', 'Hello', '/'))->toBe(1)
        ->and(app(PushNotifier::class)->send($this->admin, PushCategory::MENTION, 'Demo', 'Hello', '/'))->toBe(0)
        ->and(fn () => $push->store($this->admin, 'https://internal.test/hook', 'k', 'a'))->toThrow(PushRefused::class);

    Notification::assertSentOnDemandTimes(WebPushAlert::class, 1);
});

it('gives the member a free membership and the entitlement it brings', function () {
    $membership = UserMembership::where('user_id', $this->member->id)->sole();

    expect($membership->plan->code)->toBe('supporter-free')
        ->and($membership->isCurrentlyActive())->toBeTrue()
        ->and(app(Memberships::class)->hasActive($this->member))->toBeTrue()
        ->and(app(Memberships::class)->hasActive($this->admin))->toBeFalse()
        ->and(app(EntitlementResolver::class)->hasEntitlement($this->member, 'demo.supporter'))->toBeTrue()
        ->and(app(EntitlementResolver::class)->hasEntitlement($this->member, 'demo.gold'))->toBeFalse();
});

it('lists the free and the paid plan, the paid one with a price in each currency', function () {
    $gold = MembershipPlan::where('code', 'supporter-gold')->sole();

    expect(MembershipPlan::count())->toBe(2)
        ->and($gold->prices->pluck('amount', 'currency')->all())->toEqual(['USD' => '5.00', 'EUR' => '4.50'])
        ->and($gold->defaultPrice()->currency)->toBe('USD')
        ->and($gold->isCheckoutReady())->toBeFalse();
});

it('shows the memberships on the user screen to staff', function () {
    actingAs($this->admin);

    expect(UserResource::getRelations())->toContain(UserMembershipsRelationManager::class)
        ->and(UserMembershipsRelationManager::canViewForRecord($this->member, EditUser::class))->toBeTrue();

    actingAs($this->member);

    expect(UserMembershipsRelationManager::canViewForRecord($this->member, EditUser::class))->toBeFalse();
});

it('badges the billing log with the failed event the seed left', function () {
    actingAs($this->admin);

    expect(BillingEventResource::getNavigationBadge())->toBe('1');
});

it('refuses a PayPal delivery on a site with no PayPal account set up', function () {
    $this->postJson('/paypal/webhook', ['id' => 'WH-X', 'event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED'])->assertStatus(400);
});

it('keeps three stored notification choices for the member and defaults for everything else', function () {
    $preferences = app(Preferences::class);

    expect(NotificationPreference::where('user_id', $this->member->id)->count())->toBe(3)
        ->and(NotificationPreference::where('user_id', $this->admin->id)->count())->toBe(0)
        ->and($preferences->allows($this->member, 'forum.thread_updates_digest'))->toBeFalse()
        ->and($preferences->allows($this->member, 'forum.thread_updates_instant'))->toBeTrue()
        ->and($preferences->allows($this->member, 'messages.received', NotificationChannel::IN_APP))->toBeFalse()
        ->and($preferences->allows($this->member, 'messages.received', NotificationChannel::PUSH))->toBeTrue()
        ->and($preferences->canDeliver($this->member, 'forum.mention', NotificationChannel::PUSH))->toBeTrue()
        ->and($preferences->canDeliver($this->member, 'forum.mention', NotificationChannel::IN_APP))->toBeFalse();
});

it('answers push, forums and messages from the member\'s choices', function () {
    expect(app(PushPreferences::class))->toBeInstanceOf(PushBridge::class)
        ->and(app(SubscriptionPreferences::class))->toBeInstanceOf(ForumsBridge::class)
        ->and(app(MessagingPolicy::class))->toBeInstanceOf(MessagesBridge::class)
        ->and(app(PushPreferences::class)->allows($this->member, PushCategory::MENTION))->toBeTrue();

    app(Preferences::class)->set($this->member, 'forum.mention', NotificationChannel::PUSH, false);

    expect(app(PushPreferences::class)->allows($this->member, PushCategory::MENTION))->toBeFalse();
});

it('shows the stored choices on the user screen to staff, and not to the member', function () {
    actingAs($this->admin);

    expect(UserResource::getRelations())->toContain(UserNotificationPreferencesRelationManager::class)
        ->and(UserNotificationPreferencesRelationManager::canViewForRecord($this->member, EditUser::class))->toBeTrue();

    actingAs($this->member);

    expect(UserNotificationPreferencesRelationManager::canViewForRecord($this->member, EditUser::class))->toBeFalse();
});

it('switches the digest mail off from a signed link, and refuses an altered one', function () {
    app(Preferences::class)->set($this->member, 'forum.thread_updates_digest', NotificationChannel::EMAIL, true);
    $url = app(Unsubscribe::class)->url($this->member, 'forum.thread_updates_digest');

    get($url.'x')->assertForbidden();
    expect(app(Preferences::class)->allows($this->member, 'forum.thread_updates_digest'))->toBeTrue();

    get($url)->assertOk()->assertSee('Digest of new posts in threads you follow')->assertSee('/'.config('demo.panel_path'));

    app(Preferences::class)->flush();

    expect(app(Preferences::class)->allows($this->member, 'forum.thread_updates_digest'))->toBeFalse();
});

it('lists the member\'s stored choices on the user screen, by label and channel', function () {
    actingAs($this->admin);
    Filament::setCurrentPanel('admin');

    Livewire::test(UserNotificationPreferencesRelationManager::class, ['ownerRecord' => $this->member, 'pageClass' => EditUser::class])
        ->assertCanSeeTableRecords(NotificationPreference::where('user_id', $this->member->id)->get())
        ->assertSee('Private messages')
        ->assertSee('On-site bell')
        ->assertSee('Demo Admin');
});
