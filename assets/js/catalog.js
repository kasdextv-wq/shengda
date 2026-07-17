/* =====================================================================
   SHENGDA — Catalogue page logic
   catalog.js  (загрузка из БД + фильтрация + рендер)
   ===================================================================== */
(function () {
  'use strict';

  const grid = () => document.getElementById('products-grid');
  const empty = () => document.getElementById('catalog-empty');
  const countText = () => document.getElementById('catalog-count');

  // Локальный кэш товаров. НЕ затеняем глобальный PRODUCTS из data.js!
  let items = (typeof PRODUCTS !== 'undefined') ? PRODUCTS.slice() : [];

  // Плейсхолдер для товара без фото
  const PLACEHOLDER = 'data:image/svg+xml,' + encodeURIComponent(
    '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400"><rect width="400" height="400" fill="#F6F4EF"/><text x="200" y="220" font-size="80" fill="#D0AE6F" text-anchor="middle" font-family="Georgia,serif">Ш</text></svg>'
  );

  function imgUrl(p) {
    if (p.img && String(p.img).trim()) return p.img;
    return PLACEHOLDER;
  }

  async function loadFromDB() {
    try {
      const res = await fetch('api/products.php', { cache: 'no-store' });
      console.log('[catalog] api/products.php → HTTP', res.status);
      if (!res.ok) { console.warn('[catalog] ответ не OK'); return null; }
      const data = await res.json();
      console.log('[catalog] получено товаров из БД:', Array.isArray(data) ? data.length : 'не массив');
      if (Array.isArray(data) && data.length > 0) return data;
      console.warn('[catalog] БД пуста или нет данных, откат на data.js');
      return null;
    } catch (e) {
      console.warn('[catalog] ошибка загрузки из БД:', e.message);
      return null;
    }
  }

  function getFilters() {
    const cat = document.querySelector('input[name="category"]:checked');
    const search = (document.getElementById('catalog-search')?.value || '').toLowerCase().trim();
    const sub = Array.from(document.querySelectorAll('.subcat-filter:checked')).map(e => e.value);
    const custom = document.getElementById('filter-customizable')?.checked;
    return { cat: cat ? cat.value : 'all', search, sub, custom };
  }

  function filter() {
    const { cat, search, sub, custom } = getFilters();
    const subBox = document.getElementById('sanitary-subcategories');
    if (subBox) subBox.style.display = (cat === 'sanitary') ? '' : 'none';

    const list = items.filter(p => {
      const okSearch = !search || p.name.toLowerCase().includes(search) || (p.art || '').toLowerCase().includes(search);
      const okCat = cat === 'all' || p.category === cat;
      const okSub = !(cat === 'sanitary' && sub.length) || sub.includes(p.subcategory);
      const okCustom = !custom || p.custom;
      return okSearch && okCat && okSub && okCustom;
    });
    render(list);
  }

  function reset() {
    const all = document.querySelector('input[name="category"][value="all"]');
    if (all) all.checked = true;
    const s = document.getElementById('catalog-search'); if (s) s.value = '';
    const fc = document.getElementById('filter-customizable'); if (fc) fc.checked = false;
    document.querySelectorAll('.subcat-filter').forEach(e => e.checked = false);
    filter();
  }

  function render(list) {
    const g = grid(), e = empty(), c = countText();
    if (!g) return;
    if (c) c.textContent = 'Показано: ' + list.length + ' ' + plural(list.length, 'позиция','позиции','позиций');
    if (!list.length) {
      g.style.display = 'none';
      if (e) e.style.display = '';
      return;
    }
    g.style.display = '';
    if (e) e.style.display = 'none';
    g.innerHTML = list.map(p => {
      const catLabel = (typeof CATEGORY_LABELS !== 'undefined' && CATEGORY_LABELS[p.category]) || p.category;
      const pImg = imgUrl(p);
      const safeName = esc(p.name);
      const safeArt = esc(p.art || '');
      return '<article class="product-card">' +
        '<div class="product-card__media">' +
          '<a href="product.html?id=' + p.id + '"><img src="' + pImg + '" alt="' + safeName + '" loading="lazy"></a>' +
          '<span class="tag">' + safeArt + '</span>' +
          (p.custom ? '<span class="tag tag--gold">Под заказ</span>' : '') +
        '</div>' +
        '<div class="product-card__body">' +
          '<div class="product-card__cat">' + esc(catLabel) + '</div>' +
          '<h3><a href="product.html?id=' + p.id + '">' + safeName + '</a></h3>' +
          (p.sizes && String(p.sizes).trim() ? '<div class="product-card__meta"><strong>Габариты:</strong> ' + esc(p.sizes) + '</div>' : '') +
          '<div class="product-card__price">Цена по запросу</div>' +
          '<div class="product-card__actions">' +
            '<a href="product.html?id=' + p.id + '" class="btn btn-outline btn-sm">Детали</a>' +
            '<button class="btn btn-gold btn-sm" data-add="' + p.id + '" ' +
              "onclick=\"Shengda.add('" + p.id + "','" + safeName + "','" + safeArt + "','" + pImg + "')\">" + 'В запрос</button>' +
          '</div>' +
        '</div>' +
      '</article>';
    }).join('');
  }

  function esc(s) {
    return String(s).replace(/'/g, '&#39;').replace(/[&<>"]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c]));
  }
  function plural(n, one, few, many) {
    const m10 = n % 10, m100 = n % 100;
    if (m10 === 1 && m100 !== 11) return one;
    if (m10 >= 2 && m10 <= 4 && (m100 < 10 || m100 >= 20)) return few;
    return many;
  }

  window.ShengdaCatalog = { filter, reset };

  document.addEventListener('DOMContentLoaded', async () => {
    if (!grid()) return;
    // Пробуем загрузить из базы; если не вышло — остаётся data.js
    const dbData = await loadFromDB();
    if (dbData) items = dbData;
    const params = new URLSearchParams(location.search);
    const cat = params.get('category');
    if (cat) {
      const r = document.querySelector('input[name="category"][value="' + cat + '"]');
      if (r) r.checked = true;
    }
    filter();
  });
})();
