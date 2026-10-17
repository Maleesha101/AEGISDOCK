import type { NextPage } from 'next';
import { useEffect, useState } from 'react';
import Head from 'next/head';
import Link from 'next/link';
import { getTerminals, getTerminal } from '../utils/api';

const TerminalsPage: NextPage = () => {
  const [terminals, setTerminals] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const loadTerminals = async () => {
      try {
        setLoading(true);
        const res = await getTerminals();
        setTerminals(res.data);
      } catch (err: any) {
        setError(err.response?.data?.message || 'Failed to load terminals');
      } finally {
        setLoading(false);
      }
    };

    loadTerminals();
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
        <title>Terminals - AegisDock</title>
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
                <nav className="hidden md:block">
                  <div className="ml-10 flex items-baseline space-x-4">
                    <Link href="/dashboard" className="px-3 pt-2 border-b-2 border-transparent text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">Dashboard</Link>
                    <Link href="/terminals" className="px-3 pt-2 border-b-2 border-navy-500 text-navy-600 font-medium">Terminals</Link>
                    <Link href="/containers" className="px-3 pt-2 border-b-2 border-transparent text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">Containers</Link>
                  </div>
                </nav>
              </div>
              <div className="hidden md:block">
                <div className="ml-4 flex items-center md:ml-6">
                  <Link href="/terminals/new" className="px-4 py-2 bg-navy-600 hover:bg-navy-700 text-white font-medium rounded-lg text-sm">New Terminal</Link>
                </div>
              </div>
            </div>
          </div>
        </header>

        {/* Main */}
        <main className="mt-10">
          {error && (
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-6">
              <div className="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded">
                {error}
              </div>
            </div>
          )}

          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-8">
            <div className="mb-6">
              <h1 className="text-2xl font-bold text-navy-800">
                Terminal Management
              </h1>
              <p className="mt-2 text-gray-600">
                Monitor and manage port terminal operations
              </p>
            </div>

            {terminals.length > 0 ? (
              <div className="space-y-4">
                {terminals.map((terminal) => (
                  <div key={terminal.id} className="bg-white rounded-xl shadow overflow-hidden">
                    <div className="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                      <div className="flex items-center space-x-4">
                        <div className="w-10 h-10 bg-navy-100 rounded-lg flex items-center justify-center">
                          <span className="text-navy-600 font-bold text-lg">{terminal.code}</span>
                        </div>
                        <div>
                          <h3 className="text-lg font-medium text-navy-800">{terminal.name}</h3>
                          <p className="text-sm text-gray-500">{terminal.location}</p>
                        </div>
                      </div>
                      <div className="flex items-center space-x-3">
                        <span className={`px-3 py-1 text-xs font-medium rounded-full ${
                          terminal.status === 'OPERATIONAL' ? 'bg-green-100 text-green-800' :
                          terminal.status === 'MAINTENANCE' ? 'bg-yellow-100 text-yellow-800' :
                          'bg-red-100 text-red-800'
                        }`}>
                          {terminal.status}
                        </span>
                        <Link
                          href={`/terminals/${terminal.code}`}
                          className="text-sm font-medium text-navy-600 hover:text-navy-800"
                        >
                          Details
                        </Link>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <div className="text-center py-12">
                <p className="text-gray-500">No terminals found</p>
              </div>
            )}
          </div>
        </main>
      </div>
    </>
  );
};

export default TerminalsPage;