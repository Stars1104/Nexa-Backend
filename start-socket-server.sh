#!/bin/bash

# Nexa Socket Server Startup Script
# This script starts the Socket.IO server for real-time communication

echo "🚀 Starting Nexa Socket Server..."

# Check if Node.js is installed
if ! command -v node &> /dev/null; then
    echo "❌ Node.js is not installed. Please install Node.js first."
    exit 1
fi

# Check if npm is installed
if ! command -v npm &> /dev/null; then
    echo "❌ npm is not installed. Please install npm first."
    exit 1
fi

# Check if we're in the backend directory
if [ ! -f "package.json" ]; then
    echo "❌ Please run this script from the backend directory."
    exit 1
fi

# Install dependencies if node_modules doesn't exist
if [ ! -d "node_modules" ]; then
    echo "📦 Installing Node.js dependencies..."
    npm install
fi

# Check if port 3001 is already in use
if lsof -Pi :3001 -sTCP:LISTEN -t >/dev/null ; then
    echo "⚠️  Port 3001 is already in use. Stopping existing process..."
    lsof -ti:3001 | xargs kill -9
    sleep 2
fi

# Start the socket server
echo "🌐 Starting Socket.IO server on http://localhost:3001"
echo "🔄 Press Ctrl+C to stop the server"
echo ""

node socket-server.js
