'use client';

// FILE 5: /admin/items/add?category=xxx — Add new item form with image upload.

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

function AddItemForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const category = searchParams.get('category') || '';

  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [price, setPrice] = useState('');
  const [image, setImage] = useState(null);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    fetch('/api/admin/me').then((res) => {
      if (!res.ok) router.replace('/admin/login');
    });
  }, [router]);

  async function handleSubmit(e) {
    e.preventDefault();
    setError('');

    if (!category) { setError('Категорію не вказано.'); return; }
    if (!image) { setError("Оберіть зображення."); return; }

    setLoading(true);
    try {
      const formData = new FormData();
      formData.append('category', category);
      formData.append('name', name);
      formData.append('description', description);
      formData.append('price', price);
      formData.append('image', image);

      const res = await fetch('/api/admin/items', { method: 'POST', body: formData });
      const data = await res.json();

      if (!res.ok) {
        setError(data.error || 'Помилка збереження.');
        return;
      }

      router.push(`/admin/items?category=${category}`);
    } catch {
      setError('Помилка мережі.');
    } finally {
      setLoading(false);
    }
  }

  const categoryLabel = CATEGORY_NAMES[category] || category;

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
        <span className="text-gray-800 font-medium">Додати товар</span>
      </nav>

      <h1 className="text-2xl font-bold text-gray-800 mb-6">Новий товар</h1>

      <form onSubmit={handleSubmit} className="space-y-5 bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Назва</label>
          <input
            type="text"
            value={name}
            onChange={(e) => setName(e.target.value)}
            required
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent"
            placeholder="Введіть назву товару"
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
            placeholder="Короткий опис товару"
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
            placeholder="0.00"
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Зображення</label>
          <input
            type="file"
            accept="image/*"
            onChange={(e) => setImage(e.target.files?.[0] || null)}
            required
            className="w-full text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:text-white file:cursor-pointer"
            style={{ '--tw-file-bg': '#8b4513' }}
          />
          <style>{`input[type="file"]::file-selector-button { background-color: #8b4513; color: white; border-radius: 8px; border: none; padding: 6px 12px; font-size: 12px; font-weight: 500; cursor: pointer; }`}</style>
          {image && (
            <p className="text-xs text-gray-500 mt-1">{image.name}</p>
          )}
        </div>

        {error && (
          <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
            {error}
          </p>
        )}

        <div className="flex items-center gap-3 pt-2">
          <button
            type="submit"
            disabled={loading}
            className="px-5 py-2.5 rounded-lg text-white text-sm font-semibold transition-colors disabled:opacity-60"
            style={{ backgroundColor: '#8b4513' }}
          >
            {loading ? 'Збереження…' : 'Зберегти товар'}
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

export default function AdminAddItemPage() {
  return (
    <Suspense fallback={<div className="p-8 text-gray-500 text-sm">Завантаження…</div>}>
      <AddItemForm />
    </Suspense>
  );
}
