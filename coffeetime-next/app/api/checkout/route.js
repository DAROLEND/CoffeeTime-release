import { NextResponse } from 'next/server';
import { query, queryOne } from '@/lib/db';
import { getAuthUser } from '@/lib/auth';

const ALLOWED_TABLES = [
  'coffee_items',
  'fast_food_items',
  'pizza_items',
  'cold_drink_items',
  'dessert_items',
  'giftcards',
];

const VALID_PAYMENTS = ['cash', 'card', 'online'];

// Working hours per day-of-week (0=Sun, 1=Mon, ..., 6=Sat)
const WORKING_HOURS = {
  0: { open: 12, close: 20 }, // Sunday
  1: { open: 8,  close: 20 }, // Monday
  2: { open: 8,  close: 20 }, // Tuesday
  3: { open: 8,  close: 20 }, // Wednesday
  4: { open: 8,  close: 20 }, // Thursday
  5: { open: 8,  close: 20 }, // Friday
  6: { open: 10, close: 20 }, // Saturday
};

function validateWorkingHours(readyTime) {
  const date = new Date(readyTime);
  if (isNaN(date.getTime())) return false;

  const day = date.getDay();
  const hours = date.getHours();
  const minutes = date.getMinutes();
  const timeInMinutes = hours * 60 + minutes;

  const { open, close } = WORKING_HOURS[day];
  return timeInMinutes >= open * 60 && timeInMinutes < close * 60;
}

function getTableForCategory(category) {
  if (!ALLOWED_TABLES.includes(category)) return null;
  return category;
}

export async function POST(request) {
  let userId = 0;
  try {
    const user = await getAuthUser(request);
    if (user) userId = user.id;
  } catch {
    // Not logged in is acceptable; userId remains 0
  }

  try {
    const body = await request.json();
    const { cart, firstName, lastName, phone, readyTime, comment, payment, deliveryAddress } = body;

    // Validate required fields
    if (!firstName || typeof firstName !== 'string' || firstName.trim() === '') {
      return NextResponse.json({ error: 'First name is required' }, { status: 400 });
    }
    if (!lastName || typeof lastName !== 'string' || lastName.trim() === '') {
      return NextResponse.json({ error: 'Last name is required' }, { status: 400 });
    }
    if (!phone || typeof phone !== 'string' || phone.trim() === '') {
      return NextResponse.json({ error: 'Phone is required' }, { status: 400 });
    }
    if (!readyTime) {
      return NextResponse.json({ error: 'Ready time is required' }, { status: 400 });
    }
    if (!payment) {
      return NextResponse.json({ error: 'Payment method is required' }, { status: 400 });
    }

    // Validate working hours
    if (!validateWorkingHours(readyTime)) {
      return NextResponse.json(
        {
          error:
            'Selected ready time is outside working hours. ' +
            'Mon–Fri: 08:00–20:00, Sat: 10:00–20:00, Sun: 12:00–20:00.',
        },
        { status: 400 }
      );
    }

    // Validate cart
    if (!Array.isArray(cart) || cart.length === 0) {
      return NextResponse.json({ error: 'Cart must be a non-empty array' }, { status: 400 });
    }

    for (const item of cart) {
      if (!item.category || !ALLOWED_TABLES.includes(item.category)) {
        return NextResponse.json(
          { error: `Invalid category: ${item.category}` },
          { status: 400 }
        );
      }
      if (!item.id) {
        return NextResponse.json({ error: 'Each cart item must have an id' }, { status: 400 });
      }
      if (!item.quantity || item.quantity < 1) {
        return NextResponse.json(
          { error: 'Each cart item must have a quantity of at least 1' },
          { status: 400 }
        );
      }
    }

    // Fetch prices from DB and calculate total
    const resolvedItems = await Promise.all(
      cart.map(async (item) => {
        const table = getTableForCategory(item.category);
        const row = await queryOne(`SELECT price FROM \`${table}\` WHERE id = ?`, [item.id]);
        if (!row) {
          throw new Error(`Product not found: category=${item.category}, id=${item.id}`);
        }
        return {
          ...item,
          price: parseFloat(row.price),
        };
      })
    );

    const total = resolvedItems.reduce((sum, item) => sum + item.price * item.quantity, 0);

    // Insert order
    const orderResult = await query(
      `INSERT INTO orders
         (user_id, total, delivery_address, phone, status, customer_name, customer_surname, comment, ready_time, payment_method)
       VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?)`,
      [
        userId,
        total.toFixed(2),
        deliveryAddress || null,
        phone.trim(),
        firstName.trim(),
        lastName.trim(),
        comment || null,
        new Date(readyTime),
        payment,
      ]
    );

    const orderId = orderResult.insertId;

    // Insert order items
    await Promise.all(
      resolvedItems.map((item) =>
        query(
          'INSERT INTO order_items (order_id, product_id, quantity, price, category) VALUES (?, ?, ?, ?, ?)',
          [orderId, item.id, item.quantity, item.price, item.category]
        )
      )
    );

    // Update popularity for non-giftcard items
    const popularityUpdates = resolvedItems
      .filter((item) => item.category !== 'giftcards')
      .map((item) =>
        query(
          `UPDATE \`${item.category}\` SET popularity = popularity + ? WHERE id = ?`,
          [item.quantity, item.id]
        )
      );

    await Promise.all(popularityUpdates);

    return NextResponse.json({ success: true, orderId });
  } catch (error) {
    console.error('[POST /api/checkout]', error);
    if (error.message && error.message.startsWith('Product not found')) {
      return NextResponse.json({ error: error.message }, { status: 400 });
    }
    return NextResponse.json({ error: 'Failed to process checkout' }, { status: 500 });
  }
}
