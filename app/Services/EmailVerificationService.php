<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use Aws\Ses\SesClient;
use Aws\Exception\AwsException;
use Exception;

class EmailVerificationService
{
    /**
     * Send email verification with intelligent fallback
     */
    public static function sendVerificationEmail(User $user): array
    {
        Log::info('Attempting to send verification email to user: ' . $user->email);

        // Try AWS SES first (if configured)
        if (self::isSESConfigured()) {
            $sesResult = self::sendViaSES($user);
            if ($sesResult['success']) {
                Log::info('Email verification sent successfully via AWS SES for user: ' . $user->email);
                return $sesResult;
            }
        }

        // Fallback: Try SMTP (if configured)
        if (self::isSMTPConfigured()) {
            $smtpResult = self::sendViaSMTP($user);
            if ($smtpResult['success']) {
                Log::info('Email verification sent successfully via SMTP for user: ' . $user->email);
                return $smtpResult;
            }
        }

        // Final fallback: Log the email for debugging
        $logResult = self::sendViaLog($user);
        if ($logResult['success']) {
            Log::info('Email verification logged for debugging - user: ' . $user->email);
            return $logResult;
        }

        Log::error('All email sending methods failed for user: ' . $user->email);
        return [
            'success' => false,
            'message' => 'Failed to send verification email. Please contact support.',
            'method' => 'none',
            'verification_url' => null
        ];
    }

    /**
     * Check if AWS SES is properly configured
     */
    private static function isSESConfigured(): bool
    {
        return !empty(env('AWS_ACCESS_KEY_ID')) && 
               !empty(env('AWS_SECRET_ACCESS_KEY')) && 
               !empty(env('AWS_DEFAULT_REGION'));
    }

    /**
     * Check if SMTP is properly configured
     */
    private static function isSMTPConfigured(): bool
    {
        return !empty(env('MAIL_USERNAME')) && 
               !empty(env('MAIL_PASSWORD')) && 
               !empty(env('MAIL_HOST'));
    }

    /**
     * Try sending via AWS SES
     */
    private static function sendViaSES(User $user): array
    {
        try {
            $ses = new SesClient([
                'version' => 'latest',
                'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
                'credentials' => [
                    'key' => env('AWS_ACCESS_KEY_ID'),
                    'secret' => env('AWS_SECRET_ACCESS_KEY'),
                ],
            ]);

            // Check if sender email is verified
            $senderEmail = config('mail.from.address', 'dove.engineer86@gmail.com');
            $senderStatus = self::getEmailVerificationStatus($ses, $senderEmail);
            
            if ($senderStatus !== 'Success') {
                Log::warning("Sender email {$senderEmail} is not verified in SES. Attempting to verify...");
                
                try {
                    $ses->verifyEmailIdentity(['EmailAddress' => $senderEmail]);
                    Log::info("Verification email sent to sender: {$senderEmail}");
                    
                    // Wait a bit for verification to process
                    sleep(2);
                    
                    // Check status again
                    $senderStatus = self::getEmailVerificationStatus($ses, $senderEmail);
                } catch (Exception $e) {
                    Log::error("Failed to verify sender email {$senderEmail}: " . $e->getMessage());
                }
            }

            // If sender is verified, try to send the email
            if ($senderStatus === 'Success') {
                // Check if recipient email is verified (required in sandbox mode)
                $recipientStatus = self::getEmailVerificationStatus($ses, $user->email);
                
                if ($recipientStatus === 'Success') {
                    // Both emails verified, send the email
                    return self::sendEmailViaSES($ses, $user, $senderEmail);
                } else {
                    // Recipient not verified, try to verify it
                    Log::info("Recipient email {$user->email} is not verified. Attempting to verify...");
                    
                    try {
                        $ses->verifyEmailIdentity(['EmailAddress' => $user->email]);
                        Log::info("Verification email sent to recipient: {$user->email}");
                        
                        // Send a notification about verification
                        self::sendVerificationNotification($user);
                        return [
                            'success' => true,
                            'message' => 'Email verification sent via SES (recipient not verified)',
                            'method' => 'ses',
                            'verification_url' => null
                        ];
                    } catch (Exception $e) {
                        Log::error("Failed to verify recipient email {$user->email}: " . $e->getMessage());
                    }
                }
            }

            Log::warning("SES setup incomplete - sender or recipient not verified");
            return [
                'success' => false,
                'message' => 'SES setup incomplete - sender or recipient not verified',
                'method' => 'ses',
                'verification_url' => null
            ];

        } catch (Exception $e) {
            Log::error('SES email sending failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'SES email sending failed: ' . $e->getMessage(),
                'method' => 'ses',
                'verification_url' => null
            ];
        }
    }

    /**
     * Send email via SES once both emails are verified
     */
    private static function sendEmailViaSES(SesClient $ses, User $user, string $senderEmail): array
    {
        try {
            $verificationUrl = self::generateVerificationUrl($user);
            $logoUrl = self::generateLogoUrl();
            
            $result = $ses->sendEmail([
                'Source' => $senderEmail,
                'Destination' => ['ToAddresses' => [$user->email]],
                'Message' => [
                    'Subject' => ['Data' => 'Verify Email Address - Nexa Platform'],
                    'Body' => [
                        'Text' => ['Data' => self::getEmailText($user, $verificationUrl)],
                        'Html' => ['Data' => self::getEmailHtml($user, $verificationUrl, $logoUrl)]
                    ]
                ]
            ]);

            Log::info('Email verification sent successfully via SES for user: ' . $user->email);
            return [
                'success' => true,
                'message' => 'Email verification sent successfully via SES',
                'method' => 'ses',
                'verification_url' => $verificationUrl
            ];

        } catch (Exception $e) {
            Log::error('Failed to send email via SES: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send email via SES: ' . $e->getMessage(),
                'method' => 'ses',
                'verification_url' => null
            ];
        }
    }

    /**
     * Try sending via SMTP
     */
    private static function sendViaSMTP(User $user): array
    {
        try {
            // Configure mail to use SMTP
            Config::set('mail.default', 'smtp');
            Config::set('mail.mailers.smtp.transport', 'smtp');
            Config::set('mail.mailers.smtp.host', env('MAIL_HOST', 'smtp.gmail.com'));
            Config::set('mail.mailers.smtp.port', env('MAIL_PORT', 587));
            Config::set('mail.mailers.smtp.encryption', env('MAIL_ENCRYPTION', 'tls'));
            Config::set('mail.mailers.smtp.username', env('MAIL_USERNAME'));
            Config::set('mail.mailers.smtp.password', env('MAIL_PASSWORD'));
            Config::set('mail.from.address', config('mail.from.address', 'dove.engineer86@gmail.com'));
            Config::set('mail.from.name', env('MAIL_FROM_NAME', 'Nexa'));
            
            // Use our new template directly instead of the old Mailable
            $verificationUrl = self::generateVerificationUrl($user);
            $logoUrl = self::generateLogoUrl();
            
            Mail::raw(self::getEmailText($user, $verificationUrl), function($message) use ($user, $logoUrl) {
                $message->to($user->email)
                        ->subject('Verify Email Address - Nexa Platform')
                        ->setBody(self::getEmailHtml($user, self::generateVerificationUrl($user), $logoUrl), 'text/html');
            });
            
            Log::info('Email verification sent successfully via SMTP for user: ' . $user->email);
            return [
                'success' => true,
                'message' => 'Email verification sent successfully via SMTP',
                'method' => 'smtp',
                'verification_url' => $verificationUrl
            ];

        } catch (Exception $e) {
            Log::error('SMTP email sending failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'SMTP email sending failed: ' . $e->getMessage(),
                'method' => 'smtp',
                'verification_url' => null
            ];
        }
    }

    /**
     * Send via log for debugging purposes
     */
    private static function sendViaLog(User $user): array
    {
        try {
            $verificationUrl = self::generateVerificationUrl($user);
            $logoUrl = self::generateLogoUrl();
            
            Log::info('=== NEW EMAIL VERIFICATION TEMPLATE LOGGED ===');
            Log::info('To: ' . $user->email);
            Log::info('Subject: Verify Email Address - Nexa Platform');
            Log::info('Verification URL: ' . $verificationUrl);
            Log::info('--- HTML Content Preview ---');
            Log::info(self::getEmailHtml($user, $verificationUrl, $logoUrl));
            Log::info('--- Text Content Preview ---');
            Log::info(self::getEmailText($user, $verificationUrl));
            Log::info('=====================================');
            
            return [
                'success' => true,
                'message' => 'Email verification logged for debugging',
                'method' => 'log',
                'verification_url' => $verificationUrl
            ];

        } catch (Exception $e) {
            Log::error('Failed to log email: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to log email: ' . $e->getMessage(),
                'method' => 'log',
                'verification_url' => null
            ];
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
            Log::error('Failed to get verification status for ' . $email . ': ' . $e->getMessage());
            return 'Error';
        }
    }

    /**
     * Get plain text email content
     */
    private static function getEmailText(User $user, string $verificationUrl): string
    {
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
    private static function getEmailHtml(User $user, string $verificationUrl, string $logoUrl): string
    {
        $year = date('Y');

        return <<<HTML
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='utf-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Verify Email Address - Nexa Platform</title>
                <style>
                    @media only screen and (max-width: 600px) {
                        .container { padding: 15px !important; }
                        .header { padding: 20px 15px !important; }
                        .content { padding: 25px 20px !important; }
                        .logo { width: 80px !important; height: auto !important; }
                        h1 { font-size: 24px !important; }
                        h2 { font-size: 20px !important; }
                        .cta-button { padding: 14px 25px !important; font-size: 16px !important; }
                    }
                </style>
            </head>
            <body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #374151; margin: 0; padding: 0; background-color: #f9fafb;">
                <div class="container" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);">
                    
                    <!-- Header Section -->
                    <div class="header" style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); padding: 30px 20px; text-align: center; border-radius: 8px 8px 0 0;">
                        <div style="margin-bottom: 20px;">
                            <img src="{$logoUrl}" 
                                alt="Nexa Logo" 
                                class="logo" 
                                style="width: 100px; background: rgba(255, 255, 255, 0.1); padding: 10px; display: block; margin: 0 auto;">
                        </div>
                        <h1 style="color: #ffffff; margin: 0; font-size: 28px; font-weight: 700; text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);">
                            Welcome to Nexa-UGC!
                        </h1>
                    </div>
                    
                    <!-- Main Content -->
                    <div class="content" style="padding: 40px 30px;">
                        <h2 style="color: #1f2937; margin: 0 0 20px 0; font-size: 22px; font-weight: 600;">
                            🎉 Congratulations {$user->name}!
                        </h2>
                        
                        <p style="font-size: 16px; margin-bottom: 25px; color: #4b5563; line-height: 1.7;">
                            Thank you for joining our community! To get started and unlock all the amazing features of Nexa, please confirm your email address by clicking the button below.
                        </p>
                        
                        <!-- CTA Button -->
                        <div style="text-align: center; margin: 35px 0;">
                            <a href="{$verificationUrl}" 
                            class="cta-button"
                            style="background: linear-gradient(135deg, #e91e63 0%, #f06292 100%); color: white; padding: 16px 32px; text-decoration: none; border-radius: 50px; font-weight: 600; font-size: 16px; display: inline-block; box-shadow: 0 4px 15px rgba(233, 30, 99, 0.3); transition: all 0.3s ease; border: none;">
                                ✨ Verify My Email Address
                            </a>
                        </div>
                        
                        <!-- Security Notice -->
                        <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 20px; border-radius: 6px; margin: 30px 0;">
                            <p style="margin: 0; color: #92400e; font-size: 14px; font-weight: 500;">
                                🔒 <strong>Security Note:</strong> For your protection, this verification link will expire in <strong>60 minutes</strong>.
                            </p>
                        </div>
                        
                        <!-- Additional Info -->
                        <p style="font-size: 14px; color: #6b7280; margin-bottom: 15px; line-height: 1.6;">
                            If you didn't create a Nexa account, you can safely ignore this email. No action is required.
                        </p>
                        
                        <p style="font-size: 14px; color: #6b7280; margin-bottom: 0; line-height: 1.6;">
                            Having trouble? Contact our support team and we'll be happy to help!
                        </p>
                    </div>
                    
                    <!-- Footer -->
                    <div style="background: #f8fafc; padding: 25px 30px; border-radius: 0 0 8px 8px; border-top: 1px solid #e5e7eb;">
                        <div style="text-align: center; margin-bottom: 20px;">
                            <p style="margin: 0; color: #6b7280; font-size: 14px; font-weight: 500;">
                                Best regards,<br>
                                <span style="color: #6366f1; font-weight: 600;">The Nexa Team</span>
                            </p>
                        </div>
                        
                        <div style="text-align: center; margin-bottom: 15px;">
                            <a href="mailto:support@nexacreators.com.br" style="color: #6366f1; text-decoration: none; font-size: 14px; font-weight: 500;">
                                📧 support@nexacreators.com.br
                            </a>
                        </div>
                        
                        <div style="text-align: center; border-top: 1px solid #e5e7eb; padding-top: 15px;">
                            <p style="margin: 0; color: #9ca3af; font-size: 12px;">
                                © {$year} Nexa UGC. All rights reserved.
                            </p>
                        </div>
                    </div>
                </div>
            </body>
            </html>
        HTML;
    }



    /**
     * Generate verification URL
     */
    private static function generateVerificationUrl(User $user): string
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
        
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
     * Generate logo URL for emails
     */
    private static function generateLogoUrl(): string
    {
        // Option 1: Use environment variable for logo URL (most reliable)
        if (!empty(env('EMAIL_LOGO_URL'))) {
            Log::info('Using EMAIL_LOGO_URL from environment: ' . env('EMAIL_LOGO_URL'));
            return env('EMAIL_LOGO_URL');
        }
        
        // Option 2: Use app URL + storage path
        $appUrl = config('app.url');
        if (!empty($appUrl) && $appUrl !== 'http://localhost:8000') {
            $logoUrl = $appUrl . '/storage/logo.png';
            Log::info('Using app URL + storage path for logo: ' . $logoUrl);
            return $logoUrl;
        }
        
        // Option 3: Use a placeholder logo (fallback)
        Log::warning('Using placeholder logo as fallback - consider setting EMAIL_LOGO_URL environment variable');
        return 'https://via.placeholder.com/100x100/6366f1/ffffff?text=N';
    }

    /**
     * Debug method to check logo URL generation
     */
    public static function debugLogoUrl(): array
    {
        return [
            'env_email_logo_url' => env('EMAIL_LOGO_URL'),
            'app_url' => config('app.url'),
            'generated_logo_url' => self::generateLogoUrl(),
            'storage_link_exists' => is_link(public_path('storage')),
            'logo_file_exists' => file_exists(storage_path('app/public/logo.png')),
        ];
    }

    /**
     * Send verification notification to user
     */
    private static function sendVerificationNotification(User $user): void
    {
        try {
            Log::info("Verification notification sent to user: {$user->email}");
            Log::info("User needs to check their email and click the verification link from AWS SES");
        } catch (Exception $e) {
            Log::error('Failed to send verification notification: ' . $e->getMessage());
        }
    }

    /**
     * Test method to preview email templates (for development/testing)
     */
    public static function testTemplate(User $user): array
    {
        $verificationUrl = self::generateVerificationUrl($user);
        $logoUrl = self::generateLogoUrl();
        
        return [
            'html' => self::getEmailHtml($user, $verificationUrl, $logoUrl),
            'text' => self::getEmailText($user, $verificationUrl),
            'verification_url' => $verificationUrl
        ];
    }
} 