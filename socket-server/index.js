require('dotenv').config();
const { Server } = require('socket.io');
const { createClient } = require('redis');
const axios = require('axios');

const io = new Server(process.env.PORT || 3000, {
    cors: {
        origin: "*",
        methods: ["GET", "POST"]
    }
});

const redisClient = createClient({
    url: `redis://${process.env.REDIS_HOST}:${process.env.REDIS_PORT}`
});

redisClient.on('error', (err) => console.log('Redis Client Error', err));

// Middleware for auth
io.use(async (socket, next) => {
    const token = socket.handshake.auth.token || socket.handshake.headers.authorization;

    if (!token) {
        return next(new Error('Authentication error: No token provided'));
    }

    try {
        // Clean "Bearer " prefix if present
        const cleanToken = token.replace('Bearer ', '');

        // Call Backend to verify user
        // We need to run the backend server for this to work.
        // In this environment, we assume backend is running on localhost:8000
        const response = await axios.get(`${process.env.BACKEND_URL}/me`, {
            headers: {
                'Authorization': `Bearer ${cleanToken}`,
                'Accept': 'application/json'
            }
        });

        socket.user = response.data;
        console.log(`User authenticated: ${socket.user.id} - ${socket.user.name}`);
        next();
    } catch (err) {
        console.error('Auth failed:', err.message);
        next(new Error('Authentication error: Invalid token'));
    }
});

io.on('connection', (socket) => {
    console.log(`Client connected: ${socket.id}`);

    // Join user-specific room
    socket.join(`user_${socket.user.id}`);

    socket.on('join_match', (matchId) => {
        console.log(`User ${socket.user.id} joining match_${matchId}`);
        socket.join(`match_${matchId}`);
    });

    socket.on('leave_match', (matchId) => {
        socket.leave(`match_${matchId}`);
    });

    socket.on('join_lobby', () => {
        socket.join('lobby');
    });

    socket.on('disconnect', () => {
        console.log(`Client disconnected: ${socket.id}`);
    });
});

async function startRedisSubscriber() {
    await redisClient.connect();

    // Subscribe to 'dots_events' channel from Laravel
    await redisClient.subscribe('dots_events', (message) => {
        try {
            const payload = JSON.parse(message);
            const { event, data, room } = payload;

            console.log(`Broadcasting ${event} to ${room}`);

            if (room) {
                io.to(room).emit(event, data);
            } else {
                io.emit(event, data);
            }
        } catch (e) {
            console.error('Error parsing Redis message:', e);
        }
    });
}

startRedisSubscriber();

console.log(`Socket.IO server running on port ${process.env.PORT || 3000}`);
