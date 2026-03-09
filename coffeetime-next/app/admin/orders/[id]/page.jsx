'use client';

// FILE 8: /admin/orders/[id] — Single order detail view with approve/decline actions.

import { useEffect, useState } from 'react';
import { useRouter, useParams } from 'next/navigation';
import Link from 'next/link';

const STATUS_LABELS = {
  pending:  { label: 'Очікує',     color: 'bg-yellow-100 text-yellow-800 border-yellow-200' },
  approved: { label: 'Підтверджено', color: 'bg-green-100 text-green-800 border-green-200' },
  declined: { label: 'Відхилено',  color: 'bg-red-100 text-red-800 border-red-200' },
};

const PAYMENT_LABELS = {
  cash:   'Готівка',
  card:   'Картка',
  online: 'Онлайн',
};

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

export default function AdminOrderDetailPage() {
  const router = useRouter();
  const { id } = useParams();

  const [order, setOrder] = useState(null);
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [actionLoading, setActionLoading] = useState(false);
  const [actionError, setActionError] = useState('');

  useEffect(() => {
    async function load() {
      setLoading(true);
      setError('');
      try {
        const meRes = await fetch('/api/admin/me');
        if (!meRes.ok) { router.replace('/admin/login'); return; }

        const res = await fetch(`/api/admin/orders/${id}`);
        if (!res.ok) {
          setError(res.status === 404 ? 'Замовлення не знайдено.' : 'Помилка завантаження.');
          setLoading(false);
          return;
        }
        const data = await res.json();
        setOrder(data.order);
        setItems(data.items || []);
      } catch {
        setError('Помилка мережі.');
      } finally {
        setLoading(false);
      }
    }
    load();
  }, [id, router]);

  async function handleStatusChange(status) {
    setActionError('');
    setActionLoading(true);
    try {
      const res = await fetch(`/api/admin/orders/${id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ status }),
      });
      const data = await res.json();
      if (!res.ok) {
        setActionError(data.error || 'Помилка оновлення статусу.');
        return;
      }
      setOrder((prev) => ({ ...prev, status }));
    } catch {
      setActionError('Помилка мережі.');
    } finally {
      setActionLoading(false);
    }
  }

  if (loading) {
    return <div className="p-8 text-gray-500 text-sm">Завантаження…</div>;
  }

  if (error) {
    return (
      <div className="p-8">
        <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-4 py-3 mb-4">
          {error}
        </p>
        <Link href="/admin/orders" className="text-sm text-amber-800 hover:underline">
          ← Назад до замовлень
        </Link>
      </div>
    );
  }

  if (!order) return null;

  const statusInfo = STATUS_LABELS[order.status] || { label: order.status, color: 'bg-gray-100 text-gray-700 border-gray-200' };
  const total = items.reduce((sum, item) => sum + Number(item.price) * Number(item.qty), 0);

  return (
    <div className="p-8 max-w-3xl">
      {/* Back link */}
      <Link href="/admin/orders" className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-800 mb-6 transition-colors">
        ← Назад до замовлень
      </Link>

      {/* Title + status badge */}
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-2xl font-bold text-gray-800">
          Замовлення <span className="font-mono text-lg">{order.order_id}</span>
        </h1>
        <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border ${statusInfo.color}`}>
          {statusInfo.label}
        </span>
      </div>

      {/* Order details card */}
      <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm mb-6">
        <h2 className="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wide">Інформація про клієнта</h2>
        <dl className="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3 text-sm">
          <InfoRow label="Ім'я" value={order.client_name} />
          <InfoRow label="Телефон" value={order.phone} />
          <InfoRow label="Дата" value={formatDate(order.created_at)} />
          <InfoRow label="Готовність о" value={order.ready_time || '—'} />
          <InfoRow label="Спосіб оплати" value={PAYMENT_LABELS[order.payment_method] || order.payment_method || '—'} />
          <InfoRow label="Коментар" value={order.comment || '—'} />
        </dl>
      </div>

      {/* Items table */}
      <div className="bg-white border border-gray-200 rounded-xl shadow-sm mb-6 overflow-hidden">
        <h2 className="text-sm font-semibold text-gray-700 uppercase tracking-wide px-6 pt-5 pb-3">
          Товари у замовленні
        </h2>
        <table className="min-w-full text-sm">
          <thead className="bg-gray-50 border-y border-gray-200">
            <tr>
              <th className="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Товар</th>
              <th className="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Кількість</th>
              <th className="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Ціна</th>
              <th className="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Сума</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {items.length === 0 ? (
              <tr>
                <td colSpan={4} className="px-6 py-6 text-center text-gray-400">
                  Товарів немає
                </td>
              </tr>
            ) : (
              items.map((item, idx) => (
                <tr key={idx} className="hover:bg-gray-50 transition-colors">
                  <td className="px-6 py-3 text-gray-800 font-medium">
                    {item.product_name || `#${item.product_id}`}
                  </td>
                  <td className="px-6 py-3 text-center text-gray-600">{item.qty}</td>
                  <td className="px-6 py-3 text-right text-gray-600 whitespace-nowrap">
                    {Number(item.price).toFixed(2)} грн
                  </td>
                  <td className="px-6 py-3 text-right font-medium text-gray-800 whitespace-nowrap">
                    {(Number(item.price) * Number(item.qty)).toFixed(2)} грн
                  </td>
                </tr>
              ))
            )}
          </tbody>
          <tfoot className="bg-gray-50 border-t border-gray-200">
            <tr>
              <td colSpan={3} className="px-6 py-3 text-right text-sm font-semibold text-gray-700">
                Разом:
              </td>
              <td className="px-6 py-3 text-right text-sm font-bold text-gray-900 whitespace-nowrap">
                {total.toFixed(2)} грн
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      {/* Action buttons — only show if still pending */}
      {order.status === 'pending' && (
        <div className="flex items-center gap-3">
          <button
            onClick={() => handleStatusChange('approved')}
            disabled={actionLoading}
            className="px-5 py-2.5 rounded-lg text-white text-sm font-semibold bg-green-600 hover:bg-green-700 transition-colors disabled:opacity-60"
          >
            {actionLoading ? '…' : 'Підтвердити'}
          </button>
          <button
            onClick={() => handleStatusChange('declined')}
            disabled={actionLoading}
            className="px-5 py-2.5 rounded-lg text-white text-sm font-semibold bg-red-600 hover:bg-red-700 transition-colors disabled:opacity-60"
          >
            {actionLoading ? '…' : 'Відхилити'}
          </button>
          {actionError && (
            <p className="text-sm text-red-600">{actionError}</p>
          )}
        </div>
      )}

      {/* Confirmed/declined message */}
      {order.status !== 'pending' && (
        <p className="text-sm text-gray-500">
          Статус замовлення вже оновлено: <strong>{statusInfo.label}</strong>.
        </p>
      )}
    </div>
  );
}

function InfoRow({ label, value }) {
  return (
    <div>
      <dt className="text-xs font-medium text-gray-400 uppercase tracking-wide">{label}</dt>
      <dd className="mt-0.5 text-gray-800">{value || '—'}</dd>
    </div>
  );
}
