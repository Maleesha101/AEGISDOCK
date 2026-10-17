import type { NextPage } from 'next';
import { useEffect, useState } from 'react';
import Head from 'next/head';
import Link from 'next/link';
import { getContainers, getContainer, inspectContainer, holdContainer, releaseContainer,  } from '../utils/api';
import { useRouter } from 'next/router';

const ContainersPage: NextPage = () => {
  const [containers, setContainers] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedContainer, setSelectedContainer] = useState<any>(null);
  const router = useRouter();

  useEffect(() => {
    const loadContainers = async () => {
      try {
        setLoading(true);
        const res = await getContainers();
        setContainers(res.data);
      } catch (err: any) {
        setError(err.response?.data?.message || 'Failed to load containers');
      } finally {
        setLoading(false);
      }
    };

    loadContainers();
  }, []);

  const handleInspect = async (containerNumber: string) => {
    try {
      await inspectContainer(containerNumber);
      // Refresh container data
      const res = await getContainer(containerNumber);
      setSelectedContainer(res.data);
      // Refresh containers list
      const containersRes = await getContainers();
      setContainers(containersRes.data);
    } catch (err: any) {
      alert(err.response?.data?.message || 'Inspection failed');
    }
  };

  const handleHold = async (containerNumber: string) => {
    try {
      await holdContainer(containerNumber, 'Security hold for inspection');
      // Refresh container data
      const res = await getContainer(containerNumber);
      setSelectedContainer(res.data);
      // Refresh containers list
      const containersRes = await getContainers();
      setContainers(containersRes.data);
    } catch (err: any) {
      alert(err.response?.data?.message || 'Hold failed');
    }
  };

  const handleRelease = async (containerNumber: string) => {
    try {
      // Normal UI operations use server-side role authorization. The
      // vulnerable artifact path remains available for manual lab investigation.
      await releaseContainer(containerNumber, 'Authorized release');

      const res = await getContainer(containerNumber);
      setSelectedContainer(res.data);
      const containersRes = await getContainers();
      setContainers(containersRes.data);
    } catch (err: any) {
      alert(err.response?.data?.message || 'Release failed');
    }
  };;

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
        <title>Containers - AegisDock</title>
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
                      className="px-3 pt-2 border-b-2 border-navy-500 text-navy-600 border-b-2 font-medium"
                    >
                      Containers
                    </Link>
                  </div>
                </div>
              </div>

              <div className="hidden md:block">
                <div className="ml-4 flex items-center md:ml-6">
                  <button
                    href="/containers/new"
                    className="px-4 py-2 bg-navy-600 hover:bg-navy-700 text-white font-medium rounded-lg text-sm"
                  >
                    New Container
                  </button>
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
                Container Management
              </h1>
              <p className="mt-2 text-gray-600">
                Track and manage container movements through port terminals
              </p>
            </div>

            {/* Container List */}
            <div className="space-y-4">
              {containers.length > 0 ? (
                <div>
                  {containers.map((container) => (
                    <div key={container.id} className="bg-white rounded-xl shadow overflow-hidden">
                      <div className="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <div className="flex items-center space-x-4">
                          <div className="w-10 h-10 bg-navy-100 rounded-lg flex items-center justify-center">
                            <span className="text-navy-600 font-bold text-lg">{container.container_number.slice(0, 4)}</span>
                          </div>
                          <div>
                            <h3 className="text-lg font-medium text-navy-800">
                              {container.container_number}
                            </h3>
                            <p className="text-sm text-gray-500">
                              {container.cargo_type} • Terminal {container.terminal?.code}
                            </p>
                          </div>
                        </div>
                        <div className="flex items-center space-x-3">
                          <span className={`px-3 py-1 text-xs font-medium rounded-full ${container.status === 'READY' ? 'bg-green-100 text-green-800' : container.status === 'HELD' ? 'bg-yellow-100 text-yellow-800' : container.status === 'RELEASED' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'}`}>
                            {container.status}
                          </span>
                          <div className="flex space-x-2">
                            <button
                              onClick={() => handleInspect(container.container_number)}
                              disabled={container.status === 'RELEASED'}
                              className="px-3 py-1 text-xs font-medium bg-navy-600 hover:bg-navy-700 text-white rounded"
                            >
                              Inspect
                            </button>
                            <button
                              onClick={() => handleHold(container.container_number)}
                              disabled={container.status !== 'READY'}
                              className="px-3 py-1 text-xs font-medium bg-yellow-600 hover:bg-yellow-700 text-white rounded"
                            >
                              Hold
                            </button>
                            <button
                              onClick={() => handleRelease(container.container_number)}
                              disabled={container.status !== 'READY'}
                              className="px-3 py-1 text-xs font-medium bg-green-600 hover:bg-green-700 text-white rounded"
                            >
                              Release
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="text-center py-12">
                  <p className="text-gray-500">No containers found</p>
                </div>
              )}
            </div>

            {/* Selected Container Details */}
            {selectedContainer && (
              <div className="mt-8">
                <div className="bg-white rounded-xl shadow">
                  <div className="px-6 py-4 border-b border-gray-200">
                    <h2 className="text-xl font-bold text-navy-800">
                      Container Details: {selectedContainer.container_number}
                    </h2>
                  </div>
                  <div className="px-6 py-6 space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                      <div>
                        <p className="text-sm font-medium text-gray-500">Container Number</p>
                        <p className="text-lg font-medium text-navy-800">
                          {selectedContainer.container_number}
                        </p>
                      </div>
                      <div>
                        <p className="text-sm font-medium text-gray-500">Terminal</p>
                        <p className="text-lg font-medium text-navy-800">
                          {selectedContainer.terminal?.name} ({selectedContainer.terminal?.code})
                        </p>
                      </div>
                      <div>
                        <p className="text-sm font-medium text-gray-500">Owner</p>
                        <p className="text-lg font-medium text-navy-800">
                          {selectedContainer.owner}
                        </p>
                      </div>
                      <div>
                        <p className="text-sm font-medium text-gray-500">Cargo Type</p>
                        <p className="text-lg font-medium text-navy-800">
                          {selectedContainer.cargo_type}
                        </p>
                      </div>
                      <div>
                        <p className="text-sm font-medium text-gray-500">Status</p>
                        <p className={`text-lg font-medium ${selectedContainer.status === 'READY' ? 'text-green-600' : selectedContainer.status === 'HELD' ? 'text-yellow-600' : selectedContainer.status === 'RELEASED' ? 'text-blue-600' : 'text-gray-600'}`}>
                          {selectedContainer.status}
                        </p>
                      </div>
                      <div>
                        <p className="text-sm font-medium text-gray-500">Created At</p>
                        <p className="text-lg font-medium text-navy-800">
                          {new Date(selectedContainer.created_at).toLocaleString()}
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            )}
          </div>
        </main>
      </div>
    </>
  );
};

export default ContainersPage;