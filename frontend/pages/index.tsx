import type { NextPage } from 'next';
import Head from 'next/head';
import Link from 'next/link';

const Home: NextPage = () => {
  return (
    <>
      <Head>
        <title>AegisDock - Autonomous Port Container Release System</title>
        <meta name="description" content="Training environment for container security education" />
      </Head>

      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="bg-white rounded-xl shadow-2xl w-full max-w-md p-8 space-y-6">
          <div className="text-center">
            <h1 className="text-3xl font-bold text-navy-800">
              AegisDock
            </h1>
            <p className="text-lg text-gray-600">
              Autonomous Port Container Release System
            </p>
            <div className="w-full h-0.5 bg-gradient-to-r from-navy-400 to-accent-400 rounded-full mt-4" />
          </div>

          <div className="space-y-4">
            <Link
              href="/login"
              className="w-full bg-navy-600 hover:bg-navy-700 text-white font-medium py-3 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center gap-2"
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
              </svg>
              Login to Training System
            </Link>

            <div className="text-center text-sm text-gray-500">
              <p>Security Training Lab</p>
              <p className="mt-1">
                <span className="text-red-500">⚠️ Intentionally Vulnerable</span> -
                Do not use credentials or techniques from this lab in production
              </p>
            </div>
          </div>
        </div>
      </div>
    </>
  );
};

export default Home;