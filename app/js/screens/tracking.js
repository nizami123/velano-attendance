import { session, pointsDb, cache } from '../store.js';
import { TrackingRepo } from '../repo.js';
import { Time, icon, esc, $, mapsLink, toast, isIOS } from '../util.js';
import { EmployeeTracker, onTrackerChange, ensureEmployeeTracking, retryEmployeeTracking, syncKind, wakeLockActive, refreshWakeLock } from '../tracker.js';
import { drawMap } from '../map.js';
import { t } from '../i18n.js';

export function trackingTab(view, ctx) {
  const emp = session.employee || {};
  view.innerHTML = `
  <div class="topbar"><h1>Tracking</h1><button class="icon-btn" id="refresh" aria-label="${t('Segarkan')}">${icon('refresh', 22)}</button></div>
  <div class="pad" style="padding-bottom:0"></div>
  <div id="body"></div>`;
  const body = $('#body', view);
  let points = [], ticker = null, mapCtl = null, pending = 0;

  async function load() {
    points = await pointsDb.forDate(emp.id, Time.today());
    pending = await pointsDb.countPending('att');
    if (ctx.isCurrent()) render();
  }

  function renderIdle() {
    const err = EmployeeTracker.error;
    const denied = err && err.code === 1;
    const role = emp.role;
    const na = !['EMPLOYEE', 'DRIVER'].includes(role);
    body.innerHTML = `
    <div class="card center" style="padding:28px 20px">
      <div class="muted">${icon('pin', 44)}</div>
      <h3 class="mt8">${na ? t('Tracking tidak aktif') : denied ? t('Izin lokasi diperlukan') : t('Menyiapkan Tracking')}</h3>
      <p class="muted mt8">${na ? t('Tracking otomatis tidak berlaku untuk akun ini.')
        : denied ? t('Aktifkan izin lokasi agar tracking bisa mencatat. Di iPhone: Pengaturan › Privasi › Layanan Lokasi.')
          : (err ? esc(err.message) : t('Tracking akan mulai otomatis dalam beberapa saat.'))}</p>
      ${na ? '' : `<button class="btn primary mt16" id="enable">${denied ? t('Coba Aktifkan Lagi') : t('Mulai Tracking')}</button>`}
    </div>`;
    const b = $('#enable', body);
    if (b) b.addEventListener('click', async () => { await retryEmployeeTracking(); render(); });
  }

  function iosNotice() {
    return `<div class="banner warn">${icon('info', 18)}<div><b>${t('Tracking hanya berjalan saat aplikasi terbuka.')}</b>
      ${esc(t('iPhone tidak mengizinkan aplikasi web membaca lokasi di latar belakang. Biarkan aplikasi terbuka & layar menyala; titik akan dicatat otomatis begitu aplikasi dibuka kembali.'))}</div></div>`;
  }

  function render() {
    if (!EmployeeTracker.active) { renderIdle(); return; }
    const first = points[0], last = points[points.length - 1];
    const startMs = first ? Time.parse(first.recorded_at) : EmployeeTracker.startedAt;
    const office = session.offices.find((o) => o.id === emp.office_id);
    const intervalS = (office && office.tracking_interval_seconds > 0) ? office.tracking_interval_seconds : 600;
    const every = intervalS % 60 === 0 ? t('{n} menit', { n: intervalS / 60 }) : t('{n} detik', { n: intervalS });
    const err = EmployeeTracker.error;
    body.innerHTML = `
    <div class="card green">
      <div class="row between"><div><div style="font-weight:800;font-size:17px">${t('Tracking Sedang Berjalan')}</div>
        <div style="opacity:.85;font-size:13px;margin-top:2px">${t('Lokasi Anda dicatat setiap {every}, terlepas dari status absensi', { every })}</div></div>
        <span class="pill" style="background:rgba(255,255,255,.22);color:#fff"><span class="dot-live"></span>${t('Aktif')}</span></div>
      <div class="grid2 mt16" style="color:#fff">
        <div><div class="lbl" style="color:#CFF2DE">${t('Mulai')}</div><div class="val" id="tStart">${first ? Time.withLabel(first.recorded_at) : '-'}</div></div>
        <div><div class="lbl" style="color:#CFF2DE">${t('Durasi')}</div><div class="val" id="tDur">${Time.hms(Date.now() - startMs)}</div></div>
        <div><div class="lbl" style="color:#CFF2DE">${t('Update Terakhir')}</div><div class="val">${last ? Time.withLabel(last.recorded_at) : t('Menunggu titik pertama')}</div></div>
        <div><div class="lbl" style="color:#CFF2DE">${t('Akurasi')}</div><div class="val">${last && last.accuracy != null ? t('{n} meter', { n: Math.round(last.accuracy) }) : '-'}</div></div>
      </div>
    </div>
    ${err ? `<div class="banner err">${icon('info', 18)}<div>${esc(err.message)}</div></div>` : ''}
    ${iosNotice()}
    <div class="card">
      <h3>${t('Lokasi Terakhir')}</h3>
      <p class="muted mt8" id="lastLoc">${last ? `${last.latitude.toFixed(6)}, ${last.longitude.toFixed(6)}` : t('Belum ada titik lokasi tercatat')}</p>
      ${last ? `<a class="link" href="${mapsLink(last.latitude, last.longitude)}" target="_blank" rel="noopener">${t('Buka di Peta')}</a>` : ''}
      <div id="map" class="mt12"></div>
    </div>
    <div class="card">
      <div class="kv"><span>${icon('battery', 16)} ${t('Baterai')}</span><b>${last && last.battery_level != null ? last.battery_level + '%' : '-'}</b></div>
      <div class="kv"><span>${t('Tracking interval')}</span><b>${t('Setiap {every}', { every })}</b></div>
      <div class="kv"><span>${t('Titik hari ini')}</span><b>${points.length}</b></div>
      <div class="kv"><span>${t('Menunggu sinkron')}</span><b id="pend">${pending}</b></div>
      <div class="kv"><span>${t('Layar dijaga menyala')}</span><b>${wakeLockActive() ? t('Ya') : t('Tidak')}</b></div>
    </div>
    <div class="row gap" style="padding:0 16px">
      <button class="btn outline grow" id="now">${t('Catat Titik Sekarang')}</button>
      <button class="btn ghost grow" id="sync">${icon('sync', 18)} ${t('Sinkronkan')}</button>
    </div>`;
    if (mapCtl) mapCtl.destroy();
    mapCtl = drawMap($('#map', body), {
      route: points,
      markers: last ? [{ lat: last.latitude, lng: last.longitude, color: '#E53935' }] : [],
      emptyText: t('Menunggu titik lokasi pertama…'),
    });
    $('#now', body).addEventListener('click', async (e) => {
      e.target.disabled = true;
      const r = await EmployeeTracker.record();
      if (!r && EmployeeTracker.error) toast(EmployeeTracker.error.message);
      e.target.disabled = false;
    });
    $('#sync', body).addEventListener('click', async (e) => {
      e.target.disabled = true;
      const n = await syncKind('att');
      toast(n ? t('{n} titik tersinkron', { n }) : t('Tidak ada titik yang perlu disinkronkan / server tidak terjangkau'));
      load();
    });
  }

  $('#refresh', view).addEventListener('click', async () => {
    await ensureEmployeeTracking();
    await syncKind('att');
    load();
  });

  const off = onTrackerChange(load);
  ticker = setInterval(() => {
    const el = $('#tDur', view);
    if (!el || !EmployeeTracker.active) return;
    const first = points[0];
    const startMs = first ? Time.parse(first.recorded_at) : EmployeeTracker.startedAt;
    el.textContent = Time.hms(Date.now() - startMs);
  }, 1000);

  ensureEmployeeTracking().then(() => { refreshWakeLock(); load(); });
  load();
  return () => { off(); clearInterval(ticker); if (mapCtl) mapCtl.destroy(); };
}

// ---------------------------------------------------------------------------
export async function trackingDetail(view, ctx) {
  const date = ctx.arg || Time.today();
  const emp = session.employee;
  view.innerHTML = `
  <div class="topbar"><button class="icon-btn" id="back" aria-label="${t('Kembali')}">${icon('back', 24)}</button><h1>${t('Detail Tracking')}</h1></div>
  <div class="pad">
    <div class="card" style="margin:0 0 14px">
      <div style="font-weight:700">${Time.longDate(date)}</div>
      <div class="muted mt8" id="att">${t('Absensi: memuat…')}</div>
      <div class="muted" id="count">${t('Total titik: …')}</div>
    </div>
    <div id="map" class="map tall"></div>
    <h3 class="section-title" style="margin-left:0">${t('Daftar Titik')}</h3>
    <div class="card flat" id="list" style="margin:0"><div class="loading"><span class="spinner"></span></div></div>
  </div>`;
  $('#back', view).addEventListener('click', () => ctx.go('history'));

  const hist = cache.get(`history_${emp.id}`, []);
  const a = hist.find((x) => x.attendance_date === date);
  $('#att', view).textContent = a
    ? t('Masuk: {in}  •  Pulang: {out}', { in: a.check_in_time ? Time.withLabel(a.check_in_time) : '-', out: a.check_out_time ? Time.withLabel(a.check_out_time) : '-' })
    : t('Absensi: belum ada');

  let ctl = null;
  const paint = async () => {
    const pts = await pointsDb.forDate(emp.id, date);
    if (!ctx.isCurrent()) return;
    $('#count', view).textContent = t('Total titik: {n}', { n: pts.length });
    if (ctl) ctl.destroy();
    ctl = drawMap($('#map', view), {
      route: pts,
      markers: pts.length ? [
        { lat: pts[0].latitude, lng: pts[0].longitude, color: '#1E9E5A' },
        ...(pts.length > 1 ? [{ lat: pts[pts.length - 1].latitude, lng: pts[pts.length - 1].longitude, color: '#E53935' }] : []),
      ] : [],
      emptyText: t('Tidak ada titik tracking pada tanggal ini'),
    });
    $('#list', view).innerHTML = pts.length
      ? pts.map((p, i) => `<div class="kv"><span>${i + 1}. ${Time.withLabel(p.recorded_at)}</span><b>${p.latitude.toFixed(5)}, ${p.longitude.toFixed(5)}</b></div>`).join('')
      : `<div class="empty" style="padding:20px">${t('Belum ada titik')}</div>`;
  };
  await paint();
  await TrackingRepo.refreshFromServer(date);
  await paint();
  return () => { if (ctl) ctl.destroy(); };
}
