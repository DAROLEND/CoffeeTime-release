"use client";

import { createContext, useContext, useState, useEffect, useCallback } from "react";

const CartContext = createContext({
  cart: [],
  setCart: () => {},
  addToCart: () => {},
  removeFromCart: () => {},
  updateQuantity: () => {},
  clearCart: () => {},
  cartCount: 0,
  cartTotal: 0,
});

export function CartProvider({ children }) {
  const [cart, setCartRaw] = useState([]);

  // Hydrate from localStorage on mount (client only)
  useEffect(() => {
    try {
      const stored = localStorage.getItem("ct_cart");
      if (stored) setCartRaw(JSON.parse(stored));
    } catch {}
  }, []);

  const setCart = useCallback((updater) => {
    setCartRaw((prev) => {
      const next = typeof updater === "function" ? updater(prev) : updater;
      try {
        localStorage.setItem("ct_cart", JSON.stringify(next));
      } catch {}
      return next;
    });
  }, []);

  const addToCart = useCallback((item) => {
    // item: { category, id, name, price, image, description? }
    setCart((prev) => {
      const idx = prev.findIndex(
        (i) => i.category === item.category && i.id === item.id
      );
      if (idx >= 0) {
        const next = [...prev];
        next[idx] = { ...next[idx], quantity: next[idx].quantity + 1 };
        return next;
      }
      return [...prev, { ...item, quantity: 1 }];
    });
  }, [setCart]);

  const removeFromCart = useCallback((category, id) => {
    setCart((prev) =>
      prev.filter((i) => !(i.category === category && i.id === id))
    );
  }, [setCart]);

  const updateQuantity = useCallback((category, id, quantity) => {
    const q = Math.max(1, quantity);
    setCart((prev) =>
      prev.map((i) =>
        i.category === category && i.id === id ? { ...i, quantity: q } : i
      )
    );
  }, [setCart]);

  const clearCart = useCallback(() => {
    setCart([]);
  }, [setCart]);

  const cartCount = cart.reduce((sum, item) => sum + (item.quantity ?? 1), 0);
  const cartTotal = cart.reduce(
    (sum, item) => sum + item.price * (item.quantity ?? 1),
    0
  );

  return (
    <CartContext.Provider
      value={{ cart, setCart, addToCart, removeFromCart, updateQuantity, clearCart, cartCount, cartTotal }}
    >
      {children}
    </CartContext.Provider>
  );
}

export function useCart() {
  return useContext(CartContext);
}
