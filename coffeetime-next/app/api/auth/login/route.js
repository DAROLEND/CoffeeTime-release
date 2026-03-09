import { NextResponse } from 'next/server';
import bcrypt from 'bcryptjs';
import { queryOne } from '@/lib/db';
import { signToken, setAuthCookie } from '@/lib/auth';

export async function POST(request) {
  try {
    const body = await request.json();
    const { emailOrLogin, password } = body;

    if (!emailOrLogin || !password) {
      return NextResponse.json(
        { error: 'Email/login and password are required' },
        { status: 400 }
      );
    }

    const user = await queryOne(
      'SELECT * FROM users WHERE email = ? OR login = ?',
      [emailOrLogin, emailOrLogin]
    );

    if (!user) {
      return NextResponse.json(
        { error: 'Invalid credentials' },
        { status: 401 }
      );
    }

    const passwordMatch = await bcrypt.compare(password, user.password);
    if (!passwordMatch) {
      return NextResponse.json(
        { error: 'Invalid credentials' },
        { status: 401 }
      );
    }

    const token = await signToken({
      id: user.client_id,
      email: user.email,
      login: user.login,
      name: user.client_name,
      surname: user.client_surname,
      phone: user.client_PhoneNumber,
    });

    await setAuthCookie(token);

    return NextResponse.json({
      user: {
        id: user.client_id,
        email: user.email,
        login: user.login,
        name: user.client_name,
        surname: user.client_surname,
        phone: user.client_PhoneNumber,
      },
    });
  } catch (error) {
    console.error('Login error:', error);
    return NextResponse.json(
      { error: 'Internal server error' },
      { status: 500 }
    );
  }
}
