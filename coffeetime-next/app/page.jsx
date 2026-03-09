import Image from "next/image";
import Link from "next/link";
import HeroSlider from "@/components/HeroSlider";

const HERO_SLIDES = [
  { image: "/images/categories/coffee_category.jpg", text: "Кожен ковток — тепла історія" },
  { image: "/images/categories/dessert.jpg",          text: "Неможливо встояти…" },
  { image: "/images/categories/fast_food.jpg",        text: "Ідеальне комбо" },
];

async function getPopularData() {
  try {
    const res = await fetch(`${process.env.NEXT_PUBLIC_APP_URL}/api/popular`, { cache: "no-store" });
    if (!res.ok) return { sliders: [] };
    return res.json();
  } catch {
    return { sliders: [] };
  }
}

function ItemCard({ item }) {
  return (
    <div className="flex-shrink-0 w-44 md:w-56 bg-white rounded-2xl shadow-md overflow-hidden
      card-lift cursor-default border border-[#f0e8dc]">
      <div className="relative w-full h-36 md:h-44 bg-[#f5efe7]">
        <Image
          src={item.image ? `/${item.image}` : "/images/main/logo.png"}
          alt={item.name}
          fill
          className="object-cover"
          sizes="(max-width:768px)176px,224px"
        />
      </div>
      <div className="p-3">
        <p className="text-[#1a1a1a] font-semibold text-sm text-center leading-snug line-clamp-2">{item.name}</p>
      </div>
    </div>
  );
}

function PopularSection({ title, items, index }) {
  if (!items?.length) return null;
  return (
    <section className={`py-8 animate-fade-up delay-${(index + 1) * 100}`}>
      <div className="flex items-center gap-3 mb-5">
        <span className="w-1 h-8 bg-[#ffa500] rounded-full inline-block" />
        <h2 className="text-2xl md:text-3xl font-bold text-[#1a1a1a]">{title}</h2>
      </div>
      <div className="flex gap-4 overflow-x-auto pb-3 scrollbar-thin">
        {items.map((item, i) => <ItemCard key={i} item={item} />)}
      </div>
    </section>
  );
}

export default async function HomePage() {
  const { sliders = [] } = await getPopularData();

  return (
    <div className="min-h-screen bg-[#faf9f7]">
      <HeroSlider slides={HERO_SLIDES} />

      {/* About strip */}
      <div className="bg-gradient-to-r from-[#8b4513] to-[#a0522d] py-6 px-5">
        <div className="max-w-[1200px] mx-auto flex flex-col sm:flex-row items-center
          justify-between gap-4 text-white text-sm">
          <div className="flex items-center gap-3 animate-slide-right">
            <span className="text-2xl">☕</span>
            <span className="font-medium">Свіжозварена кава щодня</span>
          </div>
          <div className="flex items-center gap-3 animate-fade-in delay-200">
            <span className="text-2xl">🍕</span>
            <span className="font-medium">Піца та закуски власного приготування</span>
          </div>
          <div className="flex items-center gap-3 animate-slide-right delay-300"
            style={{animationDirection:"reverse"}}>
            <span className="text-2xl">🎁</span>
            <span className="font-medium">Подарункові сертифікати</span>
          </div>
        </div>
      </div>

      {/* Sliders */}
      <div className="max-w-[1200px] mx-auto px-4 py-10">
        {sliders.map((s, i) => (
          <PopularSection key={i} title={s.title} items={s.items} index={i} />
        ))}

        {/* CTA */}
        <div className="mt-12 rounded-3xl overflow-hidden relative
          bg-gradient-to-br from-[#8b4513] to-[#5c2d0e] p-10 text-center shadow-2xl">
          <div className="absolute inset-0 opacity-10"
            style={{backgroundImage:"url('/images/main/coffee_pattern.png')",backgroundSize:"200px"}} />
          <div className="relative z-10">
            <h2 className="text-white text-3xl md:text-4xl font-bold mb-3 animate-fade-up">
              Готові замовити?
            </h2>
            <p className="text-white/70 mb-8 text-base md:text-lg animate-fade-up delay-100">
              Переглядайте наше повне меню або купіть сертифікат у подарунок
            </p>
            <div className="flex flex-col sm:flex-row gap-4 justify-center animate-fade-up delay-200">
              <Link href="/menu"
                className="px-8 py-3 bg-[#ffa500] text-black font-bold rounded-2xl text-base
                  hover:bg-white hover:text-[#8b4513] transition-all duration-300
                  shadow-lg btn-pulse">
                Переглянути меню
              </Link>
              <Link href="/giftcards"
                className="px-8 py-3 bg-white/10 text-white font-bold rounded-2xl text-base
                  border border-white/30 hover:bg-white hover:text-[#8b4513]
                  transition-all duration-300">
                Сертифікати
              </Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
