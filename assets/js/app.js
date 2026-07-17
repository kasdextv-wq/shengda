/* =====================================================================
   SHENGDA — Shared application logic
   app.js  (request list, drawer, modals, mobile menu, reveals, counters)
   ===================================================================== */
(function () {
  'use strict';

  /* ---------------- Request list (B2B спецификация) ---------------- */
  const STORE_KEY = 'shengda_request_list';
  let requestList = loadList();

  function loadList() {
    try { return JSON.parse(localStorage.getItem(STORE_KEY)) || []; }
    catch (e) { return []; }
  }
  function saveList() { localStorage.setItem(STORE_KEY, JSON.stringify(requestList)); }

  function updateBadges() {
    const count = requestList.reduce((s, i) => s + i.qty, 0);
    document.querySelectorAll('[data-req-count]').forEach(el => {
      el.textContent = count;
      el.classList.toggle('show', count > 0);
    });
  }

  function renderDrawerItems() {
    const list = document.getElementById('drawer-items');
    const empty = document.getElementById('drawer-empty');
    const foot = document.getElementById('drawer-foot');
    if (!list) return;

    if (!requestList.length) {
      empty.style.display = '';
      foot.style.display = 'none';
      list.innerHTML = '';
      return;
    }
    empty.style.display = 'none';
    foot.style.display = '';
    list.innerHTML = requestList.map(i => `
      <div class="req-item">
        <img src="${i.img}" class="req-item__img" alt="${escapeHtml(i.name)}">
        <div class="req-item__det">
          <div class="req-item__title">${escapeHtml(i.name)}</div>
          <div class="req-item__art">Арт. ${escapeHtml(i.art)}</div>
          <div class="qty">
            <button type="button" onclick="Shengda.changeQty('${i.id}',-1)" aria-label="Меньше">−</button>
            <span>${i.qty}</span>
            <button type="button" onclick="Shengda.changeQty('${i.id}',1)" aria-label="Больше">+</button>
          </div>
        </div>
        <button type="button" class="req-remove" onclick="Shengda.remove('${i.id}')">Удалить</button>
      </div>`).join('');
  }

  function add(id, name, art, img) {
    const ex = requestList.find(i => i.id === id);
    if (ex) ex.qty += 1; else requestList.push({ id, name, art, img, qty: 1 });
    saveList(); updateBadges(); renderDrawerItems();
    pulseButton(id);
  }
  function remove(id) {
    requestList = requestList.filter(i => i.id !== id);
    saveList(); updateBadges(); renderDrawerItems();
  }
  function changeQty(id, d) {
    const it = requestList.find(i => i.id === id);
    if (!it) return;
    it.qty += d;
    if (it.qty <= 0) return remove(id);
    saveList(); updateBadges(); renderDrawerItems();
  }

  function pulseButton(id) {
    document.querySelectorAll(`[data-add="${id}"]`).forEach(btn => {
      const orig = btn.innerHTML;
      btn.innerHTML = '✓ Добавлено';
      btn.disabled = true;
      setTimeout(() => { btn.innerHTML = orig; btn.disabled = false; }, 1400);
    });
  }

  /* ---------------- Drawer ---------------- */
  function toggleDrawer(force) {
    const drawer = document.getElementById('request-drawer');
    const overlay = document.getElementById('drawer-overlay');
    if (!drawer) return;
    const open = force !== undefined ? force : !drawer.classList.contains('open');
    drawer.classList.toggle('open', open);
    overlay.classList.toggle('open', open);
    document.body.style.overflow = open ? 'hidden' : '';
    if (open) renderDrawerItems();
  }

  /* ---------------- Mobile menu ---------------- */
  function toggleMenu(force) {
    const menu = document.getElementById('mobile-menu');
    const toggle = document.querySelector('.nav-toggle');
    if (!menu) return;
    const open = force !== undefined ? force : !menu.classList.contains('open');
    menu.classList.toggle('open', open);
    toggle && toggle.classList.toggle('active', open);
    document.body.style.overflow = open ? 'hidden' : '';
  }

  /* ---------------- Quick request modal ---------------- */
  function openModal(subject, title, subtitle) {
    const modal = document.getElementById('quick-modal');
    if (!modal) return;
    const t = document.getElementById('modal-title');
    const s = document.getElementById('modal-subtitle');
    const subj = document.getElementById('modal-subject');
    if (title) t.textContent = title;
    if (subtitle) s.textContent = subtitle;
    if (subj) subj.value = subject || 'Запрос';
    openLayer(modal);
    resetModalForm(modal);
  }
  function closeModal() {
    const modal = document.getElementById('quick-modal');
    if (modal) closeLayer(modal);
  }

  /* ---------------- Popup (policy / agreement) ---------------- */
  function openPopup(type) {
    const m = document.getElementById('popup-' + type);
    if (m) openLayer(m);
  }
  function closePopup(type) {
    const m = document.getElementById('popup-' + type);
    if (m) closeLayer(m);
  }

  function openLayer(layer) {
    layer.classList.add('open');
    document.body.style.overflow = 'hidden';
  }
  function closeLayer(layer) {
    layer.classList.remove('open');
    if (!anyLayerOpen()) document.body.style.overflow = '';
  }
  function anyLayerOpen() {
    return !!document.querySelector('.modal.open, .mobile-menu.open, .drawer.open');
  }

  /* ---------------- Forms ---------------- */
  function resetModalForm(scope) {
    const ok = scope.querySelector('.form-success');
    const form = scope.querySelector('form');
    if (ok) ok.classList.remove('show');
    if (form) form.style.display = '';
  }
  async function handleSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const btn = form.querySelector('[type=submit]');
    const origText = btn ? btn.textContent : '';
    if (btn) { btn.disabled = true; btn.textContent = 'Отправка…'; }

    // Собираем ВСЕ поля формы
    const payload = { source: leadSource(form) };
    const fd = new FormData(form);
    for (const [key, val] of fd.entries()) {
      if (String(val).trim()) payload[key] = String(val).trim();
    }

    const ok = await sendLead(payload);   // ждём результат

    if (btn) { btn.disabled = false; btn.textContent = origText; }

    // Показываем сообщение об успехе (в любом случае — даже если сервер недоступен,
    // чтобы клиент не видел «зависшую» форму)
    const success = findSuccess(form);
    if (success) {
      form.style.display = 'none';
      success.classList.add('show');
    }
    form.reset();
    const modal = form.closest('#quick-modal');
    if (modal) setTimeout(() => closeLayer(modal), 2800);
  }

  async function submitDrawer(e) {
    e.preventDefault();
    const form = e.target;
    const items = requestList.map(i => `${i.name} (${i.art}) ×${i.qty}`).join('\n');
    const payload = { source: 'Каталог / заявка на расчёт', items: items };
    const fd = new FormData(form);
    for (const [key, val] of fd.entries()) {
      if (val && String(val).trim()) payload[key] = String(val).trim();
    }
    sendLead(payload);
    const success = findSuccess(form);
    if (success) {
      form.style.display = 'none';
      const itemsEl = success.querySelector('[data-items]');
      if (itemsEl) itemsEl.textContent = items || '—';
      success.classList.add('show');
    }
    requestList = [];
    saveList(); updateBadges();
    setTimeout(() => { toggleDrawer(false); resetModalForm(document.getElementById('request-drawer')); }, 3200);
  }

  function findSuccess(form) {
    // Ищем .form-success среди родительских элементов
    let node = form.parentElement;
    for (let i = 0; i < 8 && node; i++) {
      const ok = node.querySelector('.form-success');
      if (ok) return ok;
      node = node.parentElement;
    }
    return null;
  }

  /* ---------------- API: отправка заявки на сервер ---------------- */
  const LEAD_URL = 'api/lead.php';
  async function sendLead(payload) {
    try {
      const fd = new FormData();
      for (const k in payload) if (payload[k] != null && payload[k] !== '') fd.append(k, payload[k]);
      const res = await fetch(LEAD_URL, { method: 'POST', body: fd });
      if (!res.ok) return false;
      const data = await res.json().catch(() => ({}));
      if (!data || !data.ok) console.warn('lead.php:', data);
      return !!(data && data.ok);
    } catch (e) { console.warn('sendLead error:', e); return false; }
  }
  // Определяем, с какой формы пришла заявка (по странице + контексту)
  function leadSource(form) {
    const page = (location.pathname.split('/').pop() || 'index.html').replace('.html', '');
    const subj = form.querySelector('#modal-subject');
    return subj && subj.value ? page + ' / ' + subj.value : page;
  }

  /* ---------------- Helpers ---------------- */
  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
  }

  /* ---------------- Scroll reveal ---------------- */
  function initReveal() {
    const els = document.querySelectorAll('[data-reveal]');
    if (!('IntersectionObserver' in window)) { els.forEach(e => e.classList.add('in')); return; }
    const io = new IntersectionObserver((entries) => {
      entries.forEach(en => {
        if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });
    els.forEach(e => io.observe(e));
  }

  /* ---------------- Animated counters ---------------- */
  function initCounters() {
    const nums = document.querySelectorAll('[data-count]');
    if (!nums.length) return;
    const animate = (el) => {
      const target = parseFloat(el.dataset.count);
      const dur = 1600;
      const start = performance.now();
      const suffix = el.dataset.suffix || '';
      const step = (now) => {
        const p = Math.min((now - start) / dur, 1);
        const eased = 1 - Math.pow(1 - p, 3);
        const val = Math.round(target * eased);
        el.textContent = val + suffix;
        if (p < 1) requestAnimationFrame(step);
      };
      requestAnimationFrame(step);
    };
    const io = new IntersectionObserver((entries) => {
      entries.forEach(en => { if (en.isIntersecting) { animate(en.target); io.unobserve(en.target); } });
    }, { threshold: 0.5 });
    nums.forEach(n => io.observe(n));
  }

  /* ---------------- Header scroll state + active nav ---------------- */
  function initHeader() {
    const header = document.querySelector('.site-header');
    const onScroll = () => header && header.classList.toggle('scrolled', window.scrollY > 40);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });

    const path = location.pathname.split('/').pop() || 'index.html';
    const map = { 'index.html':'nav-home','about.html':'nav-about','catalog.html':'nav-catalog','custom.html':'nav-custom','delivery.html':'nav-delivery','contacts.html':'nav-contacts','product.html':'nav-catalog' };
    const id = map[path];
    if (id) {
      const link = document.getElementById(id);
      link && link.classList.add('active');
      const mlink = document.getElementById('m-' + id);
      mlink && mlink.classList.add('active');
    }
  }

  /* ---------------- Global keyboard / overlay close ---------------- */
  function initListeners() {
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        document.querySelectorAll('.modal.open').forEach(closeLayer);
        if (document.getElementById('mobile-menu')?.classList.contains('open')) toggleMenu(false);
        if (document.getElementById('request-drawer')?.classList.contains('open')) toggleDrawer(false);
      }
    });
    // close layers when clicking the dimmer
    document.querySelectorAll('[data-close-self]').forEach(el => {
      el.addEventListener('click', (e) => { if (e.target === el) closeLayer(el); });
    });
    // generic data-close
    document.querySelectorAll('[data-close]').forEach(btn => {
      btn.addEventListener('click', () => {
        const t = document.getElementById(btn.dataset.close) || btn.closest('.modal');
        if (t) closeLayer(t);
      });
    });
  }

  /* ---------------- Smooth image fade-in (no pop-in on reload) ---------------- */
  function initImgFade() {
    const imgs = document.querySelectorAll('img');
    imgs.forEach(img => {
      if (img.closest('.brand') || img.closest('.fab')) return;        // logos & icons: show instantly
      img.classList.add('img-fade');
      const reveal = () => img.classList.add('loaded');
      if (img.complete && img.naturalWidth > 0) reveal();
      else { img.addEventListener('load', reveal); img.addEventListener('error', reveal); }
    });
  }

  /* ---------------- Public API ---------------- */
  window.Shengda = {
    add, remove, changeQty,
    toggleDrawer, toggleMenu,
    openModal, closeModal, openPopup, closePopup,
    handleSubmit, submitDrawer,
    fadeImages: initImgFade
  };

  /* ---------------- Boot ---------------- */
  document.addEventListener('DOMContentLoaded', () => {
    initHeader();
    initListeners();
    initReveal();
    initCounters();
    initImgFade();
    updateBadges();
    renderDrawerItems();
    // fire hero entrance
    const hero = document.querySelector('.hero');
    if (hero) requestAnimationFrame(() => hero.classList.add('is-loaded'));
    // footer year
    document.querySelectorAll('[data-year]').forEach(el => el.textContent = new Date().getFullYear());
  });
})();
