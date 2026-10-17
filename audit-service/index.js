const express = require('express');

const app = express();
app.use(express.json({ limit: '32kb' }));

// This service records simulated events only. It must never issue terminal commands.
app.get('/health', (req, res) => res.json({ success: true, service: 'audit-service', mode: 'simulation-only' }));

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

// Terminal release is deliberately not implemented in this service.
// Release authorization and simulated state changes belong to the Laravel API.

app.listen(3000, () => {
    console.log('AegisDock Audit Service running on port 3000');
    console.log('Endpoints:');
    console.log('  GET    /audit/logs');
    console.log('  POST   /audit/logs');
    console.log('  GET    /audit/terminals/:code');
    console.log('  POST   /audit/terminals/:code/release');
});