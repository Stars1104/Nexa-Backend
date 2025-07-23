const { createServer } = require('http');
const { Server } = require('socket.io');
const cors = require('cors');
const fetch = require('node-fetch');

const httpServer = createServer();
const io = new Server(httpServer, {
    cors: {
        origin: [
            "http://localhost:5000",
        ],
        methods: ["GET", "POST"],
        credentials: true
    },
    transports: ['websocket', 'polling'],
    pingTimeout: 60000,
    pingInterval: 25000,
    upgradeTimeout: 10000,
    allowUpgrades: true,
    maxHttpBufferSize: 1e8
});

// Store connected users
const connectedUsers = new Map();
const userRooms = new Map();

// Store user socket rooms for notifications
const userNotificationRooms = new Map();

// Make socket instance available globally for Laravel
global.socket_server = io;

io.on('connection', (socket) => {
    // User joins with authentication
    socket.on('user_join', (data) => {
        const { userId, userRole } = data;
        
        connectedUsers.set(socket.id, {
            userId,
            userRole,
            socketId: socket.id
        });
        
        userRooms.set(userId, socket.id);
        
        // Join user's notification room
        const notificationRoom = `user_${userId}`;
        socket.join(notificationRoom);
        userNotificationRooms.set(userId, notificationRoom);
        
        console.log(`User ${userId} joined notification room: ${notificationRoom}`);
    });

    // Join a specific chat room
    socket.on('join_room', (roomId) => {
        socket.join(roomId);
        
        // Log all sockets in the room
        io.in(roomId).fetchSockets().then(sockets => {
            console.log(`Room ${roomId} now has ${sockets.length} sockets:`, sockets.map(s => s.id));
        });
    });

    // Leave a specific chat room
    socket.on('leave_room', (roomId) => {
        socket.leave(roomId);
    });

    // Handle new message
    socket.on('send_message', (data) => {
        const { roomId, message, senderId, senderName, senderAvatar, messageType, fileData } = data;
        
        // Broadcast message to other users in the room (not back to sender)
        // Use io.to() instead of socket.to() for more reliable delivery
        socket.to(roomId).emit('new_message', {
            roomId,
            messageId: data.messageId, // Include the message ID from the database
            message,
            senderId,
            senderName,
            senderAvatar,
            messageType,
            fileData,
            timestamp: new Date().toISOString()
        });
        
        // Log all sockets in the room
        io.in(roomId).fetchSockets().then(sockets => {
            const otherSockets = sockets.filter(s => s.id !== socket.id);
        });
    });

    // Handle typing indicator
    socket.on('typing_start', (data) => {
        const { roomId, userId, userName } = data;
        
        // Get all sockets in the room
        io.in(roomId).fetchSockets().then(sockets => {
            const otherSockets = sockets.filter(s => s.id !== socket.id);
        });
        
        // Broadcast typing indicator to other users in the room
        socket.to(roomId).emit('user_typing', {
            roomId,
            userId,
            userName,
            isTyping: true
        });
        
    });

    socket.on('typing_stop', (data) => {
        const { roomId, userId, userName } = data;
        
        // Get all sockets in the room
        io.in(roomId).fetchSockets().then(sockets => {
            const otherSockets = sockets.filter(s => s.id !== socket.id);
        });
        
        // Broadcast typing stop to other users in the room
        socket.to(roomId).emit('user_typing', {
            roomId,
            userId,
            userName,
            isTyping: false
        });
        
    });

    // Handle message read status
    socket.on('mark_read', (data) => {
        const { roomId, messageIds, userId } = data;
        
        // Broadcast read status to all users in the room (including sender for their own messages)
        io.to(roomId).emit('messages_read', {
            roomId,
            messageIds,
            readBy: userId,
            timestamp: new Date().toISOString()
        });
        
    });

    // Handle file upload progress
    socket.on('file_upload_progress', (data) => {
        const { roomId, fileName, progress } = data;
        
        // Broadcast upload progress to other users in the room
        socket.to(roomId).emit('file_upload_progress', {
            roomId,
            fileName,
            progress
        });
    });

    // Handle disconnection
    socket.on('disconnect', (reason) => {
        const userData = connectedUsers.get(socket.id);
        
        if (userData) {
            const { userId } = userData;
            
            // Remove from connected users
            connectedUsers.delete(socket.id);
            userRooms.delete(userId);
            userNotificationRooms.delete(userId);
        }
        
    });
});

const PORT = process.env.SOCKET_PORT || 3001;

httpServer.listen(PORT, () => {
    console.log(`Socket.IO server running on port ${PORT}`);
    console.log(`CORS enabled for: http://localhost:5000, http://localhost:5173, http://localhost:3000, http://localhost:4173`);
});

module.exports = { io, connectedUsers, userRooms, userNotificationRooms }; 