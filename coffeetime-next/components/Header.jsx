"use client";

import { useState, useEffect } from "react";
import Link from "next/link";
import Image from "next/image";
import { usePathname } from "next/navigation";
import { useCart } from "@/context/CartContext";
import { useAuth } from "@/context/AuthContext";

const NAV_LINKS = [
  { href: "/",          label: "Головна" },
  { href: "/menu",      label: "Меню" },
  { href: "/giftcards", label: "Сертифікати" },
  { href: "/gallery",   label: "Галерея" },
  { href: "/reviews",   label: "Відгуки" },
];

export default function Header() {
  const pathname = usePathname();
  const [open, setOpen] = useState(false);
  const [scrolled, setScrolled] = useState(false);
  const { cartCount } = useCart();
  const { user } = useAuth();

  useEffect(() => setOpen(false), [pathname]);

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 20);
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  return (
    <header
      className={`sticky top-0 z-[1000] transition-all duration-300 backdrop-blur-[10px]
        ${scrolled ? "bg-[rgba(90,42,8,0.97)] shadow-2xl" : "bg-[rgba(151,86,39,0.90)] shadow-md"}`}
    >
      <div className="max-w-[1200px] mx-auto px-5 flex items-center justify-between h-[84px]">

        {/* Logo */}
        <Link href="/" className="flex-shrink-0 flex items-center gap-3 group">
          <Image
            src="/images/main/logo.png"
            alt="Coffee Time"
            width={74}
            height={74}
            className="drop-shadow-lg transition-all duration-300 group-hover:scale-110 group-hover:rotate-3"
            priority
          />
          <span className="hidden md:block text-white font-bold text-xl tracking-wide
            transition-colors duration-300 group-hover:text-[#ffa500]">
            Coffee Time
          </span>
        </Link>

        {/* Desktop nav */}
        <nav className="hidden lg:flex items-center gap-7">
          {NAV_LINKS.map(({ href, label }) => {
            const active = href === "/" ? pathname === "/" : pathname.startsWith(href);
            return (
              <Link key={href} href={href}
                className={`relative text-sm font-semibold tracking-wide py-1
                  transition-colors duration-200
                  after:absolute after:bottom-0 after:left-0 after:h-[2px]
                  after:bg-[#ffa500] after:rounded-full after:transition-all after:duration-300
                  ${active
                    ? "text-[#ffa500] after:w-full"
                    : "text-white/90 hover:text-white after:w-0 hover:after:w-full"}`}>
                {label}
              </Link>
            );
          })}
        </nav>

        {/* Cart + Auth + Burger */}
        <div className="flex items-center gap-3">
          <Link href="/cart"
            className="relative group flex items-center justify-center
              w-11 h-11 rounded-2xl hover:bg-[#ffa500]/20 transition-all duration-300">
            <Image src="/images/main/cart.png" alt="Кошик" width={30} height={30}
              className="transition-transform duration-300 group-hover:scale-110 drop-shadow" />
            {cartCount > 0 && (
              <span className="absolute -top-1 -right-1 min-w-[20px] h-5 px-1
                flex items-center justify-center
                bg-[#ffa500] text-black text-[11px] font-bold rounded-full shadow-lg">
                {cartCount}
              </span>
            )}
          </Link>

          {user ? (
            <Link href="/profile"
              className="px-4 py-2 bg-[#ffa500] text-black text-sm font-bold rounded-xl
                hover:bg-white hover:text-[#8b4513] transition-all duration-300 shadow-md">
              Профіль
            </Link>
          ) : (
            <>
              <Link href="/login"
                className="px-4 py-2 bg-[#ffa500] text-black text-sm font-bold rounded-xl
                  hover:bg-white hover:text-[#8b4513] transition-all duration-300 shadow-md">
                Увійти
              </Link>
              <Link href="/register"
                className="hidden sm:block px-4 py-2 text-white text-sm font-semibold
                  rounded-xl border border-white/30 bg-white/10
                  hover:bg-white hover:text-[#8b4513] transition-all duration-300">
                Реєстрація
              </Link>
            </>
          )}

          {/* Hamburger */}
          <button onClick={() => setOpen(o => !o)} aria-label="Меню"
            className="lg:hidden flex flex-col justify-center gap-[5px] w-10 h-10
              rounded-xl hover:bg-white/10 transition-colors duration-200 p-2">
            <span className={`h-[2px] bg-white rounded-full transition-all duration-300
              ${open ? "rotate-45 translate-y-[7px] w-full" : "w-full"}`} />
            <span className={`h-[2px] bg-white rounded-full transition-all duration-300
              ${open ? "opacity-0 w-0" : "w-4/5"}`} />
            <span className={`h-[2px] bg-white rounded-full transition-all duration-300
              ${open ? "-rotate-45 -translate-y-[7px] w-full" : "w-full"}`} />
          </button>
        </div>
      </div>

      {/* Mobile menu */}
      <div className={`lg:hidden overflow-hidden transition-all duration-300
        bg-[rgba(90,42,8,0.98)] backdrop-blur-lg
        ${open ? "max-h-96 border-t border-white/10" : "max-h-0"}`}>
        <nav className="flex flex-col px-5 py-4 gap-1">
          {NAV_LINKS.map(({ href, label }) => {
            const active = href === "/" ? pathname === "/" : pathname.startsWith(href);
            return (
              <Link key={href} href={href}
                className={`px-4 py-3 rounded-xl font-semibold text-sm transition-all duration-200
                  ${active ? "bg-[#ffa500] text-black" : "text-white/90 hover:bg-white/10 hover:text-white"}`}>
                {label}
              </Link>
            );
          })}
          {!user && (
            <Link href="/register"
              className="mt-2 px-4 py-3 rounded-xl bg-white/10 text-white font-semibold text-sm
                hover:bg-white/20 transition-colors duration-200">
              Реєстрація
            </Link>
          )}
        </nav>
      </div>
    </header>
  );
}
