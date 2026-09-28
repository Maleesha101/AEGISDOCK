import type { NextPage } from 'next';
import { useEffect, useState } from 'react';
import Head from 'next/head';
import Link from 'next/link';
import { useRouter } from 'next/router';
import { getTerminal, getContainers } from '../../utils/api';

const TerminalDetailPage: NextPage = () => {
  const router = useRouter();
  const { code } = router.query;
  const [terminal, setTerminal] = useState<any>(null);
  const [containers, setContainers] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!code) return;

    const loadData = async () => {
      try {
        setLoading(true);
        const [termRes, contRes] = await Promise.all([
          getTerminal(code as string),
          getContainers(code as string)
        ]);
        setTerminal(termRes.data);
        setContainers(contRes.data);
      } catch (err) {
        console.error('Failed to load terminal:', err);
      } finally {
        setLoading(false);
      }
    };

    loadData();
  }, [code]);

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-navy-500"></div>
      </div>
    );
  }

  if (!terminal) {
    return <div className="p-4">Terminal not found</div>;
  }

  return (
    <>
      <Head>
        <title>Terminal {code} - AegisDock</title>
      </Head>

      <div className="min-h-screen bg-gray-50">
        <header className="bg-white shadow-sm">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div className="flex justify-between h-16">
              <div className="flex items-center">
                <Link href="/terminals" className="flex-shrink-0">
                  <img className="h-8 w-8" src="https://via.placeholder.com/40" alt="AegisDock" />
                </Link>
                <div className="hidden md:block">
                  <div className="ml-10 flex items-baseline space-x-4">
                    <Link
                      href="/dashboard"
                      className="px-3 pt-2 border-b-2 border-transparent text-sm font-medium text-gray-500 hover:text-gray-700"
                    >
                      Dashboard
                    </Link>
                    <Link
                      href="/terminals"
                      className="px-3 pt-2 border-b-2 border-navy-500 text-navy-600 border-b-2 font-medium"
                    >
                      Terminals
                    </Link>
                    <Link
                      href="/containers"
                      className="px-3 pt-2 border-b-2 border-transparent text-sm font-medium text-gray-500 hover:text-gray-700"
                    >
                      Containers
                    </Link>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </header>

        <main className="mt-10">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-8">
            <div className="mb-8">
              <div className="flex items-center justify-between">
                <div>
                  <Link
                    href="/terminals"
                    className="text-sm text-navy-600 hover:text-navy-800 mb-2 block"
                  >
                    ← Back to Terminals
                  </Link>
                  <h1 className="text-2xl font-bold text-navy-800">
                    Terminal {terminal.code}
                  </h1>
                  <p className="mt-2 text-gray-600">{terminal.name} • {terminal.location}</p>
                </div>
                <span className={`px-3 py-1 text-xs font-medium rounded-full ${
                  terminal.status === 'OPERATIONAL' ? 'bg-green-100 text-green-800' :
                  terminal.status === 'MAINTENANCE' ? 'bg-yellow-100 text-yellow-800' :
                  'bg-red-100 text-red-800'
                }`}>
                  {terminal.status}
                </span>
              </div>
            </div>

            <div className="bg-white rounded-xl shadow">
              <div className="px-6 py-4 border-b border-gray-200">
                <h2 className="text-lg font-medium text-navy-600">Containers at this Terminal</h2>
              </div>
              <div className="divide-y divide-gray-200">
                {containers.length > 0 ? (
                  containers.map((container) => (
                    <Link
                      key={container.id}
                      href={`/containers/${container.container_number}`}
                      className="block px-6 py-4 hover:bg-gray-50"
                    >
                      <div className="flex items-center justify-between">
                        <div className="flex items-center space-x-4">
                          <div className="w-10 h-10 bg-navy-100 rounded-lg flex items-center justify-center">
                            <span className="text-navy-600 font-bold text-lg">
                              {container.container_number.slice(0, 4)}
                            </span>
                          </div>
                          <div>
                            <p className="text-lg font-medium text-navy-800">
                              {container.container_number}
                            </p>
                            <p className="text-sm text-gray-500">
                              {container.cargo_type} • {container.owner}
                            </p>
                          </div>
                        </div>
                        <span className={`px-3 py-1 text-xs font-medium rounded-full ${
                          container.status === 'READY' ? 'bg-green-100 text-green-800' :
                          container.status === 'HELD' ? 'bg-yellow-100 text-yellow-800' :
                          container.status === 'RELEASED' ? 'bg-blue-100 text-blue-800' :
                          'bg-gray-100 text-gray-800'
                        }`}>
                          {container.status}
                        </span>
                      </div>
                    </Link>
                  ))
                ) : (
                  <div className="px-6 py-12 text-center text-gray-500">
                    No containers at this terminal
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

export default TerminalDetailPage;