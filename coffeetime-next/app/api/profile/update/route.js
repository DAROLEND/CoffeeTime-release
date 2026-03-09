import { NextResponse } from 'next/server';
import { query, queryOne } from '@/lib/db';
import { getAuthUser } from '@/lib/auth';

export async function POST(request) {
  try {
    const authUser = await getAuthUser();

    if (!authUser) {
      return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
    }

    const body = await request.json();
    const { firstName, lastName, phone } = body;

    await query(
      'UPDATE users SET client_name = ?, client_surname = ?, client_PhoneNumber = ? WHERE client_id = ?',
      [
        firstName?.trim() ?? null,
        lastName?.trim() ?? null,
        phone?.trim() ?? null,
        authUser.id,
      ]
    );

    const updated = await queryOne(
      'SELECT client_id, login, email, client_name, client_surname, client_PhoneNumber, created_at FROM users WHERE client_id = ?',
      [authUser.id]
    );

    return NextResponse.json({ success: true, user: updated });
  } catch (error) {
    console.error('[POST /api/profile/update]', error);
    return NextResponse.json({ error: 'Internal server error' }, { status: 500 });
  }
}
