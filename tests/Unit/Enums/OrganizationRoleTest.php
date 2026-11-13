<?php

namespace Tests\Unit\Enums;

use App\Enums\OrganizationRole;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OrganizationRoleTest extends TestCase
{
    /**
     * Expected access per role, in the order: organization, events, marketing, finances, payouts.
     *
     * @return array<string, array{OrganizationRole, array{bool, bool, bool, bool, bool}}>
     */
    public static function accessMatrix(): array
    {
        return [
            'owner' => [OrganizationRole::Owner, [true, true, true, true, true]],
            'admin' => [OrganizationRole::Admin, [true, true, true, true, false]],
            'manager' => [OrganizationRole::Manager, [false, true, true, false, false]],
            'finance' => [OrganizationRole::Finance, [false, false, false, true, true]],
            'marketing' => [OrganizationRole::Marketing, [false, false, true, false, false]],
            'staff' => [OrganizationRole::Staff, [false, false, false, false, false]],
        ];
    }

    /** @param  array{bool, bool, bool, bool, bool}  $expected */
    #[DataProvider('accessMatrix')]
    public function test_role_grants_exactly_its_permissions(OrganizationRole $role, array $expected): void
    {
        $this->assertSame($expected, [
            $role->canManageOrganization(),
            $role->canManageEvents(),
            $role->canManageMarketing(),
            $role->canManageFinances(),
            $role->canRequestPayouts(),
        ]);
    }

    public function test_ownership_is_never_assignable(): void
    {
        $this->assertNotContains(OrganizationRole::Owner, OrganizationRole::assignable());
        $this->assertCount(count(OrganizationRole::cases()) - 1, OrganizationRole::assignable());
    }
}
