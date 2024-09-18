<?php

namespace Tests\Feature\Checkins\Api;

use App\Models\Accessory;
use App\Models\Company;
use App\Models\User;
use Tests\Concerns\TestsFullMultipleCompaniesSupport;
use Tests\Concerns\TestsPermissionsRequirement;
use Tests\TestCase;

class AccessoryCheckinTest extends TestCase implements TestsFullMultipleCompaniesSupport, TestsPermissionsRequirement
{
    public function testRequiresPermission()
    {
        $accessory = Accessory::factory()->checkedOutToUser()->create();

        $this->actingAsForApi(User::factory()->create())
            ->postJson(route('api.accessories.checkin', $accessory))
            ->assertForbidden();
    }

    public function testAdheresToFullMultipleCompaniesSupportScoping()
    {
        [$companyA, $companyB] = Company::factory()->count(2)->create();

        $superUser = $companyA->users()->save(User::factory()->superuser()->make());
        $userInCompanyA = User::factory()->for($companyA)->checkinAccessories()->create();
        $accessoryForCompanyB = Accessory::factory()->for($companyB)->checkedOutToUser()->create();
        $anotherAccessoryForCompanyB = Accessory::factory()->for($companyB)->checkedOutToUser()->create();

        $this->assertEquals(1, $accessoryForCompanyB->checkouts->count());
        $this->assertEquals(1, $anotherAccessoryForCompanyB->checkouts->count());

        $this->settings->enableMultipleFullCompanySupport();

        $this->actingAsForApi($userInCompanyA)
            ->postJson(route('api.accessories.checkin', $accessoryForCompanyB))
            ->assertForbidden();

        $this->actingAsForApi($superUser)
            ->postJson(route('api.accessories.checkin', $anotherAccessoryForCompanyB))
            ->assertStatusMessageIs('success');

        $this->assertEquals(1, $accessoryForCompanyB->fresh()->checkouts->count(), 'Accessory should not be checked in');
        $this->assertEquals(0, $anotherAccessoryForCompanyB->fresh()->checkouts->count(), 'Accessory should be checked in');
    }

    public function testCanCheckinAccessory()
    {
        $accessory = Accessory::factory()->checkedOutToUser()->create();

        $this->assertEquals(1, $accessory->checkouts->count());

        $this->actingAsForApi(User::factory()->checkinAccessories()->create())
            ->postJson(route('api.accessories.checkin', $accessory))
            ->assertStatusMessageIs('success');

        $this->assertEquals(0, $accessory->fresh()->checkouts->count(), 'Accessory should be checked in');
    }
}
