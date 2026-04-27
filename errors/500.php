<!DOCTYPE html>
<html lang="uk">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Помилка сервера — Coffee Time</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: 'Lato', 'Helvetica Neue', Arial, sans-serif;
      background: #FAF7F2;
      color: #2c2c2a;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 24px;
    }
    .wrap {
      text-align: center;
      max-width: 480px;
    }
    .icon {
      font-size: 72px;
      line-height: 1;
      margin-bottom: 24px;
      opacity: .6;
    }
    h1 {
      font-family: Georgia, serif;
      font-size: 2rem;
      margin: 0 0 12px;
      color: #2c1810;
    }
    p {
      font-size: 1rem;
      color: #666;
      line-height: 1.6;
      margin: 0 0 32px;
    }
    a {
      display: inline-block;
      padding: 13px 36px;
      background: #FFC107;
      color: #5a2d00;
      font-weight: 700;
      border-radius: 50px;
      text-decoration: none;
      transition: background .2s, transform .2s;
    }
    a:hover {
      background: #e6ac00;
      transform: translateY(-1px);
    }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="icon">☕</div>
    <h1>Щось пішло не так</h1>
    <p>
      На сервері сталася помилка. Ми вже знаємо про проблему і виправляємо її.<br>
      Спробуйте повернутися через кілька хвилин.
    </p>
    <a href="/CoffeeTime-release/pages/index.php">На головну</a>
  </div>
</body>
</html>
