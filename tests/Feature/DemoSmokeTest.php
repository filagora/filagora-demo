<?php

use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use ForumKit\Core\Filament\Resources\Settings\SettingResource;
use ForumKit\Entitlements\Enums\EntitlementRequestStatus;
use ForumKit\Entitlements\Filament\RelationManagers\UserEntitlementRequestsRelationManager;
use ForumKit\Entitlements\Filament\RelationManagers\UserEntitlementsRelationManager;
use ForumKit\Entitlements\Filament\Resources\DefaultEntitlementGrants\DefaultEntitlementGrantResource;
use ForumKit\Entitlements\Filament\Resources\EntitlementDefinitions\EntitlementDefinitionResource;
use ForumKit\Entitlements\Filament\Resources\EntitlementRequests\EntitlementRequestResource;
use ForumKit\Entitlements\Filament\Resources\EntitlementRequests\Pages\ViewEntitlementRequest;
use ForumKit\Entitlements\Filament\Resources\UserEntitlementGrants\UserEntitlementGrantResource;
use ForumKit\Entitlements\Models\EntitlementRequest;
use ForumKit\Entitlements\Services\EntitlementResolver;
use ForumKit\Forums\Filament\Pages\ModerationQueue;
use ForumKit\Forums\Filament\Resources\ForumGroups\ForumGroupResource;
use ForumKit\Forums\Filament\Resources\Forums\ForumResource;
use ForumKit\Forums\Filament\Resources\PostReports\PostReportResource;
use ForumKit\Forums\Filament\Resources\Posts\PostResource;
use ForumKit\Forums\Filament\Resources\Threads\ThreadResource;
use ForumKit\Forums\Models\PostReport;
use ForumKit\Forums\Models\Thread;
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

it('passes the ForumKit doctor on a migrated install', function () {
    artisan('forumkit:doctor')->expectsOutputToContain('All checks passed.')->assertExitCode(0);
});

it('sends guests to the sign-in page', function () {
    get('/admin')->assertRedirect('/admin/login');
    get('/')->assertRedirect('/admin');
});

it('shows the sign-in page', function () {
    get('/admin/login')->assertOk();
});

it('opens the dashboard and every ForumKit screen for staff', function () {
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
    ] as $url) {
        get($url)->assertForbidden();
    }
});

it('lists the staff entitlements as one wildcard grant', function () {
    expect(app(EntitlementResolver::class)->entitlementsFor($this->admin))->toBe(['forumkit.*'])
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
        ->assertSee('forumkit.*');

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
        ->and(PostReport::count())->toBe(1);
});
