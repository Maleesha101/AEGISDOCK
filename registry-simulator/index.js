const express = require('express');
const cors = require('cors');
const path = require('path');
const fs = require('fs');

const app = express();
app.use(cors());
app.use(express.json({ limit: '32kb' }));

const ARTIFACT_DIR = process.env.ARTIFACT_DIR || '/artifacts';
const IMAGE_ARTIFACTS = { 'aegisdock-terminal-api': 'aegisdock-terminal-api.tar' };

function flagValue(code) {
    const file = process.env.AEGISDOCK_FLAGS_FILE || '/run/secrets/lab_flags';
    try {
        const prefix = `${code.replace('-', '_')}=`;
        const line = fs.readFileSync(file, 'utf8').split(/\\r?\\n/).find((entry) => entry.startsWith(prefix));
        return line ? line.slice(prefix.length).trim() : null;
    } catch {
        return null;
    }
}

app.get('/health', (_req, res) => res.json({ success: true, service: 'registry-simulator' }));

// Simulated internal image registry
// In real life, this would be a Docker registry with authentication
// In our lab, this simulates an internal service that accidentally exposes image metadata

const IMAGE_METADATA = {
    'aegisdock-terminal-api': {
        name: 'aegisdock-terminal-api',
        tag: 'vulnerable',
        architecture: 'amd64',
        os: 'linux',
        created: '2025-01-15T10:30:00Z',
        size: '245MB',
        layers: 14,
        description: 'AegisDock Terminal API - Training version',
        exposed_ports: ['80'],
        environment: {
            APP_ENV: 'local',
            APP_DEBUG: 'true',
            CRYPTO_MODE: 'AES-256-CBC',
        }
    }
};

// GET /registry/images - List available images
app.get('/images', (req, res) => {
    res.json({
        success: true,
        images: Object.keys(IMAGE_METADATA).map(name => ({
            name,
            tag: IMAGE_METADATA[name].tag,
            size: IMAGE_METADATA[name].size,
            description: IMAGE_METADATA[name].description,
        }))
    });
});

// GET /registry/images/:name - Get image metadata
app.get('/images/:name', (req, res) => {
    const name = req.params.name;
    if (!IMAGE_METADATA[name]) {
        return res.status(404).json({ success: false, error: 'Image not found' });
    }
    res.json({ success: true, image: IMAGE_METADATA[name] });
});

// GET /registry/images/:name/download - Download the locally built training image.
app.get('/images/:name/download', (req, res) => {
    const name = req.params.name;
    const filename = IMAGE_ARTIFACTS[name];

    if (!filename || !IMAGE_METADATA[name]) {
        return res.status(404).json({ success: false, error: 'Image not found' });
    }

    const artifactPath = path.join(ARTIFACT_DIR, filename);
    if (!fs.existsSync(artifactPath)) {
        return res.status(404).json({
            success: false,
            error: 'The image artifact has not been built yet.',
            next_step: 'Run make build-vulnerable, then retry this download.',
        });
    }

    return res.download(artifactPath, filename);
});

// GET /registry/debug/layers/:name - Simulate layer inspection (for training)
app.get('/debug/layers/:name', (req, res) => {
    const name = req.params.name;
    res.json({
        success: true,
        name,
        note: 'Use `docker history --no-trunc` to inspect image layers',
        layers: [
            { id: 'layer_01', command: 'FROM php:8.3-apache', size: '120MB' },
            { id: 'layer_02', command: 'RUN apt-get install -y ...', size: '45MB' },
            { id: 'layer_03', command: 'COPY deploy/archive/terminal-gateway.env.bak /opt/legacy/terminal-gateway.env.bak', size: '0.2KB', secret: true },
            { id: 'layer_04', command: 'COPY api/ ./', size: '15MB' },
            { id: 'layer_05', command: 'RUN rm /opt/legacy/terminal-gateway.env.bak', size: '1KB' },
            { id: 'layer_06', command: 'EXPOSE 80', size: '0.1KB' },
            { id: 'layer_07', command: 'CMD ["apache2-foreground"]', size: '0.1KB' },
        ],
        warning: 'Layer 03 contains sensitive deployment artifact. Even though later layers remove it, the old layer retains the data.'
    });
});

// GET /registry/artifact/:name/history - Show image build history
app.get('/artifact/:name/history', (req, res) => {
    const name = req.params.name;
    res.json({
        success: true,
        name,
        history: [
            { layer: 'a1b2c3d4', created: '2025-01-15T10:00:00Z', command: 'FROM php:8.3-apache' },
            { layer: 'e5f6g7h8', created: '2025-01-15T10:05:00Z', command: 'RUN apt-get update && apt-get install -y ...' },
            { layer: 'i9j0k1l2', created: '2025-01-15T10:10:00Z', command: 'COPY deploy/archive/terminal-gateway.env.bak /opt/legacy/terminal-gateway.env.bak', secret: true },
            { layer: 'm3n4o5p6', created: '2025-01-15T10:15:00Z', command: 'COPY api/ ./', secret: false },
            { layer: 'q7r8s9t0', created: '2025-01-15T10:20:00Z', command: 'RUN rm /opt/legacy/terminal-gateway.env.bak', secret: false },
        ]
    });
});

app.listen(Number(process.env.PORT || 3000), () => {
    console.log('AegisDock Registry Simulator running on port 3000');
    console.log('Endpoints:');
    console.log('  GET  /images');
    console.log('  GET  /images/:name');
    console.log('  GET  /images/:name/download');
    console.log('  GET  /debug/layers/:name');
    console.log('  GET  /artifact/:name/history');
});
