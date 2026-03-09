import { NextResponse } from 'next/server';
import { query } from '@/lib/db';

const ALLOWED_TABLES = [
  'coffee_items',
  'fast_food_items',
  'pizza_items',
  'cold_drink_items',
  'dessert_items',
  'giftcards',
];

const MENU_TABLES = ALLOWED_TABLES.filter((t) => t !== 'giftcards');

export async function GET(request) {
  const { searchParams } = new URL(request.url);
  const category = searchParams.get('category');

  if (!category || !MENU_TABLES.includes(category)) {
    return NextResponse.json(
      { error: 'Invalid or missing category. Must be one of: ' + MENU_TABLES.join(', ') },
      { status: 400 }
    );
  }

  try {
    const items = await query(`SELECT * FROM \`${category}\``);
    return NextResponse.json({ items });
  } catch (error) {
    console.error('[GET /api/menu]', error);
    return NextResponse.json({ error: 'Failed to fetch menu items' }, { status: 500 });
  }
}
