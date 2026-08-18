/**
 * ATSV Marktlauf Kirchseeon – Consent & Conversion-Tracking
 * ---------------------------------------------------------
 * DSGVO-konformes Opt-in-Tracking (Google Analytics 4 / Ads) mit
 * Google Consent Mode v2. STRIKT: gtag wird erst NACH aktiver
 * Zustimmung geladen – vorher kein Cookie, kein Netzwerk-Hit.
 *
 * >>> AKTIVIERUNG <<<
 * Trage unten bei GA_MEASUREMENT_ID die echte GA4-Mess-ID ein
 * (Format "G-XXXXXXXXXX"). Solange das Feld leer ist, passiert
 * NICHTS: kein Banner, kein Tracking – die Seite bleibt unveraendert.
 *
 * Optional Google Ads: ADS_CONVERSION_ID = "AW-XXXXXXXXX" setzen,
 * dann werden Conversions zusaetzlich an Ads gemeldet.
 */
(function () {
  "use strict";

  // ===== Konfiguration =====
  var GA_MEASUREMENT_ID = ""; // z. B. "G-XXXXXXXXXX" – HIER eintragen
  var ADS_CONVERSION_ID = ""; // optional, z. B. "AW-XXXXXXXXX"
  var STORAGE_KEY = "ml_consent"; // "granted" | "denied"
  var LANG_KEY = "preferredLang"; // gleiche Quelle wie js/main.js

  // Wenn keine Mess-ID hinterlegt ist: komplett inaktiv bleiben.
  if (!GA_MEASUREMENT_ID) {
    // Trotzdem eine Stub-API bereitstellen, damit Aufrufe auf anderen
    // Seiten (z. B. mlTrack in contact.php) keine Fehler werfen.
    window.mlTrack = function () {};
    window.mlOpenConsent = function () {};
    return;
  }

  // ===== i18n (self-contained, damit es auf allen Seiten funktioniert) =====
  var I18N = {
    de: {
      text: "Wir nutzen Google Analytics, um die Nutzung unserer Seite zu verstehen und zu verbessern. Das ist nur mit deiner Einwilligung aktiv. Details in der ",
      privacy: "Datenschutzerklärung",
      accept: "Zustimmen",
      decline: "Ablehnen",
      settings: "Cookie-Einstellungen",
      aria: "Einwilligung zu Analyse-Cookies",
    },
    en: {
      text: "We use Google Analytics to understand and improve how our site is used. This is only active with your consent. Details in our ",
      privacy: "privacy policy",
      accept: "Accept",
      decline: "Decline",
      settings: "Cookie settings",
      aria: "Consent for analytics cookies",
    },
  };

  function lang() {
    try {
      var l = localStorage.getItem(LANG_KEY);
      return l === "en" ? "en" : "de";
    } catch (e) {
      return "de";
    }
  }
  function t(key) {
    var l = lang();
    return (I18N[l] && I18N[l][key]) || I18N.de[key];
  }

  // ===== Consent-Speicher =====
  function getStored() {
    try {
      return localStorage.getItem(STORAGE_KEY);
    } catch (e) {
      return null;
    }
  }
  function setStored(v) {
    try {
      localStorage.setItem(STORAGE_KEY, v);
    } catch (e) {}
  }

  // ===== gtag / Consent Mode v2 =====
  window.dataLayer = window.dataLayer || [];
  function gtag() {
    window.dataLayer.push(arguments);
  }

  var gtagLoaded = false;
  var eventQueue = [];

  function loadGtag() {
    if (gtagLoaded) return;
    gtagLoaded = true;

    // Consent Mode v2: erst alles denied als Default …
    gtag("consent", "default", {
      ad_storage: "denied",
      analytics_storage: "denied",
      ad_user_data: "denied",
      ad_personalization: "denied",
    });
    // … dann nach erteilter Einwilligung auf granted heben.
    gtag("consent", "update", {
      ad_storage: ADS_CONVERSION_ID ? "granted" : "denied",
      analytics_storage: "granted",
      ad_user_data: ADS_CONVERSION_ID ? "granted" : "denied",
      ad_personalization: "denied",
    });

    var s = document.createElement("script");
    s.async = true;
    s.src = "https://www.googletagmanager.com/gtag/js?id=" + encodeURIComponent(GA_MEASUREMENT_ID);
    document.head.appendChild(s);

    gtag("js", new Date());
    gtag("config", GA_MEASUREMENT_ID, { anonymize_ip: true });
    if (ADS_CONVERSION_ID) {
      gtag("config", ADS_CONVERSION_ID);
    }

    // Aufgestaute Conversion-Events nachfeuern.
    for (var i = 0; i < eventQueue.length; i++) {
      gtag("event", eventQueue[i].name, eventQueue[i].params || {});
    }
    eventQueue = [];
  }

  // ===== Oeffentliche Track-API =====
  // Feuert ein Event nur, wenn Einwilligung vorliegt (sonst verworfen).
  window.mlTrack = function (name, params) {
    if (!name) return;
    if (getStored() === "granted") {
      if (gtagLoaded) {
        gtag("event", name, params || {});
      } else {
        eventQueue.push({ name: name, params: params });
        loadGtag();
      }
    }
    // Kein Consent -> bewusst verworfen (kein Tracking ohne Einwilligung).
  };

  // ===== Banner-UI =====
  var STYLE_ID = "ml-consent-style";
  function injectStyle() {
    if (document.getElementById(STYLE_ID)) return;
    var css =
      ".ml-consent{position:fixed;left:50%;bottom:16px;transform:translateX(-50%);" +
      "z-index:99999;width:min(680px,calc(100% - 24px));background:#fff;color:#1f2937;" +
      "border:1px solid #e5e7eb;border-radius:14px;box-shadow:0 10px 40px rgba(0,0,0,.18);" +
      "padding:18px 20px;font-family:'Inter',system-ui,-apple-system,sans-serif;font-size:.95rem;line-height:1.55}" +
      ".ml-consent p{margin:0 0 14px}" +
      ".ml-consent a{color:#009640;font-weight:600;text-decoration:underline}" +
      ".ml-consent-actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end;align-items:center}" +
      ".ml-consent-btn{cursor:pointer;border:none;border-radius:8px;padding:.6rem 1.15rem;font-weight:600;font-size:.95rem;font-family:inherit}" +
      ".ml-consent-accept{background:#009640;color:#fff}" +
      ".ml-consent-accept:hover{background:#007230}" +
      ".ml-consent-decline{background:#f1f5f9;color:#1f2937}" +
      ".ml-consent-decline:hover{background:#e2e8f0}" +
      "@media(max-width:480px){.ml-consent-actions{justify-content:stretch}.ml-consent-btn{flex:1}}";
    var st = document.createElement("style");
    st.id = STYLE_ID;
    st.textContent = css;
    document.head.appendChild(st);
  }

  var bannerEl = null;
  function buildBanner() {
    injectStyle();
    var wrap = document.createElement("div");
    wrap.className = "ml-consent";
    wrap.setAttribute("role", "dialog");
    wrap.setAttribute("aria-live", "polite");
    wrap.setAttribute("aria-label", t("aria"));

    var p = document.createElement("p");
    p.appendChild(document.createTextNode(t("text")));
    var a = document.createElement("a");
    a.href = "datenschutz.html";
    a.textContent = t("privacy");
    p.appendChild(a);
    p.appendChild(document.createTextNode("."));

    var actions = document.createElement("div");
    actions.className = "ml-consent-actions";

    var decline = document.createElement("button");
    decline.type = "button";
    decline.className = "ml-consent-btn ml-consent-decline";
    decline.textContent = t("decline");
    decline.addEventListener("click", function () {
      setStored("denied");
      removeBanner();
    });

    var accept = document.createElement("button");
    accept.type = "button";
    accept.className = "ml-consent-btn ml-consent-accept";
    accept.textContent = t("accept");
    accept.addEventListener("click", function () {
      setStored("granted");
      removeBanner();
      loadGtag();
    });

    actions.appendChild(decline);
    actions.appendChild(accept);
    wrap.appendChild(p);
    wrap.appendChild(actions);
    return wrap;
  }

  function showBanner() {
    if (bannerEl) return;
    bannerEl = buildBanner();
    document.body.appendChild(bannerEl);
  }
  function removeBanner() {
    if (bannerEl && bannerEl.parentNode) {
      bannerEl.parentNode.removeChild(bannerEl);
    }
    bannerEl = null;
  }

  // Erneut oeffnen (z. B. Link "Cookie-Einstellungen" im Footer/Datenschutz).
  window.mlOpenConsent = function () {
    removeBanner();
    showBanner();
  };

  // ===== Init =====
  function init() {
    // data-mltrack="event" -> Klick feuert Conversion
    var clickEls = document.querySelectorAll("[data-mltrack]");
    Array.prototype.forEach.call(clickEls, function (el) {
      el.addEventListener("click", function () {
        window.mlTrack(el.getAttribute("data-mltrack"));
      });
    });
    // data-mltrack-onload="event" -> beim Laden feuern (Danke-/Erfolgsseiten)
    var onloadEl = document.querySelector("[data-mltrack-onload]");
    if (onloadEl) {
      window.mlTrack(onloadEl.getAttribute("data-mltrack-onload"));
    }

    var stored = getStored();
    if (stored === "granted") {
      loadGtag(); // Zustimmung liegt bereits vor
    } else if (stored === "denied") {
      // nichts tun
    } else {
      showBanner(); // noch keine Entscheidung
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
