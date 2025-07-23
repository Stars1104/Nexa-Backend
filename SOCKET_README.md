# Socket.IO Server Setup

This document explains how to set up and run the real-time chat Socket.IO server for the Nexa application.

## Prerequisites

-   Node.js (v16 or higher)
-   npm or yarn
-   Laravel backend running on port 8000

## Installation

1. Navigate to the backend directory:

```bash
cd backend
```

2. Install Socket.IO dependencies:

```bash
npm install
```

## Running the Socket.IO Server

### Development Mode

```bash
npm run dev
```

### Production Mode

```bash
npm start
```

The server will start on port 3001 by default. You can change this by setting the `SOCKET_PORT` environment variable.

## Configuration

### Environment Variables

-   `SOCKET_PORT`: Port for the Socket.IO server (default: 3001)

### CORS Configuration

The server is configured to accept connections from:

-   http://localhost:5173 (Vite dev server)
-   http://localhost:3000 (Alternative dev server)
-   http://localhost:4173 (Vite preview server)

## Features

### Real-time Chat Features

1. **Message Broadcasting**: Real-time message delivery to all users in a chat room
2. **Typing Indicators**: Shows when users are typing
3. **Online Status**: Tracks user online/offline status
4. **Read Receipts**: Shows when messages are read
5. **File Upload Progress**: Real-time file upload progress indicators

### Socket Events

#### Client to Server

-   `user_join`: User joins with authentication data
-   `join_room`: Join a specific chat room
-   `leave_room`: Leave a specific chat room
-   `send_message`: Send a new message
-   `typing_start`: Start typing indicator
-   `typing_stop`: Stop typing indicator
-   `mark_read`: Mark messages as read
-   `file_upload_progress`: File upload progress update

#### Server to Client

-   `new_message`: New message received
-   `user_typing`: User typing indicator
-   `messages_read`: Message read receipts
-   `file_upload_progress`: File upload progress
-   `connected_users_count`: Number of connected users

## Integration with Laravel

The Socket.IO server communicates with the Laravel backend via HTTP requests to:

-   Update user online status
-   Validate user authentication
-   Store messages in the database

## Troubleshooting

### Common Issues

1. **Port already in use**: Change the `SOCKET_PORT` environment variable
2. **CORS errors**: Ensure the frontend URL is in the CORS configuration
3. **Connection refused**: Make sure the Laravel backend is running on port 8000

### Logs

The server logs connection events and errors to the console. Check the terminal output for debugging information.

## Security Considerations

-   The server validates user authentication via HTTP requests to Laravel
-   CORS is configured to only allow specific origins
-   File uploads are validated and stored securely

## Production Deployment

For production deployment:

1. Set appropriate environment variables
2. Configure CORS for production domains
3. Use a process manager like PM2
4. Set up SSL/TLS for secure WebSocket connections
5. Configure proper logging and monitoring
