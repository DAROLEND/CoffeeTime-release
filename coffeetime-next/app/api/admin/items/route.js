import { NextResponse } from 'next/server';
import { SignJWT, jwtVerify } from 'jose';
import { cookies } from 'next/headers';
import { randomUUID } from 'node:crypto';
import { writeFile, mkdir } from 'node:fs/promises';
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

const CATEGORY_TO_SUBFOLDER = {
  coffee_items:     'coffee',
  cold_drink_items: 'cold_drinks',
  dessert_items:    'desserts',
  fast_food_items:  'fast_food',
  pizza_items:      'pizza',
  giftcards:        'giftcards',
};

// ── GET /api/admin/items?category=xxx ────────────────────────────────────────

export async function GET(request) {
  try {
    const { searchParams } = new URL(request.url);
    const category = searchParams.get('category');

    if (!category || !ALLOWED_TABLES.includes(category)) {
      return NextResponse.json(
        { error: 'Invalid or missing category' },
        { status: 400 }
      );
    }

    const items = await query(`SELECT * FROM \`${category}\` ORDER BY id DESC`);
    return NextResponse.json({ items });
  } catch (error) {
    console.error('Admin GET items error:', error);
    return NextResponse.json({ error: 'Internal server error' }, { status: 500 });
  }
}

// ── POST /api/admin/items (multipart/form-data) ───────────────────────────────

export async function POST(request) {
  try {
    const admin = await getAdminUser();
    if (!admin) {
      return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
    }

    const formData = await request.formData();
    const category    = formData.get('category');
    const name        = formData.get('name');
    const description = formData.get('description');
    const price       = formData.get('price');
    const imageFile   = formData.get('image');

    if (!category || !ALLOWED_TABLES.includes(category)) {
      return NextResponse.json(
        { error: 'Invalid or missing category' },
        { status: 400 }
      );
    }

    if (!name || !description || !price || !imageFile) {
      return NextResponse.json(
        { error: 'name, description, price, and image are required' },
        { status: 400 }
      );
    }

    const parsedPrice = parseFloat(price);
    if (isNaN(parsedPrice) || parsedPrice < 0) {
      return NextResponse.json({ error: 'Invalid price' }, { status: 400 });
    }

    // Save image file
    const subfolder = CATEGORY_TO_SUBFOLDER[category];
    const ext = path.extname(imageFile.name) || '.jpg';
    const filename = `${randomUUID()}${ext}`;
    const relativePath = `images/menu_items/${subfolder}/${filename}`;

    const publicDir = path.join(process.cwd(), 'public');
    const targetDir = path.join(publicDir, 'images', 'menu_items', subfolder);
    await mkdir(targetDir, { recursive: true });

    const buffer = Buffer.from(await imageFile.arrayBuffer());
    await writeFile(path.join(targetDir, filename), buffer);

    // Giftcards table uses 'title' instead of 'name'
    const nameColumn = category === 'giftcards' ? 'title' : 'name';

    const result = await query(
      `INSERT INTO \`${category}\` (\`${nameColumn}\`, description, price, image) VALUES (?, ?, ?, ?)`,
      [name, description, parsedPrice, relativePath]
    );

    const insertId = result.insertId;
    const item = await queryOne(
      `SELECT * FROM \`${category}\` WHERE id = ?`,
      [insertId]
    );

    return NextResponse.json({ success: true, item }, { status: 201 });
  } catch (error) {
    console.error('Admin POST items error:', error);
    return NextResponse.json({ error: 'Internal server error' }, { status: 500 });
  }
}
