import { NextResponse } from 'next/server';
import { query } from '@/lib/db';

const SORT_MAP = {
  best: 'rating DESC',
  worst: 'rating ASC',
  newest: 'created_at DESC',
  oldest: 'created_at ASC',
};

export async function GET(request) {
  const { searchParams } = new URL(request.url);
  const sort = searchParams.get('sort') || 'newest';

  const orderBy = SORT_MAP[sort];
  if (!orderBy) {
    return NextResponse.json(
      { error: 'Invalid sort value. Must be one of: best, worst, newest, oldest' },
      { status: 400 }
    );
  }

  try {
    const reviews = await query(`SELECT * FROM site_reviews ORDER BY ${orderBy}`);
    return NextResponse.json({ reviews });
  } catch (error) {
    console.error('[GET /api/reviews]', error);
    return NextResponse.json({ error: 'Failed to fetch reviews' }, { status: 500 });
  }
}

export async function POST(request) {
  try {
    const body = await request.json();
    const { name, text, rating } = body;

    if (!name || !text || rating === undefined || rating === null) {
      return NextResponse.json(
        { error: 'All fields (name, text, rating) are required' },
        { status: 400 }
      );
    }

    if (typeof name !== 'string' || name.trim() === '') {
      return NextResponse.json({ error: 'Name must be a non-empty string' }, { status: 400 });
    }

    if (typeof text !== 'string' || text.trim() === '') {
      return NextResponse.json({ error: 'Text must be a non-empty string' }, { status: 400 });
    }

    const ratingNum = Number(rating);
    if (!Number.isInteger(ratingNum) || ratingNum < 1 || ratingNum > 5) {
      return NextResponse.json({ error: 'Rating must be an integer between 1 and 5' }, { status: 400 });
    }

    await query(
      'INSERT INTO site_reviews (name, text, rating) VALUES (?, ?, ?)',
      [name.trim(), text.trim(), ratingNum]
    );

    return NextResponse.json({ success: true });
  } catch (error) {
    console.error('[POST /api/reviews]', error);
    return NextResponse.json({ error: 'Failed to submit review' }, { status: 500 });
  }
}
