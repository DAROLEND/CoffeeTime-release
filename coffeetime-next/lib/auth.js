import { SignJWT, jwtVerify } from "jose";
import { cookies } from "next/headers";

const SECRET = new TextEncoder().encode(
  process.env.JWT_SECRET || "change-me-in-production"
);
const COOKIE_NAME = "ct_token";
const MAX_AGE = 60 * 60 * 24 * 7; // 7 days (seconds)

// ── Token helpers ──────────────────────────────────────────────────────────

/**
 * Sign a JWT with the given payload.
 * @param {{ id: number, email: string, name: string, role?: string }} payload
 * @returns {Promise<string>}
 */
export async function signToken(payload) {
  return new SignJWT(payload)
    .setProtectedHeader({ alg: "HS256" })
    .setIssuedAt()
    .setExpirationTime(`${MAX_AGE}s`)
    .sign(SECRET);
}

/**
 * Verify a JWT string. Returns the payload or null.
 * @param {string} token
 */
export async function verifyToken(token) {
  try {
    const { payload } = await jwtVerify(token, SECRET);
    return payload;
  } catch {
    return null;
  }
}

// ── Cookie helpers (Server Components / Route Handlers) ───────────────────

/**
 * Set the auth cookie in a Route Handler response.
 * Call this inside an API route handler.
 */
export async function setAuthCookie(token) {
  const cookieStore = await cookies();
  cookieStore.set(COOKIE_NAME, token, {
    httpOnly: true,
    secure: process.env.NODE_ENV === "production",
    sameSite: "lax",
    maxAge: MAX_AGE,
    path: "/",
  });
}

/**
 * Read and verify the auth cookie from the current request.
 * Returns the token payload or null.
 */
export async function getAuthUser() {
  const cookieStore = await cookies();
  const token = cookieStore.get(COOKIE_NAME)?.value;
  if (!token) return null;
  return verifyToken(token);
}

/**
 * Delete the auth cookie (logout).
 */
export async function clearAuthCookie() {
  const cookieStore = await cookies();
  cookieStore.delete(COOKIE_NAME);
}
