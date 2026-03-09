'use client';

// FILE 3: /admin — Dashboard with category navigation cards.

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';

const CATEGORIES = [
  { key: 'coffee_items',     label: 'Кава',                 emoji: '☕' },
  { key: 'fast_food_items',  label: 'Фаст-фуд',             emoji: '🍔' },
  { key: 'pizza_items',      label: 'Піца',                 emoji: '🍕' },
  { key: 'cold_drink_items', label: 'Холодні напої',        emoji: '🧃' },
  { key: 'dessert_items',    label: 'Десерти',              emoji: '🍰' },
  { key: 'giftcards',        label: 'Подарункові картки',   emoji: '🎁' },
];

export default function AdminDashboard() {
  const router = useRouter();

  useEffect(() => {
    fetch('/api/admin/me').then((res) => {
      if (!res.ok) router.replace('/admin/login');
    });
  }, [router]);

  async function handleLogout(e) {
    e.preventDefault();
    await fetch('/api/admin/logout', { method: 'POST' });
    router.replace('/admin/login');
  }

  return (
    <div className="p-8">
      <h1 className="text-2xl font-bold text-gray-800 mb-2">Головна</h1>
      <p className="text-sm text-gray-500 mb-8">Оберіть розділ для керування</p>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        {/* Category cards */}
        {CATEGORIES.map(({ key, label, emoji }) => (
          <Link
            key={key}
            href={`/admin/items?category=${key}`}
            className="flex items-center gap-4 bg-white border border-gray-200 rounded-xl px-5 py-4 shadow-sm hover:shadow-md hover:border-amber-400 transition-all group"
          >
            <span className="text-3xl">{emoji}</span>
            <div>
              <p className="text-sm font-semibold text-gray-800 group-hover:text-amber-800 transition-colors">
                {label}
              </p>
              <p className="text-xs text-gray-400 mt-0.5">Переглянути товари</p>
            </div>
          </Link>
        ))}

        {/* Orders card */}
        <Link
          href="/admin/orders"
          className="flex items-center gap-4 bg-white border border-gray-200 rounded-xl px-5 py-4 shadow-sm hover:shadow-md hover:border-amber-400 transition-all group"
        >
          <span className="text-3xl">📋</span>
          <div>
            <p className="text-sm font-semibold text-gray-800 group-hover:text-amber-800 transition-colors">
              Замовлення
            </p>
            <p className="text-xs text-gray-400 mt-0.5">Переглянути замовлення</p>
          </div>
        </Link>

        {/* Logout card */}
        <form onSubmit={handleLogout}>
          <button
            type="submit"
            className="w-full flex items-center gap-4 bg-white border border-gray-200 rounded-xl px-5 py-4 shadow-sm hover:shadow-md hover:border-red-300 transition-all group text-left"
          >
            <span className="text-3xl">🚪</span>
            <div>
              <p className="text-sm font-semibold text-gray-800 group-hover:text-red-700 transition-colors">
                Вийти
              </p>
              <p className="text-xs text-gray-400 mt-0.5">Завершити сесію</p>
            </div>
          </button>
        </form>
      </div>
    </div>
  );
}
