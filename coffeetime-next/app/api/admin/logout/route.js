import { NextResponse } from 'next/server';
import { cookies } from 'next/headers';

// ── POST /api/admin/logout ────────────────────────────────────────────────────

export async function POST() {
  try {
    const cookieStore = await cookies();
    cookieStore.delete('ct_admin_token');

    return NextResponse.json({ success: true });
  } catch (error) {
    console.error('Admin logout error:', error);
    return NextResponse.json({ error: 'Internal server error' }, { status: 500 });
  }
}
