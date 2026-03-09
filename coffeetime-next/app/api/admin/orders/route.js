import { NextResponse } from 'next/server';
import { SignJWT, jwtVerify } from 'jose';
import { cookies } from 'next/headers';
import { query } from '@/lib/db';

// ── Admin auth helpers ────────────────────────────────────────────────────────

const ADMIN_COOKIE = 'ct_admin_token';
const MAX_AGE = 60 * 60 * 8; // 8 hours in seconds

function getSecret() {
  return new TextEncoder().encode(process.env.JWT_SECRET || 'change-me-in-production');
}

async function signAdminToken(payload) {
  return new SignJWT(payload)
    .setProtectedHeader({ alg: 'HS256' })
    .setIssuedAt()
    .setExpirationTime(`${MAX_AGE}s`)
    .sign(getSecret());
}

async function verifyAdminToken(token) {
  try {
    const { payload } = await jwtVerify(token, getSecret());
    return payload;
  } catch {
    return null;
  }
}

async function getAdminUser() {
  const cookieStore = await cookies();
  const token = cookieStore.get(ADMIN_COOKIE)?.value;
  if (!token) return null;
  return verifyAdminToken(token);
}

// ── GET /api/admin/orders ─────────────────────────────────────────────────────

export async function GET() {
  try {
    const admin = await getAdminUser();
    if (!admin) {
      return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
    }

    const orders = await query(
      `SELECT * FROM orders WHERE status = 'pending' ORDER BY created_at DESC`
    );

    return NextResponse.json({ orders });
  } catch (error) {
    console.error('Admin GET orders error:', error);
    return NextResponse.json({ error: 'Internal server error' }, { status: 500 });
  }
}
