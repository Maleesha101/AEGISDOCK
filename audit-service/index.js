const express = require('express');
const cors = require('cors');

const app = express();
app.use(cors());
app.use(express.json());

// Simulated terminal controller audit service
// In production, this would be an actual industrial control system
// For training, it simulates the audit trail of terminal operations

let auditLog = [
    {
        id: 1,
        timestamp: '2025-01-15T08:00:00Z',
        event: 'CONTAINER_ARRIVAL',
        terminal: 'T-01',
        container: 'CONT-88421',
        operator: 'system',
        status: 'LOGGED'
    },
    {
        id: 2,
        timestamp: '2025-01-15T09:30:00Z',
        event: 'AUTHORIZATION_ARTIFACT_CREATED',
        terminal: 'T-07',
        container: 'CONT-88421',
        operator: 'operator@aegisdock.local',
        status: 'LOGGED',
        details: 'Action: VIEW, Expires: 2025-01-15T10:30:00Z'
    },
    {
        id: 3,
        timestamp: '2025-01-15T10:00:00Z',
        event: 'CONTAINER_VIEWED',
        terminal: 'T-07',
        container: 'CONT-88421',
        operator: 'operator@aegisdock.local',
        status: 'LOGGED'
    },
    {
        id: 4,
        timestamp: '2025-01-15T10:15:00Z',
        event: 'CONTAINER_INSPECTED',
        terminal: 'T-07',
        container: 'CONT-88421',
        operator: 'operator@aegisdock.local',
        status: 'LOGGED',
        details: 'Cargo manifest verified, seals intact'
    }
];

// GET /audit/logs - Get audit trail
app.get('/logs', (req, res) => {
    const { terminal, container, limit = 50 } = req.query;
    let filtered = auditLog;

    if (terminal) {
        filtered = filtered.filter(log => log.terminal === terminal);
    }
    if (container) {
        filtered = filtered.filter(log => log.container === container);
    }

    res.json({
        success: true,
        total: filtered.length,
        logs: filtered.slice(0, parseInt(limit))
    });
});

// POST /audit/logs - Add audit entry
app.post('/logs', (req, res) => {
    const { event, terminal, container, operator, details, status } = req.body;

    if (!event || !terminal || !container || !operator) {
        return res.status(400).json({ success: false, error: 'Missing required fields' });
    }

    const newEntry = {
        id: auditLog.length + 1,
        timestamp: new Date().toISOString(),
        event,
        terminal,
        container,
        operator,
        status: status || 'LOGGED',
        details: details || null
    };

    auditLog.push(newEntry);
    res.json({ success: true, log: newEntry });
});

// GET /audit/terminals/:code - Get terminal status
app.get('/terminals/:code', (req, res) => {
    const { code } = req.params;
    res.json({
        success: true,
        terminal: {
            code,
            name: `Terminal ${code}`,
            status: 'OPERATIONAL',
            last_seen: new Date().toISOString(),
            containers_processed_today: 47
        }
    });
});

// POST /audit/terminals/:code/release - Simulate container release
app.post('/terminals/:code/release', (req, res) => {
    const { code } = req.params;
    const { container, operator, authorization_artifact } = req.body;

    // Log the release attempt
    const logEntry = {
        id: auditLog.length + 1,
        timestamp: new Date().toISOString(),
        event: 'CONTAINER_RELEASE_ATTEMPT',
        terminal: code,
        container: container || 'UNKNOWN',
        operator: operator || 'UNKNOWN',
        status: 'PROCESSED',
        details: authorization_artifact ? 'Authorization artifact provided' : 'No artifact provided'
    };
    auditLog.push(logEntry);

    res.json({
        success: true,
        message: `Container release command sent to terminal ${code}`,
        terminal: code,
        container,
        status: 'RELEASED',
        timestamp: new Date().toISOString()
    });
});

app.listen(3000, () => {
    console.log('AegisDock Audit Service running on port 3000');
    console.log('Endpoints:');
    console.log('  GET    /audit/logs');
    console.log('  POST   /audit/logs');
    console.log('  GET    /audit/terminals/:code');
    console.log('  POST   /audit/terminals/:code/release');
});