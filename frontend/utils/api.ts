import axios from 'axios';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';

const api = axios.create({
  baseURL: API_BASE_URL,
  withCredentials: true,
});

// Add request interceptor for auth token
api.interceptors.request.use((config) => {
  const token = typeof window !== 'undefined' ? localStorage.getItem('auth_token') : null;
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Auth endpoints
export const login = (email: string, password: string) =>
  api.post('/api/login', { email, password });

export const getMe = () =>
  api.get('/api/me');

// Terminal endpoints
export const getTerminals = () =>
  api.get('/api/terminals');

export const getTerminal = (code: string) =>
  api.get(`/api/terminals/${code}`);

// Container endpoints
export const getContainers = (terminalId?: string) =>
  api.get('/api/containers', { params: terminalId ? { terminal_id: terminalId } : undefined });

export const getContainer = (containerNumber: string) =>
  api.get(`/api/containers/${containerNumber}`);

export const inspectContainer = (containerNumber: string) =>
  api.post(`/api/containers/${containerNumber}/inspect`);

export const holdContainer = (containerNumber: string, reason: string) =>
  api.post(`/api/containers/${containerNumber}/hold`, { reason });

export const releaseContainer = (containerNumber: string, reason: string, authArtifact?: string) =>
  api.post(`/api/containers/${containerNumber}/release`, { reason }, {
    headers: authArtifact ? { 'X-Terminal-Authorization': authArtifact } : undefined,
  });

// Authorization artifact endpoints
export const getAuthorizationArtifact = (terminalCode: string, containerNumber: string) =>
  api.get('/api/authorization/artifact', { params: { terminal: terminalCode, container: containerNumber } });

// Audit endpoints
export const getAuditLogs = () =>
  api.get('/api/audit');

// Debug endpoints (intentionally present for training)
export const getDebugArtifact = (artifactId: string) =>
  api.get(`/api/debug/authorization/${artifactId}`);

export default api;