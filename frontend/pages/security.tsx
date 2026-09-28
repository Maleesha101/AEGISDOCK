import type { NextPage } from 'next';
import { useEffect, useState } from 'react';
import Head from 'next/head';
import Link from 'next/link';
import { getAuditLogs, getDebugArtifact } from '../utils/api';

const SecurityPage: NextPage = () => {
  const [auditLogs, setAuditLogs] = useState<any[]>([]);
  const [debugData, setDebugData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [debugArtifactId, setDebugArtifactId] = useState('');

  useEffect(() => {
    const loadSecurityData = async () => {
      try {
        setLoading(true);
        const res = await getAuditLogs();
        setAuditLogs(res.data);
      } catch (err) {
        console.error('Failed to load security data:', err);
      } finally {
        setLoading(false);
      }
    };

    loadSecurityData();
  }, []);

  const handleDebugArtifact = async () => {
    if (!debugArtifactId) return;
    try {
      const res = await getDebugArtifact(debugArtifactId);
      setDebugData(res.data);
    } catch (err: any) {
      alert(err.response?.data?.message || 'Failed to load debug data');
    }
  };

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
        <title>Security - AegisDock</title>
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
                      href="/audit"
                      className="px-3 pt-2 border-b-2 border-transparent text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300"
                    >
                      Audit
                    </Link>
                    <Link
                      href="/security"
                      className="px-3 pt-2 border-b-2 border-navy-500 text-navy-600 border-b-2 font-medium"
                    >
                      Security
                    </Link>
                  </div>
                </div>
              </div>

              <div className="hidden md:block">
                <div className="ml-4 flex items-center md:ml-6">
                  <span className="text-sm font-medium text-gray-600">
                    Security Admin
                  </span>
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
                Security Dashboard
              </h1>
              <p className="mt-2 text-gray-600">
                Cryptographic security monitoring and artifact analysis
              </p>
            </div>

            {/* Debug Artifact Analysis */}
            <div className="bg-white rounded-xl shadow mb-8">
              <div className="px-6 py-4 border-b border-gray-200">
                <h2 className="text-lg font-medium text-navy-600">
                  Debug Artifact Analysis
                </h2>
                <p className="text-sm text-gray-500">
                  For authorized security personnel only. Review cryptographic metadata for debugging purposes.
                </p>
              </div>
              <div className="px-6 py-6 space-y-4">
                <div className="flex space-x-4">
                  <input
                    type="text"
                    placeholder="Enter artifact ID"
                    value={debugArtifactId}
                    onChange={(e) => setDebugArtifactId(e.target.value)}
                    className="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-navy-500 focus:border-navy-500"
                  />
                  <button
                    onClick={handleDebugArtifact}
                    className="px-4 py-2 bg-navy-600 hover:bg-navy-700 text-white font-medium rounded-lg"
                  >
                    Analyze
                  </button>
                </div>

                {debugData && (
                  <div className="bg-gray-50 rounded-lg p-4">
                    <h3 className="font-medium text-navy-800 mb-2">Artifact Details</h3>
                    <div className="space-y-2 text-sm">
                      <div className="flex items-start">
                        <span className="w-40 font-medium text-gray-600">Algorithm:</span>
                        <span className="text-gray-800">{debugData.algorithm}</span>
                      </div>
                      <div className="flex items-start">
                        <span className="w-40 font-medium text-gray-600">IV:</span>
                        <code className="text-gray-800 break-all">{debugData.iv}</code>
                      </div>
                      <div className="flex items-start">
                        <span className="w-40 font-medium text-gray-600">Ciphertext:</span>
                        <code className="text-gray-800 break-all">{debugData.ciphertext}</code>
                      </div>
                      <div className="flex items-start">
                        <span className="w-40 font-medium text-gray-600">Key Length:</span>
                        <span className="text-gray-800">{debugData.key_length} bytes</span>
                      </div>
                      <div className="flex items-start">
                        <span className="w-40 font-medium text-gray-600">Plaintext Payload:</span>
                        <pre className="text-gray-800 bg-white rounded p-2 text-xs overflow-x-auto">
                          {JSON.stringify(debugData.metadata, null, 2)}
                        </pre>
                      </div>
                    </div>
                  </div>
                )}
              </div>
            </div>

            {/* Recent Security Events */}
            <div className="bg-white rounded-xl shadow">
              <div className="px-6 py-4 border-b border-gray-200">
                <h2 className="text-lg font-medium text-navy-600">
                  Security Events
                </h2>
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
                      </div>
                      <span className="text-sm text-gray-500">
                        {new Date(log.timestamp).toLocaleString()}
                      </span>
                    </div>
                  ))
                ) : (
                  <div className="px-6 py-12 text-center text-gray-500">
                    No security events found
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

export default SecurityPage;