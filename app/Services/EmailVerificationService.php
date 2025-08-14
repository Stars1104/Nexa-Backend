<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Aws\Ses\SesClient;
use Exception;

class EmailVerificationService
{
    /**
     * Send email verification with intelligent fallback
     */
    public static function sendVerificationEmail(User $user): bool
    {
        // Try AWS SES first
        if (self::sendViaSES($user)) {
            return true;
        }

        // Fallback: Send via Laravel's default mailer
        if (self::sendViaLaravelMailer($user)) {
            return true;
        }

        // Final fallback: Log and return false
        Log::warning('All email sending methods failed for user: ' . $user->email);
        return false;
    }

    /**
     * Try sending via AWS SES
     */
    private static function sendViaSES(User $user): bool
    {
        try {
            $ses = new SesClient([
                'version' => 'latest',
                'region' => env('AWS_DEFAULT_REGION'),
                'credentials' => [
                    'key' => env('AWS_ACCESS_KEY_ID'),
                    'secret' => env('AWS_SECRET_ACCESS_KEY'),
                ],
            ]);

            // Check if recipient email is verified in SES
            $verificationStatus = self::getEmailVerificationStatus($ses, $user->email);
            
            if ($verificationStatus === 'Success') {
                // Send via SES
                $result = $ses->sendEmail([
                    'Source' => 'dove.engineer86@gmail.com',
                    'Destination' => ['ToAddresses' => [$user->email]],
                    'Message' => [
                        'Subject' => ['Data' => 'Verify Email Address - Nexa Platform'],
                        'Body' => [
                            'Text' => ['Data' => self::getEmailText($user)],
                            'Html' => ['Data' => self::getEmailHtml($user)]
                        ]
                    ]
                ]);

                Log::info('Email verification sent via SES for user: ' . $user->email);
                return true;
            }

            // If email not verified, try to verify it
            if ($verificationStatus === 'NotVerified') {
                try {
                    $ses->verifyEmailIdentity(['EmailAddress' => $user->email]);
                    Log::info('Verification email sent to: ' . $user->email);
                    
                    // Also send a notification email to the user about verification
                    self::sendVerificationNotification($user);
                    
                    // Return true since we initiated verification
                    return true;
                } catch (Exception $e) {
                    Log::error('Failed to send AWS verification: ' . $e->getMessage());
                    // Fall through to Laravel mailer
                }
            }

            return false;
        } catch (Exception $e) {
            Log::error('SES email sending failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Try sending via Laravel's default mailer
     */
    private static function sendViaLaravelMailer(User $user): bool
    {
        try {
            // Configure mail to use SES properly
            config([
                'mail.default' => 'ses',
                'mail.mailers.ses.transport' => 'ses',
                'mail.from.address' => env('MAIL_FROM_ADDRESS', 'no-reply@nexacreators.com.br'),
                'mail.from.name' => env('MAIL_FROM_NAME', 'Nexa'),
                'services.ses.key' => env('AWS_ACCESS_KEY_ID'),
                'services.ses.secret' => env('AWS_SECRET_ACCESS_KEY'),
                'services.ses.region' => env('AWS_DEFAULT_REGION', 'sa-east-1'),
            ]);
            
            Mail::to($user->email)->send(new \App\Mail\VerificationEmail($user));
            
            Log::info('Email verification sent via Laravel mailer for user: ' . $user->email);
            return true;
        } catch (Exception $e) {
            Log::error('Laravel mailer failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get email verification status from SES
     */
    private static function getEmailVerificationStatus(SesClient $ses, string $email): string
    {
        try {
            $result = $ses->getIdentityVerificationAttributes(['Identities' => [$email]]);
            
            if (isset($result['VerificationAttributes'][$email]['VerificationStatus'])) {
                return $result['VerificationAttributes'][$email]['VerificationStatus'];
            }
            
            return 'NotVerified';
        } catch (Exception $e) {
            Log::error('Failed to get verification status: ' . $e->getMessage());
            return 'Error';
        }
    }

    /**
     * Get plain text email content
     */
    private static function getEmailText(User $user): string
    {
        $verificationUrl = self::generateVerificationUrl($user);
        
        return "Hello {$user->name}!\n\n" .
               "Thank you for registering with Nexa! Please click the link below to verify your email address:\n\n" .
               "{$verificationUrl}\n\n" .
               "If you did not create an account, no further action is required.\n\n" .
               "This verification link will expire in 60 minutes.\n\n" .
               "Best regards,\nThe Nexa Team";
    }

    /**
     * Get HTML email content
     */
    private static function getEmailHtml(User $user): string
    {
        $verificationUrl = self::generateVerificationUrl($user);
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Verify Email Address - Nexa Platform</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='text-align: center; margin-bottom: 30px;'>
                <h1 style='color: #6366f1; margin: 0;'>Nexa</h1>
            </div>
            
            <div style='background: #f8fafc; padding: 30px; border-radius: 10px; border: 1px solid #e2e8f0;'>
                <h2 style='color: #1e293b; margin-top: 0;'>Hello {$user->name}!</h2>
                
                <p style='font-size: 16px; margin-bottom: 20px;'>
                    Thank you for registering with Nexa! Please click the button below to verify your email address.
                </p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$verificationUrl}' 
                       style='background: #6366f1; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-weight: 600; display: inline-block;'>
                        Verify Email Address
                    </a>
                </div>
                
                <p style='font-size: 14px; color: #64748b; margin-bottom: 15px;'>
                    If you did not create an account, no further action is required.
                </p>
                
                <p style='font-size: 14px; color: #64748b; margin-bottom: 0;'>
                    This verification link will expire in 60 minutes.
                </p>
            </div>
            
            <div style='text-align: center; margin-top: 30px; color: #64748b; font-size: 14px;'>
                <p>Best regards,<br>The Nexa Team</p>
            </div>
        </body>
        </html>";
    }

    /**
     * Generate verification URL
     */
    private static function generateVerificationUrl(User $user): string
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:5000');
        
        $id = $user->getKey();
        $hash = sha1($user->getEmailForVerification());
        $expires = now()->addMinutes(config('auth.verification.expire', 60))->timestamp;
        
        $signature = hash_hmac('sha256', $id . $hash . $expires, config('app.key'));
        
        return $frontendUrl . '/verify-email?' . http_build_query([
            'id' => $id,
            'hash' => $hash,
            'signature' => $signature,
            'expires' => $expires
        ]);
    }

    /**
     * Send verification notification to user
     */
    private static function sendVerificationNotification(User $user): void
    {
        try {
            // Configure mail to use SES properly
            config([
                'mail.default' => 'ses',
                'mail.mailers.ses.transport' => 'ses',
                'mail.from.address' => env('MAIL_FROM_ADDRESS', 'no-reply@nexacreators.com.br'),
                'mail.from.name' => env('MAIL_FROM_NAME', 'Nexa'),
                'services.ses.key' => env('AWS_ACCESS_KEY_ID'),
                'services.ses.secret' => env('AWS_SECRET_ACCESS_KEY'),
                'services.ses.region' => env('AWS_DEFAULT_REGION', 'sa-east-1'),
            ]);
            
            Mail::to($user->email)->send(new \App\Mail\VerificationEmail($user));
            
            Log::info('Verification notification sent via Laravel mailer for user: ' . $user->email);
        } catch (Exception $e) {
            Log::error('Failed to send verification notification: ' . $e->getMessage());
        }
    }
} 