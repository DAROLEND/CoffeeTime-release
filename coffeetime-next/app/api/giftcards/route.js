import { NextResponse } from 'next/server';
import { query } from '@/lib/db';

export async function GET() {
  try {
    const giftcards = await query('SELECT * FROM giftcards');
    return NextResponse.json({ giftcards });
  } catch (error) {
    console.error('[GET /api/giftcards]', error);
    return NextResponse.json({ error: 'Failed to fetch gift cards' }, { status: 500 });
  }
}
