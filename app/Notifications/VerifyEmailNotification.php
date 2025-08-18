<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Verificar Endereço de Email - Plataforma Nexa')
            ->greeting('Olá ' . $notifiable->name . '!')
            ->line('Obrigado por se registrar na Nexa! Por favor, clique no botão abaixo para verificar seu endereço de email.')
            ->action('Verificar Endereço de Email', $verificationUrl)
            ->line('Se você não criou uma conta, nenhuma ação adicional é necessária.')
            ->line('Este link de verificação expirará em 60 minutos.')
            ->salutation('Atenciosamente, A Equipe Nexa');
    }

    /**
     * Get the verification URL for the given notifiable.
     */
    protected function verificationUrl($notifiable)
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:5000');
        
        // Create a simple verification URL with parameters
        $id = $notifiable->getKey();
        $hash = sha1($notifiable->getEmailForVerification());
        $expires = Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60))->timestamp;
        
        // Create a simple hash for verification (in production, you might want to use a more secure method)
        $signature = hash_hmac('sha256', $id . $hash . $expires, config('app.key'));
        
        return $frontendUrl . '/verify-email?' . http_build_query([
            'id' => $id,
            'hash' => $hash,
            'signature' => $signature,
            'expires' => $expires
        ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
} 