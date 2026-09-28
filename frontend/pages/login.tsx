import type { NextPage } from 'next';
import { useState } from 'react';
import Head from 'next/head';
import { useRouter } from 'next/router';
import { login } from '../utils/api';

const LoginPage: NextPage = () => {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const router = useRouter();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    try {
      const response = await login(email, password);
      const { token, user } = response.data;

      // Store token
      localStorage.setItem('auth_token', token);
      localStorage.setItem('user', JSON.stringify(user));

      // Redirect based on role
      if (user.role?.name === 'security_admin') {
        router.push('/security');
      } else if (user.role?.name === 'supervisor') {
        router.push('/supervisor');
      } else {
        router.push('/dashboard');
      }
    } catch (err: any) {
      setError(err.response?.data?.message || 'Login failed');
    } finally {
      setLoading(false);
    }
  };

  return (
    <>
      <Head>
        <title>Login - AegisDock</title>
      </Head>

      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="bg-white rounded-xl shadow-2xl w-full max-w-md p-8 space-y-6">
          <div className="text-center">
            <h1 className="text-2xl font-bold text-navy-800">
              Sign In
            </h1>
            <p className="text-gray-600">
              Access the AegisDock training environment
            </p>
          </div>

          {error && (
            <div className="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded">
              {error}
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Email Address
              </label>
              <input
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-navy-500 focus:border-navy-500 transition-colors"
                placeholder="operator@aegisdock.local"
                disabled={loading}
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Password
              </label>
              <input
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-navy-500 focus:border-navy-500 transition-colors"
                placeholder="••••••••"
                disabled={loading}
              />
            </div>

            <button
              type="submit"
              disabled={loading || !(email && password)}
              className="w-full bg-navy-600 hover:bg-navy-700 text-white font-medium py-3 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center gap-2"
            >
              {loading ? (
                <>
                  <svg className="animate-spin w-5 h-5" aria-hidden="true" fill="none" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                  </svg>
                  <span>Signing in...</span>
                </>
              ) : (
                <>
                  <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                  Sign In
                </>
              )}
            </button>

            <div className="text-center mt-4">
              <p className="text-sm text-gray-500">
                Demo Credentials:
              </p>
              <div className="mt-2 space-y-1 text-xs">
                <div className="flex flex-col">
                  <span className="font-medium">Operator:</span>
                  <span>operator@aegisdock.local / Operator123!</span>
                </div>
                <div className="flex flex-col">
                  <span className="font-medium">Supervisor:</span>
                  <span>supervisor@aegisdock.local / Supervisor123!</span>
                </div>
                <div className="flex flex-col">
                  <span className="font-medium">Security:</span>
                  <span>security@aegisdock.local / Security123!</span>
                </div>
              </div>
            </div>
          </form>

          <div className="text-center text-xs text-gray-400">
            <p>Security Training Lab • Intentionally Vulnerable System</p>
          </div>
        </div>
      </div>
    </>
  );
};

export default LoginPage;