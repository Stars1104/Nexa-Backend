const { io } = require('socket.io-client');

// Test socket connection
const socket = io('http://localhost:3001', {
    transports: ['websocket', 'polling'],
    autoConnect: true,
});

socket.on('connect', () => {
    console.log('✅ Connected to socket server');
    
    // Join a test room
    socket.emit('user_join', {
        userId: 999,
        userRole: 'test'
    });
    
    socket.emit('join_room', 'test-room-123');
    
    // Send a test message
    setTimeout(() => {
        socket.emit('send_message', {
            roomId: 'test-room-123',
            message: 'Test message from Node.js',
            senderId: 999,
            senderName: 'Test User',
            senderAvatar: null,
            messageType: 'text',
            fileData: null
        });
    }, 2000);
});

socket.on('new_message', (data) => {
    console.log('📨 Received message:', data);
});

socket.on('disconnect', () => {
    console.log('❌ Disconnected from socket server');
});

socket.on('connect_error', (error) => {
    console.error('❌ Connection error:', error);
});

// Test HTTP endpoint
const fetch = require('node-fetch');

setTimeout(async () => {
    try {
        const response = await fetch('http://localhost:3001/emit', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                event: 'new_message',
                data: {
                    roomId: 'test-room-123',
                    messageId: 12345,
                    message: 'Test message via HTTP',
                    senderId: 888,
                    senderName: 'HTTP Test User',
                    senderAvatar: null,
                    messageType: 'text',
                    fileData: null,
                    timestamp: new Date().toISOString()
                }
            })
        });
        
        const result = await response.json();
        console.log('📡 HTTP test result:', result);
    } catch (error) {
        console.error('❌ HTTP test error:', error);
    }
}, 3000);

// Keep the script running
setTimeout(() => {
    console.log('🔄 Test completed, keeping connection alive...');
}, 5000);
