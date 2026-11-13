import { session } from '../store.js';
import { Office, Attendance } from '../repo.js';
import { Time, icon, esc, $, haversine, toast } from '../util.js';
import { Camera, cameraSupported, imageFileToJpeg } from '../camera.js';
import { t } from '../i18n.js';

export async function checkin(view, ctx) {
  const isOut = ctx.query.out === '1';
  const title = isOut ? t('Absen Pulang') : t('Absen Masuk');
  const emp = session.employee;

  view.innerHTML = `
  <div class="topbar"><button class="icon-btn" id="back" aria-label="${t('Kembali')}">${icon('back', 24)}</button><h1>${title}</h1>
    <button class="icon-btn" id="refresh" aria-label="${t('Segarkan lokasi')}">${icon('refresh', 22)}</button></div>
  <div class="pad">
    <div class="card" style="margin:0 0 14px">
      <div class="row gap"><div class="grow"><div style="font-weight:700" id="offName">-</div><div class="small muted" id="offAddr">&nbsp;</div></div>
        <span class="pill gray" id="area">${t('Mencari lokasi')}</span></div>
    </div>

    <div class="cam" id="cam">
      <video id="video" playsinline muted autoplay></video>
      <img class="shot hidden" id="shot" alt="${t('Foto absen')}">
      <div class="oval" id="oval"></div>
      <div class="hint" id="hint">${t('Posisikan wajah di dalam bingkai')}</div>
      <div class="msg hidden" id="camMsg"></div>
    </div>
    <div class="row gap mt12">
      <button class="btn outline grow" id="capture" disabled>${icon('camera', 20)} ${t('Ambil Foto')}</button>
      <button class="btn ghost hidden" id="retake">${t('Ulangi')}</button>
    </div>
    <input type="file" id="fileCam" accept="image/*" capture="user" class="hidden">

    <div class="card mt16" style="margin:16px 0 0">
      <div class="kv"><span>${t('Jarak dari kantor')}</span><b id="vDist">-</b></div>
      <div class="kv"><span>${t('Akurasi GPS')}</span><b id="vAcc">-</b></div>
      <div class="kv"><span>${t('Waktu')}</span><b id="vTime">${Time.clock()} ${Time.label}</b></div>
      <div class="kv"><span>${t('Tanggal')}</span><b>${Time.longDate()}</b></div>
    </div>
    <div id="status" class="center mt12" style="font-weight:800">${t('Mencari lokasi GPS...')}</div>
    <div id="err" class="error hidden" role="alert"></div>
    <button class="btn primary block mt12" id="submit" disabled>${isOut ? t('ABSEN PULANG') : t('ABSEN MASUK')}</button>
  </div>`;

  $('#back', view).addEventListener('click', () => ctx.go('home'));

  let office = null, loc = null, photo = null, locCheck = null, watchId = null, submitting = false;
  const cam = new Camera($('#video', view), 'user');
  const err = $('#err', view);
  const showErr = (m) => { err.textContent = m; err.classList.toggle('hidden', !m); };

  function updateSubmit() { $('#submit', view).disabled = submitting || !(locCheck && locCheck.valid && photo); }

  function evaluate() {
    if (!office || !loc) return;
    const radius = isOut ? office.check_out_radius : office.check_in_radius;
    const dist = haversine(loc.latitude, loc.longitude, office.latitude, office.longitude);
    locCheck = { dist, radius, valid: dist <= radius };
    $('#vDist', view).textContent = t('{n} meter', { n: Math.round(dist) });
    const area = $('#area', view), status = $('#status', view);
    area.className = 'pill ' + (locCheck.valid ? 'green' : 'red');
    area.textContent = locCheck.valid ? t('Di dalam area') : t('Di luar area');
    status.textContent = locCheck.valid ? t('✓ LOKASI VALID') : t('✕ LOKASI DI LUAR RADIUS (maks. {radius} m)', { radius });
    status.style.color = locCheck.valid ? 'var(--green)' : 'var(--red)';
    updateSubmit();
  }

  function startWatch() {
    if (watchId != null) navigator.geolocation.clearWatch(watchId);
    if (!('geolocation' in navigator)) { $('#status', view).textContent = t('Perangkat tidak mendukung GPS'); return; }
    $('#status', view).textContent = t('Mencari lokasi GPS...'); $('#status', view).style.color = '';
    watchId = navigator.geolocation.watchPosition((pos) => {
      loc = pos.coords;
      $('#vAcc', view).textContent = t('{n} meter', { n: Math.round(pos.coords.accuracy) });
      evaluate();
    }, (e) => {
      $('#status', view).style.color = 'var(--red)';
      $('#status', view).textContent = e.code === 1
        ? t('Izin lokasi ditolak. Aktifkan di Pengaturan iPhone › Privasi › Layanan Lokasi.')
        : t('Tidak dapat mengambil lokasi GPS. Pastikan GPS aktif, keluar ruangan/dekat jendela, lalu ketuk segarkan.');
    }, { enableHighAccuracy: true, timeout: 20000, maximumAge: 3000 });
  }

  async function startCamera() {
    $('#camMsg', view).classList.add('hidden');
    try {
      await cam.start();
      $('#capture', view).disabled = false;
    } catch (e) {
      $('#camMsg', view).innerHTML = `<div>${esc(e.message)}<br><br>${t('Anda tetap bisa mengambil foto dengan kamera iPhone lewat tombol “Ambil Foto”.')}</div>`;
      $('#camMsg', view).classList.remove('hidden');
      $('#oval', view).classList.add('hidden'); $('#hint', view).classList.add('hidden');
      $('#capture', view).disabled = false;
      cam.failed = true;
    }
  }

  function setPhoto(p) {
    photo = p;
    const shot = $('#shot', view);
    shot.src = p.dataUrl; shot.classList.remove('hidden');
    $('#video', view).classList.add('hidden'); $('#oval', view).classList.add('hidden'); $('#hint', view).classList.add('hidden');
    $('#camMsg', view).classList.add('hidden');
    $('#capture', view).classList.add('hidden'); $('#retake', view).classList.remove('hidden');
    updateSubmit();
  }

  $('#capture', view).addEventListener('click', () => {
    if (cam.stream) {
      try { setPhoto(cam.capture()); } catch (e) { showErr(t('Gagal mengambil foto: {msg}', { msg: e.message })); }
    } else {
      $('#fileCam', view).click();
    }
  });
  $('#fileCam', view).addEventListener('change', async (e) => {
    const f = e.target.files && e.target.files[0];
    if (!f) return;
    try { setPhoto(await imageFileToJpeg(f)); } catch { showErr(t('Gagal membaca foto')); }
    e.target.value = '';
  });
  $('#retake', view).addEventListener('click', async () => {
    photo = null; updateSubmit();
    $('#shot', view).classList.add('hidden'); $('#retake', view).classList.add('hidden'); $('#capture', view).classList.remove('hidden');
    if (cam.stream) {
      $('#video', view).classList.remove('hidden'); $('#oval', view).classList.remove('hidden'); $('#hint', view).classList.remove('hidden');
    } else if (!cam.failed) { await startCamera(); $('#video', view).classList.remove('hidden'); }
    else { $('#camMsg', view).classList.remove('hidden'); }
  });
  $('#refresh', view).addEventListener('click', async () => {
    startWatch();
    const o = await Office.refresh().catch(() => []);
    if (o.length) { office = await Office.get(emp.office_id); fillOffice(); evaluate(); }
  });

  function fillOffice() {
    if (!office) return;
    $('#offName', view).textContent = office.name;
    $('#offAddr', view).textContent = office.address || `${office.latitude}, ${office.longitude}`;
  }

  $('#submit', view).addEventListener('click', async () => {
    if (!loc || !photo || !office || submitting) return;
    showErr('');
    submitting = true; const btn = $('#submit', view);
    btn.disabled = true; btn.innerHTML = `<span class="spinner"></span> ${t('Mengirim...')}`;
    const base = {
      employee_id: emp.id, latitude: loc.latitude, longitude: loc.longitude,
      accuracy: loc.accuracy, timestamp: Time.now(), photo_base64: photo.base64,
    };
    let res;
    if (isOut) {
      const today = await Attendance.today();
      if (!today || !today.check_in_time) {
        res = { type: 'error', message: t('Data absen masuk hari ini tidak ditemukan') };
      } else res = await Attendance.checkOut({ ...base, attendance_id: today.id });
    } else res = await Attendance.checkIn(base);

    submitting = false;
    if (res.type === 'ok') {
      toast(isOut ? t('Absen pulang berhasil') : t('Absen masuk berhasil'));
      ctx.go('home');
      return;
    }
    btn.innerHTML = isOut ? t('ABSEN PULANG') : t('ABSEN MASUK');
    updateSubmit();
    if (res.type === 'rejected') {
      $('#status', view).style.color = 'var(--red)';
      $('#status', view).textContent = `✕ ${res.message}${res.radius ? ` (${Math.round(res.distance)} m > ${res.radius} m)` : ''}`;
    } else showErr(res.message);
  });

  // jam berjalan
  const clock = setInterval(() => { const el = $('#vTime', view); if (el) el.textContent = `${Time.clock()} ${Time.label}`; }, 1000);

  office = await Office.get(emp.office_id);
  fillOffice();
  if (!cameraSupported()) { cam.failed = true; await startCamera(); } else startCamera();
  startWatch();

  return () => {
    clearInterval(clock);
    if (watchId != null) navigator.geolocation.clearWatch(watchId);
    cam.stop();
  };
}
