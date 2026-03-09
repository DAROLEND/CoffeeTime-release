'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { useAuth } from '@/context/AuthContext';

function InfoRow({ label, value }) {
  return (
    <div className="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3">
      <span className="text-sm text-gray-500 sm:w-40 shrink-0">{label}</span>
      <span className="text-sm font-medium text-gray-800 break-all">{value ?? '—'}</span>
    </div>
  );
}

function formatDate(dateStr) {
  if (!dateStr) return '—';
  try {
    return new Date(dateStr).toLocaleDateString('uk-UA', {
      day: '2-digit',
      month: 'long',
      year: 'numeric',
    });
  } catch {
    return dateStr;
  }
}

export default function ProfilePage() {
  const router = useRouter();
  const { user, setUser } = useAuth();

  // Extended profile data fetched from server
  const [profile, setProfile] = useState(null);
  const [profileLoading, setProfileLoading] = useState(true);

  // Editable fields
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [phone, setPhone] = useState('');

  const [saveError, setSaveError] = useState('');
  const [saveSuccess, setSaveSuccess] = useState('');
  const [saving, setSaving] = useState(false);
  const [loggingOut, setLoggingOut] = useState(false);

  // Redirect if not logged in
  useEffect(() => {
    if (user === null) {
      router.push('/login');
    }
  }, [user, router]);

  // Fetch full profile from server
  useEffect(() => {
    if (!user) return;

    async function fetchProfile() {
      setProfileLoading(true);
      try {
        const res = await fetch('/api/profile/me');
        if (!res.ok) {
          if (res.status === 401) {
            setUser(null);
            router.push('/login');
            return;
          }
          throw new Error('Failed to fetch profile');
        }
        const data = await res.json();
        setProfile(data.user);
        setFirstName(data.user.client_name || '');
        setLastName(data.user.client_surname || '');
        setPhone(data.user.client_PhoneNumber || '');
      } catch {
        // Profile fetch failed — use data from auth context as fallback
        setFirstName(user.name || '');
        setLastName(user.surname || '');
        setPhone(user.phone || '');
      } finally {
        setProfileLoading(false);
      }
    }

    fetchProfile();
  }, [user, router, setUser]);

  if (!user) return null;

  async function handleSave(e) {
    e.preventDefault();
    setSaveError('');
    setSaveSuccess('');
    setSaving(true);

    try {
      const res = await fetch('/api/profile/update', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ firstName, lastName, phone }),
      });

      const data = await res.json();

      if (!res.ok) {
        setSaveError(data.error || 'Помилка збереження');
        return;
      }

      // Update auth context with new name/phone
      setUser((prev) => ({
        ...prev,
        name: data.user?.client_name ?? firstName,
        surname: data.user?.client_surname ?? lastName,
        phone: data.user?.client_PhoneNumber ?? phone,
      }));

      // Update local profile state
      setProfile((prev) => prev ? { ...prev, ...data.user } : prev);
      setSaveSuccess('Зміни успішно збережено!');
    } catch {
      setSaveError('Мережева помилка. Спробуйте ще раз.');
    } finally {
      setSaving(false);
    }
  }

  async function handleLogout() {
    setLoggingOut(true);
    try {
      await fetch('/api/auth/logout', { method: 'POST' });
    } catch {
      // Ignore network errors on logout
    } finally {
      setUser(null);
      router.push('/');
    }
  }

  const displayEmail = profile?.email ?? user?.email ?? '—';
  const displayLogin = profile?.login ?? user?.login ?? '—';
  const displayCreatedAt = formatDate(profile?.created_at);
  const ordersCount = profile?.ordersCount ?? '—';

  return (
    <main className="min-h-screen bg-gray-50 px-4 py-12">
      <div className="w-full max-w-md mx-auto space-y-6">

        {/* Header card */}
        <div className="bg-white rounded-2xl shadow-lg p-8">
          <div className="flex items-center gap-4 mb-6">
            <div className="w-14 h-14 rounded-full bg-[#ffa500] flex items-center justify-center text-white text-xl font-bold shrink-0">
              {(firstName || displayLogin || '?')[0].toUpperCase()}
            </div>
            <div>
              <h1 className="text-xl font-bold text-gray-800">
                {firstName || lastName
                  ? `${firstName} ${lastName}`.trim()
                  : displayLogin}
              </h1>
              <p className="text-sm text-gray-500">{displayLogin}</p>
            </div>
          </div>

          <div className="space-y-3 divide-y divide-gray-100">
            <InfoRow label="Email" value={displayEmail} />
            <div className="pt-3">
              <InfoRow label="Дата реєстрації" value={displayCreatedAt} />
            </div>
            <div className="pt-3">
              <InfoRow
                label="Всього замовлень"
                value={profileLoading ? 'Завантаження...' : String(ordersCount)}
              />
            </div>
          </div>
        </div>

        {/* Edit form card */}
        <div className="bg-white rounded-2xl shadow-lg p-8">
          <h2 className="text-lg font-semibold text-gray-800 mb-5">Особисті дані</h2>

          <form onSubmit={handleSave} className="space-y-4" noValidate>
            <div>
              <label htmlFor="firstName" className="block text-sm font-medium text-gray-700 mb-1">
                Ім&apos;я
              </label>
              <input
                id="firstName"
                type="text"
                value={firstName}
                onChange={(e) => setFirstName(e.target.value)}
                autoComplete="given-name"
                placeholder="Іван"
                className="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#ffa500] focus:border-transparent transition"
              />
            </div>

            <div>
              <label htmlFor="lastName" className="block text-sm font-medium text-gray-700 mb-1">
                Прізвище
              </label>
              <input
                id="lastName"
                type="text"
                value={lastName}
                onChange={(e) => setLastName(e.target.value)}
                autoComplete="family-name"
                placeholder="Петренко"
                className="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#ffa500] focus:border-transparent transition"
              />
            </div>

            <div>
              <label htmlFor="phone" className="block text-sm font-medium text-gray-700 mb-1">
                Телефон
              </label>
              <input
                id="phone"
                type="tel"
                value={phone}
                onChange={(e) => setPhone(e.target.value)}
                autoComplete="tel"
                placeholder="+380 XX XXX XX XX"
                className="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#ffa500] focus:border-transparent transition"
              />
            </div>

            {/* Save error */}
            {saveError && (
              <p className="text-red-500 text-sm bg-red-50 border border-red-200 rounded-lg px-3 py-2">
                {saveError}
              </p>
            )}

            {/* Save success */}
            {saveSuccess && (
              <p className="text-green-700 text-sm bg-green-50 border border-green-200 rounded-lg px-3 py-2">
                {saveSuccess}
              </p>
            )}

            <button
              type="submit"
              disabled={saving}
              className="w-full bg-[#ffa500] hover:bg-[#e69400] disabled:opacity-60 disabled:cursor-not-allowed text-white font-semibold py-2.5 rounded-lg transition"
            >
              {saving ? 'Збереження...' : 'Зберегти зміни'}
            </button>
          </form>
        </div>

        {/* Actions card */}
        <div className="bg-white rounded-2xl shadow-lg p-8 space-y-3">
          <Link
            href="/change-password"
            className="flex items-center justify-between w-full px-4 py-3 border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:border-[#ffa500] hover:text-[#ffa500] transition"
          >
            <span>Змінити пароль</span>
            <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
            </svg>
          </Link>

          <button
            type="button"
            onClick={handleLogout}
            disabled={loggingOut}
            className="w-full flex items-center justify-center gap-2 px-4 py-3 border border-red-200 rounded-lg text-sm font-medium text-red-600 hover:bg-red-50 disabled:opacity-60 disabled:cursor-not-allowed transition"
          >
            <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
            {loggingOut ? 'Вихід...' : 'Вийти'}
          </button>
        </div>

      </div>
    </main>
  );
}
