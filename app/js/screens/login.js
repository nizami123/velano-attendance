import { settings } from "../store.js";
import { Auth } from "../repo.js";
import { icon, esc, $, isIOS, isStandalone } from "../util.js";
import { ensureEmployeeTracking } from "../tracker.js";
import { CONFIG } from "../config.js";
import { installDemoDevice, uninstallDemoDevice } from "../demo.js";
import { t, langSwitchHtml, bindLangSwitch } from "../i18n.js";

export function login(view, ctx) {
	const st = settings.get();
	const showIOSHint = isIOS() && !isStandalone();

	view.className = "";
	view.innerHTML = `
  <div class="login">
    <div class="langbar">${langSwitchHtml()}</div>
    <img class="logo" src="icons/logo.png" alt="Logo">
    <h1>${esc(CONFIG.APP_NAME)}</h1>
    <p class="tag">${esc(t("Absensi & Tracking Karyawan"))}</p>

    ${
			showIOSHint
				? `
    <div class="banner" style="margin:0 0 16px">
      ${icon("share", 20)}
      <div><b>${t("Pasang di iPhone")}</b>
        <ol class="ios-steps">
          <li>${t("Ketuk tombol <b>Bagikan</b> (kotak berpanah) di Safari")}</li>
          <li>${t("Pilih <b>Tambah ke Layar Utama</b>")}</li>
          <li>${t("Buka aplikasi dari ikon di layar utama")}</li>
        </ol>
      </div>
    </div>`
				: ""
		}

    <h2>${t("Masuk ke Akun Anda")}</h2>
    <form id="f" autocomplete="on" novalidate>
      <div class="field">
        <label for="email">${t("Email / Username")}</label>
        <input class="input" id="email" name="email" type="text" inputmode="email" autocomplete="username"
               autocapitalize="none" autocorrect="off" spellcheck="false" placeholder="${t('masukkan email atau username')}">
      </div>
      <div class="field">
        <label for="pw">${t("Password")}</label>
        <div class="input-wrap">
          <input class="input" id="pw" name="password" type="password" autocomplete="current-password" placeholder="${t('masukkan password')}">
          <button type="button" class="icon-btn" id="eye" aria-label="${t('Tampilkan password')}">${icon("eyeoff", 20)}</button>
        </div>
      </div>
      <div id="err" class="error hidden" role="alert"></div>
      <button class="btn primary block" id="go" type="submit">${t("MASUK")}</button>
    </form>
    <p class="foot">${t("Belum punya akun? Hubungi admin")}</p>

    <details class="adv" ${st.demo ? "open" : ""}>
      <summary>${t("Pengaturan lanjutan")}</summary>
      <label class="row gap" style="margin:8px 0"><span class="switch"><input type="checkbox" id="demo" ${st.demo ? "checked" : ""}><i></i></span>
        <span>${t("Mode demo (data tiruan, tanpa server)")}</span></label>
      <div id="demoHint" class="small muted ${st.demo ? "" : "hidden"}">${t("Akun demo: <b>ahmad@example.com</b> (karyawan) atau <b>sopir@example.com</b> (driver), password <b>demo</b>.")}</div>
      <div class="field mt8 ${st.demo ? "hidden" : ""}" id="srvWrap"><label for="srv">${t("Alamat server")}</label>
        <input class="input" id="srv" type="url" inputmode="url" autocapitalize="none" value="${esc(st.apiBase || CONFIG.API_BASE)}" placeholder="https://..."></div>
    </details>
  </div>`;

	bindLangSwitch(view);

	const email = $("#email", view),
		pw = $("#pw", view),
		err = $("#err", view),
		btn = $("#go", view);
	if (st.demo && !email.value) {
		email.value = "ahmad@example.com";
		pw.value = "demo";
	}

	let shown = false;
	$("#eye", view).addEventListener("click", () => {
		shown = !shown;
		pw.type = shown ? "text" : "password";
		$("#eye", view).innerHTML = icon(shown ? "eye" : "eyeoff", 20);
	});

	$("#demo", view).addEventListener("change", (e) => {
		settings.set({ demo: e.target.checked });
		if (e.target.checked) installDemoDevice(); else uninstallDemoDevice();
		const rb = document.getElementById("demoRibbon");
		if (rb) rb.style.display = e.target.checked ? "" : "none";
		$("#demoHint", view).classList.toggle("hidden", !e.target.checked);
		$("#srvWrap", view).classList.toggle("hidden", e.target.checked);
		if (e.target.checked) {
			email.value = "ahmad@example.com";
			pw.value = "demo";
		} else {
			pw.value = "";
		}
	});
	$("#srv", view).addEventListener("change", (e) => {
		let v = e.target.value.trim();
		if (v && !v.endsWith("/")) v += "/";
		settings.set({ apiBase: v === CONFIG.API_BASE ? "" : v });
	});

	$("#f", view).addEventListener("submit", async (ev) => {
		ev.preventDefault();
		err.classList.add("hidden");
		const e = email.value.trim(),
			p = pw.value;
		if (!e || !p) {
			err.textContent = t("Email dan password wajib diisi");
			err.classList.remove("hidden");
			return;
		}
		btn.disabled = true;
		btn.innerHTML = '<span class="spinner"></span> ' + t("Memproses...");
		const res = await Auth.login(e, p);
		if (res.ok) {
			settings.set({ email: e });
			ensureEmployeeTracking(); // dari gesture pengguna: iOS boleh menampilkan dialog izin lokasi
			ctx.go("home");
		} else {
			btn.disabled = false;
			btn.textContent = t("MASUK");
			err.textContent = res.message;
			err.classList.remove("hidden");
		}
	});
}
