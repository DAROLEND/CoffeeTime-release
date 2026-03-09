"use client";

import { useState, useEffect, useCallback } from "react";
import Image from "next/image";
import Link from "next/link";

export default function HeroSlider({ slides = [] }) {
  const [current, setCurrent] = useState(0);
  const [prev, setPrev]       = useState(null);

  const goTo = useCallback((idx) => {
    setPrev(current);
    setCurrent(idx);
    setTimeout(() => setPrev(null), 700);
  }, [current]);

  const next = useCallback(() => goTo((current + 1) % slides.length), [current, slides.length, goTo]);
  const prevSlide = useCallback(() => goTo((current - 1 + slides.length) % slides.length), [current, slides.length, goTo]);

  useEffect(() => {
    if (slides.length <= 1) return;
    const id = setInterval(next, 5000);
    return () => clearInterval(id);
  }, [next, slides.length]);

  if (!slides.length) return null;

  return (
    <div className="relative w-full h-[320px] md:h-[540px] overflow-hidden select-none">
      {slides.map((slide, i) => (
        <div key={i}
          className={`absolute inset-0 transition-all duration-700
            ${i === current ? "opacity-100 scale-100" : "opacity-0 scale-105 pointer-events-none"}`}>
          <Image src={slide.image} alt={slide.text} fill
            className="object-cover" priority={i === 0} sizes="100vw" />
          {/* Gradient overlay */}
          <div className="absolute inset-0 bg-gradient-to-b from-black/20 via-black/30 to-black/60" />
          {/* Text */}
          <div className={`absolute inset-0 flex flex-col items-center justify-center px-6 gap-6
            ${i === current ? "animate-hero-text" : ""}`}>
            <h2 className="text-white text-3xl md:text-6xl font-extrabold text-center
              drop-shadow-2xl tracking-tight leading-tight max-w-3xl">
              {slide.text}
            </h2>
            <Link href="/menu"
              className="px-8 py-3 bg-[#ffa500] text-black font-bold rounded-2xl text-base md:text-lg
                hover:bg-white hover:text-[#8b4513] transition-all duration-300 shadow-xl
                hover:scale-105 active:scale-95">
              Переглянути меню →
            </Link>
          </div>
        </div>
      ))}

      {/* Arrows */}
      <button onClick={prevSlide} aria-label="Назад"
        className="absolute left-4 md:left-6 top-1/2 -translate-y-1/2 z-10
          w-10 h-10 md:w-12 md:h-12 flex items-center justify-center
          rounded-full bg-black/35 text-white backdrop-blur-sm
          hover:bg-[#ffa500] hover:text-black transition-all duration-300 text-lg font-bold">
        ‹
      </button>
      <button onClick={next} aria-label="Далі"
        className="absolute right-4 md:right-6 top-1/2 -translate-y-1/2 z-10
          w-10 h-10 md:w-12 md:h-12 flex items-center justify-center
          rounded-full bg-black/35 text-white backdrop-blur-sm
          hover:bg-[#ffa500] hover:text-black transition-all duration-300 text-lg font-bold">
        ›
      </button>

      {/* Dots */}
      <div className="absolute bottom-5 left-1/2 -translate-x-1/2 flex gap-2 z-10">
        {slides.map((_, i) => (
          <button key={i} onClick={() => goTo(i)} aria-label={`Слайд ${i+1}`}
            className={`rounded-full transition-all duration-300
              ${i === current
                ? "w-7 h-2.5 bg-[#ffa500]"
                : "w-2.5 h-2.5 bg-white/50 hover:bg-white/80"}`} />
        ))}
      </div>
    </div>
  );
}
