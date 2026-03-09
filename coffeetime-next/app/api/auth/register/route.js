import { NextResponse } from 'next/server';
import bcrypt from 'bcryptjs';
import { query, queryOne } from '@/lib/db';

export async function POST(request) {
  try {
    const body = await request.json();
    const { email, login, password, confirm } = body;

    // Validate required fields
    if (!email || !login || !password || !confirm) {
      return NextResponse.json(
        { error: 'All fields are required' },
        { status: 400 }
      );
    }

    // Validate email format
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
      return NextResponse.json(
        { error: 'Invalid email address' },
        { status: 400 }
      );
    }

    // Validate login length
    if (login.length < 3) {
      return NextResponse.json(
        { error: 'Login must be at least 3 characters long' },
        { status: 400 }
      );
    }

    // Validate password length
    if (password.length < 6) {
      return NextResponse.json(
        { error: 'Password must be at least 6 characters long' },
        { status: 400 }
      );
    }

    // Validate passwords match
    if (password !== confirm) {
      return NextResponse.json(
        { error: 'Passwords do not match' },
        { status: 400 }
      );
    }

    // Check email uniqueness
    const existingEmail = await queryOne(
      'SELECT client_id FROM users WHERE email = ?',
      [email]
    );
    if (existingEmail) {
      return NextResponse.json(
        { error: 'Email is already in use' },
        { status: 409 }
      );
    }

    // Check login uniqueness
    const existingLogin = await queryOne(
      'SELECT client_id FROM users WHERE login = ?',
      [login]
    );
    if (existingLogin) {
      return NextResponse.json(
        { error: 'Login is already taken' },
        { status: 409 }
      );
    }

    // Hash password and insert user
    const hashedPassword = await bcrypt.hash(password, 10);

    await query(
      'INSERT INTO users (email, login, password, created_at) VALUES (?, ?, ?, NOW())',
      [email, login, hashedPassword]
    );

    return NextResponse.json({ success: true }, { status: 201 });
  } catch (error) {
    console.error('Register error:', error);
    return NextResponse.json(
      { error: 'Internal server error' },
      { status: 500 }
    );
  }
}
