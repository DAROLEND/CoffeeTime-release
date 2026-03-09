"use client";

import { useState } from "react";
import Image from "next/image";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useCart } from "@/context/CartContext";

export default function CartPage() {
  const { cart, setCart } = useCart();
  const router = useRouter();
  const [confirmClear, setConfirmClear] = useState(false);

  const total = cart.reduce((sum, item) => sum + Number(item.price) * item.quantity, 0);

  function updateQuantity(category, id, value) {
    const qty = Math.max(1, parseInt(value) || 1);
    setCart((prev) =>
      prev.map((item) =>
        item.category === category && item.id === id
          ? { ...item, quantity: qty }
          : item
      )
    );
  }

  function removeItem(category, id) {
    setCart((prev) =>
      prev.filter((item) => !(item.category === category && item.id === id))
    );
  }

  function clearCart() {
    if (confirmClear) {
      setCart([]);
      setConfirmClear(false);
    } else {
      setConfirmClear(true);
      setTimeout(() => setConfirmClear(false), 3000);
    }
  }

  /* ---- Empty state ---- */
  if (cart.length === 0) {
    return (
      <div className="min-h-screen bg-light flex flex-col items-center justify-center gap-6 px-4">
        <div className="text-7xl">🛒</div>
        <h1 className="text-2xl md:text-3xl font-bold text-dark text-center">
          Ваш кошик порожній
        </h1>
        <p className="text-gray-500 text-center">
          Додайте щось смачне з нашого меню!
        </p>
        <Link
          href="/menu"
          className="px-8 py-3 bg-accent text-black font-bold rounded-2xl text-lg
            hover:bg-footer hover:text-white transition-colors duration-300 shadow-md"
        >
          Перейти до меню
        </Link>
      </div>
    );
  }

  /* ---- Cart ---- */
  return (
    <div className="min-h-screen bg-light">
      {/* Header */}
      <div className="bg-footer py-10 text-center">
        <h1 className="text-white text-3xl md:text-4xl font-bold">Кошик</h1>
      </div>

      <div className="max-w-site mx-auto px-4 py-8">
        {/* Desktop table / mobile cards */}
        <div className="hidden md:block overflow-x-auto rounded-2xl shadow-md bg-white">
          <table className="w-full text-sm text-dark">
            <thead>
              <tr className="bg-footer text-white text-left">
                <th className="p-4 rounded-tl-2xl">Товар</th>
                <th className="p-4">Назва</th>
                <th className="p-4">Ціна</th>
                <th className="p-4">Кількість</th>
                <th className="p-4">Сума</th>
                <th className="p-4 rounded-tr-2xl">Видалити</th>
              </tr>
            </thead>
            <tbody>
              {cart.map((item, i) => (
                <tr
                  key={`${item.category}-${item.id}`}
                  className={`border-b border-gray-100 ${i % 2 === 1 ? "bg-gray-50" : ""}`}
                >
                  {/* Image */}
                  <td className="p-4">
                    <div className="relative w-16 h-16 rounded-xl overflow-hidden flex-shrink-0">
                      <Image
                        src={
                          item.image
                            ? `/${item.image}`
                            : "/images/main/placeholder.jpg"
                        }
                        alt={item.name}
                        fill
                        className="object-cover"
                        sizes="64px"
                      />
                    </div>
                  </td>
                  {/* Name */}
                  <td className="p-4">
                    <span className="font-semibold">{item.name}</span>
                  </td>
                  {/* Price */}
                  <td className="p-4 text-accent font-semibold">
                    {Number(item.price).toFixed(2)} ₴
                  </td>
                  {/* Quantity */}
                  <td className="p-4">
                    <input
                      type="number"
                      min={1}
                      value={item.quantity}
                      onChange={(e) => updateQuantity(item.category, item.id, e.target.value)}
                      className="w-16 border-2 border-gray-200 rounded-lg px-2 py-1 text-center
                        focus:outline-none focus:border-accent transition-colors"
                    />
                  </td>
                  {/* Subtotal */}
                  <td className="p-4 font-bold text-dark">
                    {(Number(item.price) * item.quantity).toFixed(2)} ₴
                  </td>
                  {/* Remove */}
                  <td className="p-4">
                    <button
                      onClick={() => removeItem(item.category, item.id)}
                      className="w-8 h-8 flex items-center justify-center rounded-full
                        bg-red-100 text-red-500 hover:bg-red-500 hover:text-white
                        transition-colors duration-200 text-lg font-bold"
                      aria-label="Видалити"
                    >
                      ×
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {/* Mobile cards */}
        <div className="flex flex-col gap-4 md:hidden">
          {cart.map((item) => (
            <div
              key={`${item.category}-${item.id}`}
              className="bg-white rounded-2xl shadow-md p-4 flex gap-4 items-start"
            >
              <div className="relative w-20 h-20 rounded-xl overflow-hidden flex-shrink-0">
                <Image
                  src={
                    item.image
                      ? `/${item.image}`
                      : "/images/main/placeholder.jpg"
                  }
                  alt={item.name}
                  fill
                  className="object-cover"
                  sizes="80px"
                />
              </div>
              <div className="flex-1 flex flex-col gap-1">
                <p className="font-bold text-dark">{item.name}</p>
                <p className="text-accent font-semibold">{Number(item.price).toFixed(2)} ₴</p>
                <div className="flex items-center gap-2 mt-1">
                  <label className="text-gray-500 text-sm">Кількість:</label>
                  <input
                    type="number"
                    min={1}
                    value={item.quantity}
                    onChange={(e) => updateQuantity(item.category, item.id, e.target.value)}
                    className="w-14 border-2 border-gray-200 rounded-lg px-2 py-1 text-center text-sm
                      focus:outline-none focus:border-accent transition-colors"
                  />
                </div>
                <p className="text-dark font-bold text-sm mt-1">
                  Сума: {(Number(item.price) * item.quantity).toFixed(2)} ₴
                </p>
              </div>
              <button
                onClick={() => removeItem(item.category, item.id)}
                className="w-8 h-8 flex-shrink-0 flex items-center justify-center rounded-full
                  bg-red-100 text-red-500 hover:bg-red-500 hover:text-white
                  transition-colors duration-200 text-lg font-bold"
                aria-label="Видалити"
              >
                ×
              </button>
            </div>
          ))}
        </div>

        {/* Total */}
        <div className="mt-6 flex justify-end">
          <div className="bg-white rounded-2xl shadow-md px-8 py-5 flex items-center gap-6">
            <span className="text-gray-500 text-lg">Разом:</span>
            <span className="text-3xl font-extrabold text-accent">
              {total.toFixed(2)} ₴
            </span>
          </div>
        </div>

        {/* Action buttons */}
        <div className="mt-6 flex flex-col sm:flex-row flex-wrap gap-3 justify-between items-center">
          <div className="flex gap-3 flex-wrap">
            <Link
              href="/menu"
              className="px-6 py-3 bg-white border-2 border-gray-200 text-dark font-semibold rounded-2xl
                hover:border-accent hover:text-accent transition-colors duration-200"
            >
              ← Повернутися до меню
            </Link>
            <button
              onClick={clearCart}
              className={`px-6 py-3 font-semibold rounded-2xl transition-colors duration-200
                ${
                  confirmClear
                    ? "bg-red-500 text-white hover:bg-red-600"
                    : "bg-white border-2 border-gray-200 text-red-500 hover:border-red-500"
                }`}
            >
              {confirmClear ? "Підтвердити очищення?" : "Очистити кошик"}
            </button>
          </div>

          <Link
            href="/checkout"
            className="px-8 py-3 bg-accent text-black font-bold rounded-2xl text-lg
              hover:bg-footer hover:text-white transition-colors duration-300 shadow-md"
          >
            Оформити замовлення →
          </Link>
        </div>
      </div>
    </div>
  );
}
