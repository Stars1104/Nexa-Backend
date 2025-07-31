<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class SocketServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register the socket server instance
        $this->app->singleton('socket.server', function ($app) {
            // Try to get the socket server instance from the global scope
            // This will be set by the socket server when it starts
            if (isset($GLOBALS['socket_server'])) {
                return $GLOBALS['socket_server'];
            }
            
            // If not available, return null (will be set later)
            return null;
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
} 