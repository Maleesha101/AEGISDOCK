const API_BASE = process.env.NEXT_PUBLIC_API_URL || "/api";

export type ApiEnvelope<T> = {
  success: boolean;
  data: T;
  message?: string;
};

export function getToken(): string | null {
  if (typeof window === "undefined") return null;
  return window.sessionStorage.getItem("aegisdock_token");
}

export function setToken(token: string): void {
  window.sessionStorage.setItem("aegisdock_token", token);
}

export function clearToken(): void {
  if (typeof window !== "undefined") {
    window.sessionStorage.removeItem("aegisdock_token");
  }
}

export async function apiRequest<T>(path: string, init: RequestInit = {}): Promise<T> {
  const headers = new Headers(init.headers);
  const token = getToken();
  if (token) headers.set("Authorization", `Bearer ${token}`);
  if (init.body && !headers.has("Content-Type")) {
    headers.set("Content-Type", "application/json");
  }

  const response = await fetch(`${API_BASE}${path}`, {
    ...init,
    headers,
    cache: "no-store",
  });
  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    if (response.status === 401) clearToken();
    throw new Error(payload.message || `Request failed (${response.status})`);
  }

  return payload as T;
}
