"use client";

import { FormEvent, useCallback, useEffect, useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { apiRequest, clearToken, getToken } from "../lib/api";

type User = { id: number; name: string; email: string; role?: { name?: string } };
type Terminal = { id: number; code: string; name: string; location: string; status: string };
type ContainerRecord = {
  id: number;
  container_number: string;
  terminal_id: number;
  terminal?: Terminal;
  owner: string;
  cargo_type: string;
  status: string;
};
type AuditRecord = {
  id: number;
  action: string;
  resource_type: string;
  resource_id: number | string;
  created_at: string;
  metadata?: Record<string, unknown>;
};
type PaginatedAudit = { data?: AuditRecord[] };
type FlagRecord = { code: string; description: string; points: number; submitted: boolean };
type ApiResponse<T> = { success: boolean; user?: User; data?: T; message?: string };

const statusClass = (status: string) => {
  const value = status.toLowerCase();
  if (value === "ready" || value === "operational" || value === "released") return "status status-good";
  if (value === "held" || value === "maintenance" || value === "inspected") return "status status-warn";
  return "status";
};

export default function DashboardPage() {
  const router = useRouter();
  const [user, setUser] = useState<User | null>(null);
  const [terminals, setTerminals] = useState<Terminal[]>([]);
  const [containers, setContainers] = useState<ContainerRecord[]>([]);
  const [audit, setAudit] = useState<AuditRecord[]>([]);
  const [flags, setFlags] = useState<FlagRecord[]>([]);
  const [flagCode, setFlagCode] = useState('FLAG-01');
  const [flagInput, setFlagInput] = useState('');
  const [loading, setLoading] = useState(true);
  const [busyId, setBusyId] = useState<string | null>(null);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [artifact, setArtifact] = useState("");

  const role = user?.role?.name || "operator";
  const canRelease = role === "supervisor" || role === "security_admin";
  const readyCount = useMemo(() => containers.filter((item) => item.status === "READY").length, [containers]);
  const heldCount = useMemo(() => containers.filter((item) => item.status === "HELD").length, [containers]);

  const loadDashboard = useCallback(async () => {
    setError("");
    try {
      const [me, terminalResponse, containerResponse, auditResponse, flagResponse] = await Promise.all([
        apiRequest<ApiResponse<never>>("/me"),
        apiRequest<ApiResponse<Terminal[]>>("/terminals"),
        apiRequest<ApiResponse<ContainerRecord[]>>("/containers"),
        apiRequest<ApiResponse<PaginatedAudit>>("/audit?limit=6"),
        apiRequest<ApiResponse<FlagRecord[]>>("/flags"),
      ]);
      if (!me.user) throw new Error("Unable to load authenticated operator.");
      setUser(me.user);
      setTerminals(terminalResponse.data || []);
      setContainers(containerResponse.data || []);
      setAudit(auditResponse.data?.data || []);
      setFlags(flagResponse.data || []);
    } catch (err) {
      const message = err instanceof Error ? err.message : "Could not load the operations dashboard.";
      if (!getToken()) {
        router.replace("/login");
        return;
      }
      setError(message);
    } finally {
      setLoading(false);
    }
  }, [router]);

  useEffect(() => {
    if (!getToken()) {
      router.replace("/login");
      return;
    }
    void loadDashboard();
  }, [loadDashboard, router]);

  async function performAction(container: ContainerRecord, action: "inspect" | "hold" | "release") {
    setBusyId(container.container_number);
    setNotice("");
    setError("");
    try {
      await apiRequest<ApiResponse<ContainerRecord>>(
        `/containers/${encodeURIComponent(container.container_number)}/${action}`,
        { method: "POST", body: JSON.stringify({ reason: "Requested from operations dashboard" }) },
      );
      setNotice(`${container.container_number}: ${action} request completed.`);
      await loadDashboard();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Operation failed.");
    } finally {
      setBusyId(null);
    }
  }

  async function requestArtifact(container: ContainerRecord) {
    setError("");
    setNotice("");
    try {
      const terminalCode = container.terminal?.code || terminals.find((item) => item.id === container.terminal_id)?.code;
      if (!terminalCode) throw new Error("Terminal details are not available.");
      const result = await apiRequest<ApiResponse<{ artifact: string; action: string; expires_at: number }>>(
        `/authorization/artifact?terminal=${encodeURIComponent(terminalCode)}&container=${encodeURIComponent(container.container_number)}`,
      );
      setArtifact(result.data?.artifact || "");
      setNotice(`A ${result.data?.action || "VIEW"} authorization artifact was generated for ${container.container_number}.`);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Could not generate authorization artifact.");
    }
  }

  async function submitFlag(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError('');
    setNotice('');
    try {
      const result = await apiRequest<ApiResponse<{ code: string; points: number }>>('/flags/submit', {
        method: 'POST',
        body: JSON.stringify({ code: flagCode, flag: flagInput }),
      });
      setFlagInput('');
      setNotice(result.message || `Accepted ${flagCode} (+${result.data?.points || 0} points).`);
      await loadDashboard();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Flag submission failed.');
    }
  }

  function signOut() {
    clearToken();
    router.replace("/login");
  }

  if (loading) {
    return <main className="loading-screen"><div className="brand-symbol">A</div><p>Connecting to terminal operations…</p></main>;
  }

  return (
    <main className="app-shell">
      <aside className="sidebar">
        <div className="brand-lockup">
          <div className="brand-symbol">A</div>
          <div><strong>AEGISDOCK</strong><span>PORT OPERATIONS</span></div>
        </div>
        <div className="nav-label">WORKSPACE</div>
        <nav className="side-nav" aria-label="Main navigation">
          <a className="nav-item active" href="#overview"><span className="nav-icon">▦</span>Overview</a>
          <a className="nav-item" href="#terminals"><span className="nav-icon">⌁</span>Terminals</a>
          <a className="nav-item" href="#containers"><span className="nav-icon">▤</span>Containers</a>
          <a className="nav-item" href="#audit"><span className="nav-icon">◷</span>Audit trail</a>
          <a className="nav-item" href="#flags"><span className="nav-icon">⚑</span>CTF progress</a>
          <a className="nav-item" href="#security"><span className="nav-icon">◇</span>Security center</a>
        </nav>
        <div className="sidebar-bottom">
          <span className="live-dot" />
          <div><strong>LOCAL SIMULATION</strong><small>No physical terminal connected</small></div>
        </div>
      </aside>

      <section className="main-area" id="overview">
        <header className="topbar">
          <div><p className="breadcrumb">OPERATIONS <span>/</span> OVERVIEW</p><h1>Terminal operations</h1></div>
          <div className="user-menu">
            <div className="user-avatar">{(user?.name || "OP").split(" ").map((part) => part[0]).slice(0, 2).join("").toUpperCase()}</div>
            <div className="user-details"><strong>{user?.name || "Operator"}</strong><span>{role.replace("_", " ")} · authenticated</span></div>
            <button className="signout-button" onClick={signOut} type="button">Sign out</button>
          </div>
        </header>

        <section className="hero-panel">
          <div className="hero-copy"><p className="eyebrow">SHIFT CONTROL / LIVE VIEW</p><h2>Good day, {user?.name?.split(" ")[0] || "operator"}.</h2><p>Monitor terminal readiness, review container movements, and keep every release accountable.</p></div>
          <div className="hero-status"><span className="live-dot" /> SIMULATION ACTIVE <span className="hero-divider" /> <span>UTC {new Date().toLocaleTimeString("en-GB", { timeZone: "UTC", hour: "2-digit", minute: "2-digit" })}</span></div>
        </section>

        {error && <div className="alert alert-error" role="alert">{error}</div>}
        {notice && <div className="alert alert-success" role="status">{notice}</div>}

        <section className="metric-grid" aria-label="Operations metrics">
          <article className="metric-card"><div className="metric-top"><span>TERMINALS</span><span className="metric-icon">⌁</span></div><strong>{terminals.length.toString().padStart(2, "0")}</strong><small><span className="metric-positive">●</span> Registered locations</small></article>
          <article className="metric-card"><div className="metric-top"><span>TRACKED CONTAINERS</span><span className="metric-icon">▤</span></div><strong>{containers.length.toString().padStart(2, "0")}</strong><small>Across all terminals</small></article>
          <article className="metric-card"><div className="metric-top"><span>READY TO MOVE</span><span className="metric-icon">↗</span></div><strong>{readyCount.toString().padStart(2, "0")}</strong><small>Awaiting authorized action</small></article>
          <article className="metric-card"><div className="metric-top"><span>ACTIVE HOLDS</span><span className="metric-icon">Ⅱ</span></div><strong>{heldCount.toString().padStart(2, "0")}</strong><small>Requires operator attention</small></article>
        </section>

        <section className="content-panel" id="flags">
          <div className="section-heading"><div><p className="eyebrow">INVESTIGATION TRACKER</p><h2>CTF progress</h2></div><span className="section-count">{flags.filter((flag) => flag.submitted).length} / {flags.length} flags</span></div>
          <form className="flag-submit-form" onSubmit={submitFlag}>
            <label htmlFor="flag-code">Stage</label>
            <select id="flag-code" value={flagCode} onChange={(event) => setFlagCode(event.target.value)}>
              {flags.map((flag) => <option key={flag.code} value={flag.code}>{flag.code} · {flag.description}</option>)}
            </select>
            <label htmlFor="flag-value">Flag value</label>
            <input id="flag-value" value={flagInput} onChange={(event) => setFlagInput(event.target.value)} placeholder="AEGIS{...}" autoComplete="off" required />
            <button className="action-button action-submit-flag" type="submit">Submit flag</button>
          </form>
          <div className="flag-grid">{flags.map((flag) => <article className={`flag-card ${flag.submitted ? 'flag-complete' : ''}`} key={flag.code}>
            <div><strong>{flag.code}</strong><span>{flag.submitted ? 'COMPLETED' : `${flag.points} PTS`}</span></div>
            <p>{flag.description}</p>
          </article>)}</div>
        </section>

        <section className="content-panel" id="terminals">
          <div className="section-heading"><div><p className="eyebrow">INFRASTRUCTURE</p><h2>Terminal status</h2></div><span className="section-count">{terminals.length} locations</span></div>
          <div className="terminal-grid">
            {terminals.map((terminal) => <article className="terminal-card" key={terminal.id}>
              <div className="terminal-card-top"><span className="terminal-glyph">⌁</span><span className={statusClass(terminal.status)}>{terminal.status}</span></div>
              <p className="terminal-code">{terminal.code}</p><h3>{terminal.name}</h3><p className="terminal-location">{terminal.location}</p>
            </article>)}
            {terminals.length === 0 && <p className="empty-state">No terminals found. Run the database seeder to load training data.</p>}
          </div>
        </section>

        <section className="content-panel" id="containers">
          <div className="section-heading"><div><p className="eyebrow">SHIPMENT MANAGEMENT</p><h2>Container movements</h2></div><span className="section-count">{containers.length} records</span></div>
          <div className="table-scroll"><table className="container-table"><thead><tr><th>CONTAINER</th><th>TERMINAL</th><th>OWNER / CARGO</th><th>STATUS</th><th>OPERATIONS</th></tr></thead>
            <tbody>{containers.map((container) => <tr key={container.id}>
              <td><strong className="container-id">{container.container_number}</strong><small>Record #{container.id}</small></td>
              <td><span className="terminal-chip">{container.terminal?.code || terminals.find((item) => item.id === container.terminal_id)?.code || "—"}</span></td>
              <td><strong>{container.owner}</strong><small>{container.cargo_type}</small></td>
              <td><span className={statusClass(container.status)}>{container.status}</span></td>
              <td><div className="action-row">
                <button className="action-button" disabled={busyId === container.container_number} onClick={() => void performAction(container, "inspect")}>Inspect</button>
                {canRelease && <button className="action-button action-secondary" disabled={busyId === container.container_number} onClick={() => void performAction(container, "hold")}>Hold</button>}
                <button className="action-button action-quiet" onClick={() => void requestArtifact(container)}>Artifact</button>
                {canRelease ? <button className="action-button action-release" disabled={busyId === container.container_number || container.status === "RELEASED"} onClick={() => void performAction(container, "release")}>Release</button> : <span className="permission-hint">Supervisor release</span>}
              </div></td>
            </tr>)}</tbody>
          </table></div>
        </section>

        {artifact && <section className="artifact-panel"><div className="section-heading"><div><p className="eyebrow">AUTHORIZATION</p><h2>Encrypted artifact</h2></div><button className="action-button" onClick={() => setArtifact("")}>Close</button></div><p>This is the encoded training artifact returned by the API. Copy it into your authorized local lab investigation when needed.</p><code className="artifact-value">{artifact}</code><button className="action-button" onClick={() => void navigator.clipboard.writeText(artifact)}>Copy artifact</button></section>}

        <section className="content-panel" id="audit">
          <div className="section-heading"><div><p className="eyebrow">OBSERVABILITY</p><h2>Recent audit activity</h2></div><span className="section-count">{audit.length} events</span></div>
          <div className="audit-list">
            {audit.map((event) => <article className="audit-row" key={event.id}><div className="audit-marker" /><div className="audit-main"><strong>{event.action.replaceAll("_", " ")}</strong><span>{event.resource_type} · {event.resource_id}</span></div><time>{new Date(event.created_at).toLocaleString()}</time></article>)}
            {audit.length === 0 && <p className="empty-state">No audit activity yet. Inspect a container to create the first event.</p>}
          </div>
        </section>

        <footer className="app-footer"><span>AEGISDOCK OPERATIONS SYSTEM</span><span id="security">Training environment · no physical infrastructure connected</span></footer>
      </section>
    </main>
  );
}
