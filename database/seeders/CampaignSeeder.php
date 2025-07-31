<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Campaign;
use App\Models\Bid;
use Carbon\Carbon;

class CampaignSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create sample users if they don't exist
        $admin = User::firstOrCreate([
            'email' => 'admin@example.com'
        ], [
            'name' => 'Admin User',
            'password' => bcrypt('password'),
            'role' => 'admin'
        ]);

        $brand1 = User::firstOrCreate([
            'email' => 'brand1@example.com'
        ], [
            'name' => 'Nike Brand',
            'password' => bcrypt('password'),
            'role' => 'brand',
            'company_name' => 'Nike Inc.'
        ]);

        $brand2 = User::firstOrCreate([
            'email' => 'brand2@example.com'
        ], [
            'name' => 'Adidas Brand',
            'password' => bcrypt('password'),
            'role' => 'brand',
            'company_name' => 'Adidas Group'
        ]);

        $creator1 = User::firstOrCreate([
            'email' => 'creator1@example.com'
        ], [
            'name' => 'John Creator',
            'password' => bcrypt('password'),
            'role' => 'creator',
            'bio' => 'Fashion and lifestyle influencer with 100K+ followers'
        ]);

        $creator2 = User::firstOrCreate([
            'email' => 'creator2@example.com'
        ], [
            'name' => 'Jane Creator',
            'password' => bcrypt('password'),
            'role' => 'creator',
            'bio' => 'Tech reviewer and unboxing specialist'
        ]);

        $creator3 = User::firstOrCreate([
            'email' => 'creator3@example.com'
        ], [
            'name' => 'Mike Creator',
            'password' => bcrypt('password'),
            'role' => 'creator',
            'bio' => 'Fitness and sports content creator'
        ]);

        // Create sample campaigns
        $campaigns = [
            [
                'brand_id' => $brand1->id,
                'title' => 'Summer Collection 2024 Launch',
                'description' => 'Looking for fashion influencers to showcase our new summer collection. Must have experience with fashion content and styling.',
                'budget' => 5000.00,
                'location' => 'São Paulo, SP',
                'requirements' => 'Must have 10K+ followers on Instagram, previous fashion brand collaborations preferred',
                'target_states' => ['SP', 'RJ', 'MG'],
                'category' => 'Fashion',
                'campaign_type' => 'Instagram',
                'deadline' => Carbon::now()->addMonths(2),
                'max_bids' => 15,
                'status' => 'approved',
                'approved_at' => Carbon::now()->subDays(5),
                'approved_by' => $admin->id,
            ],
            [
                'brand_id' => $brand2->id,
                'title' => 'New Running Shoes Review',
                'description' => 'Seeking sports and fitness creators to review our latest running shoes. Video content preferred.',
                'budget' => 3000.00,
                'location' => 'Rio de Janeiro, RJ',
                'requirements' => 'Active in fitness community, ability to create video content',
                'target_states' => ['RJ', 'SP'],
                'category' => 'Sports',
                'campaign_type' => 'YouTube',
                'deadline' => Carbon::now()->addMonth(),
                'max_bids' => 10,
                'status' => 'approved',
                'approved_at' => Carbon::now()->subDays(3),
                'approved_by' => $admin->id,
            ],
            [
                'brand_id' => $brand1->id,
                'title' => 'Tech Gadget Unboxing',
                'description' => 'Need tech reviewers for our new smartwatch unboxing and review campaign.',
                'budget' => 2500.00,
                'location' => 'Belo Horizonte, MG',
                'requirements' => 'Experience with tech reviews, good video production quality',
                'target_states' => ['MG', 'SP'],
                'category' => 'Technology',
                'campaign_type' => 'YouTube',
                'deadline' => Carbon::now()->addWeeks(3),
                'max_bids' => 8,
                'status' => 'pending',
            ],
            [
                'brand_id' => $brand2->id,
                'title' => 'Lifestyle Brand Collaboration',
                'description' => 'Looking for lifestyle influencers to create authentic content featuring our products.',
                'budget' => 4000.00,
                'location' => 'Nationwide',
                'requirements' => 'Authentic voice, engagement rate above 3%',
                'target_states' => ['SP', 'RJ', 'MG', 'RS', 'PR'],
                'category' => 'Lifestyle',
                'campaign_type' => 'Instagram',
                'deadline' => Carbon::now()->addMonths(1),
                'max_bids' => 20,
                'status' => 'pending',
            ],
        ];

        foreach ($campaigns as $campaignData) {
            Campaign::create($campaignData);
        }

        // Create sample bids for approved campaigns
        $approvedCampaigns = Campaign::where('status', 'approved')->get();

        foreach ($approvedCampaigns as $campaign) {
            // Create multiple bids for each campaign
            Bid::create([
                'campaign_id' => $campaign->id,
                'user_id' => $creator1->id,
                'bid_amount' => $campaign->budget * 0.8, // 80% of budget
                'proposal' => 'I have extensive experience in this category and can deliver high-quality content that aligns with your brand values.',
                'portfolio_links' => 'https://instagram.com/johncreator, https://youtube.com/johncreator',
                'estimated_delivery_days' => 7,
                'status' => 'pending',
            ]);

            Bid::create([
                'campaign_id' => $campaign->id,
                'user_id' => $creator2->id,
                'bid_amount' => $campaign->budget * 0.9, // 90% of budget
                'proposal' => 'I specialize in creating engaging content that drives results. My previous campaigns have achieved 15%+ engagement rates.',
                'portfolio_links' => 'https://instagram.com/janecreator, https://tiktok.com/@janecreator',
                'estimated_delivery_days' => 5,
                'status' => 'pending',
            ]);

            Bid::create([
                'campaign_id' => $campaign->id,
                'user_id' => $creator3->id,
                'bid_amount' => $campaign->budget * 0.7, // 70% of budget
                'proposal' => 'As a fitness creator, I can authentically represent your brand and create compelling content that resonates with your target audience.',
                'portfolio_links' => 'https://instagram.com/mikecreator, https://youtube.com/mikecreator',
                'estimated_delivery_days' => 10,
                'status' => 'pending',
            ]);
        }

        // Accept one bid for the first campaign
        $firstCampaign = Campaign::where('status', 'approved')->first();
        if ($firstCampaign) {
            $firstBid = $firstCampaign->bids()->first();
            if ($firstBid) {
                $firstBid->accept();
            }
        }

        $this->command->info('Campaign and bid data seeded successfully!');
        $this->command->info('Sample accounts created:');
        $this->command->info('- Admin: admin@example.com / password');
        $this->command->info('- Brand 1: brand1@example.com / password');
        $this->command->info('- Brand 2: brand2@example.com / password');
        $this->command->info('- Creator 1: creator1@example.com / password');
        $this->command->info('- Creator 2: creator2@example.com / password');
        $this->command->info('- Creator 3: creator3@example.com / password');
    }
}
