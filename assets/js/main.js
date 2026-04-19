(function() {
  var params = new URLSearchParams(window.location.search);
  var famiglia = params.get('famiglia');
  if (famiglia) {
    var modal = document.getElementById('welcomeModal');
    document.getElementById('welcomeTitle').textContent = famiglia;
    document.getElementById('rsvpTitle').textContent = famiglia;
    modal.style.display = 'flex';
    document.getElementById('welcomeOk').addEventListener('click', function() {
      modal.style.opacity = '0';
      modal.style.transition = 'opacity 0.3s';
      setTimeout(function() { modal.style.display = 'none'; }, 300);
    });
  }
})();

// ─── NAV ───
const navBar = document.getElementById('navBar');
const navToggle = document.getElementById('navToggle');
const navOverlay = document.getElementById('navOverlay');

function closeMenu() {
  navToggle.classList.remove('active');
  navOverlay.classList.remove('open');
  document.body.style.overflow = '';
}

function smoothScrollTo(targetId) {
  const target = document.querySelector(targetId);
  if (!target) return;
  const offset = targetId === '#home' ? 0 : target.getBoundingClientRect().top + window.pageYOffset - 55;
  window.scrollTo({ top: offset, behavior: 'smooth' });
}

navToggle.addEventListener('click', () => {
  const isOpen = navOverlay.classList.contains('open');
  if (isOpen) { closeMenu(); }
  else {
    navToggle.classList.add('active');
    navOverlay.classList.add('open');
    document.body.style.overflow = 'hidden';
  }
});

// All nav links (overlay + desktop + brand)
document.querySelectorAll('.nav-overlay a, .nav-links a, .nav-brand').forEach(link => {
  link.addEventListener('click', (e) => {
    e.preventDefault();
    const targetId = link.getAttribute('href');
    closeMenu();
    setTimeout(() => smoothScrollTo(targetId), 80);
  });
});

// Hero scroll arrow
document.querySelector('.hero-scroll').addEventListener('click', (e) => {
  e.preventDefault();
  smoothScrollTo('#countdown');
});

// Nav background on scroll
window.addEventListener('scroll', () => {
  navBar.classList.toggle('scrolled', window.scrollY > 60);
}, { passive: true });

// ─── PETALS ───
(function() {
  const container = document.getElementById('petals');
  const colors = ['rgba(170,130,190,0.8)', 'rgba(155,127,167,0.75)', 'rgba(190,140,140,0.7)', 'rgba(185,155,100,0.65)'];
  const count = window.innerWidth < 768 ? 12 : 20;
  for (let i = 0; i < count; i++) {
    const p = document.createElement('div');
    p.classList.add('petal');
    p.style.left = Math.random() * 100 + '%';
    p.style.animationDuration = (10 + Math.random() * 14) + 's';
    p.style.animationDelay = (Math.random() * 18) + 's';
    p.style.background = colors[Math.floor(Math.random() * colors.length)];
    p.style.width = (9 + Math.random() * 12) + 'px';
    p.style.height = (12 + Math.random() * 14) + 'px';
    container.appendChild(p);
  }
})();

// ─── COUNTDOWN ───
(function() {
  const wedding = new Date('2026-09-27T15:00:00+02:00').getTime();
  function update() {
    const diff = Math.max(0, wedding - Date.now());
    document.getElementById('cd-days').textContent = Math.floor(diff / 86400000);
    document.getElementById('cd-hours').textContent = String(Math.floor((diff % 86400000) / 3600000)).padStart(2, '0');
    document.getElementById('cd-mins').textContent = String(Math.floor((diff % 3600000) / 60000)).padStart(2, '0');
    document.getElementById('cd-secs').textContent = String(Math.floor((diff % 60000) / 1000)).padStart(2, '0');
  }
  update();
  setInterval(update, 1000);
})();

// ─── PARALLAX — JS-DRIVEN, MOBILE COMPATIBLE ───
(function() {
  const parallaxEls = document.querySelectorAll('.parallax-bg[data-parallax]');
  let ticking = false;

  function updateParallax() {
    const wh = window.innerHeight;
    parallaxEls.forEach(el => {
      const wrap = el.parentElement;
      const rect = wrap.getBoundingClientRect();

      // Only process when visible
      if (rect.bottom < -100 || rect.top > wh + 100) return;

      const speed = parseFloat(el.dataset.parallax) || 0.3;
      // Calculate how far through the viewport the element is
      // 0 = just entering bottom, 1 = just leaving top
      const progress = (wh - rect.top) / (wh + rect.height);
      // Map to translation range
      const translate = (progress - 0.5) * rect.height * speed;

      el.style.transform = 'translate3d(0,' + translate + 'px, 0)';
    });
    ticking = false;
  }

  window.addEventListener('scroll', () => {
    if (!ticking) {
      requestAnimationFrame(updateParallax);
      ticking = true;
    }
  }, { passive: true });

  // Initial call
  updateParallax();
})();

// ─── SCROLL REVEAL ───
(function() {
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) entry.target.classList.add('visible');
    });
  }, { threshold: 0.12, rootMargin: '0px 0px -30px 0px' });

  document.querySelectorAll('.reveal, .timeline-item, .gallery-item, .detail-card').forEach(el => observer.observe(el));
})();

// ─── STAGGER DELAYS ───
document.querySelectorAll('.gallery-item').forEach((el, i) => { el.style.transitionDelay = (i * 0.08) + 's'; });
document.querySelectorAll('.detail-card').forEach((el, i) => { el.style.transitionDelay = (i * 0.08) + 's'; });
document.querySelectorAll('.timeline-item').forEach((el, i) => { el.style.transitionDelay = (i * 0.1) + 's'; });