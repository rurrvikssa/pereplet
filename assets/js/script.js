// слайдер
(function () {
  const track = document.getElementById('reviewsTrack');
  const btnPrev = document.getElementById('reviewsPrev');
  const btnNext = document.getElementById('reviewsNext');
  if (!track || !btnPrev || !btnNext) return;

  const viewport = track.parentElement;
  const cards = Array.from(track.children);
  if (!cards.length) return;

  let index = 0;
  let step = 0;
  let maxIndex = 0;

  const getGap = () => 20;

  function recalc() {
    const cardWidth = cards[0].getBoundingClientRect().width;
    step = cardWidth + getGap();
    const visible = Math.max(1, Math.floor((viewport.clientWidth + getGap()) / step));
    maxIndex = Math.max(0, cards.length - visible);
    index = Math.min(index, maxIndex);
    apply();
  }

  function apply() {
    const x = -(index * step);
    track.style.transform = `translateX(${x}px)`;
  }

  btnPrev.addEventListener('click', (() => {
    index = Math.max(0, index - 1);
    apply();
  }));

  btnNext.addEventListener('click', () => {
    index = Math.min(maxIndex, index + 1);
    apply();
  });

  window.addEventListener('resize', recalc);
  recalc();
})();
(function () {
  const track = document.getElementById('selectionsTrack');
  const btnPrev = document.getElementById('selectionsPrev');
  const btnNext = document.getElementById('selectionsNext');
  if (!track || !btnPrev || !btnNext) return;

  const viewport = track.parentElement;
  const cards = Array.from(track.children);
  if (!cards.length) return;

  let index = 0;
  let step = 0;
  let maxIndex = 0;

  const getGap = () => 20;

  function recalc() {
    const cardWidth = cards[0].getBoundingClientRect().width;
    step = cardWidth + getGap();
    const visible = Math.max(1, Math.floor((viewport.clientWidth + getGap()) / step));
    maxIndex = Math.max(0, cards.length - visible);
    index = Math.min(index, maxIndex);
    apply();
  }

  function apply() {
    const x = -(index * step);
    track.style.transform = `translateX(${x}px)`;
  }

  btnPrev.addEventListener('click', (() => {
    index = Math.max(0, index - 1);
    apply();
  }));

  btnNext.addEventListener('click', () => {
    index = Math.min(maxIndex, index + 1);
    apply();
  });

  window.addEventListener('resize', recalc);
  recalc();
})();

// выпадающее меню
(function () {
  const filters = Array.from(document.querySelectorAll('.catalog__filter'));
  if (!filters.length) return;

  function closeAll(except) {
    filters.forEach(f => { if (f !== except) f.classList.remove('is-open'); });
  }

  filters.forEach(filter => {
    const btn = filter.querySelector('.catalog__dropdown');
    if (!btn) return;
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const isOpen = filter.classList.contains('is-open');
      closeAll(filter);
      filter.classList.toggle('is-open', !isOpen);
    });
  });

  document.addEventListener('click', () => closeAll());
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeAll();
  });
})();

(function () {
  const selects = Array.from(document.querySelectorAll('.catalog-filters__select'));
  if (!selects.length) return;
  selects.forEach(select => {
    const btn = select.querySelector('.catalog-filters__select-btn');
    const menu = select.querySelector('.catalog-filters__menu');
    if (!btn || !menu) return;
    const labelEl = btn.querySelector('span');
    const placeholder = labelEl ? labelEl.textContent.trim() : '';
    menu.addEventListener('click', (e) => {
      const item = e.target.closest('.catalog__menu-item');
      if (!item) return;
      e.stopPropagation();
      e.preventDefault();
      item.classList.toggle('is-selected');
      item.setAttribute('aria-pressed', item.classList.contains('is-selected') ? 'true' : 'false');
      const selected = Array.from(menu.querySelectorAll('.catalog__menu-item.is-selected')).map(i => i.textContent.trim());
      if (labelEl) {
        if (!selected.length) labelEl.textContent = placeholder;
        else if (selected.length <= 2) labelEl.textContent = selected.join(', ');
        else labelEl.textContent = selected.length + ' выбрано';
      }
    });
    menu.addEventListener('mousedown', (e) => {
      e.stopPropagation();
    });
  });
})();

// аккордеон
(function () {
  const items = Array.from(document.querySelectorAll('.faq__item'));
  if (!items.length) return;

  function setOpen(item, open) {
    const content = item.querySelector('.faq__content');
    const arrow = item.querySelector('.faq__arrow');
    if (!content || !arrow) return;
    if (open) {
      item.classList.add('faq__item--open');
      content.style.maxHeight = content.scrollHeight + 'px';
      arrow.classList.add('rotated');
    } else {
      item.classList.remove('faq__item--open');
      content.style.maxHeight = '0px';
      arrow.classList.remove('rotated');
    }
  }

  items.forEach((item) => {
    const header = item.querySelector('.faq__header');
    if (!header) return;
    header.addEventListener('click', () => {
      const willOpen = !item.classList.contains('faq__item--open');
      items.forEach(i => setOpen(i, false));
      setOpen(item, willOpen);
    });
  });

  items.forEach((item) => {
    const isOpen = item.classList.contains('faq__item--open');
    setOpen(item, isOpen);
  });

  window.addEventListener('resize', () => {
    items.forEach(item => {
      if (item.classList.contains('faq__item--open')) {
        const content = item.querySelector('.faq__content');
        if (content) content.style.maxHeight = content.scrollHeight + 'px';
      }
    });
  });
})();

// бургер-меню
(function () {
  const burger = document.querySelector('.header__burger');
  const menu = document.getElementById('mobileMenu');
  if (!burger || !menu) return;

  const closeBtn = menu.querySelector('.mobile-menu__close');

  function openMenu() {
    menu.classList.add('is-open');
    document.body.style.overflow = 'hidden';
  }

  function closeMenu() {
    menu.classList.remove('is-open');
    document.body.style.overflow = '';
  }

  burger.addEventListener('click', function () {
    if (menu.classList.contains('is-open')) closeMenu();
    else openMenu();
  });

  if (closeBtn) {
    closeBtn.addEventListener('click', closeMenu);
  }

  menu.addEventListener('click', function (e) {
    if (e.target === menu) {
      closeMenu();
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && menu.classList.contains('is-open')) {
      closeMenu();
    }
  });
})();

// яндекс карта
(function () {
  if (typeof ymaps === 'undefined') return;
  ymaps.ready(function () {
    var mapEl = document.getElementById('map');
    if (!mapEl) return;
    var center = [55.796289, 49.108795];
    var map = new ymaps.Map('map', { center: center, zoom: 13, controls: ['zoomControl'] });
    var placemark = new ymaps.Placemark(center, { balloonContent: 'Казань' }, { preset: 'islands#redIcon' });
    map.geoObjects.add(placemark);
  });
})();


// карточки
(function () {
  const container = document.querySelector('.status-levels .container');
  if (!container) return;
  const levels = Array.from(container.querySelectorAll('.status-level'));
  if (!levels.length) return;

  const COLLAPSED = 132;
  const EXPANDED = 650;

  function layout(activeIndex) {
    const delta = EXPANDED - COLLAPSED;
    levels.forEach((level, i) => {
      const base = i * COLLAPSED;
      const push = i > activeIndex ? delta : 0;
      const top = base + push;
      level.style.top = top + 'px';

      if (i === activeIndex) {
        level.classList.add('is-active');
        level.style.zIndex = 100;
      } else {
        level.classList.remove('is-active');
        level.style.zIndex = String(50 - i);
      }
    });
  }

  function indexOf(el) {
    return levels.indexOf(el);
  }

  const defaultActiveEl = container.querySelector('.status-level--reader') || levels[0];
  let activeIndex = indexOf(defaultActiveEl);
  if (activeIndex < 0) activeIndex = 0;
  layout(activeIndex);

  levels.forEach((level, i) => {
    const header = level.querySelector('.status-level__header');
    if (!header) return;
    header.setAttribute('role', 'button');
    header.setAttribute('tabindex', '0');
    header.addEventListener('click', () => {
      activeIndex = i;
      layout(activeIndex);
    });
    header.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        activeIndex = i;
        layout(activeIndex);
      }
    });
  });
})();

(function () {
  document.addEventListener('click', function (e) {
    const img = e.target.closest('.catalog__card-image');
    const title = e.target.closest('.catalog__card-title');
    const card = e.target.closest('.catalog__card');
    if (!img && !title && !card) return;
    if (e.target.closest('.catalog__card-btn')) return;
    if (e.target.closest('.catalog__card-bookmark')) return;
    if (img || title || (card && !e.defaultPrevented)) {
      window.location.href = './product.php';
    }
  });
})();

// карточки
(function () {
  const container = document.querySelector('.status-levels .container');
  if (!container) return;
  const levels = Array.from(container.querySelectorAll('.status-level'));
  if (!levels.length) return;

  const COLLAPSED = 132;
  const EXPANDED = 650;

  function layout(activeIndex) {
    const delta = EXPANDED - COLLAPSED;
    levels.forEach((level, i) => {
      const base = i * COLLAPSED;
      const push = i > activeIndex ? delta : 0;
      const top = base + push;
      level.style.top = top + 'px';

      if (i === activeIndex) {
        level.classList.add('is-active');
        level.style.zIndex = 100;
      } else {
        level.classList.remove('is-active');
        level.style.zIndex = String(50 - i);
      }
    });
  }

  function indexOf(el) {
    return levels.indexOf(el);
  }

  const defaultActiveEl = container.querySelector('.status-level--reader') || levels[0];
  let activeIndex = indexOf(defaultActiveEl);
  if (activeIndex < 0) activeIndex = 0;
  layout(activeIndex);

  levels.forEach((level, i) => {
    const header = level.querySelector('.status-level__header');
    if (!header) return;
    header.setAttribute('role', 'button');
    header.setAttribute('tabindex', '0');
    header.addEventListener('click', () => {
      activeIndex = i;
      layout(activeIndex);
    });
    header.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        activeIndex = i;
        layout(activeIndex);
      }
    });
  });
})();