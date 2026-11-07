// Peta ringan tanpa library & tanpa API key: ubin OpenStreetMap + rute (SVG).
// Pengganti Google Maps di aplikasi Android.
const TILE = 256;
const TILE_URL = (z, x, y) => `https://tile.openstreetmap.org/${z}/${x}/${y}.png`;

const lng2x = (lng, z) => ((lng + 180) / 360) * TILE * 2 ** z;
const lat2y = (lat, z) => {
  const s = Math.sin((lat * Math.PI) / 180);
  return (0.5 - Math.log((1 + s) / (1 - s)) / (4 * Math.PI)) * TILE * 2 ** z;
};
const metersPerPx = (lat, z) => (156543.03392 * Math.cos((lat * Math.PI) / 180)) / 2 ** z;

/**
 * opts: { route: [{latitude, longitude}], markers: [{lat,lng,label,color}],
 *         circle: {lat,lng,radius}, emptyText }
 */
export function drawMap(el, opts = {}) {
  let lastW = 0, lastH = 0;
  const render = () => {
    const W = el.clientWidth, H = el.clientHeight;
    if (!W || !H) return;
    lastW = W; lastH = H;
    const route = (opts.route || []).map((p) => ({ lat: p.latitude, lng: p.longitude }));
    const marks = opts.markers || [];
    const pts = [...route, ...marks.map((m) => ({ lat: m.lat, lng: m.lng }))];
    if (opts.circle) pts.push({ lat: opts.circle.lat, lng: opts.circle.lng });
    el.innerHTML = '';
    el.classList.add('map');
    if (!pts.length) { el.innerHTML = `<div class="map-empty">${opts.emptyText || 'Belum ada data lokasi'}</div>`; return; }

    // zoom terbesar yang memuat semua titik (+ lingkaran)
    let minLat = Math.min(...pts.map((p) => p.lat)), maxLat = Math.max(...pts.map((p) => p.lat));
    let minLng = Math.min(...pts.map((p) => p.lng)), maxLng = Math.max(...pts.map((p) => p.lng));
    if (opts.circle) {
      const dLat = opts.circle.radius / 111320, dLng = opts.circle.radius / (111320 * Math.cos((opts.circle.lat * Math.PI) / 180));
      minLat = Math.min(minLat, opts.circle.lat - dLat); maxLat = Math.max(maxLat, opts.circle.lat + dLat);
      minLng = Math.min(minLng, opts.circle.lng - dLng); maxLng = Math.max(maxLng, opts.circle.lng + dLng);
    }
    const pad = 36;
    let z = 17;
    for (; z > 2; z--) {
      const w = Math.abs(lng2x(maxLng, z) - lng2x(minLng, z));
      const h = Math.abs(lat2y(minLat, z) - lat2y(maxLat, z));
      if (w <= W - pad * 2 && h <= H - pad * 2) break;
    }
    const cx = lng2x((minLng + maxLng) / 2, z), cy = lat2y((minLat + maxLat) / 2, z);
    const ox = cx - W / 2, oy = cy - H / 2;
    const toXY = (lat, lng) => [lng2x(lng, z) - ox, lat2y(lat, z) - oy];

    // ubin
    const tiles = document.createElement('div');
    tiles.className = 'map-tiles';
    const n = 2 ** z;
    for (let tx = Math.floor(ox / TILE); tx <= Math.floor((ox + W) / TILE); tx++) {
      for (let ty = Math.floor(oy / TILE); ty <= Math.floor((oy + H) / TILE); ty++) {
        if (ty < 0 || ty >= n) continue;
        const img = new Image();
        img.alt = ''; img.decoding = 'async'; img.referrerPolicy = 'no-referrer';
        img.src = TILE_URL(z, ((tx % n) + n) % n, ty);
        img.style.cssText = `left:${tx * TILE - ox}px;top:${ty * TILE - oy}px`;
        tiles.appendChild(img);
      }
    }
    el.appendChild(tiles);

    // overlay SVG
    const ns = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(ns, 'svg');
    svg.setAttribute('width', W); svg.setAttribute('height', H); svg.setAttribute('class', 'map-svg');
    if (opts.circle) {
      const [x, y] = toXY(opts.circle.lat, opts.circle.lng);
      const c = document.createElementNS(ns, 'circle');
      c.setAttribute('cx', x); c.setAttribute('cy', y);
      c.setAttribute('r', Math.max(6, opts.circle.radius / metersPerPx(opts.circle.lat, z)));
      c.setAttribute('fill', 'rgba(47,111,237,.2)'); c.setAttribute('stroke', '#2F6FED'); c.setAttribute('stroke-width', '2');
      svg.appendChild(c);
    }
    if (route.length > 1) {
      const pl = document.createElementNS(ns, 'polyline');
      pl.setAttribute('points', route.map((p) => toXY(p.lat, p.lng).join(',')).join(' '));
      pl.setAttribute('fill', 'none'); pl.setAttribute('stroke', '#1565C0'); pl.setAttribute('stroke-width', '5');
      pl.setAttribute('stroke-linecap', 'round'); pl.setAttribute('stroke-linejoin', 'round'); pl.setAttribute('opacity', '.9');
      svg.appendChild(pl);
      route.forEach((p) => {
        const [x, y] = toXY(p.lat, p.lng);
        const d = document.createElementNS(ns, 'circle');
        d.setAttribute('cx', x); d.setAttribute('cy', y); d.setAttribute('r', 3.5);
        d.setAttribute('fill', '#fff'); d.setAttribute('stroke', '#1565C0'); d.setAttribute('stroke-width', '2');
        svg.appendChild(d);
      });
    }
    marks.forEach((m) => {
      const [x, y] = toXY(m.lat, m.lng);
      const g = document.createElementNS(ns, 'g');
      g.setAttribute('transform', `translate(${x},${y})`);
      g.innerHTML = `<path d="M0 0C-9-10-13-16-13-22a13 13 0 0 1 26 0c0 6-4 12-13 22z" fill="${m.color || '#E53935'}" stroke="#fff" stroke-width="2"/><circle cx="0" cy="-22" r="4.5" fill="#fff"/>`;
      svg.appendChild(g);
    });
    el.appendChild(svg);

    const attr = document.createElement('div');
    attr.className = 'map-attr'; attr.textContent = '© OpenStreetMap';
    el.appendChild(attr);
  };

  render();
  // ukuran bisa belum final saat dipanggil; gambar ulang saat berubah
  let ro;
  if ('ResizeObserver' in window) {
    ro = new ResizeObserver(() => { if (el.clientWidth !== lastW || el.clientHeight !== lastH) render(); });
    ro.observe(el);
  }
  return { redraw: render, destroy: () => ro && ro.disconnect() };
}
