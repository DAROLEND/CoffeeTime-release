import Image from "next/image";
import Link from "next/link";

export default function Footer() {
  const year = new Date().getFullYear();
  return (
    <footer className="bg-[#8b4513] text-white">
      {/* Wave top */}
      <div className="w-full overflow-hidden leading-[0] bg-[#faf9f7]">
        <svg viewBox="0 0 1440 60" preserveAspectRatio="none" className="w-full h-12 block">
          <path d="M0,40 C360,80 1080,0 1440,40 L1440,60 L0,60 Z" fill="#8b4513"/>
        </svg>
      </div>

      <div className="max-w-[1200px] mx-auto px-5 pt-4 pb-10 grid grid-cols-1 sm:grid-cols-3 gap-10">
        {/* Brand */}
        <div className="flex flex-col gap-4">
          <div className="flex items-center gap-3">
            <Image src="/images/main/logo.png" alt="Coffee Time" width={52} height={52}
              className="drop-shadow-lg" />
            <span className="text-white font-bold text-xl tracking-wide">Coffee Time</span>
          </div>
          <p className="text-white/65 text-sm leading-relaxed">
            Ваш затишний куточок для відпочинку,<br/>смачної кави та гарного настрою.
          </p>
          <a href="https://www.instagram.com/coffeetime_husiatyn/" target="_blank" rel="noopener noreferrer"
            className="inline-flex items-center gap-2 text-white/70 hover:text-[#ffa500]
              transition-colors duration-300 text-sm w-fit">
            <Image src="/images/icons/instagram.svg" alt="Instagram" width={20} height={20} />
            @coffeetime_husiatyn
          </a>
        </div>

        {/* Navigation */}
        <div>
          <h4 className="text-[#ffa500] font-bold text-base mb-4 uppercase tracking-widest text-sm">
            Навігація
          </h4>
          <ul className="space-y-2 text-sm">
            {[
              ["/menu",      "Меню"],
              ["/giftcards", "Сертифікати"],
              ["/gallery",   "Галерея"],
              ["/reviews",   "Відгуки"],
              ["/contact",   "Контакти"],
            ].map(([href, label]) => (
              <li key={href}>
                <Link href={href}
                  className="text-white/70 hover:text-[#ffa500] transition-colors duration-200">
                  {label}
                </Link>
              </li>
            ))}
          </ul>
        </div>

        {/* Contacts */}
        <div>
          <h4 className="text-[#ffa500] font-bold text-base mb-4 uppercase tracking-widest text-sm">
            Контакти
          </h4>
          <ul className="space-y-3 text-sm text-white/70">
            <li>
              <a href="tel:+380989357337"
                className="hover:text-[#ffa500] transition-colors duration-200">
                📞 +38 (098) 935-73-37
              </a>
            </li>
            <li>
              <a href="https://www.google.com/maps/place/Coffee+Time/@49.0703593,26.2004433,17z"
                target="_blank" rel="noopener noreferrer"
                className="hover:text-[#ffa500] transition-colors duration-200">
                📍 Переглянути на мапі
              </a>
            </li>
            <li className="pt-1">
              <p className="text-white/50 text-xs">Пн–Пт: 8:00–20:00</p>
              <p className="text-white/50 text-xs">Сб: 10:00–20:00</p>
              <p className="text-white/50 text-xs">Нд: 12:00–20:00</p>
            </li>
          </ul>
        </div>
      </div>

      {/* Bottom bar */}
      <div className="border-t border-white/15 py-4 px-5 text-center text-xs text-white/40">
        © {year} Coffee Time. Всі права захищені.
      </div>
    </footer>
  );
}
