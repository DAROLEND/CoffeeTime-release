"use client";

import { useState, useEffect } from "react";
import Image from "next/image";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useCart } from "@/context/CartContext";
import { useAuth } from "@/context/AuthContext";

const PAYMENT_OPTIONS = [
  { value: "apple_pay",     label: "Apple Pay" },
  { value: "google_pay",    label: "Google Pay" },
  { value: "card_online",   label: "Картка онлайн" },
  { value: "cash_on_pickup",label: "Готівка при отриманні" },
];

export default function CheckoutPage() {
  const { cart, setCart } = useCart();
  const { user } = useAuth();
  const router = useRouter();

  const [form, setForm] = useState({
    firstName: "",
    lastName: "",
    phone: "",
    readyTime: "",
    comment: "",
    payment: "cash_on_pickup",
  });

  const [submitting, setSubmitting] = useState(false);
  const [success, setSuccess] = useState(false);
  const [error, setError] = useState("");

  /* Pre-fill from auth user */
  useEffect(() => {
    if (user) {
      setForm((prev) => ({
        ...prev,
        firstName: user.name    ?? prev.firstName,
        lastName:  user.surname ?? prev.lastName,
        phone:     user.phone   ?? prev.phone,
      }));
    }
  }, [user]);

  /* Redirect to cart if empty (but only after mount to avoid SSR mismatch) */
  useEffect(() => {
    if (cart.length === 0 && !success) {
      router.replace("/cart");
    }
  }, [cart, success, router]);

  const total = cart.reduce((sum, item) => sum + Number(item.price) * item.quantity, 0);

  function handleChange(e) {
    const { name, value } = e.target;
    setForm((prev) => ({ ...prev, [name]: value }));
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setSubmitting(true);
    setError("");

    try {
      const res = await fetch("/api/checkout", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ cart, ...form }),
      });

      const data = await res.json();

      if (!res.ok) {
        setError(data.error || "Виникла помилка при оформленні замовлення.");
        return;
      }

      setCart([]);
      setSuccess(true);
    } catch {
      setError("Не вдалося з'єднатися з сервером. Спробуйте пізніше.");
    } finally {
      setSubmitting(false);
    }
  }

  /* Success screen */
  if (success) {
    return (
      <div className="min-h-screen bg-light flex flex-col items-center justify-center gap-6 px-4">
        <div className="bg-white rounded-3xl shadow-xl p-10 max-w-md w-full text-center flex flex-col items-center gap-5">
          <div className="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center text-3xl">
            ✓
          </div>
          <h2 className="text-2xl font-bold text-dark">
            Ваше замовлення успішно оформлено!
          </h2>
          <p className="text-gray-500">
            Дякуємо за вибір Coffee Time. Ми вже готуємо ваше замовлення!
          </p>
          <Link
            href="/"
            className="px-8 py-3 bg-accent text-black font-bold rounded-2xl
              hover:bg-footer hover:text-white transition-colors duration-300"
          >
            На головну
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-light">
      {/* Header */}
      <div className="bg-footer py-10 text-center">
        <h1 className="text-white text-3xl md:text-4xl font-bold">Оформлення замовлення</h1>
      </div>

      <div className="max-w-site mx-auto px-4 py-8 grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">

        {/* Order summary */}
        <div className="bg-white rounded-2xl shadow-md p-6 flex flex-col gap-4">
          <h2 className="text-xl font-bold text-dark border-b border-gray-100 pb-3">
            Ваше замовлення
          </h2>

          <div className="flex flex-col gap-3 max-h-80 overflow-y-auto pr-1">
            {cart.map((item) => (
              <div
                key={`${item.category}-${item.id}`}
                className="flex items-center gap-3"
              >
                <div className="relative w-14 h-14 rounded-xl overflow-hidden flex-shrink-0">
                  <Image
                    src={
                      item.image
                        ? `/${item.image}`
                        : "/images/main/placeholder.jpg"
                    }
                    alt={item.name}
                    fill
                    className="object-cover"
                    sizes="56px"
                  />
                </div>
                <div className="flex-1 min-w-0">
                  <p className="font-semibold text-dark text-sm truncate">{item.name}</p>
                  <p className="text-gray-400 text-xs">
                    {item.quantity} × {Number(item.price).toFixed(2)} ₴
                  </p>
                </div>
                <p className="font-bold text-accent text-sm flex-shrink-0">
                  {(Number(item.price) * item.quantity).toFixed(2)} ₴
                </p>
              </div>
            ))}
          </div>

          <div className="border-t border-gray-100 pt-4 flex justify-between items-center">
            <span className="text-gray-500 font-medium">Всього:</span>
            <span className="text-2xl font-extrabold text-accent">{total.toFixed(2)} ₴</span>
          </div>

          <Link
            href="/cart"
            className="text-center text-sm text-gray-400 hover:text-accent transition-colors duration-200 mt-1"
          >
            ← Змінити кошик
          </Link>
        </div>

        {/* Checkout form */}
        <form
          onSubmit={handleSubmit}
          className="bg-white rounded-2xl shadow-md p-6 flex flex-col gap-5"
        >
          <h2 className="text-xl font-bold text-dark border-b border-gray-100 pb-3">
            Дані для замовлення
          </h2>

          {/* Name row */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="flex flex-col gap-1">
              <label className="text-sm font-semibold text-dark" htmlFor="firstName">
                Ім'я <span className="text-red-500">*</span>
              </label>
              <input
                id="firstName"
                name="firstName"
                type="text"
                required
                value={form.firstName}
                onChange={handleChange}
                placeholder="Іван"
                className="border-2 border-gray-200 rounded-xl px-4 py-2.5 text-sm
                  focus:outline-none focus:border-accent transition-colors"
              />
            </div>
            <div className="flex flex-col gap-1">
              <label className="text-sm font-semibold text-dark" htmlFor="lastName">
                Прізвище <span className="text-red-500">*</span>
              </label>
              <input
                id="lastName"
                name="lastName"
                type="text"
                required
                value={form.lastName}
                onChange={handleChange}
                placeholder="Іваненко"
                className="border-2 border-gray-200 rounded-xl px-4 py-2.5 text-sm
                  focus:outline-none focus:border-accent transition-colors"
              />
            </div>
          </div>

          {/* Phone */}
          <div className="flex flex-col gap-1">
            <label className="text-sm font-semibold text-dark" htmlFor="phone">
              Телефон <span className="text-red-500">*</span>
            </label>
            <input
              id="phone"
              name="phone"
              type="tel"
              required
              value={form.phone}
              onChange={handleChange}
              placeholder="+380 XX XXX XX XX"
              className="border-2 border-gray-200 rounded-xl px-4 py-2.5 text-sm
                focus:outline-none focus:border-accent transition-colors"
            />
          </div>

          {/* Ready time */}
          <div className="flex flex-col gap-1">
            <label className="text-sm font-semibold text-dark" htmlFor="readyTime">
              Час готовності <span className="text-red-500">*</span>
            </label>
            <input
              id="readyTime"
              name="readyTime"
              type="datetime-local"
              required
              step={1800}
              value={form.readyTime}
              onChange={handleChange}
              className="border-2 border-gray-200 rounded-xl px-4 py-2.5 text-sm
                focus:outline-none focus:border-accent transition-colors"
            />
            <p className="text-xs text-gray-400">
              Пн–Пт: 08:00–20:00 · Сб: 10:00–20:00 · Нд: 12:00–20:00
            </p>
          </div>

          {/* Payment */}
          <div className="flex flex-col gap-1">
            <label className="text-sm font-semibold text-dark" htmlFor="payment">
              Спосіб оплати <span className="text-red-500">*</span>
            </label>
            <select
              id="payment"
              name="payment"
              required
              value={form.payment}
              onChange={handleChange}
              className="border-2 border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-white
                focus:outline-none focus:border-accent transition-colors cursor-pointer"
            >
              {PAYMENT_OPTIONS.map(({ value, label }) => (
                <option key={value} value={value}>{label}</option>
              ))}
            </select>
          </div>

          {/* Comment */}
          <div className="flex flex-col gap-1">
            <label className="text-sm font-semibold text-dark" htmlFor="comment">
              Коментар до замовлення
            </label>
            <textarea
              id="comment"
              name="comment"
              rows={3}
              value={form.comment}
              onChange={handleChange}
              placeholder="Побажання, алергії, деталі…"
              className="border-2 border-gray-200 rounded-xl px-4 py-2.5 text-sm resize-none
                focus:outline-none focus:border-accent transition-colors"
            />
          </div>

          {/* Error */}
          {error && (
            <div className="bg-red-50 border border-red-200 text-red-600 rounded-xl px-4 py-3 text-sm">
              {error}
            </div>
          )}

          {/* Submit */}
          <button
            type="submit"
            disabled={submitting}
            className="w-full py-3.5 bg-accent text-black font-bold rounded-2xl text-lg
              hover:bg-footer hover:text-white active:scale-[0.98]
              disabled:opacity-60 disabled:cursor-not-allowed
              transition-all duration-200 shadow-md"
          >
            {submitting ? "Оформлюємо…" : "Підтвердити замовлення"}
          </button>
        </form>

      </div>
    </div>
  );
}
