<?php
/* =====================================================
   Coffee Time — Configuration
   All secrets come from .env — never hardcode them here.
===================================================== */

require_once __DIR__ . '/env.php';

/* ── LiqPay Keys ── */
define('LIQPAY_PUBLIC_KEY',  getenv('LIQPAY_PUBLIC_KEY')  ?: '');
define('LIQPAY_PRIVATE_KEY', getenv('LIQPAY_PRIVATE_KEY') ?: '');

/* ── Sandbox mode: 1 = test, 0 = production ── */
define('LIQPAY_SANDBOX', (int)(getenv('LIQPAY_SANDBOX') !== false ? getenv('LIQPAY_SANDBOX') : 1));

/* ── Site base URL (no trailing slash) ── */
define('SITE_URL', rtrim(getenv('APP_URL') ?: 'http://localhost/CoffeeTime-release', '/'));

/*
 ── LiqPay Test Cards (Sandbox) ──────────────────────
  Visa:    4242 4242 4242 4242
  MC:      5375 4141 4141 4141
  Expiry:  any future date  (e.g. 12/28)
  CVV:     any 3 digits      (e.g. 123)
  OTP:     1234
 ────────────────────────────────────────────────────
 ── Required DB migration ────────────────────────────
  ALTER TABLE orders
    MODIFY user_id INT NULL,
    ADD COLUMN IF NOT EXISTS payment_status
      ENUM('pending','paid','failed','cash') DEFAULT 'pending',
    ADD COLUMN IF NOT EXISTS paid_at     DATETIME      NULL,
    ADD COLUMN IF NOT EXISTS liqpay_order_id VARCHAR(100) NULL;
 ────────────────────────────────────────────────────
*/
