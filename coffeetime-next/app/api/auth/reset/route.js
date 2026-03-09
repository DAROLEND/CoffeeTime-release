import { NextResponse } from 'next/server';
import bcrypt from 'bcryptjs';
import { query, queryOne } from '@/lib/db';

export async function POST(request) {
  try {
    const body = await request.json();
    const { token, password, confirm } = body;

    if (!token || !password || !confirm) {
      return NextResponse.json(
        { error: 'Token, password, and confirmation are required' },
        { status: 400 }
      );
    }

    if (password.length < 6) {
      return NextResponse.json(
        { error: 'Password must be at least 6 characters long' },
        { status: 400 }
      );
    }

    if (password !== confirm) {
      return NextResponse.json(
        { error: 'Passwords do not match' },
        { status: 400 }
      );
    }

    const resetRecord = await queryOne(
      'SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()',
      [token]
    );

    if (!resetRecord) {
      return NextResponse.json(
        { error: 'Invalid or expired reset token' },
        { status: 400 }
      );
    }

    const hashedPassword = await bcrypt.hash(password, 10);

    await query(
      'UPDATE users SET password = ? WHERE email = ?',
      [hashedPassword, resetRecord.email]
    );

    await query(
      'DELETE FROM password_resets WHERE token = ?',
      [token]
    );

    return NextResponse.json({ success: true });
  } catch (error) {
    console.error('Reset password error:', error);
    return NextResponse.json(
      { error: 'Internal server error' },
      { status: 500 }
    );
  }
}
