// Perangkat tiruan untuk MODE DEMO.
//  - GPS: selalu berada di titik Kantor Lamongan demo (± beberapa meter), supaya pengunjung dari mana pun
//    bisa mencoba absen masuk/pulang. Tanpa ini, GPS asli pengunjung pasti di luar radius kantor demo.
//  - Kamera: memakai kamera asli bila diizinkan; bila tidak tersedia/ditolak (mis. di dalam iframe) dipakai
//    gambar tiruan "FOTO DEMO" supaya alur absen selfie tetap bisa dicoba.
//  - Izin lokasi dilaporkan "granted" agar pelacakan demo tidak berhenti karena dialog izin.
import { Time } from './util.js';
import { t } from './i18n.js';

const DEMO_POINT = { lat: -7.116, lng: 112.4145 }; // = Kantor Lamongan di mock.js

let installed = false;
let origGUM = null;
let origPermQuery = null;
let hadMediaDevices = true;
let nextWatch = 1;
const timers = new Map();

function fakePosition() {
  const j = () => (Math.random() - 0.5) * 0.00014; // kira-kira ± 8 m
  return {
    coords: {
      latitude: DEMO_POINT.lat + j(), longitude: DEMO_POINT.lng + j(),
      accuracy: 6 + Math.random() * 6, altitude: null, altitudeAccuracy: null, heading: null, speed: null,
    },
    timestamp: Date.now(),
  };
}

const fakeGeo = {
  getCurrentPosition(ok) { setTimeout(() => ok(fakePosition()), 350); },
  watchPosition(ok) {
    const id = nextWatch++;
    setTimeout(() => ok(fakePosition()), 350);
    timers.set(id, setInterval(() => ok(fakePosition()), 4000));
    return id;
  },
  clearWatch(id) { clearInterval(timers.get(id)); timers.delete(id); },
};

function fakeStream() {
  const c = document.createElement('canvas');
  c.width = 640; c.height = 480;
  if (!c.captureStream) throw new Error(t('Kamera tidak tersedia'));
  const g = c.getContext('2d');
  const draw = () => {
    const bg = g.createLinearGradient(0, 0, 640, 480);
    bg.addColorStop(0, '#d9f2dc'); bg.addColorStop(1, '#8fd19e');
    g.fillStyle = bg; g.fillRect(0, 0, 640, 480);
    g.fillStyle = '#048f38';
    g.beginPath(); g.ellipse(320, 560, 210, 190, 0, 0, Math.PI * 2); g.fill(); // bahu
    g.fillStyle = '#f2c9a0';
    g.beginPath(); g.ellipse(320, 215, 98, 120, 0, 0, Math.PI * 2); g.fill();  // wajah
    g.fillStyle = '#2b2b2b';
    g.beginPath(); g.arc(285, 200, 8, 0, Math.PI * 2); g.arc(355, 200, 8, 0, Math.PI * 2); g.fill();
    g.strokeStyle = '#2b2b2b'; g.lineWidth = 5; g.beginPath(); g.arc(320, 240, 38, 0.2 * Math.PI, 0.8 * Math.PI); g.stroke();
    g.fillStyle = 'rgba(0,0,0,.55)'; g.fillRect(0, 430, 640, 50);
    g.fillStyle = '#fff'; g.font = 'bold 22px sans-serif'; g.textAlign = 'center';
    g.fillText(t('FOTO DEMO') + ' · ' + Time.clock(), 320, 462);
  };
  draw();
  const timer = setInterval(draw, 1000);
  const stream = c.captureStream(5);
  stream.getTracks().forEach((tr) => {
    const stop = tr.stop.bind(tr);
    tr.stop = () => { clearInterval(timer); stop(); };
  });
  return stream;
}

async function demoGetUserMedia(constraints) {
  if (origGUM) {
    try { return await origGUM(constraints); } catch { /* jatuh ke kamera tiruan */ }
  }
  return fakeStream();
}

export function installDemoDevice() {
  if (installed) return;
  installed = true;
  try { Object.defineProperty(navigator, 'geolocation', { configurable: true, get: () => fakeGeo }); } catch { /* */ }
  try {
    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
      origGUM = navigator.mediaDevices.getUserMedia.bind(navigator.mediaDevices);
      navigator.mediaDevices.getUserMedia = demoGetUserMedia;
    } else {
      hadMediaDevices = false;
      Object.defineProperty(navigator, 'mediaDevices', { configurable: true, value: { getUserMedia: demoGetUserMedia } });
    }
  } catch { /* */ }
  try {
    if (navigator.permissions && navigator.permissions.query) {
      origPermQuery = navigator.permissions.query.bind(navigator.permissions);
      navigator.permissions.query = (d) => (d && d.name === 'geolocation' ? Promise.resolve({ state: 'granted' }) : origPermQuery(d));
    }
  } catch { /* */ }
}

export function uninstallDemoDevice() {
  if (!installed) return;
  installed = false;
  timers.forEach((t) => clearInterval(t)); timers.clear();
  try { delete navigator.geolocation; } catch { /* */ }
  try {
    if (!hadMediaDevices) delete navigator.mediaDevices;
    else if (origGUM) delete navigator.mediaDevices.getUserMedia;
  } catch { /* */ }
  try { if (origPermQuery) delete navigator.permissions.query; } catch { /* */ }
  origGUM = null; origPermQuery = null; hadMediaDevices = true;
}
