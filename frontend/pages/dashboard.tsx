import type { NextPage } from 'next';
import { useEffect, useState } from 'react';
import Head from 'next/head';
import Link from 'next/link';
import { getMe, getTerminals, getContainers, getAuditLogs } from '../utils/api';

const DashboardPage: NextPage = () => {
  const [user, setUser] = useState<any>(null);
  const [terminals, setTerminals] = useState<any[]>([]);
  const [containers, setContainers] = useState<any[]>([]);
  const [auditLogs, setAuditLogs] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const loadData = async () => {
      try {
        setLoading(true);
        const [userRes, terminalsRes, containersRes, auditRes] = await Promise.all([
          getMe(),
          getTerminals(),
          getContainers(),
          getAuditLogs()
        ]);

        setUser(userRes.data);
        setTerminals(terminalsRes.data);
        setContainers(containersRes.data);
        setAuditLogs(auditRes.data);
      } catch (err) {
        console.error('Failed to load dashboard data:', err);
      } finally {
        setLoading(false);
      }
    };

    loadData();
  }, []);

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-navy-500"></div>
      </div>
    );
  }

  if (!user) {
    return <div className="p-4">Redirecting...</div>;
  }

  return (
    <>
      <Head>
        <title>Dashboard - AegisDock</title>
      </Head>

      <div className="min-h-screen bg-gray-50">
        {/* Header */}
        <header className="bg-white shadow-sm">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div className="flex justify-between h-16">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <img className="h-8 w-8" src="https://via.placeholder.com/40" alt="AegisDock" />
                </div>
                <div className="hidden md:block">
                  <div className="ml-10 flex items-baseline space-x-4">
                    <Link
                      href="/dashboard"
                      className={`px-3 pt-2 border-b-2 border-transparent text-sm font-medium text-navy-600 hover:text-navy-800 hover:border-navy-300`}
                      aria-current="page"
                    >
                      Dashboard
                    </Link>
                    <Link
                      href="/terminals"
                      className="px-3 pt-2 border-b-2 border-transparent text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300"
                    >
                      Terminals
                    </Link>
                    <Link
                      href="/containers"
                      className="px-3 pt-2 border-b-2 border-transparent text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300"
                    >
                      Containers
                    </Link>
                  </div>
                </div>
              </div>

              <div className="hidden md:block">
                <div className="ml-4 flex items-center md:ml-6">
                  <span className="text-sm font-medium text-gray-600">
                    Welcome, {user.name} ({user.role?.name})
                  </span>
                </div>
              </div>
            </div>
          </div>
        </header>

        {/* Main */}
        <main className="mt-10">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-8">
            {/* Welcome */}
            <div className="mb-8">
              <h1 className="text-2xl font-bold text-navy-800">
                Port Operations Dashboard
              </h1>
              <p className="mt-2 text-gray-600">
                Current status of terminal operations and container movements
              </p>
            </div>

            {/* Stats Grid */}
            <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
              {/* Terminals */}
              <div className="bg-white rounded-xl shadow p-6">
                <div className="flex items-center justify-between">
                  <div>
                    <h3 className="text-sm font-medium text-gray-500">Active Terminals</h3>
                    <p className="mt-1 text-2xl font-bold text-navy-600">
                      {terminals.filter(t => t.status === 'OPERATIONAL').length}
                    </p>
                  </div>
                  <div className="flex h-10 w-10 items-center justify-center bg-navy-100 text-navy-500 rounded-lg">
                    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 7h18M3 12h18M3 17h18"></path>
                    </svg>
                  </div>
                </div>
              </div>

              {/* Containers */}
              <div className="bg-white rounded-xl shadow p-6">
                <div className="flex items-center justify-between">
                  <div>
                    <h3 className="text-sm font-medium text-gray-500">Total Containers</h3>
                    <p className="mt-1 text-2xl font-bold text-navy-600">
                      {containers.length}
                    </p>
                  </div>
                  <div className="flex h-10 w-10 items-center justify-center bg-navy-100 text-navy-500 rounded-lg">
                    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 7h18M3 12h18M3 17h18"></path>
                    </svg>
                  </div>
                </div>
              </div>

              {/* Released Today */}
              <div className="bg-white rounded-xl shadow p-6">
                <div className="flex items-center justify-between">
                  <div>
                    <h3 className="text-sm font-medium text-gray-500">Released Today</h3>
                    <p className="mt-1 text-2xl font-bold text-navy-600">
                      {containers.filter(c => c.status === 'RELEASED').length}
                    </p>
                  </div>
                  <div className="flex h-10 w-10 items-center justify-center bg-navy-100 text-navy-500 rounded-lg">
                    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                    </svg>
                  </div>
                </div>
              </div>

              {/* Held Containers */}
              <div className="bg-white rounded-xl shadow p-6">
                <div className="flex items-center justify-between">
                  <div>
                    <h3 className="text-sm font-medium text-gray-500">Held Containers</h3>
                    <p className="mt-1 text-2xl font-bold text-navy-600">
                      {containers.filter(c => c.status === 'HELD').length}
                    </p>
                  </div>
                  <div className="flex h-10 w-10 items-center justify-center bg-navy-100 text-navy-500 rounded-lg">
                    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                  </div>
                </div>
              </div>
            </div>

            {/* Recent Activity */}
            <div className="mt-8">
              <h2 className="text-xl font-bold text-navy-800 mb-4">
                Recent Activity
              </h2>
              <div className="bg-white rounded-xl shadow">
                <div className="px-6 py-4 border-b border-gray-200">
                  <h3 className="text-lg font-medium text-navy-600">Audit Trail</h3>
                </div>
                <div className="space-y-2">
                  {auditLogs.slice(0, 5).map((log, index) => (
                    <div key={index} className="px-6 py-3 border-t border-gray-100 last:border-b-0">
                      <div className="flex justify-between text-sm">
                        <span className="flex items-center">
                          <span className="w-8 inline-flex items-center justify-center text-xs font-medium rounded-full {
                            log.event.includes('RELEASE') ? 'bg-green-100 text-green-800' :
                            log.event.includes('HOLD') ? 'bg-yellow-100 text-yellow-800' :
                            'bg-gray-100 text-gray-800'
                          }">
                            {log.event.substring(0, 1)}
                          </span>
                          <span className="ml-2">{log.event}</span>
                        </span>
                        <span className="text-gray-500">
                          {new Date(log.timestamp).toLocaleTimeString()}
                        </span>
                      </div>
                      {log.details && (
                        <p className="mt-1 text-xs text-gray-600 pl-10">{log.details}</p>
                      )}
                    </div>
                  ))}
                  {auditLogs.length === 0 && (
                    <div className="px-6 py-4 text-center text-gray-500">
                      No recent activity
                    </div>
                  )}
                </div>
              </div>
            </div>
          </div>
        </main>
      </div>
    </>
  );
};

export default DashboardPage;