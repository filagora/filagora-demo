<?php

namespace Database\Seeders;

use App\Models\User;
use ForumKit\Entitlements\Enums\EntitlementRequestStatus;
use ForumKit\Entitlements\Models\EntitlementDefinition;
use ForumKit\Entitlements\Models\EntitlementRequest;
use ForumKit\Entitlements\Models\UserEntitlementGrant;
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
    }
}
