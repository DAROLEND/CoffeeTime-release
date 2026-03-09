import { NextResponse } from 'next/server';
import { queryOne } from '@/lib/db';
import { getAuthUser } from '@/lib/auth';

export async function GET() {
  try {
    const authUser = await getAuthUser();

    if (!authUser) {
      return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
    }

    const user = await queryOne(
      'SELECT client_id, login, email, client_name, client_surname, client_PhoneNumber, created_at FROM users WHERE client_id = ?',
      [authUser.id]
    );

    if (!user) {
      return NextResponse.json({ error: 'User not found' }, { status: 404 });
    }

    const countRow = await queryOne(
      'SELECT COUNT(*) AS cnt FROM orders WHERE user_id = ?',
      [authUser.id]
    );

    const ordersCount = countRow?.cnt ?? 0;

    return NextResponse.json({
      user: {
        ...user,
        ordersCount: Number(ordersCount),
      },
    });
  } catch (error) {
    console.error('[GET /api/profile/me]', error);
    return NextResponse.json({ error: 'Internal server error' }, { status: 500 });
  }
}
