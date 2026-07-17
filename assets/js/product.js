/* =====================================================================
   SHENGDA — Product detail page logic
   product.js
   ===================================================================== */
(function () {
  'use strict';

  const PLACEHOLDER = 'data:image/svg+xml,' + encodeURIComponent(
    '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="450"><rect width="600" height="450" fill="#F6F4EF"/><text x="300" y="250" font-size="90" fill="#D0AE6F" text-anchor="middle" font-family="Georgia,serif">Ш</text></svg>'
  );

  function imgSrc(p) {
    if (p.img && String(p.img).trim()) return String(p.img).replace('w=900', 'w=1100');
    return PLACEHOLDER;
  }

  function catLabel(cat) {
    if (typeof CATEGORY_LABELS !== 'undefined' && CATEGORY_LABELS[cat]) return CATEGORY_LABELS[cat];
    return cat;
  }

  function specsHtml(p) {
    const specs = [
      ['Материалы', p.material],
      ['Габариты', p.sizes],
      ['Цвета', p.color],
      ['Упаковка', p.packing],
      ['Назначение', p.destination],
      ['Кастомизация', p.custom ? 'Доступна под чертежи и референсы' : ''],
    ];
    return specs
      .filter(([label, val]) => val && String(val).trim() && val !== 'None')
      .map(([label, val]) => '<div class="spec-row"><div class="k">' + label + '</div><div class="v">' + esc(val) + '</div></div>')
      .join('');
  }

  function esc(s) {
    return String(s).replace(/'/g, '&#39;').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  }

  function cardHtml(r) {
    const pImg = imgSrc(r);
    const safeName = esc(r.name);
    const safeArt = esc(r.art || '');
    return '<article class="product-card">' +
      '<div class="product-card__media">' +
        '<a href="product.html?id=' + r.id + '"><img src="' + pImg + '" alt="' + safeName + '" loading="lazy"></a>' +
        '<span class="tag">' + safeArt + '</span>' +
      '</div>' +
      '<div class="product-card__body">' +
        '<div class="product-card__cat">' + esc(catLabel(r.category)) + '</div>' +
        '<h3><a href="product.html?id=' + r.id + '">' + safeName + '</a></h3>' +
        (r.sizes && String(r.sizes).trim() ? '<div class="product-card__meta"><strong>Габариты:</strong> ' + esc(r.sizes) + '</div>' : '') +
        '<div class="product-card__price">Цена по запросу</div>' +
      '</div>' +
    '</article>';
  }

  document.addEventListener('DOMContentLoaded', async () => {
    const host = document.getElementById('product-detail');
    if (!host) return;

    const id = new URLSearchParams(location.search).get('id');

    // Загружаем конкретный товар
    let p = null;
    try {
      if (id) {
        const res = await fetch('api/products.php?slug=' + encodeURIComponent(id), { cache: 'no-store' });
        if (res.ok) {
          const data = await res.json();
          if (data && data.id) p = data;
        }
      }
    } catch (e) {}

    // Откат на data.js
    if (!p && typeof PRODUCTS !== 'undefined') {
      p = PRODUCTS.find(x => x.id === id) || PRODUCTS[0];
    }

    if (!p) { host.innerHTML = '<p style="text-align:center;padding:80px 0">Товар не найден.</p>'; return; }

    document.title = p.name + ' — Шэнда';
    const h1 = document.querySelector('h1[data-prod-title]');
    if (h1) h1.textContent = p.name;
    const crumb = document.getElementById('crumb-name');
    if (crumb) crumb.textContent = p.name;

    const pImg = imgSrc(p);
    host.innerHTML =
      '<div class="split" data-reveal>' +
        '<div class="product-gallery">' +
          '<img src="' + pImg + '" alt="' + esc(p.name) + '">' +
        '</div>' +
        '<div class="product-info">' +
          '<span class="eyebrow no-line">' + esc(catLabel(p.category)) + ' · Арт. ' + esc(p.art || '') + '</span>' +
          '<h1 class="serif" style="margin-top:16px">' + esc(p.name) + '</h1>' +
          '<div class="price-pill">Цена по запросу · расчёт под партию</div>' +
          (p.desc && String(p.desc).trim() ? '<p>' + esc(p.desc) + '</p>' : '') +
          '<div style="margin-top:30px">' + specsHtml(p) + '</div>' +
          '<div style="display:flex;gap:14px;flex-wrap:wrap;margin-top:32px">' +
            '<button class="btn btn-gold" data-add="' + p.id + '" ' +
              "onclick=\"Shengda.add('" + p.id + "','" + esc(p.name) + "','" + esc(p.art || '') + "','" + pImg + "')\">Добавить в запрос</button>" +
            '<button class="btn btn-outline" onclick="Shengda.openModal(\'Узнать цену\',\'Запросить расчёт\',\'Опишите объём партии — рассчитаем цену с учётом производства и фрахта до вашего города.\')">Рассчитать партию</button>' +
          '</div>' +
        '</div>' +
      '</div>' +
      '<div style="margin-top:clamp(50px,7vw,90px)" data-reveal>' +
        '<div class="section-head" style="margin-bottom:40px">' +
          '<span class="eyebrow">Сопутствующие позиции</span>' +
          '<h2>Похожие артикулы</h2>' +
        '</div>' +
        '<div class="products-grid" id="related-grid"></div>' +
      '</div>';

    // === Загружаем похожие товары из той же категории ===
    let allProducts = [];
    try {
      const res = await fetch('api/products.php', { cache: 'no-store' });
      if (res.ok) {
        const data = await res.json();
        if (Array.isArray(data)) allProducts = data;
      }
    } catch (e) {}

    if (!allProducts.length && typeof PRODUCTS !== 'undefined') {
      allProducts = PRODUCTS;
    }

    const related = allProducts
      .filter(x => x.category === p.category && x.id !== p.id)
      .slice(0, 3);
    const fallback = allProducts.filter(x => x.id !== p.id).slice(0, 3);
    const relatedList = (related.length ? related : fallback);
    const grid = document.getElementById('related-grid');
    if (grid) {
      grid.innerHTML = relatedList.map(r => cardHtml(r)).join('');
    }

    if (window.Shengda && window.Shengda.fadeImages) window.Shengda.fadeImages();

    // reveal
    window.requestAnimationFrame(() => {
      document.querySelectorAll('#product-detail [data-reveal]').forEach(el => {
        const io = new IntersectionObserver((ents) => {
          ents.forEach(en => { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } });
        }, { threshold: .12 });
        io.observe(el);
      });
    });
  });
})();
