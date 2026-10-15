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
use Filagora\Forums\Filament\Pages\ModerationQueue;
use Filagora\Forums\Filament\Resources\ForumGroups\ForumGroupResource;
use Filagora\Forums\Filament\Resources\Forums\ForumResource;
use Filagora\Forums\Filament\Resources\PostReports\PostReportResource;
use Filagora\Forums\Filament\Resources\Posts\PostResource;
use Filagora\Forums\Filament\Resources\SiteNotifications\SiteNotificationResource;
use Filagora\Forums\Filament\Resources\Threads\ThreadResource;
use Filagora\Forums\Models\Poll;
use Filagora\Forums\Models\PostReport;
use Filagora\Forums\Models\Reaction;
use Filagora\Forums\Models\SiteNotification;
use Filagora\Forums\Models\Thread;
use Filagora\Forums\Services\ActiveAnnouncements;
use Filagora\Forums\Services\AnnouncementDismissals;
use Filagora\Forums\Services\Polls;
use Filagora\Forums\Services\Reactions;
use Filagora\Forums\Services\Subscriptions;
use Filagora\Logs\Filament\Resources\EmailLogs\EmailLogResource;
use Filagora\Logs\Models\EmailLog;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(DemoSeeder::class);

    $this->admin = User::where('email', 'admin@example.test')->sole();
    $this->member = User::where('email', 'member@example.test')->sole();
});

it('passes the Filagora doctor on a migrated install', function () {
    artisan('filagora:doctor')->expectsOutputToContain('All checks passed.')->assertExitCode(0);
});

it('sends guests to the sign-in page', function () {
    get('/admin')->assertRedirect('/admin/login');
    get('/')->assertRedirect('/admin');
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

it('lists the staff entitlements as one wildcard grant', function () {
    expect(app(EntitlementResolver::class)->entitlementsFor($this->admin))->toBe(['filagora.*'])
        ->and(app(EntitlementResolver::class)->entitlementsFor($this->member))->toBe([]);
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
        ->and(Reaction::count())->toBe(1);
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

    expect($file)->toHaveKeys(['generated_at', 'user', 'forums', 'email_logs', 'entitlements', 'compliance'])
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
