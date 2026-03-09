'use client';

// FILE 6: /admin/items/[id]/edit?category=xxx — Edit item form (no image change).

import { useEffect, useState, Suspense } from 'react';
import { useRouter, useSearchParams, useParams } from 'next/navigation';
import Link from 'next/link';

const CATEGORY_NAMES = {
  coffee_items:     'Кава',
  fast_food_items:  'Фаст-фуд',
  pizza_items:      'Піца',
  cold_drink_items: 'Холодні напої',
  dessert_items:    'Десерти',
  giftcards:        'Подарункові картки',
};

function EditItemForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const params = useParams();

  const category = searchParams.get('category') || '';
  const id = params.id;

  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [price, setPrice] = useState('');
  const [fetchError, setFetchError] = useState('');
  const [saveError, setSaveError] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    async function init() {
      // Auth check
      const meRes = await fetch('/api/admin/me');
      if (!meRes.ok) { router.replace('/admin/login'); return; }

      if (!category || !id) { setFetchError('Невірні параметри.'); setLoading(false); return; }

      // Load item list and find the matching item
      try {
        const res = await fetch(`/api/admin/items?category=${category}`);
        if (!res.ok) { setFetchError('Не вдалося завантажити товар.'); setLoading(false); return; }

        const data = await res.json();
        const item = (data.items || []).find((i) => String(i.id) === String(id));

        if (!item) { setFetchError('Товар не знайдено.'); setLoading(false); return; }

        setName(item.name ?? item.title ?? '');
        setDescription(item.description ?? '');
        setPrice(String(item.price ?? ''));
      } catch {
        setFetchError('Помилка мережі.');
      } finally {
        setLoading(false);
      }
    }
    init();
  }, [category, id, router]);

  async function handleSubmit(e) {
    e.preventDefault();
    setSaveError('');
    setSaving(true);

    try {
      const res = await fetch(`/api/admin/items/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ category, name, description, price: parseFloat(price) }),
      });

      const data = await res.json();

      if (!res.ok) {
        setSaveError(data.error || 'Помилка збереження.');
        return;
      }

      router.push(`/admin/items?category=${category}`);
    } catch {
      setSaveError('Помилка мережі.');
    } finally {
      setSaving(false);
    }
  }

  const categoryLabel = CATEGORY_NAMES[category] || category;

  if (loading) {
    return <div className="p-8 text-gray-500 text-sm">Завантаження…</div>;
  }

  if (fetchError) {
    return (
      <div className="p-8">
        <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
          {fetchError}
        </p>
      </div>
    );
  }

  return (
    <div className="p-8 max-w-xl">
      {/* Breadcrumb */}
      <nav className="flex items-center gap-2 text-sm text-gray-500 mb-6">
        <Link href="/admin" className="hover:text-gray-800">Головна</Link>
        <span>/</span>
        <Link href={`/admin/items?category=${category}`} className="hover:text-gray-800">
          {categoryLabel}
        </Link>
        <span>/</span>
        <span className="text-gray-800 font-medium">Редагувати товар</span>
      </nav>

      <h1 className="text-2xl font-bold text-gray-800 mb-6">Редагувати товар</h1>

      <form onSubmit={handleSubmit} className="space-y-5 bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Назва</label>
          <input
            type="text"
            value={name}
            onChange={(e) => setName(e.target.value)}
            required
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent"
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Опис</label>
          <textarea
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            required
            rows={3}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent resize-none"
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Ціна (грн)</label>
          <input
            type="number"
            step="0.01"
            min="0"
            value={price}
            onChange={(e) => setPrice(e.target.value)}
            required
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent"
          />
        </div>

        {saveError && (
          <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
            {saveError}
          </p>
        )}

        <div className="flex items-center gap-3 pt-2">
          <button
            type="submit"
            disabled={saving}
            className="px-5 py-2.5 rounded-lg text-white text-sm font-semibold transition-colors disabled:opacity-60"
            style={{ backgroundColor: '#8b4513' }}
          >
            {saving ? 'Збереження…' : 'Зберегти зміни'}
          </button>
          <Link
            href={`/admin/items?category=${category}`}
            className="px-5 py-2.5 rounded-lg text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 transition-colors"
          >
            Скасувати
          </Link>
        </div>
      </form>
    </div>
  );
}

export default function AdminEditItemPage() {
  return (
    <Suspense fallback={<div className="p-8 text-gray-500 text-sm">Завантаження…</div>}>
      <EditItemForm />
    </Suspense>
  );
}
