'use client';

import { usePathname, useRouter } from 'next/navigation';
import Link from 'next/link';

// FILE 1: Admin shell layout.
// Renders a dark-brown sidebar with nav links for all admin pages.
// On /admin/login the sidebar is hidden and children are rendered full-screen.

function AdminSidebar() {
  const router = useRouter();

  async function handleLogout(e) {
    e.preventDefault();
    await fetch('/api/admin/logout', { method: 'POST' });
    router.replace('/admin/login');
  }

  return (
    <aside className="flex flex-col w-56 min-h-screen text-white" style={{ backgroundColor: '#8b4513' }}>
      {/* Branding */}
      <div className="px-6 py-5 border-b border-white/20">
        <span className="text-xl font-bold tracking-wide">CoffeeTime</span>
        <p className="text-xs text-white/60 mt-0.5">Адмін-панель</p>
      </div>

      {/* Navigation */}
      <nav className="flex-1 px-3 py-4 space-y-1">
        <SideLink href="/admin" label="Головна" />
        <SideLink href="/admin/items?category=coffee_items" label="Товари" />
        <SideLink href="/admin/orders" label="Замовлення" />
      </nav>

      {/* Logout */}
      <div className="px-3 py-4 border-t border-white/20">
        <form onSubmit={handleLogout}>
          <button
            type="submit"
            className="w-full text-left px-4 py-2 rounded-lg text-sm font-medium text-white/80 hover:bg-white/10 hover:text-white transition-colors"
          >
            Вийти
          </button>
        </form>
      </div>
    </aside>
  );
}

function SideLink({ href, label }) {
  const pathname = usePathname();
  const isActive =
    href === '/admin'
      ? pathname === '/admin'
      : pathname.startsWith(href.split('?')[0]);

  return (
    <Link
      href={href}
      className={`flex items-center px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
        isActive
          ? 'bg-white/20 text-white'
          : 'text-white/70 hover:bg-white/10 hover:text-white'
      }`}
    >
      {label}
    </Link>
  );
}

export default function AdminLayout({ children }) {
  const pathname = usePathname();
  const isLoginPage = pathname === '/admin/login';

  if (isLoginPage) {
    return <>{children}</>;
  }

  return (
    <div className="flex min-h-screen bg-gray-100">
      <AdminSidebar />
      <main className="flex-1 overflow-auto bg-white">
        {children}
      </main>
    </div>
  );
}
