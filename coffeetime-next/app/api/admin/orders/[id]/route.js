import { NextResponse } from 'next/server';
import { SignJWT, jwtVerify } from 'jose';
import { cookies } from 'next/headers';
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

// ── Category tables and their name columns ────────────────────────────────────

const CATEGORY_NAME_COLUMN = {
  coffee_items:     'name',
  fast_food_items:  'name',
  pizza_items:      'name',
  cold_drink_items: 'name',
  dessert_items:    'name',
  giftcards:        'title',
};

/**
 * For each order_item row, look up the product name from its category table.
 * Falls back to null if the category is unknown or the product was deleted.
 */
async function enrichOrderItems(orderItems) {
  return Promise.all(
    orderItems.map(async (item) => {
      const nameColumn = CATEGORY_NAME_COLUMN[item.category];
      if (!nameColumn) return { ...item, product_name: null };

      const product = await queryOne(
        `SELECT \`${nameColumn}\` AS product_name FROM \`${item.category}\` WHERE id = ?`,
        [item.product_id]
      );

      return { ...item, product_name: product?.product_name ?? null };
    })
  );
}

// ── GET /api/admin/orders/[id] ────────────────────────────────────────────────

export async function GET(request, { params }) {
  try {
    const admin = await getAdminUser();
    if (!admin) {
      return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
    }

    const { id } = await params;

    const order = await queryOne(
      'SELECT * FROM orders WHERE order_id = ?',
      [id]
    );

    if (!order) {
      return NextResponse.json({ error: 'Order not found' }, { status: 404 });
    }

    const rawItems = await query(
      'SELECT * FROM order_items WHERE order_id = ?',
      [id]
    );

    const items = await enrichOrderItems(rawItems);

    return NextResponse.json({ order, items });
  } catch (error) {
    console.error('Admin GET order error:', error);
    return NextResponse.json({ error: 'Internal server error' }, { status: 500 });
  }
}

// ── PATCH /api/admin/orders/[id] ──────────────────────────────────────────────

export async function PATCH(request, { params }) {
  try {
    const admin = await getAdminUser();
    if (!admin) {
      return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
    }

    const { id } = await params;
    const body = await request.json();
    const { status } = body;

    if (!status || !['approved', 'declined'].includes(status)) {
      return NextResponse.json(
        { error: "status must be 'approved' or 'declined'" },
        { status: 400 }
      );
    }

    const result = await query(
      'UPDATE orders SET status = ? WHERE order_id = ?',
      [status, id]
    );

    if (result.affectedRows === 0) {
      return NextResponse.json({ error: 'Order not found' }, { status: 404 });
    }

    return NextResponse.json({ success: true });
  } catch (error) {
    console.error('Admin PATCH order error:', error);
    return NextResponse.json({ error: 'Internal server error' }, { status: 500 });
  }
}
