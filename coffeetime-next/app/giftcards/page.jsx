"use client";

import { useState, useEffect } from "react";
import Image from "next/image";
import { useCart } from "@/context/CartContext";

export default function GiftCardsPage() {
  const { cart, setCart } = useCart();
  const [cards, setCards] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [addedId, setAddedId] = useState(null);

  useEffect(() => {
    fetch("/api/giftcards")
      .then((r) => (r.ok ? r.json() : Promise.reject(r)))
      .then((data) => setCards(data.giftcards ?? data ?? []))
      .catch(() => setError("Не вдалося завантажити сертифікати."))
      .finally(() => setLoading(false));
  }, []);

  function addToCart(card) {
    setCart((prev) => {
      const existing = prev.find(
        (item) => item.id === card.id && item.category === "giftcards"
      );
      if (existing) {
        return prev.map((item) =>
          item.id === card.id && item.category === "giftcards"
            ? { ...item, quantity: item.quantity + 1 }
            : item
        );
      }
      return [
        ...prev,
        {
          category: "giftcards",
          id: card.id,
          quantity: 1,
          name: card.title,
          price: card.price,
          image: "/images/main/logo.png",
        },
      ];
    });

    // Flash feedback
    setAddedId(card.id);
    setTimeout(() => setAddedId(null), 1500);
  }

  return (
    <div className="max-w-site mx-auto px-4 py-10">
      <h1 className="text-3xl font-bold text-center text-dark mb-2">
        Подарункові сертифікати
      </h1>
      <p className="text-center text-gray-500 text-sm mb-10">
        Подаруйте насолоду — сертифікат Coffee Time для ваших близьких
      </p>

      {loading && (
        <div className="flex justify-center items-center h-40 text-gray-400 text-lg">
          Завантаження…
        </div>
      )}

      {error && (
        <div className="text-center text-red-500 py-10">{error}</div>
      )}

      {!loading && !error && cards.length === 0 && (
        <div className="text-center text-gray-400 py-10">
          Сертифікати наразі недоступні.
        </div>
      )}

      {!loading && !error && cards.length > 0 && (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
          {cards.map((card) => {
            const isAdded = addedId === card.id;
            const cartItem = cart.find(
              (item) => item.id === card.id && item.category === "giftcards"
            );

            return (
              <div
                key={card.id}
                className="bg-white rounded-2xl shadow-md overflow-hidden
                  hover:shadow-xl hover:-translate-y-1 transition-all duration-300
                  flex flex-col border border-gray-100"
              >
                {/* Card visual */}
                <div className="relative bg-gradient-to-br from-footer to-[#5c2d0e] p-8 flex flex-col items-center">
                  <Image
                    src="/images/main/logo.png"
                    alt="Coffee Time"
                    width={80}
                    height={80}
                    className="drop-shadow-lg"
                  />
                  <p className="mt-3 text-white/70 text-xs tracking-widest uppercase">
                    Coffee Time
                  </p>
                  <p className="text-accent text-3xl font-extrabold mt-1">
                    {card.title}
                  </p>
                  {/* Decorative dots */}
                  <div className="absolute top-3 right-4 w-16 h-16 rounded-full bg-white/5" />
                  <div className="absolute bottom-2 left-3 w-10 h-10 rounded-full bg-white/5" />
                </div>

                {/* Card info */}
                <div className="p-5 flex flex-col flex-1">
                  <p className="text-gray-500 text-sm mb-1">Вартість</p>
                  <p className="text-dark text-2xl font-bold mb-4">
                    {Number(card.price).toLocaleString("uk-UA")} ₴
                  </p>

                  {cartItem && (
                    <p className="text-xs text-green-600 mb-2">
                      У кошику: {cartItem.quantity} шт.
                    </p>
                  )}

                  <button
                    onClick={() => addToCart(card)}
                    className={`mt-auto w-full py-3 rounded-xl font-semibold text-sm
                      transition-all duration-300 focus:outline-none
                      ${
                        isAdded
                          ? "bg-green-500 text-white scale-95"
                          : "bg-accent text-black hover:bg-footer hover:text-white"
                      }`}
                  >
                    {isAdded ? "Додано ✓" : "Купити"}
                  </button>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
