import { NextResponse } from 'next/server';
import { SignJWT, jwtVerify } from 'jose';
import { cookies } from 'next/headers';
import { unlink } from 'node:fs/promises';
import path from 'node:path';
import { query, queryOne } from '@/lib/db';

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

// ── Constants ─────────────────────────────────────────────────────────────────

const ALLOWED_TABLES = [
  'coffee_items',
  'fast_food_items',
  'pizza_items',
  'cold_drink_items',
  'dessert_items',
  'giftcards',
];

// ── PUT /api/admin/items/[id] ─────────────────────────────────────────────────

export async function PUT(request, { params }) {
  try {
    const admin = await getAdminUser();
    if (!admin) {
      return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
    }

    const { id } = await params;
    const body = await request.json();
    const { category, name, description, price } = body;

    if (!category || !ALLOWED_TABLES.includes(category)) {
      return NextResponse.json(
        { error: 'Invalid or missing category' },
        { status: 400 }
      );
    }

    if (!name || !description || price === undefined) {
      return NextResponse.json(
        { error: 'name, description, and price are required' },
        { status: 400 }
      );
    }

    const parsedPrice = parseFloat(price);
    if (isNaN(parsedPrice) || parsedPrice < 0) {
      return NextResponse.json({ error: 'Invalid price' }, { status: 400 });
    }

    // Giftcards table uses 'title' instead of 'name'
    const nameColumn = category === 'giftcards' ? 'title' : 'name';

    const result = await query(
      `UPDATE \`${category}\` SET \`${nameColumn}\` = ?, description = ?, price = ? WHERE id = ?`,
      [name, description, parsedPrice, id]
    );

    if (result.affectedRows === 0) {
      return NextResponse.json({ error: 'Item not found' }, { status: 404 });
    }

    return NextResponse.json({ success: true });
  } catch (error) {
    console.error('Admin PUT item error:', error);
    return NextResponse.json({ error: 'Internal server error' }, { status: 500 });
  }
}

// ── DELETE /api/admin/items/[id]?category=xxx ─────────────────────────────────

export async function DELETE(request, { params }) {
  try {
    const admin = await getAdminUser();
    if (!admin) {
      return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
    }

    const { id } = await params;
    const { searchParams } = new URL(request.url);
    const category = searchParams.get('category');

    if (!category || !ALLOWED_TABLES.includes(category)) {
      return NextResponse.json(
        { error: 'Invalid or missing category' },
        { status: 400 }
      );
    }

    // Fetch item to get its image path before deleting
    const item = await queryOne(
      `SELECT * FROM \`${category}\` WHERE id = ?`,
      [id]
    );

    if (!item) {
      return NextResponse.json({ error: 'Item not found' }, { status: 404 });
    }

    // Delete from DB
    await query(`DELETE FROM \`${category}\` WHERE id = ?`, [id]);

    // Delete image file from public/ if it exists
    if (item.image) {
      const imagePath = path.join(process.cwd(), 'public', item.image);
      try {
        await unlink(imagePath);
      } catch {
        // File may not exist — not a fatal error
      }
    }

    return NextResponse.json({ success: true });
  } catch (error) {
    console.error('Admin DELETE item error:', error);
    return NextResponse.json({ error: 'Internal server error' }, { status: 500 });
  }
}
