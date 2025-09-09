<?php

namespace App\Mail;

use App\Models\User;
use App\Services\EmailVerificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerificationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $verificationUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
        $this->verificationUrl = $this->generateVerificationUrl();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verificar Endereço de Email - Plataforma Nexa',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Use our working EmailVerificationService instead of the broken Blade template
        $result = EmailVerificationService::testTemplate($this->user);
        
        return new Content(
            htmlString: $result['html'],
            with: [
                'user' => $this->user,
                'verificationUrl' => $this->verificationUrl,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Generate verification URL
     */
    private function generateVerificationUrl(): string
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:5000');
        
        $id = $this->user->getKey();
        $hash = sha1($this->user->getEmailForVerification());
        $expires = now()->addMinutes(config('auth.verification.expire', 60))->timestamp;
        
        $signature = hash_hmac('sha256', $id . $hash . $expires, config('app.key'));
        
        return $frontendUrl . '/verify-email?' . http_build_query([
            'id' => $id,
            'hash' => $hash,
            'signature' => $signature,
            'expires' => $expires
        ]);
    }
} 