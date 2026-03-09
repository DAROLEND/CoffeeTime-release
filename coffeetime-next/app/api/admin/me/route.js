import { NextResponse } from 'next/server';
import { jwtVerify } from 'jose';
import { cookies } from 'next/headers';

// FILE 0: GET /api/admin/me
// Returns { admin: payload } if the ct_admin_token cookie holds a valid HS256 JWT.
// Returns 401 otherwise.

const SECRET = new TextEncoder().encode(process.env.JWT_SECRET || 'change-me');

async function getAdminUser() {
  const c = await cookies();
  const token = c.get('ct_admin_token')?.value;
  if (!token) return null;
  try {
    const { payload } = await jwtVerify(token, SECRET);
    return payload;
  } catch {
    return null;
  }
}

export async function GET() {
  try {
    const admin = await getAdminUser();
    if (!admin) {
      return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
    }
    return NextResponse.json({ admin });
  } catch (error) {
    console.error('Admin /me error:', error);
    return NextResponse.json({ error: 'Internal server error' }, { status: 500 });
  }
}
