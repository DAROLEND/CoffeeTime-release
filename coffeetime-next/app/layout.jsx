import "./globals.css";
import Header from "@/components/Header";
import Footer from "@/components/Footer";
import { CartProvider } from "@/context/CartContext";
import { AuthProvider } from "@/context/AuthContext";

export const metadata = {
  title: "Coffee Time",
  description: "Ваше улюблене кав'ярня — Coffee Time",
};

export default function RootLayout({ children }) {
  return (
    <html lang="uk">
      <body className="flex flex-col min-h-screen bg-light text-dark font-sans">
        <AuthProvider>
          <CartProvider>
            <Header />
            <main className="flex-1">{children}</main>
            <Footer />
          </CartProvider>
        </AuthProvider>
      </body>
    </html>
  );
}
