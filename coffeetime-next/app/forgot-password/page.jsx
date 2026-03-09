'use client';

import { useState } from 'react';
import Link from 'next/link';

export default function ForgotPasswordPage() {
  const [email, setEmail] = useState('');
  const [error, setError] = useState('');
  const [submitted, setSubmitted] = useState(false);
  const [loading, setLoading] = useState(false);

  async function handleSubmit(e) {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      const res = await fetch('/api/auth/forgot', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email }),
      });

      const data = await res.json();

      if (!res.ok) {
        setError(data.error || 'Виникла помилка. Спробуйте ще раз.');
        return;
      }

      setSubmitted(true);
    } catch {
      setError('Мережева помилка. Спробуйте ще раз.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <main className="min-h-screen flex items-center justify-center bg-gray-50 px-4 py-12">
      <div className="w-full max-w-md bg-white rounded-2xl shadow-lg p-8">
        <h1 className="text-2xl font-bold text-center text-gray-800 mb-2">
          Відновлення пароля
        </h1>
        <p className="text-center text-sm text-gray-500 mb-6">
          Введіть email, вказаний під час реєстрації
        </p>

        {submitted ? (
          <div className="text-center space-y-4">
            <div className="w-14 h-14 bg-orange-50 rounded-full flex items-center justify-center mx-auto">
              <svg xmlns="http://www.w3.org/2000/svg" className="h-7 w-7 text-[#ffa500]" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
            </div>
            <p className="text-gray-700 text-sm leading-relaxed">
              Якщо цей email зареєстрований, ми надішлемо вам листа з інструкціями.
            </p>
            <Link
              href="/login"
              className="inline-block text-[#ffa500] hover:underline text-sm font-medium"
            >
              ← Повернутися до входу
            </Link>
          </div>
        ) : (
          <>
            <form onSubmit={handleSubmit} className="space-y-5" noValidate>
              <div>
                <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-1">
                  Email
                </label>
                <input
                  id="email"
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  required
                  autoComplete="email"
                  placeholder="your@email.com"
                  className="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#ffa500] focus:border-transparent transition"
                />
              </div>

              {error && (
                <p className="text-red-500 text-sm bg-red-50 border border-red-200 rounded-lg px-3 py-2">
                  {error}
                </p>
              )}

              <button
                type="submit"
                disabled={loading}
                className="w-full bg-[#ffa500] hover:bg-[#e69400] disabled:opacity-60 disabled:cursor-not-allowed text-white font-semibold py-2.5 rounded-lg transition"
              >
                {loading ? 'Надсилання...' : 'Надіслати інструкції'}
              </button>
            </form>

            <p className="mt-6 text-center text-sm text-gray-500">
              <Link href="/login" className="text-[#ffa500] hover:underline">
                ← Повернутися до входу
              </Link>
            </p>
          </>
        )}
      </div>
    </main>
  );
}
