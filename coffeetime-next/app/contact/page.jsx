export const metadata = {
  title: "Контакти — Coffee Time",
  description: "Адреса, телефон та години роботи кав'ярні Coffee Time",
};

export default function ContactPage() {
  return (
    <div className="max-w-site mx-auto px-4 py-10">
      <h1 className="text-3xl font-bold text-center text-dark mb-2">
        Контакти
      </h1>
      <p className="text-center text-gray-500 text-sm mb-10">
        Будемо раді бачити вас у Coffee Time
      </p>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-10 items-start">

        {/* ── Left column: info ── */}
        <div className="space-y-8">

          {/* Phone */}
          <div className="bg-white rounded-2xl shadow-md p-6 border border-gray-100">
            <div className="flex items-center gap-3 mb-3">
              <span className="text-2xl">📞</span>
              <h2 className="text-xl font-semibold text-dark">Телефон</h2>
            </div>
            <a
              href="tel:+380989357337"
              className="text-accent text-lg font-bold hover:text-footer
                transition-colors duration-300 no-underline"
            >
              +38 (098) 935-73-37
            </a>
          </div>

          {/* Address */}
          <div className="bg-white rounded-2xl shadow-md p-6 border border-gray-100">
            <div className="flex items-center gap-3 mb-3">
              <span className="text-2xl">📍</span>
              <h2 className="text-xl font-semibold text-dark">Адреса</h2>
            </div>
            <p className="text-dark text-base leading-relaxed">
              Гусятин, Тернопільська область, Україна
            </p>
            <a
              href="https://www.google.com/maps/place/Coffee+Time/@49.0703593,26.2004433,17z"
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-2 mt-3 text-sm font-medium
                text-accent hover:text-footer transition-colors duration-300 no-underline"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                className="w-4 h-4 fill-current"
              >
                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z" />
              </svg>
              Відкрити в Google Maps
            </a>
          </div>

          {/* Working hours */}
          <div className="bg-white rounded-2xl shadow-md p-6 border border-gray-100">
            <div className="flex items-center gap-3 mb-4">
              <span className="text-2xl">🕐</span>
              <h2 className="text-xl font-semibold text-dark">Години роботи</h2>
            </div>
            <ul className="space-y-2 text-sm text-dark">
              <li className="flex justify-between border-b border-gray-100 pb-2">
                <span className="font-medium">Понеділок – П'ятниця</span>
                <span className="text-accent font-semibold">8:00 – 20:00</span>
              </li>
              <li className="flex justify-between border-b border-gray-100 pb-2">
                <span className="font-medium">Субота</span>
                <span className="text-accent font-semibold">10:00 – 20:00</span>
              </li>
              <li className="flex justify-between">
                <span className="font-medium">Неділя</span>
                <span className="text-accent font-semibold">12:00 – 20:00</span>
              </li>
            </ul>
          </div>

          {/* Social */}
          <div className="bg-white rounded-2xl shadow-md p-6 border border-gray-100">
            <div className="flex items-center gap-3 mb-3">
              <span className="text-2xl">🌐</span>
              <h2 className="text-xl font-semibold text-dark">Ми в соцмережах</h2>
            </div>
            <a
              href="https://www.instagram.com/coffeetime_husiatyn/"
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-2 text-sm font-medium
                text-dark hover:text-accent transition-colors duration-300 no-underline"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                className="w-5 h-5"
                fill="none"
                stroke="currentColor"
                strokeWidth="1.8"
                strokeLinecap="round"
                strokeLinejoin="round"
              >
                <rect x="2" y="2" width="20" height="20" rx="5" ry="5" />
                <circle cx="12" cy="12" r="4" />
                <circle cx="17.5" cy="6.5" r="0.5" fill="currentColor" stroke="none" />
              </svg>
              @coffeetime_husiatyn
            </a>
          </div>
        </div>

        {/* ── Right column: Map ── */}
        <div className="rounded-2xl overflow-hidden shadow-md border border-gray-100 h-full min-h-[400px]">
          <iframe
            title="Coffee Time на карті"
            src="https://maps.google.com/maps?q=49.0703593,26.2004433&z=16&output=embed"
            width="100%"
            height="100%"
            style={{ minHeight: "400px", border: 0, display: "block" }}
            allowFullScreen
            loading="lazy"
            referrerPolicy="no-referrer-when-downgrade"
          />
        </div>

      </div>
    </div>
  );
}
