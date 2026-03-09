import { NextResponse } from 'next/server';
import { query } from '@/lib/db';

const SLIDER_GROUPS = [
  {
    title: 'Краща їжа',
    tables: ['fast_food_items', 'pizza_items', 'dessert_items'],
  },
  {
    title: 'Найпопулярніші напої',
    tables: ['cold_drink_items', 'coffee_items'],
  },
  {
    title: 'Наші десерти',
    tables: ['dessert_items'],
  },
];

export async function GET() {
  try {
    const sliders = await Promise.all(
      SLIDER_GROUPS.map(async ({ title, tables }) => {
        const tableResults = await Promise.all(
          tables.map((table) =>
            query(
              `SELECT name, image, popularity FROM \`${table}\` ORDER BY popularity DESC LIMIT 5`
            )
          )
        );

        const merged = tableResults
          .flat()
          .sort((a, b) => b.popularity - a.popularity)
          .slice(0, 5);

        return { title, items: merged };
      })
    );

    return NextResponse.json({ sliders });
  } catch (error) {
    console.error('[GET /api/popular]', error);
    return NextResponse.json({ error: 'Failed to fetch popular items' }, { status: 500 });
  }
}
