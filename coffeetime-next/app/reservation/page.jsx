"use client";

import { useState, useEffect } from "react";
import Image from "next/image";
import Link from "next/link";
import { useAuth } from "@/context/AuthContext";

const LOCATIONS = [
  {
    id: "indoor",
    label: "В залі",
    image: "/images/main/indoor.jpg",
    tableCount: 10,
  },
  {
    id: "terrace",
    label: "Тераса",
    image: "/images/main/terasa.jpg",
    tableCount: 15,
  },
];

export default function ReservationPage() {
  const { user } = useAuth();

  // Step 1: location selection
  const [location, setLocation] = useState(null);

  // Step 2: tables data from API
  const [tables, setTables] = useState([]);
  const [loadingTables, setLoadingTables] = useState(false);
  const [selectedTables, setSelectedTables] = useState([]);

  // Form fields
  const [datetime, setDatetime] = useState("");
  const [name, setName] = useState("");
  const [phone, setPhone] = useState("");

  // Submission state
  const [submitting, setSubmitting] = useState(false);
  const [successMsg, setSuccessMsg] = useState("");
  const [errorMsg, setErrorMsg] = useState("");

  // Pre-fill form when user is available
  useEffect(() => {
    if (user) {
      setName(
        [user.name, user.surname].filter(Boolean).join(" ") || user.email || ""
      );
      setPhone(user.phone || "");
    }
  }, [user]);

  // Fetch tables whenever location changes
  useEffect(() => {
    if (!location) return;
    setLoadingTables(true);
    setSelectedTables([]);
    setSuccessMsg("");
    setErrorMsg("");

    fetch(`/api/reservation?location=${location}`)
      .then((r) => (r.ok ? r.json() : Promise.reject(r)))
      .then((data) => setTables(data.tables || []))
      .catch(() => setTables([]))
      .finally(() => setLoadingTables(false));
  }, [location]);

  function toggleTable(tableId) {
    setSelectedTables((prev) =>
      prev.includes(tableId)
        ? prev.filter((id) => id !== tableId)
        : [...prev, tableId]
    );
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setSuccessMsg("");
    setErrorMsg("");

    if (selectedTables.length === 0) {
      setErrorMsg("Оберіть хоча б один столик.");
      return;
    }
    if (!datetime) {
      setErrorMsg("Вкажіть дату та час.");
      return;
    }

    setSubmitting(true);
    try {
      const res = await fetch("/api/reservation", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ location, tables: selectedTables, datetime, name, phone }),
      });
      if (res.ok) {
        setSuccessMsg("Бронювання успішно підтверджено! Чекаємо на вас.");
        setSelectedTables([]);
        setDatetime("");
        // Re-fetch tables to reflect new bookings
        const updated = await fetch(`/api/reservation?location=${location}`);
        if (updated.ok) {
          const data = await updated.json();
          setTables(data.tables || []);
        }
      } else {
        const data = await res.json().catch(() => ({}));
        setErrorMsg(data.message || "Сталася помилка. Спробуйте ще раз.");
      }
    } catch {
      setErrorMsg("Помилка з'єднання. Спробуйте ще раз.");
    } finally {
      setSubmitting(false);
    }
  }

  const currentLocation = LOCATIONS.find((l) => l.id === location);

  return (
    <div className="max-w-site mx-auto px-4 py-10">
      <h1 className="text-3xl font-bold text-center text-dark mb-8">
        Бронювання столика
      </h1>

      {/* ── STEP 1: Location cards ── */}
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-10">
        {LOCATIONS.map((loc) => (
          <button
            key={loc.id}
            onClick={() => setLocation(loc.id)}
            className={`relative overflow-hidden rounded-2xl shadow-lg cursor-pointer
              border-4 transition-all duration-300 focus:outline-none group
              ${
                location === loc.id
                  ? "border-accent scale-[1.02]"
                  : "border-transparent hover:border-accent hover:scale-[1.02]"
              }`}
          >
            <div className="relative h-56 w-full">
              <Image
                src={loc.image}
                alt={loc.label}
                fill
                className="object-cover transition-transform duration-500 group-hover:scale-105"
                sizes="(max-width: 640px) 100vw, 50vw"
              />
              <div className="absolute inset-0 bg-black/30 group-hover:bg-black/20 transition-colors duration-300" />
            </div>
            <div className="absolute bottom-0 left-0 right-0 p-4 bg-gradient-to-t from-black/70 to-transparent">
              <span className="text-white text-xl font-bold">{loc.label}</span>
              {location === loc.id && (
                <span className="ml-3 text-accent font-semibold text-sm">
                  ✓ Обрано
                </span>
              )}
            </div>
          </button>
        ))}
      </div>

      {/* ── STEP 2: Tables + Form ── */}
      {location && (
        <div>
          <h2 className="text-2xl font-semibold text-dark mb-2">
            {currentLocation?.label} — оберіть столики
          </h2>
          <p className="text-sm text-gray-500 mb-6">
            Зелений — вільний, червоний — заброньований (наведіть для дати).
            Можна обрати декілька столиків.
          </p>

          {/* Not logged in warning */}
          {!user && (
            <div className="mb-6 p-4 rounded-xl bg-orange-50 border border-accent text-dark text-sm">
              Для бронювання потрібно{" "}
              <Link
                href="/login"
                className="text-accent font-semibold underline hover:text-footer transition-colors"
              >
                увійти в акаунт
              </Link>
              .
            </div>
          )}

          {/* Tables grid */}
          {loadingTables ? (
            <div className="flex justify-center items-center h-32 text-gray-400">
              Завантаження столиків…
            </div>
          ) : (
            <div className="grid grid-cols-5 sm:grid-cols-5 md:grid-cols-5 gap-3 mb-8">
              {Array.from(
                { length: currentLocation?.tableCount ?? 0 },
                (_, i) => {
                  const tableId = i + 1;
                  const tableData = tables.find((t) => t.id === tableId);
                  const isBooked = tableData?.booked ?? false;
                  const bookedDate = tableData?.datetime ?? "";
                  const isSelected = selectedTables.includes(tableId);

                  return (
                    <div key={tableId} className="relative group/table">
                      <button
                        onClick={() => !isBooked && toggleTable(tableId)}
                        disabled={isBooked}
                        aria-label={`Столик ${tableId}${isBooked ? " (заброньований)" : ""}`}
                        className={`
                          w-full aspect-square rounded-xl flex flex-col items-center
                          justify-center text-white font-bold text-sm shadow-md
                          transition-all duration-200 focus:outline-none
                          ${
                            isBooked
                              ? "bg-red-500 cursor-not-allowed opacity-80"
                              : isSelected
                              ? "bg-accent text-black ring-4 ring-accent/50 scale-105"
                              : "bg-green-500 hover:bg-green-400 hover:scale-105 cursor-pointer"
                          }
                        `}
                      >
                        <span className="text-lg leading-none">🪑</span>
                        <span className="mt-1 text-xs">№{tableId}</span>
                      </button>

                      {/* Tooltip for booked tables */}
                      {isBooked && bookedDate && (
                        <div
                          className="absolute bottom-full left-1/2 -translate-x-1/2 mb-2
                            hidden group-hover/table:block z-10
                            bg-black text-white text-xs rounded-lg px-3 py-1.5
                            whitespace-nowrap shadow-lg pointer-events-none"
                        >
                          {new Date(bookedDate).toLocaleString("uk-UA", {
                            day: "2-digit",
                            month: "2-digit",
                            year: "numeric",
                            hour: "2-digit",
                            minute: "2-digit",
                          })}
                          <span
                            className="absolute top-full left-1/2 -translate-x-1/2
                              border-4 border-transparent border-t-black"
                          />
                        </div>
                      )}
                    </div>
                  );
                }
              )}
            </div>
          )}

          {/* Legend */}
          <div className="flex gap-6 mb-8 text-sm text-gray-600">
            <span className="flex items-center gap-2">
              <span className="w-4 h-4 rounded bg-green-500 inline-block" />
              Вільний
            </span>
            <span className="flex items-center gap-2">
              <span className="w-4 h-4 rounded bg-red-500 inline-block" />
              Заброньований
            </span>
            <span className="flex items-center gap-2">
              <span className="w-4 h-4 rounded bg-accent inline-block" />
              Обраний
            </span>
          </div>

          {/* Booking form */}
          <form
            onSubmit={handleSubmit}
            className="bg-white rounded-2xl shadow-md p-6 max-w-lg"
          >
            <h3 className="text-lg font-semibold text-dark mb-4">
              Деталі бронювання
            </h3>

            {selectedTables.length > 0 && (
              <p className="text-sm text-gray-500 mb-4">
                Обрані столики:{" "}
                <span className="font-medium text-dark">
                  {selectedTables.map((id) => `№${id}`).join(", ")}
                </span>
              </p>
            )}

            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-dark mb-1">
                  Дата та час
                </label>
                <input
                  type="datetime-local"
                  value={datetime}
                  onChange={(e) => setDatetime(e.target.value)}
                  required
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                    focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent
                    transition-all duration-200"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-dark mb-1">
                  Ім'я
                </label>
                <input
                  type="text"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  required
                  placeholder="Ваше ім'я"
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                    focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent
                    transition-all duration-200"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-dark mb-1">
                  Телефон
                </label>
                <input
                  type="tel"
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  required
                  placeholder="+38 (0XX) XXX-XX-XX"
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                    focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent
                    transition-all duration-200"
                />
              </div>
            </div>

            {/* Messages */}
            {successMsg && (
              <div className="mt-4 p-3 rounded-lg bg-green-50 border border-green-400 text-green-700 text-sm">
                {successMsg}
              </div>
            )}
            {errorMsg && (
              <div className="mt-4 p-3 rounded-lg bg-red-50 border border-red-400 text-red-700 text-sm">
                {errorMsg}
              </div>
            )}

            <button
              type="submit"
              disabled={submitting || !user}
              className={`mt-5 w-full py-3 rounded-xl font-semibold text-sm
                transition-all duration-300 focus:outline-none
                ${
                  !user
                    ? "bg-gray-200 text-gray-400 cursor-not-allowed"
                    : submitting
                    ? "bg-accent/70 text-black cursor-wait"
                    : "bg-accent text-black hover:bg-footer hover:text-white"
                }`}
            >
              {submitting ? "Бронюємо…" : "Підтвердити бронювання"}
            </button>
          </form>
        </div>
      )}
    </div>
  );
}
