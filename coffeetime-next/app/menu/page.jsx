"use client";

import { useState, useEffect, useCallback } from "react";
import Image from "next/image";
import { useCart } from "@/context/CartContext";

const CATEGORIES = [
  { key: "coffee_items",     label: "☕ Кава" },
  { key: "fast_food_items",  label: "🍔 Фаст-фуд" },
  { key: "pizza_items",      label: "🍕 Піца" },
  { key: "cold_drink_items", label: "🧃 Напої" },
  { key: "dessert_items",    label: "🍰 Десерти" },
];

function MenuCard({ item, category, onAdd, added }) {
  return (
    <div className="bg-white rounded-2xl shadow-sm border border-[#f0e8dc] overflow-hidden
      flex flex-col card-lift group">
      <div className="relative w-full h-52 bg-[#f5efe7] overflow-hidden">
        <Image
          src={item.image ? `/${item.image}` : "/images/main/logo.png"}
          alt={item.name}
          fill
          className="object-cover transition-transform duration-500 group-hover:scale-105"
          sizes="(max-width:640px)100vw,(max-width:1024px)50vw,33vw"
        />
        <div className="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent opacity-0
          group-hover:opacity-100 transition-opacity duration-300" />
      </div>
      <div className="p-4 flex flex-col flex-1 gap-2">
        <h3 className="font-bold text-[#1a1a1a] text-lg leading-snug">{item.name}</h3>
        {item.description && (
          <p className="text-gray-500 text-sm flex-1 line-clamp-2">{item.description}</p>
        )}
        <div className="flex items-center justify-between mt-3">
          <span className="text-[#ffa500] font-extrabold text-xl">
            {Number(item.price).toFixed(2)} ₴
          </span>
          <button
            onClick={() => onAdd(item, category)}
            className={`px-5 py-2 rounded-xl font-bold text-sm transition-all duration-300
              active:scale-95
              ${added
                ? "bg-green-500 text-white scale-95"
                : "bg-[#ffa500] text-black hover:bg-[#8b4513] hover:text-white shadow-md"}`}>
            {added ? "✓ Додано" : "Додати"}
          </button>
        </div>
      </div>
    </div>
  );
}

function SkeletonCard() {
  return (
    <div className="bg-white rounded-2xl overflow-hidden border border-[#f0e8dc] shadow-sm">
      <div className="skeleton h-52 w-full" />
      <div className="p-4 space-y-3">
        <div className="skeleton h-5 w-3/4" />
        <div className="skeleton h-4 w-full" />
        <div className="skeleton h-4 w-2/3" />
        <div className="flex justify-between items-center pt-1">
          <div className="skeleton h-6 w-16" />
          <div className="skeleton h-9 w-24" />
        </div>
      </div>
    </div>
  );
}

export default function MenuPage() {
  const [activeCategory, setActiveCategory] = useState(CATEGORIES[0].key);
  const [items, setItems]   = useState([]);
  const [loading, setLoading] = useState(false);
  const [addedId, setAddedId] = useState(null);
  const { addToCart } = useCart();

  const fetchItems = useCallback(async (cat) => {
    setLoading(true);
    setItems([]);
    try {
      const res = await fetch(`/api/menu?category=${cat}`);
      if (!res.ok) throw new Error();
      const data = await res.json();
      setItems(data.items ?? []);
    } catch {
      setItems([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { fetchItems(activeCategory); }, [activeCategory, fetchItems]);

  function handleAdd(item, category) {
    addToCart({ category, id: item.id, name: item.name,
      price: Number(item.price), image: item.image ?? "" });
    setAddedId(item.id);
    setTimeout(() => setAddedId(null), 1800);
  }

  return (
    <div className="min-h-screen bg-[#faf9f7]">
      {/* Hero */}
      <div className="relative bg-gradient-to-br from-[#8b4513] to-[#5c2d0e] py-14 text-center overflow-hidden">
        <div className="absolute inset-0 opacity-10"
          style={{backgroundImage:"url('/images/main/coffee_pattern.png')",backgroundSize:"180px"}} />
        <div className="relative z-10">
          <h1 className="text-white text-4xl md:text-5xl font-extrabold tracking-tight animate-hero-text">
            Наше Меню
          </h1>
          <p className="text-white/65 mt-2 text-base animate-fade-in delay-200">
            Оберіть категорію та насолоджуйтесь
          </p>
        </div>
      </div>

      <div className="max-w-[1200px] mx-auto px-4 py-8">
        {/* Category tabs */}
        <div className="flex flex-wrap gap-2 justify-center mb-8">
          {CATEGORIES.map(({ key, label }) => (
            <button key={key} onClick={() => setActiveCategory(key)}
              className={`px-5 py-2.5 rounded-full font-semibold text-sm transition-all duration-200
                ${activeCategory === key
                  ? "bg-[#ffa500] text-black shadow-md scale-105"
                  : "bg-white text-[#1a1a1a] border border-[#e8d8c4] hover:border-[#ffa500] hover:text-[#ffa500]"}`}>
              {label}
            </button>
          ))}
        </div>

        {/* Grid */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          {loading
            ? Array.from({ length: 6 }).map((_, i) => <SkeletonCard key={i} />)
            : items.map(item => (
                <MenuCard key={item.id} item={item} category={activeCategory}
                  onAdd={handleAdd} added={addedId === item.id} />
              ))}
        </div>

        {!loading && items.length === 0 && (
          <div className="text-center py-20 text-gray-400 text-lg">
            У цій категорії поки немає позицій
          </div>
        )}
      </div>

      {/* Toast */}
      {addedId && (
        <div className="fixed bottom-6 left-1/2 -translate-x-1/2 z-50
          bg-[#8b4513] text-white px-7 py-3 rounded-2xl shadow-2xl font-semibold text-sm
          animate-fade-up pointer-events-none">
          ✓ Додано до кошика
        </div>
      )}
    </div>
  );
}
