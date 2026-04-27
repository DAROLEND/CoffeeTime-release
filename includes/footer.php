<?php
// footer.php
?>
<button class="scroll-top-btn" aria-label="Вгору">↑</button>

<footer class="site-footer">
  <div class="footer__inner">

    <!-- Колонка 1: лого + підпис + соцмережі -->
    <div class="footer__col footer__brand">
      <a href="../pages/index.php" class="footer-logo-link">
        <img src="../static/images/main/logo-cup.svg" alt="Coffee Time" class="footer-logo-img">
      </a>
      <p class="footer-tagline">Затишне кафе у серці міста</p>
      <div class="footer-socials">
        <a href="https://www.instagram.com/coffeetime_husiatyn/" target="_blank" rel="noopener" aria-label="Instagram" class="social-btn">
          <img src="../static/images/icons/instagram.svg" alt="Instagram">
        </a>
        <a href="#" target="_blank" rel="noopener" aria-label="Facebook" class="social-btn">
          <img src="../static/images/icons/facebook.svg" alt="Facebook">
        </a>
      </div>
    </div>

    <!-- Колонка 2: Години роботи -->
    <div class="footer__col">
      <h4>Години роботи</h4>
      <ul>
        <li><span class="footer-day">Пн–Пт</span> 8:00 – 20:00</li>
        <li><span class="footer-day">Субота</span> 10:00 – 20:00</li>
        <li><span class="footer-day">Неділя</span> 12:00 – 20:00</li>
      </ul>
    </div>

    <!-- Колонка 3: Контакти -->
    <div class="footer__col">
      <h4>Контакти</h4>
      <ul>
        <li><a href="tel:+380989357337">+38 (098) 935-73-37</a></li>
        <li>
          <a href="https://www.google.com/maps/place/Coffee+Time/@49.0703593,26.2004433,17z/data=!4m6!3m5!1s0x47318487eb2721a5:0x989e59f6404162e4!8m2!3d49.0703593!4d26.2004433!16s%2Fg%2F11d_8c3725?entry=ttu&g_ep=EgoyMDI1MDUwNy4wIKXMDSoASAFQAw%3D%3D"
             target="_blank" rel="noopener">
            Ми на Google Maps
          </a>
        </li>
      </ul>
    </div>

    <!-- Колонка 4: Навігація -->
    <div class="footer__col">
      <h4>Навігація</h4>
      <ul>
        <li><a href="../pages/index.php">Головна</a></li>
        <li><a href="../pages/menu.php">Меню</a></li>
        <li><a href="../pages/gallery.php">Галерея</a></li>
        <li><a href="../pages/reviews.php">Відгуки</a></li>
      </ul>
    </div>

  </div>

  <div class="footer__bottom">
    <span>© <?= date('Y') ?> Coffee Time. All Rights Reserved.</span>
    <span>Розроблено з ❤️ у Гусятині</span>
  </div>
</footer>
