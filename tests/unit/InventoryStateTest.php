<?php
declare(strict_types=1);
use App\Services\InventoryState;
use CodeIgniter\Test\CIUnitTestCase;

final class InventoryStateTest extends CIUnitTestCase
{
    public function testAvailabilityRespectsPolicyWithoutHidingExistingCommitments(): void
    {
        $lot=['commercial_status'=>'available','project_active'=>1,'sales_policy'=>'partial','is_enabled'=>0];
        $this->assertSame('blocked',InventoryState::effective($lot));
        $lot['is_enabled']=1;
        $this->assertSame('available',InventoryState::effective($lot));
        $lot['sales_policy']='blocked';
        $this->assertSame('blocked',InventoryState::effective($lot));
        $lot['commercial_status']='reserved';
        $this->assertSame('reserved',InventoryState::effective($lot));
        $lot['commercial_status']='sold';
        $this->assertSame('sold',InventoryState::effective($lot));
        $lot['commercial_status']='negotiating';$lot['sales_policy']='enabled';
        $this->assertSame('negotiating',InventoryState::effective($lot));
        $lot['project_active']=0;
        $this->assertSame('blocked',InventoryState::effective($lot));
    }
}
