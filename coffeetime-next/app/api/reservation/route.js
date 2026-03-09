import { NextResponse } from 'next/server';
import { query } from '@/lib/db';
import { getAuthUser } from '@/lib/auth';

const VALID_LOCATIONS = ['indoor', 'terrace'];

function formatDate(datetimeValue) {
  const d = new Date(datetimeValue);
  const dd = String(d.getDate()).padStart(2, '0');
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const yyyy = d.getFullYear();
  const HH = String(d.getHours()).padStart(2, '0');
  const MM = String(d.getMinutes()).padStart(2, '0');
  return `${dd}.${mm}.${yyyy} ${HH}:${MM}`;
}

export async function GET(request) {
  const { searchParams } = new URL(request.url);
  const location = searchParams.get('location');

  if (!location || !VALID_LOCATIONS.includes(location)) {
    return NextResponse.json(
      { error: 'Invalid or missing location. Must be one of: indoor, terrace' },
      { status: 400 }
    );
  }

  try {
    const rows = await query(
      'SELECT table_number, reservation_datetime FROM reservations WHERE location = ?',
      [location]
    );

    const booked = {};
    for (const row of rows) {
      booked[row.table_number] = formatDate(row.reservation_datetime);
    }

    return NextResponse.json({ booked });
  } catch (error) {
    console.error('[GET /api/reservation]', error);
    return NextResponse.json({ error: 'Failed to fetch reservations' }, { status: 500 });
  }
}

export async function POST(request) {
  const user = await getAuthUser(request);
  if (!user) {
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
  }

  try {
    const body = await request.json();
    const { location, tables, datetime, name, phone } = body;

    if (!location || !VALID_LOCATIONS.includes(location)) {
      return NextResponse.json(
        { error: 'Invalid or missing location. Must be one of: indoor, terrace' },
        { status: 400 }
      );
    }

    if (!Array.isArray(tables) || tables.length === 0) {
      return NextResponse.json(
        { error: 'Tables must be a non-empty array of table numbers' },
        { status: 400 }
      );
    }

    if (!datetime) {
      return NextResponse.json({ error: 'Datetime is required' }, { status: 400 });
    }

    const reservationDate = new Date(datetime);
    if (isNaN(reservationDate.getTime())) {
      return NextResponse.json({ error: 'Invalid datetime format' }, { status: 400 });
    }

    if (!name || typeof name !== 'string' || name.trim() === '') {
      return NextResponse.json({ error: 'Name is required' }, { status: 400 });
    }

    if (!phone || typeof phone !== 'string' || phone.trim() === '') {
      return NextResponse.json({ error: 'Phone is required' }, { status: 400 });
    }

    const insertPromises = tables.map((tableNumber) =>
      query(
        `INSERT INTO reservations (user_id, table_number, location, reservation_datetime, client_name, client_phone)
         VALUES (?, ?, ?, ?, ?, ?)`,
        [user.id, tableNumber, location, reservationDate, name.trim(), phone.trim()]
      )
    );

    await Promise.all(insertPromises);

    return NextResponse.json({
      success: true,
      message: `Successfully reserved ${tables.length} table(s) at ${location} for ${name.trim()}`,
    });
  } catch (error) {
    console.error('[POST /api/reservation]', error);
    return NextResponse.json({ error: 'Failed to create reservation' }, { status: 500 });
  }
}
