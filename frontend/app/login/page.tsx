"use client";

import { FormEvent, useState } from "react";
import { useRouter } from "next/navigation";
import { apiRequest, setToken } from "../../lib/api";

type LoginResponse = {
  success: boolean;
  token: string;
  user: { name: string; email: string; role?: { name?: string } };
  message?: string;
};

export default function LoginPage() {
  const router = useRouter();
  const [email, setEmail] = useState("operator@aegisdock.local");
  const [password, setPassword] = useState("Operator123!");
  const [error, setError] = useState("");
  const [submitting, setSubmitting] = useState(false);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSubmitting(true);
    setError("");
    try {
      const result = await apiRequest<LoginResponse>("/login", {
        method: "POST",
        body: JSON.stringify({ email, password }),
      });
      if (!result.token) throw new Error("Login response did not include a session token.");
      setToken(result.token);
      router.replace("/");
    } catch (err) {
      setError(err instanceof Error ? err.message : "Unable to sign in.");
    } finally {
      setSubmitting(false);
    }
  }

  return <main className="login-shell">
    <section className="login-brand-panel">
      <div className="brand-lockup"><div className="brand-symbol">A</div><div><strong>AEGISDOCK</strong><span>PORT OPERATIONS</span></div></div>
      <div className="login-brand-copy"><p className="eyebrow">AUTONOMOUS PORT OPERATIONS</p><h1>Every movement.<br /><span>Accounted for.</span></h1><p>One operations workspace for terminal readiness, shipment visibility, and controlled container release.</p></div>
      <div className="login-illustration" aria-hidden="true"><div className="horizon" /><div className="crane crane-one"><i /><b /><em /></div><div className="crane crane-two"><i /><b /><em /></div><div className="container-stack"><span /><span /><span /><span /><span /><span /></div></div>
      <div className="login-footnote"><span className="live-dot" /> LOCAL TRAINING SIMULATION <span>·</span> No physical terminal connected</div>
    </section>
    <section className="login-form-panel">
      <div className="login-form-wrap"><p className="eyebrow">SECURE OPERATOR ACCESS</p><h2>Welcome back</h2><p className="login-subtitle">Sign in to continue to terminal operations.</p>
        <form onSubmit={submit} className="login-form">
          <label htmlFor="email">Work email</label><input id="email" type="email" autoComplete="username" value={email} onChange={(event) => setEmail(event.target.value)} required />
          <label htmlFor="password">Password</label><input id="password" type="password" autoComplete="current-password" value={password} onChange={(event) => setPassword(event.target.value)} required />
          {error && <div className="alert alert-error" role="alert">{error}</div>}
          <button className="login-submit" type="submit" disabled={submitting}>{submitting ? "Authenticating…" : "Sign in"} <span>→</span></button>
        </form>
        <div className="demo-credentials"><strong>Local training accounts</strong><p>Operator: operator@aegisdock.local / Operator123!</p><p>Supervisor: supervisor@aegisdock.local / Supervisor123!</p><p>Security admin: security@aegisdock.local / Security123!</p></div>
        <p className="login-security-note">Credentials are fake and intended only for this local laboratory.</p>
      </div>
    </section>
  </main>;
}
