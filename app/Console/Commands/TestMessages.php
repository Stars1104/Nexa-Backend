<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'messages:test {--room-id= : Test specific room ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test message creation and retrieval to debug chat history issues';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing message creation and retrieval...');

        // Get a test user and room
        $user = User::first();
        if (!$user) {
            $this->error('No users found in database');
            return 1;
        }

        $room = ChatRoom::first();
        if (!$room) {
            $this->error('No chat rooms found in database');
            return 1;
        }

        $this->info("Using user: {$user->name} (ID: {$user->id})");
        $this->info("Using room: {$room->room_id} (ID: {$room->id})");

        // Check current message count
        $initialCount = Message::where('chat_room_id', $room->id)->count();
        $this->info("Initial message count: {$initialCount}");

        // Create a test message
        $testMessage = Message::create([
            'chat_room_id' => $room->id,
            'sender_id' => $user->id,
            'message' => 'Test message from command - ' . now(),
            'message_type' => 'text',
        ]);

        $this->info("Created test message with ID: {$testMessage->id}");

        // Check message count after creation
        $afterCreateCount = Message::where('chat_room_id', $room->id)->count();
        $this->info("Message count after creation: {$afterCreateCount}");

        // Verify the message exists
        $retrievedMessage = Message::find($testMessage->id);
        if ($retrievedMessage) {
            $this->info("✓ Message retrieved successfully");
            $this->info("  - ID: {$retrievedMessage->id}");
            $this->info("  - Message: {$retrievedMessage->message}");
            $this->info("  - Created: {$retrievedMessage->created_at}");
        } else {
            $this->error("✗ Message not found after creation!");
        }

        // Test room relationship
        $roomMessages = $room->messages()->get();
        $this->info("Room has {$roomMessages->count()} messages");

        // Test message relationship
        $messageRoom = $testMessage->chatRoom;
        if ($messageRoom) {
            $this->info("✓ Message room relationship works");
            $this->info("  - Room ID: {$messageRoom->id}");
            $this->info("  - Room ID string: {$messageRoom->room_id}");
        } else {
            $this->error("✗ Message room relationship failed!");
        }

        // Test sender relationship
        $messageSender = $testMessage->sender;
        if ($messageSender) {
            $this->info("✓ Message sender relationship works");
            $this->info("  - Sender ID: {$messageSender->id}");
            $this->info("  - Sender name: {$messageSender->name}");
        } else {
            $this->error("✗ Message sender relationship failed!");
        }

        // Check for any cascade issues
        $this->info("\nChecking for cascade constraints...");
        
        // Check if campaign exists
        $campaign = $room->campaign;
        if ($campaign) {
            $this->info("✓ Campaign exists: {$campaign->title} (ID: {$campaign->id})");
        } else {
            $this->error("✗ Campaign not found for room!");
        }

        // Check if brand exists
        $brand = $room->brand;
        if ($brand) {
            $this->info("✓ Brand exists: {$brand->name} (ID: {$brand->id})");
        } else {
            $this->error("✗ Brand not found for room!");
        }

        // Check if creator exists
        $creator = $room->creator;
        if ($creator) {
            $this->info("✓ Creator exists: {$creator->name} (ID: {$creator->id})");
        } else {
            $this->error("✗ Creator not found for room!");
        }

        // Clean up test message
        $testMessage->delete();
        $this->info("\nCleaned up test message");

        $finalCount = Message::where('chat_room_id', $room->id)->count();
        $this->info("Final message count: {$finalCount}");

        return 0;
    }
} 