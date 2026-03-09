import { NextResponse } from "next/server";
import { jwtVerify } from "jose";

const SECRET = new TextEncoder().encode(
  process.env.JWT_SECRET || "change-me-in-production"
);

// Routes that require user auth
const USER_PROTECTED = ["/profile", "/checkout", "/change-password"];
// Routes that require admin auth
const ADMIN_PROTECTED = ["/admin"];
const ADMIN_PUBLIC = ["/admin/login"];

async function verifyJwt(token, secret) {
  try {
    const { payload } = await jwtVerify(token, secret);
    return payload;
  } catch {
    return null;
  }
}

export async function middleware(request) {
  const { pathname } = request.nextUrl;

  // ── Admin protection ────────────────────────────────────────────────────
  const isAdminRoute = pathname.startsWith("/admin");
  const isAdminLoginPage = pathname === "/admin/login";

  if (isAdminRoute && !isAdminLoginPage) {
    const adminToken = request.cookies.get("ct_admin_token")?.value;
    const adminUser = adminToken ? await verifyJwt(adminToken, SECRET) : null;

    if (!adminUser) {
      return NextResponse.redirect(new URL("/admin/login", request.url));
    }
  }

  // ── User-protected routes ────────────────────────────────────────────────
  const isUserProtected = USER_PROTECTED.some((p) => pathname.startsWith(p));
  if (isUserProtected) {
    const userToken = request.cookies.get("ct_token")?.value;
    const user = userToken ? await verifyJwt(userToken, SECRET) : null;

    if (!user) {
      const loginUrl = new URL("/login", request.url);
      loginUrl.searchParams.set("from", pathname);
      return NextResponse.redirect(loginUrl);
    }
  }

  // ── Redirect logged-in users away from login/register ───────────────────
  if (pathname === "/login" || pathname === "/register") {
    const userToken = request.cookies.get("ct_token")?.value;
    const user = userToken ? await verifyJwt(userToken, SECRET) : null;
    if (user) {
      return NextResponse.redirect(new URL("/", request.url));
    }
  }

  return NextResponse.next();
}

export const config = {
  matcher: [
    "/admin/:path*",
    "/profile/:path*",
    "/checkout/:path*",
    "/change-password/:path*",
    "/login",
    "/register",
  ],
};
