'use client';

// FILE 4: /admin/items?category=xxx — Item list table for a given category.

import { useEffect, useState, Suspense } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import Link from 'next/link';

const CATEGORY_NAMES = {
  coffee_items:     'Кава',
  fast_food_items:  'Фаст-фуд',
  pizza_items:      'Піца',
  cold_drink_items: 'Холодні напої',
  dessert_items:    'Десерти',
  giftcards:        'Подарункові картки',
};

function ItemsTable() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const category = searchParams.get('category') || '';

  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  async function loadItems() {
    setLoading(true);
    setError('');
    try {
      const meRes = await fetch('/api/admin/me');
      if (!meRes.ok) { router.replace('/admin/login'); return; }

      if (!category) { setError('Категорію не вказано.'); setLoading(false); return; }

      const res = await fetch(`/api/admin/items?category=${category}`);
      if (!res.ok) { setError('Не вдалося завантажити товари.'); setLoading(false); return; }

      const data = await res.json();
      setItems(data.items || []);
    } catch {
      setError('Помилка мережі.');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { loadItems(); }, [category]); // eslint-disable-line react-hooks/exhaustive-deps

  async function handleDelete(id) {
    if (!window.confirm('Видалити цей товар?')) return;
    try {
      const res = await fetch(`/api/admin/items/${id}?category=${category}`, { method: 'DELETE' });
      if (res.ok) {
        setItems((prev) => prev.filter((item) => item.id !== id));
      } else {
        alert('Помилка при видаленні.');
      }
    } catch {
      alert('Помилка мережі.');
    }
  }

  const categoryLabel = CATEGORY_NAMES[category] || category;

  return (
    <div className="p-8">
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">{categoryLabel}</h1>
          <p className="text-sm text-gray-500 mt-0.5">Список товарів</p>
        </div>
        <Link
          href={`/admin/items/add?category=${category}`}
          className="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-white text-sm font-medium transition-colors hover:opacity-90"
          style={{ backgroundColor: '#8b4513' }}
        >
          + Додати товар
        </Link>
      </div>

      {/* Category tabs */}
      <div className="flex flex-wrap gap-2 mb-6">
        {Object.entries(CATEGORY_NAMES).map(([key, label]) => (
          <Link
            key={key}
            href={`/admin/items?category=${key}`}
            className={`px-3 py-1.5 rounded-full text-xs font-medium transition-colors ${
              key === category
                ? 'text-white'
                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
            }`}
            style={key === category ? { backgroundColor: '#8b4513' } : {}}
          >
            {label}
          </Link>
        ))}
      </div>

      {/* States */}
      {loading && <p className="text-gray-500 text-sm">Завантаження…</p>}
      {error && (
        <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
          {error}
        </p>
      )}

      {/* Table */}
      {!loading && !error && (
        <div className="overflow-x-auto rounded-xl border border-gray-200 shadow-sm">
          <table className="min-w-full text-sm">
            <thead className="bg-gray-50 border-b border-gray-200">
              <tr>
                <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ID</th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Назва</th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Опис</th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Ціна</th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Зображення</th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Дії</th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-100">
              {items.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-4 py-8 text-center text-gray-400 text-sm">
                    Товарів немає. Додайте перший!
                  </td>
                </tr>
              ) : (
                items.map((item) => (
                  <tr key={item.id} className="hover:bg-gray-50 transition-colors">
                    <td className="px-4 py-3 text-gray-500">{item.id}</td>
                    <td className="px-4 py-3 font-medium text-gray-800">
                      {item.name ?? item.title}
                    </td>
                    <td className="px-4 py-3 text-gray-600 max-w-xs truncate">
                      {item.description}
                    </td>
                    <td className="px-4 py-3 text-gray-800 whitespace-nowrap">
                      {Number(item.price).toFixed(2)} грн
                    </td>
                    <td className="px-4 py-3">
                      {item.image ? (
                        <img
                          src={`/${item.image}`}
                          alt={item.name ?? item.title}
                          className="h-10 w-10 object-cover rounded-lg border border-gray-200"
                        />
                      ) : (
                        <span className="text-gray-400 text-xs">—</span>
                      )}
                    </td>
                    <td className="px-4 py-3">
                      <div className="flex items-center gap-2">
                        <Link
                          href={`/admin/items/${item.id}/edit?category=${category}`}
                          className="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium bg-amber-50 text-amber-800 hover:bg-amber-100 border border-amber-200 transition-colors"
                        >
                          Редагувати
                        </Link>
                        <button
                          onClick={() => handleDelete(item.id)}
                          className="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium bg-red-50 text-red-700 hover:bg-red-100 border border-red-200 transition-colors"
                        >
                          Видалити
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

export default function AdminItemsPage() {
  return (
    <Suspense fallback={<div className="p-8 text-gray-500 text-sm">Завантаження…</div>}>
      <ItemsTable />
    </Suspense>
  );
}
