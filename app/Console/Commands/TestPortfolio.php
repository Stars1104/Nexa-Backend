<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Portfolio;
use App\Models\PortfolioItem;

class TestPortfolio extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:portfolio';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test portfolio functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing Portfolio Functionality...');

        try {
            // Find a creator user
            $user = User::where('role', 'creator')->first();
            
            if (!$user) {
                $this->warn('No creator user found. Creating one...');
                $user = User::create([
                    'name' => 'Test Creator',
                    'email' => 'test@creator.com',
                    'password' => bcrypt('password'),
                    'role' => 'creator'
                ]);
            }
            
            $this->info("Testing with user: {$user->name} (ID: {$user->id})");
            
            // Test portfolio creation
            $portfolio = $user->portfolio()->firstOrCreate();
            
            // Update portfolio with test data
            $portfolio->update([
                'title' => 'Test Portfolio',
                'bio' => 'This is a test portfolio bio',
                'profile_picture' => null
            ]);
            
            $this->info("Portfolio created/updated: ID {$portfolio->id}");
            $this->info("Title: {$portfolio->title}");
            $this->info("Bio: {$portfolio->bio}");
            $this->info("Profile picture URL: {$portfolio->profile_picture_url}");
            
            // Test portfolio item creation
            $item = $portfolio->items()->create([
                'file_path' => 'test/path/image.jpg',
                'file_name' => 'test-image.jpg',
                'file_type' => 'image/jpeg',
                'media_type' => 'image',
                'file_size' => 1024,
                'title' => 'Test Image',
                'description' => 'This is a test image',
                'order' => 1
            ]);
            
            $this->info("Portfolio item created: ID {$item->id}");
            $this->info("Media type: {$item->media_type}");
            $this->info("File URL: {$item->file_url}");
            
            // Test portfolio methods
            $this->info("Items count: {$portfolio->getItemsCount()}");
            $this->info("Images count: {$portfolio->getImagesCount()}");
            $this->info("Videos count: {$portfolio->getVideosCount()}");
            $this->info("Is complete: " . ($portfolio->isComplete() ? 'Yes' : 'No'));
            
            $this->info('✅ Portfolio test completed successfully!');
            
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
        }
    }
}
