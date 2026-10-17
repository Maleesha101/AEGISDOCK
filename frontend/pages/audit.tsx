import type { NextPage } from 'next';
import { useEffect, useState } from 'react';
import Head from 'next/head';
import Link from 'next/link';
import { getAuditLogs } from '../utils/api';

const AuditPage: NextPage = () => {
  const [auditLogs, setAuditLogs] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const loadAuditLogs = async () => {
      try {
        setLoading(true);
        const res = await getAuditLogs();
        setAuditLogs(res.data);
      } catch (err) {
        console.error('Failed to load audit logs:', err);
      } finally {
        setLoading(false);
      }
    };

    loadAuditLogs();
  }, []);

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-navy-500"></div>
      </div>
    );
  }

  return (
    <>
      <Head>
        <title>Audit - AegisDock</title>
      </Head>

      <div className="min-h-screen bg-gray-50">
        {/* Header */}
        <header className="bg-white shadow-sm">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div className="flex justify-between h-16">
              <div className="flex items-center">
                <Link href="/" className="flex-shrink-0">
                  <img className="h-8 w-8" src="https://via.placeholder.com/40" alt="AegisDock" />
                </Link>
                <div className="hidden md:block">
                  <div className="ml-10 flex items-baseline space-x-4">
                    <Link
                      href="/dashboard"
                      className="px-3 pt-2 border-b-2 border-transparent text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300"
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
                    <Link
                      href="/audit"
                      className="px-3 pt-2 border-b-2 border-navy-500 text-navy-600 border-b-2 font-medium"
                    >
                      Audit
                    </Link>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </header>

        {/* Main */}
        <main className="mt-10">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-8">
            <div className="mb-6">
              <h1 className="text-2xl font-bold text-navy-800">
                Audit Trail
              </h1>
              <p className="mt-2 text-gray-600">
                Review system events and authorization operations
              </p>
            </div>

            <div className="bg-white rounded-xl shadow">
              <div className="px-6 py-4 border-b border-gray-200">
                <h2 className="text-lg font-medium text-navy-600">System Events</h2>
              </div>
              <div className="divide-y divide-gray-200">
                {auditLogs.length > 0 ? (
                  auditLogs.map((log, index) => (
                    <div key={index} className="px-6 py-4 flex justify-between items-center">
                      <div>
                        <div className="flex items-center">
                          <span className="w-2 h-2 rounded-full bg-navy-500 mr-2"></span>
                          <span className="font-medium text-navy-800">{log.event}</span>
                        </div>
                        <p className="mt-1 text-sm text-gray-600">
                          Terminal: {log.terminal} • Container: {log.container} • Operator: {log.operator}
                        </p>
                        {log.details && (
                          <p className="mt-1 text-xs text-gray-500">{log.details}</p>
                        )}
                      </div>
                      <span className="text-sm text-gray-500">
                        {new Date(log.timestamp).toLocaleString()}
                      </span>
                    </div>
                  ))
                ) : (
                  <div className="px-6 py-12 text-center text-gray-500">
                    No audit events found
                  </div>
                )}
              </div>
            </div>
          </div>
        </main>
      </div>
    </>
  );
};

export default AuditPage;