// Router & shell. Hash routing (#/home, #/checkin, ...) - cocok untuk PWA statis.
import { session, settings } from "./store.js";
import { Time, icon, $, $$, esc, toast } from "./util.js";
import { Office, Employee, Auth } from "./repo.js";
import { installDemoDevice } from "./demo.js";
import {
	ensureEmployeeTracking,
	stopAllTracking,
	syncAll,
	onTrackerChange,
} from "./tracker.js";
import { applyTheme } from "./theme.js";
import { t, initLang } from "./i18n.js";
import { login } from "./screens/login.js";
import { home } from "./screens/home.js";
import { checkin } from "./screens/checkin.js";
import { trackingTab, trackingDetail } from "./screens/tracking.js";
import { history } from "./screens/history.js";
import { requests, newRequest } from "./screens/requests.js";
import { profile, settingsScreen, changePassword } from "./screens/profile.js";
import { pickup, deliveryScreen } from "./screens/delivery.js";

const appEl = document.getElementById("app");

const TABS = [
	{ id: "home", label: "Beranda", ic: "home" },
	{ id: "tracking", label: "Tracking", ic: "pin" },
	{ id: "history", label: "Riwayat", ic: "history" },
	{ id: "requests", label: "Pengajuan", ic: "doc" },
	{ id: "profile", label: "Profil", ic: "user" },
];

// route -> { fn, tab?: bool, public?: bool }
const ROUTES = {
	login: { fn: login, public: true },
	home: { fn: home, tab: true },
	tracking: { fn: trackingTab, tab: true },
	history: { fn: history, tab: true },
	requests: { fn: requests, tab: true },
	profile: { fn: profile, tab: true },
	checkin: { fn: checkin },
	trackdetail: { fn: trackingDetail },
	newrequest: { fn: newRequest },
	settings: { fn: settingsScreen },
	password: { fn: changePassword },
	pickup: { fn: pickup },
	delivery: { fn: deliveryScreen },
};

let cleanup = null;
let renderSeq = 0;

export function go(route, { replace = false } = {}) {
	const target = "#/" + route.replace(/^#?\/?/, "");
	if (location.hash === target) return render();
	if (replace) location.replace(target);
	else location.hash = target;
}

function parseHash() {
	const raw = (location.hash || "").replace(/^#\/?/, "");
	const [path, qs] = raw.split("?");
	const [name, ...rest] = (path || "").split("/");
	return {
		name: name || "",
		arg: rest.join("/"),
		query: Object.fromEntries(new URLSearchParams(qs || "")),
	};
}

export function logout() {
	stopAllTracking();
	session.clear();
	go("login", { replace: true });
}

async function render() {
	const seq = ++renderSeq;
	if (cleanup) {
		try {
			cleanup();
		} catch {
			/* */
		}
		cleanup = null;
	}
	document.querySelectorAll(".modal-back").forEach((n) => n.remove());

	let { name, arg, query } = parseHash();
	const loggedIn = session.isLoggedIn();
	if (!ROUTES[name]) name = loggedIn ? "home" : "login";
	if (!ROUTES[name].public && !loggedIn) name = "login";
	if (name === "login" && loggedIn) name = "home";
	const route = ROUTES[name];
	const wantHash = "#/" + name + (arg ? "/" + arg : "");
	if (!location.hash.startsWith(wantHash))
		window.history.replaceState(null, "", wantHash);

	appEl.innerHTML = "";
	const view = document.createElement("div");
	view.className = "view" + (route.tab ? "" : " no-tabs");
	appEl.appendChild(view);

	if (route.tab) {
		const nav = document.createElement("nav");
		nav.className = "tabbar";
		nav.innerHTML = TABS.map(
			(tb) =>
				`<a href="#/${tb.id}" class="${tb.id === name ? "active" : ""}" aria-label="${t(tb.label)}">${icon(tb.ic, 24)}<span>${t(tb.label)}</span></a>`,
		).join("");
		appEl.appendChild(nav);
	}
	window.scrollTo(0, 0);

	const ctx = {
		go,
		arg,
		query,
		view,
		logout,
		seq,
		isCurrent: () => seq === renderSeq,
	};
	const result = await route.fn(view, ctx);
	if (seq !== renderSeq) {
		if (typeof result === "function") result();
		return;
	}
	cleanup = typeof result === "function" ? result : null;
}

// ---- Event global ----
window.addEventListener("hashchange", render);
// Bahasa diganti (tombol ID | EN): perbarui label ribbon demo lalu gambar ulang layar aktif.
window.addEventListener("st:lang", () => {
	const rb = document.getElementById("demoRibbon");
	if (rb) rb.textContent = t("MODE DEMO");
	render();
});
window.addEventListener("st:unauthorized", () => {
	stopAllTracking();
	toast("Sesi berakhir, silakan masuk kembali");
	go("login", { replace: true });
});

// Dipanggil sekali per pembukaan aplikasi / kembali ke aplikasi: padanan MainActivity.onResume()
async function onAppActive() {
	if (!session.isLoggedIn()) return;
	const emp = await Employee.refresh();
	await Office.applyZone(emp, { fresh: true });
	await ensureEmployeeTracking();
	syncAll();
}
document.addEventListener("visibilitychange", () => {
	if (!document.hidden) onAppActive();
});

// Tick jam di UI yang butuh (mis. durasi) ditangani tiap layar. Di sini hanya boot:
const DEMO_ACCOUNTS = {
	employee: "ahmad@example.com",
	driver: "sopir@example.com",
};

// Tautan dari landing page: /app/?demo=1[&as=employee|driver] -> aktifkan MODE DEMO (data tiruan di browser)
// dan, bila 'as' diisi, langsung masuk dengan akun demo. Parameter dibersihkan dari URL setelahnya.
async function handleDemoLink() {
	const qs = new URLSearchParams(location.search);
	if (qs.get("demo") !== "1") return;
	settings.set({ demo: true });
	if (session.isLoggedIn() && !String(session.token).startsWith("demo-"))
		session.clear(); // sesi server asli tidak boleh bercampur dengan demo
	const as = qs.get("as");
	if (as && DEMO_ACCOUNTS[as] && !session.isLoggedIn()) {
		installDemoDevice();
		await Auth.login(DEMO_ACCOUNTS[as], "demo");
	}
	window.history.replaceState(
		null,
		"",
		location.pathname + (location.hash || ""),
	);
}

function showDemoRibbon() {
	if (!settings.get().demo || document.getElementById("demoRibbon")) return;
	const r = document.createElement("div");
	r.id = "demoRibbon";
	r.textContent = t("MODE DEMO");
	r.style.cssText =
		"position:fixed;top:env(safe-area-inset-top,0px);right:0;z-index:50;background:#f59e0b;color:#fff;font:700 10px/1 system-ui,sans-serif;letter-spacing:.08em;padding:4px 8px;border-radius:0 0 0 8px;pointer-events:none";
	document.body.appendChild(r);
}

(async function boot() {
	initLang();
	await handleDemoLink();
	if (settings.get().demo) installDemoDevice();
	showDemoRibbon();
	applyTheme();
	Time.setCode(session.data.tz || "WIB");
	if ("serviceWorker" in navigator && location.protocol !== "file:") {
		navigator.serviceWorker.register("sw.js").catch(() => {
			/* hosting tanpa HTTPS / dev */
		});
	}
	await render();
	onAppActive();
})();
