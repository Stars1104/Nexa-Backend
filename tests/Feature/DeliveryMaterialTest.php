<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Contract;
use App\Models\Campaign;
use App\Models\CampaignTimeline;
use App\Models\DeliveryMaterial;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;

class DeliveryMaterialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Mail::fake();
    }

    public function test_brand_can_approve_delivery_material()
    {
        // Create test users
        $brand = User::factory()->create(['role' => 'brand']);
        $creator = User::factory()->create(['role' => 'creator']);
        
        // Create campaign and contract
        $campaign = Campaign::factory()->create(['brand_id' => $brand->id]);
        $contract = Contract::factory()->create([
            'campaign_id' => $campaign->id,
            'brand_id' => $brand->id,
            'creator_id' => $creator->id,
            'status' => 'active'
        ]);
        
        // Create delivery material
        $material = DeliveryMaterial::factory()->create([
            'contract_id' => $contract->id,
            'creator_id' => $creator->id,
            'brand_id' => $brand->id,
            'status' => 'pending'
        ]);

        $response = $this->actingAs($brand)
            ->postJson("/api/delivery-materials/{$material->id}/approve", [
                'comment' => 'Great work!'
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        
        $this->assertDatabaseHas('delivery_materials', [
            'id' => $material->id,
            'status' => 'approved'
        ]);
    }

    public function test_brand_can_reject_delivery_material()
    {
        // Create test users
        $brand = User::factory()->create(['role' => 'brand']);
        $creator = User::factory()->create(['role' => 'creator']);
        
        // Create campaign and contract
        $campaign = Campaign::factory()->create(['brand_id' => $brand->id]);
        $contract = Contract::factory()->create([
            'campaign_id' => $campaign->id,
            'brand_id' => $brand->id,
            'creator_id' => $creator->id,
            'status' => 'active'
        ]);
        
        // Create delivery material
        $material = DeliveryMaterial::factory()->create([
            'contract_id' => $contract->id,
            'creator_id' => $creator->id,
            'brand_id' => $brand->id,
            'status' => 'pending'
        ]);

        $response = $this->actingAs($brand)
            ->postJson("/api/delivery-materials/{$material->id}/reject", [
                'rejection_reason' => 'Quality not meeting standards',
                'comment' => 'Please improve the content'
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        
        $this->assertDatabaseHas('delivery_materials', [
            'id' => $material->id,
            'status' => 'rejected',
            'rejection_reason' => 'Quality not meeting standards'
        ]);
    }

    public function test_milestone_title_is_correct()
    {
        // Create test users
        $brand = User::factory()->create(['role' => 'brand']);
        $creator = User::factory()->create(['role' => 'creator']);
        
        // Create campaign and contract
        $campaign = Campaign::factory()->create(['brand_id' => $brand->id]);
        $contract = Contract::factory()->create([
            'campaign_id' => $campaign->id,
            'brand_id' => $brand->id,
            'creator_id' => $creator->id,
            'status' => 'active'
        ]);
        
        // Create timeline with milestones
        $response = $this->actingAs($brand)
            ->postJson("/api/campaign-timeline/create-milestones", [
                'contract_id' => $contract->id
            ]);

        $response->assertStatus(200);
        
        // Check that video submission milestone has correct title
        $this->assertDatabaseHas('campaign_timelines', [
            'contract_id' => $contract->id,
            'milestone_type' => 'video_submission',
            'title' => 'Image and Video Submission'
        ]);
    }
} 