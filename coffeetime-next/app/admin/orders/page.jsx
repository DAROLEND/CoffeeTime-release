'use client';

// FILE 7: /admin/orders — Pending orders list table.

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';

function formatDate(dateString) {
  if (!dateString) return '—';
  try {
    return new Date(dateString).toLocaleString('uk-UA', {
      day: '2-digit', month: '2-digit', year: 'numeric',
      hour: '2-digit', minute: '2-digit',
    });
  } catch {
    return dateString;
  }
}

export default function AdminOrdersPage() {
  const router = useRouter();
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    async function load() {
      setLoading(true);
      setError('');
      try {
        const meRes = await fetch('/api/admin/me');
        if (!meRes.ok) { router.replace('/admin/login'); return; }

        const res = await fetch('/api/admin/orders');
        if (!res.ok) {
          setError('Не вдалося завантажити замовлення.');
          setLoading(false);
          return;
        }
        const data = await res.json();
        setOrders(data.orders || []);
      } catch {
        setError('Помилка мережі.');
      } finally {
        setLoading(false);
      }
    }
    load();
  }, [router]);

  return (
    <div className="p-8">
      {/* Header */}
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-800">Замовлення</h1>
        <p className="text-sm text-gray-500 mt-0.5">Активні замовлення зі статусом «очікує»</p>
      </div>

      {loading && <p className="text-gray-500 text-sm">Завантаження…</p>}
      {error && (
        <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
          {error}
        </p>
      )}

      {!loading && !error && (
        <div className="overflow-x-auto rounded-xl border border-gray-200 shadow-sm">
          <table className="min-w-full text-sm">
            <thead className="bg-gray-50 border-b border-gray-200">
              <tr>
                <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ID</th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Ім&apos;я</th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Дата</th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Сума</th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Дія</th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-100">
              {orders.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-4 py-8 text-center text-gray-400 text-sm">
                    Нових замовлень немає
                  </td>
                </tr>
              ) : (
                orders.map((order) => (
                  <tr key={order.order_id} className="hover:bg-gray-50 transition-colors">
                    <td className="px-4 py-3 text-gray-500 font-mono text-xs">
                      {order.order_id}
                    </td>
                    <td className="px-4 py-3 font-medium text-gray-800">
                      {order.client_name || '—'}
                    </td>
                    <td className="px-4 py-3 text-gray-600 whitespace-nowrap">
                      {formatDate(order.created_at)}
                    </td>
                    <td className="px-4 py-3 text-gray-800 whitespace-nowrap">
                      {order.total_price != null
                        ? `${Number(order.total_price).toFixed(2)} грн`
                        : '—'}
                    </td>
                    <td className="px-4 py-3">
                      <Link
                        href={`/admin/orders/${order.order_id}`}
                        className="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium text-white transition-colors hover:opacity-90"
                        style={{ backgroundColor: '#8b4513' }}
                      >
                        Переглянути
                      </Link>
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
