// Multi-bahasa PWA: Indonesia (bahasa sumber) + English.
//
// Sama seperti panel admin, teks di kode ditulis dalam bahasa Indonesia dan dibungkus t('...').
// Terjemahan Inggris ada di js/lang/en.js (kunci = teks Indonesia). Teks yang belum ada di kamus
// otomatis tampil apa adanya (Indonesia), jadi tidak ada layar yang kosong/rusak.
//   t('Absen Masuk')                          -> "Check-in" (EN) / "Absen Masuk" (ID)
//   t('Masuk pukul {time}', { time: '08:00' }) -> placeholder {nama} diganti dari parameter
//
// Bahasa aktif disimpan di settings.lang (localStorage), dikirim ke server lewat header
// Accept-Language (backend sudah menerjemahkan pesan API untuk en/id), dan diganti lewat
// tombol ID | EN di layar login & Profil › Pengaturan.
import { settings } from './store.js';
import { EN } from './lang/en.js';

// Bahasa tersedia. Indonesia = bahasa sumber (tanpa kamus); lainnya butuh kamus di DICTS.
// Menambah bahasa: buat js/lang/xx.js (salin en.js), impor di sini, lalu tambah ke LANGS & DICTS.
export const LANGS = [['id', 'Indonesia'], ['en', 'English']];
const DICTS = { en: EN };
let current = 'id';

const normalize = (c) => {
  const code = String(c || '').toLowerCase().slice(0, 2);
  return LANGS.some(([k]) => k === code) ? code : 'id';
};

export const getLang = () => current;

export function t(text, params) {
  if (text == null) return text;
  let s = String(text);
  const dict = DICTS[current];
  if (dict && Object.prototype.hasOwnProperty.call(dict, s)) s = dict[s];
  if (params) s = s.replace(/\{(\w+)\}/g, (m, k) => (k in params ? String(params[k]) : m));
  return s;
}

/** Terapkan bahasa ke bagian dokumen yang statis (atribut lang, deskripsi). */
export function applyLang() {
  document.documentElement.lang = current;
  const meta = document.querySelector('meta[name="description"]');
  if (meta) meta.content = t('Absensi & Tracking Karyawan');
}

export function initLang() {
  current = normalize(settings.get().lang);
  applyLang();
}

/** Ganti bahasa lalu beri tahu aplikasi untuk menggambar ulang layar aktif. */
export function setLang(code) {
  current = normalize(code);
  settings.set({ lang: current });
  applyLang();
  window.dispatchEvent(new Event('st:lang'));
}

/** Tombol ID | EN. Pasang aksinya dengan bindLangSwitch(elemenInduk). */
export function langSwitchHtml(cls = '') {
  return `<div class="lang-switch ${cls}" role="group" aria-label="Language / Bahasa">${LANGS.map(([c, n]) =>
    `<button type="button" data-lang="${c}" class="${c === current ? 'active' : ''}" aria-pressed="${c === current}" title="${n}">${c.toUpperCase()}</button>`).join('')}</div>`;
}

export function bindLangSwitch(root) {
  root.querySelectorAll('[data-lang]').forEach((b) => b.addEventListener('click', () => {
    if (b.dataset.lang !== current) setLang(b.dataset.lang);
  }));
}
