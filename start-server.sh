#!/bin/bash

# Nexa Backend Server Startup Script
# This script starts the Laravel development server

echo "🚀 Starting Nexa Backend Server..."

# Check if PHP is installed
if ! command -v php &> /dev/null; then
    echo "❌ PHP is not installed. Please install PHP 8.1+ first."
    exit 1
fi

# Check if Composer is installed
if ! command -v composer &> /dev/null; then
    echo "❌ Composer is not installed. Please install Composer first."
    exit 1
fi

# Check if we're in the backend directory
if [ ! -f "artisan" ]; then
    echo "❌ Please run this script from the backend directory."
    exit 1
fi

# Check if .env file exists
if [ ! -f ".env" ]; then
    echo "❌ .env file not found. Please create one from .env.example first."
    exit 1
fi

# Install dependencies if vendor directory doesn't exist
if [ ! -d "vendor" ]; then
    echo "📦 Installing PHP dependencies..."
    composer install
fi

# Generate application key if not set
if ! grep -q "APP_KEY=base64:" .env; then
    echo "🔑 Generating application key..."
    php artisan key:generate
fi

# Clear and cache config
echo "⚙️  Optimizing application..."
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Check if port 8000 is already in use
if lsof -Pi :8000 -sTCP:LISTEN -t >/dev/null ; then
    echo "⚠️  Port 8000 is already in use. Stopping existing process..."
    lsof -ti:8000 | xargs kill -9
    sleep 2
fi

# Start the server
echo "🌐 Starting Laravel development server on http://localhost:8000"
echo "📱 API will be available at http://localhost:8000/api"
echo "🔄 Press Ctrl+C to stop the server"
echo ""

php artisan serve --host=0.0.0.0 --port=8000 