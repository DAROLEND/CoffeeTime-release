"use client";

import { useState, useEffect, useCallback } from "react";

const SORT_OPTIONS = [
  { value: "best",    label: "Спочатку кращі" },
  { value: "worst",   label: "Спочатку гірші" },
  { value: "newest",  label: "Спочатку новіші" },
  { value: "oldest",  label: "Спочатку давніші" },
];

function StarDisplay({ rating, max = 5 }) {
  return (
    <span className="text-lg leading-none" aria-label={`${rating} з ${max}`}>
      {Array.from({ length: max }, (_, i) => (
        <span key={i} className={i < rating ? "text-accent" : "text-gray-300"}>
          ★
        </span>
      ))}
    </span>
  );
}

function StarSelector({ value, onChange }) {
  const [hovered, setHovered] = useState(0);
  return (
    <div className="flex gap-1" role="radiogroup" aria-label="Оцінка">
      {[1, 2, 3, 4, 5].map((star) => (
        <button
          key={star}
          type="button"
          role="radio"
          aria-checked={value === star}
          aria-label={`${star} зірок`}
          onMouseEnter={() => setHovered(star)}
          onMouseLeave={() => setHovered(0)}
          onClick={() => onChange(star)}
          className={`text-3xl leading-none transition-transform duration-100
            hover:scale-125 focus:outline-none
            ${(hovered || value) >= star ? "text-accent" : "text-gray-300"}`}
        >
          ★
        </button>
      ))}
    </div>
  );
}

export default function ReviewsPage() {
  const [sort, setSort] = useState("newest");
  const [reviews, setReviews] = useState([]);
  const [loading, setLoading] = useState(true);
  const [fetchError, setFetchError] = useState("");

  // Form state
  const [formName, setFormName] = useState("");
  const [formText, setFormText] = useState("");
  const [formRating, setFormRating] = useState(5);
  const [submitting, setSubmitting] = useState(false);
  const [submitSuccess, setSubmitSuccess] = useState("");
  const [submitError, setSubmitError] = useState("");

  const loadReviews = useCallback(() => {
    setLoading(true);
    setFetchError("");
    fetch(`/api/reviews?sort=${sort}`)
      .then((r) => (r.ok ? r.json() : Promise.reject(r)))
      .then((data) => setReviews(data.reviews ?? data ?? []))
      .catch(() => setFetchError("Не вдалося завантажити відгуки."))
      .finally(() => setLoading(false));
  }, [sort]);

  useEffect(() => {
    loadReviews();
  }, [loadReviews]);

  async function handleSubmit(e) {
    e.preventDefault();
    setSubmitSuccess("");
    setSubmitError("");

    if (!formName.trim() || !formText.trim()) {
      setSubmitError("Заповніть ім'я та текст відгуку.");
      return;
    }

    setSubmitting(true);
    try {
      const res = await fetch("/api/reviews", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          name: formName.trim(),
          text: formText.trim(),
          rating: formRating,
        }),
      });
      if (res.ok) {
        setSubmitSuccess("Дякуємо за ваш відгук!");
        setFormName("");
        setFormText("");
        setFormRating(5);
        loadReviews();
      } else {
        const data = await res.json().catch(() => ({}));
        setSubmitError(data.message || "Помилка. Спробуйте ще раз.");
      }
    } catch {
      setSubmitError("Помилка з'єднання.");
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="max-w-site mx-auto px-4 py-10">
      <h1 className="text-3xl font-bold text-center text-dark mb-2">
        Відгуки
      </h1>
      <p className="text-center text-gray-500 text-sm mb-8">
        Що кажуть наші гості про Coffee Time
      </p>

      {/* ── Sort + Google Maps link ── */}
      <div className="flex flex-wrap items-center justify-between gap-4 mb-8">
        <div className="flex items-center gap-3">
          <label htmlFor="sort-select" className="text-sm font-medium text-dark">
            Сортування:
          </label>
          <select
            id="sort-select"
            value={sort}
            onChange={(e) => setSort(e.target.value)}
            className="border border-gray-300 rounded-lg px-3 py-2 text-sm
              focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent
              bg-white transition-all duration-200"
          >
            {SORT_OPTIONS.map((opt) => (
              <option key={opt.value} value={opt.value}>
                {opt.label}
              </option>
            ))}
          </select>
        </div>

        <a
          href="https://www.google.com/maps/place/?q=place_id:ChIJpSEn64eEMUcR5GJBQPZZnpg"
          target="_blank"
          rel="noopener noreferrer"
          className="inline-flex items-center gap-2 px-4 py-2 rounded-xl
            bg-white border border-gray-200 shadow-sm text-sm font-medium text-dark
            hover:border-accent hover:text-accent transition-all duration-300"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            className="w-4 h-4 fill-current"
          >
            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z" />
          </svg>
          Залишити відгук на Google Maps
        </a>
      </div>

      {/* ── Reviews list ── */}
      {loading && (
        <div className="flex justify-center items-center h-40 text-gray-400">
          Завантаження відгуків…
        </div>
      )}

      {fetchError && (
        <div className="text-center text-red-500 py-8">{fetchError}</div>
      )}

      {!loading && !fetchError && reviews.length === 0 && (
        <div className="text-center text-gray-400 py-8">
          Відгуків ще немає. Будьте першими!
        </div>
      )}

      {!loading && !fetchError && reviews.length > 0 && (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
          {reviews.map((review, idx) => (
            <div
              key={review.id ?? idx}
              className="bg-white rounded-2xl shadow-md p-5 flex flex-col gap-3
                hover:shadow-lg hover:-translate-y-0.5 transition-all duration-300
                border border-gray-100"
            >
              <div className="flex items-start justify-between gap-2">
                <div>
                  <p className="font-semibold text-dark text-base leading-tight">
                    {review.name}
                  </p>
                  {review.date && (
                    <p className="text-xs text-gray-400 mt-0.5">
                      {new Date(review.date).toLocaleDateString("uk-UA", {
                        day: "2-digit",
                        month: "long",
                        year: "numeric",
                      })}
                    </p>
                  )}
                </div>
                <StarDisplay rating={review.rating ?? 5} />
              </div>

              <p className="text-sm text-gray-600 leading-relaxed flex-1">
                {review.text}
              </p>
            </div>
          ))}
        </div>
      )}

      {/* ── Leave a review form ── */}
      <div className="border-t border-gray-200 pt-10">
        <h2 className="text-2xl font-semibold text-dark mb-6">
          Залишити відгук
        </h2>

        <form
          onSubmit={handleSubmit}
          className="bg-white rounded-2xl shadow-md p-6 max-w-lg"
        >
          <div className="space-y-5">
            <div>
              <label className="block text-sm font-medium text-dark mb-1">
                Ваше ім'я
              </label>
              <input
                type="text"
                value={formName}
                onChange={(e) => setFormName(e.target.value)}
                required
                placeholder="Ім'я"
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                  focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent
                  transition-all duration-200"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-dark mb-1">
                Оцінка
              </label>
              <StarSelector value={formRating} onChange={setFormRating} />
            </div>

            <div>
              <label className="block text-sm font-medium text-dark mb-1">
                Відгук
              </label>
              <textarea
                value={formText}
                onChange={(e) => setFormText(e.target.value)}
                required
                rows={4}
                placeholder="Розкажіть про ваш досвід…"
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                  focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent
                  transition-all duration-200 resize-none"
              />
            </div>
          </div>

          {submitSuccess && (
            <div className="mt-4 p-3 rounded-lg bg-green-50 border border-green-400 text-green-700 text-sm">
              {submitSuccess}
            </div>
          )}
          {submitError && (
            <div className="mt-4 p-3 rounded-lg bg-red-50 border border-red-400 text-red-700 text-sm">
              {submitError}
            </div>
          )}

          <button
            type="submit"
            disabled={submitting}
            className={`mt-5 w-full py-3 rounded-xl font-semibold text-sm
              transition-all duration-300 focus:outline-none
              ${
                submitting
                  ? "bg-accent/70 text-black cursor-wait"
                  : "bg-accent text-black hover:bg-footer hover:text-white"
              }`}
          >
            {submitting ? "Надсилаємо…" : "Надіслати відгук"}
          </button>
        </form>
      </div>
    </div>
  );
}
