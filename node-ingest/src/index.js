import express from 'express';
import Redis from 'ioredis';
import { z } from 'zod';
import dotenv from 'dotenv';

dotenv.config();

const app = express();
app.use(express.json());

// Initialize Redis client connecting to our Docker container
const redis = new Redis({
    host: process.env.REDIS_HOST || 'redis',
    port: parseInt(process.env.REDIS_PORT || '6379'),
});

redis.on('connect', () => console.log('⚡ Connected to Redis Mesh Successfully.'));
redis.on('error', (err) => console.error('❌ Redis Connection Error:', err));

// Define runtime validation schema using Zod
const TelemetrySchema = z.object({
    shipment_id: z.number().int().positive(),
    latitude: z.number().min(-90).max(90),
    longitude: z.number().min(-180).max(180),
    speed_kmh: z.number().min(0).max(500),
    timestamp: z.string().datetime() // Ensures ISO 8601 string format
});

app.post('/api/v1/telemetry', async (req, res) => {
    try {
        // 1. Validate incoming telemetry payload
        const validatedData = TelemetrySchema.parse(req.body);

        // 2. Wrap payload with an ingestion receipt timestamp
        const queuePayload = {
            ...validatedData,
            ingested_at: new Date().toISOString()
        };

        // 3. Push to Redis List (RPUSH acting as a FIFO Queue)
        await redis.rpush('telemetry_stream', JSON.stringify(queuePayload));

        // 4. Return HTTP 202 (Accepted) immediately to free the client
        return res.status(202).json({ 
            status: 'success', 
            message: 'Telemetry payload queued for processing.' 
        });

    } catch (error) {
        if (error instanceof z.ZodError) {
            return res.status(400).json({ 
                status: 'error', 
                errors: error.errors 
            });
        }
        
        console.error('System Exception:', error);
        return res.status(500).json({ 
            status: 'error', 
            message: 'Internal ingestion pipeline failure.' 
        });
    }
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
    console.log(`🚀 Ingestion Server active on port ${PORT}`);
});