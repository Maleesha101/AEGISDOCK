import type { NextPage } from 'next';
import { useEffect, useState } from 'react';
import Head from 'next/head';
import Link from 'next/link';
import { useRouter } from 'next/router';
import { getContainer, inspectContainer, holdContainer, releaseContainer, getAuthorizationArtifact } from '../../utils/api';

const ContainerDetailPage: NextPage = () => {
  const router = useRouter();
  const { containerNumber } = router.query;
  const [container, setContainer] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [authArtifact, setAuthArtifact] = useState<string | null>(null);
  const [artifactLoading, setArtifactLoading] = useState(false);

  useEffect(() => {
    if (!containerNumber) return;

    const loadContainer = async () => {
      try {
        setLoading(true);
        const res = await getContainer(containerNumber as string);
        setContainer(res.data);
      } catch (err) {
        console.error('Failed to load container:', err);
      } finally {
        setLoading(false);
      }
    };

    loadContainer();
  }, [containerNumber]);

  const handleInspect = async () => {
    if (!containerNumber) return;
    try {
      await inspectContainer(containerNumber as string);
      const res = await getContainer(containerNumber as string);
      setContainer(res.data);
    } catch (err: any) {
      alert(err.response?.data?.message || 'Inspection failed');
    }
  };

  const handleHold = async () => {
    if (!containerNumber) return;
    try {
      await holdContainer(containerNumber as string, 'Security hold for inspection');
      const res = await getContainer(containerNumber as string);
      setContainer(res.data);
    } catch (err: any) {
      alert(err.response?.data?.message || 'Hold failed');
    }
  };

  const handleGetArtifact = async () => {
    if (!containerNumber) return;
    try {
      setArtifactLoading(true);
      const res = await getAuthorizationArtifact('T-07', containerNumber as string);
      setAuthArtifact(res.data.artifact);
    } catch (err: any) {
      alert(err.response?.data?.message || 'Failed to get artifact');
    } finally {
      setArtifactLoading(false);
    }
  };

  const handleRelease = async () => {
    if (!containerNumber || !authArtifact) return;
    try {
      await releaseContainer(containerNumber as string, 'Authorized release', authArtifact);
      const res = await getContainer(containerNumber as string);
      setContainer(res.data);
    } catch (err: any) {
      alert(err.response?.data?.message || 'Release failed');
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-navy-500"></div>
      </div>
    );
  }

  if (!container) {
    return <div className="p-4">Container not found</div>;
  }

  return (
    <>
      <Head>
        <title>Container {containerNumber} - AegisDock</title>
      </Head>

      <div className="min-h-screen bg-gray-50">
        <header className="bg-white shadow-sm">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div className="flex justify-between h-16">
              <div className="flex items-center">
                <Link href="/containers" className="flex-shrink-0">
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
                      className="px-3 pt-2 border-b-2 border-transparent text-sm font-medium text-gray-500 hover:text-gray-700"
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
            </div>
          </div>
        </header>

        <main className="mt-10">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-8">
            <div className="mb-8">
              <Link
                href="/containers"
                className="text-sm text-navy-600 hover:text-navy-800 mb-2 block"
              >
                ← Back to Containers
              </Link>
              <h1 className="text-2xl font-bold text-navy-800">
                {container.container_number}
              </h1>
              <p className="mt-2 text-gray-600">
                Terminal: {container.terminal?.name} ({container.terminal?.code}) • {container.cargo_type}
              </p>
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
              {/* Main Info */}
              <div className="lg:col-span-2 space-y-6">
                {/* Status */}
                <div className="bg-white rounded-xl shadow p-6">
                  <h3 className="text-lg font-medium text-navy-600 mb-4">Current Status</h3>
                  <div className="flex items-center justify-between">
                    <span className={`px-4 py-2 text-xl font-bold rounded-lg ${
                      container.status === 'READY' ? 'bg-green-100 text-green-800' :
                      container.status === 'HELD' ? 'bg-yellow-100 text-yellow-800' :
                      container.status === 'RELEASED' ? 'bg-blue-100 text-blue-800' :
                      'bg-gray-100 text-gray-800'
                    }`}>
                      {container.status}
                    </span>
                    <div className="text-right">
                      <p className="text-sm text-gray-500">Owner</p>
                      <p className="font-medium text-navy-800">{container.owner}</p>
                    </div>
                  </div>
                </div>

                {/* Details */}
                <div className="bg-white rounded-xl shadow p-6">
                  <h3 className="text-lg font-medium text-navy-600 mb-4">Container Details</h3>
                  <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                      <p className="text-sm font-medium text-gray-500">Container Number</p>
                      <p className="text-lg font-medium text-navy-800">{container.container_number}</p>
                    </div>
                    <div>
                      <p className="text-sm font-medium text-gray-500">Terminal</p>
                      <p className="text-lg font-medium text-navy-800">
                        {container.terminal?.name} ({container.terminal?.code})
                      </p>
                    </div>
                    <div>
                      <p className="text-sm font-medium text-gray-500">Owner</p>
                      <p className="text-lg font-medium text-navy-800">{container.owner}</p>
                    </div>
                    <div>
                      <p className="text-sm font-medium text-gray-500">Cargo Type</p>
                      <p className="text-lg font-medium text-navy-800">{container.cargo_type}</p>
                    </div>
                    <div>
                      <p className="text-sm font-medium text-gray-500">Created At</p>
                      <p className="text-lg font-medium text-navy-800">
                        {new Date(container.created_at).toLocaleString()}
                      </p>
                    </div>
                    <div>
                      <p className="text-sm font-medium text-gray-500">Updated At</p>
                      <p className="text-lg font-medium text-navy-800">
                        {new Date(container.updated_at).toLocaleString()}
                      </p>
                    </div>
                  </div>
                </div>

                {/* Authorization Artifact */}
                <div className="bg-white rounded-xl shadow p-6">
                  <h3 className="text-lg font-medium text-navy-600 mb-4">Authorization Artifact</h3>
                  <p className="text-sm text-gray-500 mb-4">
                    Encrypted authorization token for terminal operations. Required for container release.
                  </p>
                  <div className="flex space-x-4">
                    <button
                      onClick={handleGetArtifact}
                      disabled={artifactLoading}
                      className="px-4 py-2 bg-navy-600 hover:bg-navy-700 text-white font-medium rounded-lg"
                    >
                      {artifactLoading ? 'Generating...' : 'Get Authorization Artifact'}
                    </button>
                    {authArtifact && (
                      <button
                        className="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-medium rounded-lg"
                        onClick={() => navigator.clipboard.writeText(authArtifact)}
                      >
                        Copy Artifact
                      </button>
                    )}
                  </div>
                  {authArtifact && (
                    <div className="mt-4">
                      <p className="text-xs text-gray-500 mb-1">Base64 Encoded Ciphertext:</p>
                      <textarea
                        readOnly
                        rows={3}
                        className="w-full px-3 py-2 text-xs font-mono bg-gray-100 border border-gray-300 rounded"
                        value={authArtifact}
                      />
                    </div>
                  )}
                </div>
              </div>

              {/* Actions */}
              <div className="space-y-4">
                <div className="bg-white rounded-xl shadow p-6">
                  <h3 className="text-lg font-medium text-navy-600 mb-4">Container Operations</h3>
                  <div className="space-y-3">
                    <button
                      onClick={handleInspect}
                      disabled={container.status === 'RELEASED'}
                      className="w-full px-4 py-3 bg-navy-600 hover:bg-navy-700 text-white font-medium rounded-lg"
                    >
                      Inspect Container
                    </button>
                    <button
                      onClick={handleHold}
                      disabled={container.status !== 'READY'}
                      className="w-full px-4 py-3 bg-yellow-600 hover:bg-yellow-700 text-white font-medium rounded-lg"
                    >
                      Hold Container
                    </button>
                    <button
                      onClick={handleRelease}
                      disabled={container.status !== 'READY' || !authArtifact}
                      className="w-full px-4 py-3 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg"
                    >
                      Release Container
                    </button>
                  </div>
                  {!authArtifact && container.status === 'READY' && (
                    <p className="mt-3 text-sm text-gray-500">
                      Get authorization artifact first to enable release
                    </p>
                  )}
                </div>

                {/* Release Form */}
                {authArtifact && container.status === 'READY' && (
                  <div className="bg-white rounded-xl shadow p-6">
                    <h3 className="text-lg font-medium text-navy-600 mb-4">Release with Artifact</h3>
                    <p className="text-sm text-gray-500 mb-4">
                      The release operation will use the encrypted authorization artifact.
                      For operators, this is the only way to release containers.
                    </p>
                    <button
                      onClick={handleRelease}
                      className="w-full px-4 py-3 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg"
                    >
                      Execute Release
                    </button>
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

export default ContainerDetailPage;