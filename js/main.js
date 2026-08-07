/**
 * ATSV Marktlauf Kirchseeon 2026 - Main JavaScript
 * Handles: Mobile Menu, Tabs, Language Switching (External JSON), and i18n content updates.
 */

document.addEventListener("DOMContentLoaded", async () => {
    initMobileMenu();
    initTabs();
    initHeaderScroll();
    initCountdown();
    initScrollAnimations();
    await hydrateSponsorMarquee();
    initSponsorMarquee();
    await initLanguage();
});

/**
 * Mobile Menu Logic
 */
function initMobileMenu() {
    const menuToggle = document.querySelector(".mobile-menu-toggle");
    const navLinks = document.querySelector(".nav-links");
    if (!menuToggle || !navLinks) return;

    let isActive = false;

    menuToggle.addEventListener("click", () => {
        isActive = !isActive;
        menuToggle.setAttribute("aria-expanded", isActive);
        menuToggle.setAttribute("aria-label", isActive ? "Menü schließen" : "Menü öffnen");
        navLinks.classList.toggle("active");
        document.body.style.overflow = isActive ? "hidden" : "";
    });

    document.querySelectorAll(".nav-link").forEach(link => {
        link.addEventListener("click", () => {
            isActive = false;
            menuToggle.setAttribute("aria-expanded", "false");
            menuToggle.setAttribute("aria-label", "Menü öffnen");
            navLinks.classList.remove("active");
            document.body.style.overflow = "";
        });
    });
}

/**
 * Tab Logic (Newsletter/Contact)
 */
function initTabs() {
    // Pro Tab-Gruppe scopen (.tab-nav + zugehörige .tab-content im selben Container),
    // damit mehrere Tab-Blöcke auf einer Seite sich nicht gegenseitig umschalten.
    document.querySelectorAll(".tab-nav").forEach(nav => {
        const scope = nav.parentElement;
        if (!scope) return;
        const btns = nav.querySelectorAll(".tab-btn");
        const contents = scope.querySelectorAll(".tab-content");
        if (!btns.length || !contents.length) return;

        btns.forEach(btn => {
            btn.addEventListener("click", () => {
                const target = btn.getAttribute("data-tab");

                btns.forEach(b => b.classList.remove("active"));
                contents.forEach(c => c.classList.remove("active"));

                btn.classList.add("active");
                scope.querySelector(`#tab-${target}`)?.classList.add("active");
            });
        });
    });
}

/**
 * Header Scroll Logic
 */
function initHeaderScroll() {
    const header = document.querySelector(".main-header");
    if (!header) return;

    window.addEventListener("scroll", () => {
        if (window.scrollY > 50) {
            header.classList.add("scrolled");
        } else {
            header.classList.remove("scrolled");
        }
    });
}

/**
 * Countdown Logic
 */
function initCountdown() {
    const countdownEl = document.getElementById("countdown");
    if (!countdownEl) return;

    const targetDate = new Date("2026-09-20T09:00:00").getTime();

    const interval = setInterval(() => {
        const now = new Date().getTime();
        const distance = targetDate - now;

        const days = Math.floor(distance / (1000 * 60 * 60 * 24));
        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);

        countdownEl.innerHTML = `
            <div>
                <div class="countdown-number">${days}</div>
                <div class="countdown-label">Tage</div>
            </div>
            <div>
                <div class="countdown-number">${hours}</div>
                <div class="countdown-label">Stunden</div>
            </div>
            <div>
                <div class="countdown-number">${minutes}</div>
                <div class="countdown-label">Minuten</div>
            </div>
            <div>
                <div class="countdown-number">${seconds}</div>
                <div class="countdown-label">Sekunden</div>
            </div>
        `;

        if (distance < 0) {
            clearInterval(interval);
            countdownEl.innerHTML = "<div class=\"countdown-ended\">Der Marktlauf ist vorbei!</div>";
        }
    }, 1000);
}

/**
 * Language & i18n Logic
 */
let currentLanguage = "de";
let translations = {};

async function initLanguage() {
    // 1. Determine initial language (default to "de", or check localStorage)
    const savedLang = localStorage.getItem("preferredLang");
    currentLanguage = savedLang || "de";

    // 2. Load translations and apply
    await loadTranslations(currentLanguage);
    applyTranslations();
    updateLanguageUI();

    // 3. Setup language toggle
    const langToggle = document.getElementById("lang-toggle");
    if (langToggle) {
        langToggle.addEventListener("click", async () => {
            const newLang = currentLanguage === "de" ? "en" : "de";
            await switchLanguage(newLang);
        });
    }
}

async function loadTranslations(lang) {
    try {
        const response = await fetch(`/lang/${lang}.json`);
        if (!response.ok) throw new Error(`Could not load ${lang}.json`);
        translations = await response.json();
    } catch (error) {
        console.error("Translation Error:", error);
        // Fallback to a minimal set or alert user
    }
}

async function switchLanguage(lang) {
    currentLanguage = lang;
    localStorage.setItem("preferredLang", lang);
    await loadTranslations(lang);
    applyTranslations();
    updateLanguageUI();
}

function getNestedValue(obj, keyPath) {
    return keyPath.split(".").reduce((acc, part) => acc && acc[part], obj) || null;
}

function updateMetaTags(translations) {
    const path = window.location.pathname.toLowerCase();
    let pageType = "index";
    
    if (path.includes("impressum")) {
        pageType = "impressum";
    } else if (path.includes("datenschutz")) {
        pageType = "datenschutz";
    }

    const titleKey = pageType === "index" ? "meta.title" : `legal.${pageType}.meta_title`;
    const descKey = pageType === "index" ? "meta.description" : `legal.${pageType}.meta_description`;

    const title = getNestedValue(translations, titleKey) || translations[titleKey];
    const desc = getNestedValue(translations, descKey) || translations[descKey];

    if (title) document.title = title;
    if (desc) {
        const metaDesc = document.querySelector("meta[name=\"description\"]");
        if (metaDesc) metaDesc.setAttribute("content", desc);
    }
}

function applyTranslations() {
    // Update all elements with data-i18n attribute
    document.querySelectorAll("[data-i18n]").forEach(el => {
        const key = el.getAttribute("data-i18n");
        if (translations[key]) {
            if (el.tagName === "INPUT" || el.tagName === "TEXTAREA") {
                el.placeholder = translations[key];
            } else {
                el.innerHTML = translations[key];
            }
        }
    });

    // Options in Select (special case if needed)
    const betreffSelect = document.getElementById("betreff");
    if (betreffSelect) {
        Array.from(betreffSelect.options).forEach(opt => {
            const key = opt.getAttribute("data-i18n");
            if (key && translations[key]) {
                opt.textContent = translations[key];
            }
        });
    }

    updateMetaTags(translations);

    // Update HTML lang attribute
    document.documentElement.lang = currentLanguage;
}

function updateLanguageUI() {
    const langFlag = document.getElementById("lang-flag");
    if (!langFlag) return;

    // If current is "de", show English flag (to switch to en)
    // If current is "en", show German flag (to switch to de)
    const flagSrc = currentLanguage === "de" 
        ? "/assets/images/anglais.png" 
        : "/assets/images/allemand.png";
    
    langFlag.src = flagSrc;
    langFlag.alt = currentLanguage === "de" ? "English" : "Deutsch";
}

/**
 * Scroll-Animationen via IntersectionObserver
 */
function initScrollAnimations() {
    if (typeof IntersectionObserver === 'undefined') return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    // Elemente die animiert werden sollen — mit optionaler Richtung und Staffelung
    const targets = [
        { sel: '#ueber .ueber-inner',        dir: '' },
        { sel: '#distanzen .run-card',        dir: '', stagger: true },
        { sel: '#ablauf .container',         dir: '' },
        { sel: '#strecke .container',        dir: '' },
        { sel: '#anmeldung .container',      dir: '' },
        { sel: '#connect .container',        dir: '' },
        { sel: '#sponsoren .container',      dir: '' },
    ];

    targets.forEach(function(t) {
        document.querySelectorAll(t.sel).forEach(function(el, i) {
            el.classList.add('reveal');
            if (t.dir) el.classList.add('reveal--' + t.dir);
            if (t.stagger) el.style.transitionDelay = (i * 80) + 'ms';
        });
    });

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });

    document.querySelectorAll('.reveal').forEach(function(el) {
        observer.observe(el);
    });
}

/**
 * Sponsor-Marquee: laeuft von selbst und laesst sich mit der Maus schieben
 *
 * Die CSS-Keyframe-Animation ist der No-JS-Fallback. Sobald diese Funktion
 * greift, uebernimmt sie per scrollLeft — dadurch funktionieren Maus-Drag,
 * Trackpad, Mausrad und Touch-Swipe mit demselben Mechanismus.
 * Der Sprung bei der halben Track-Breite ist unsichtbar, weil das Markup
 * den Logo-Satz doppelt enthaelt (zweiter Satz aria-hidden).
 */
/**
 * Fuellt das Sponsoren-Laufband datengetrieben aus data/sponsoren.json (aus dem
 * Dashboard gepflegt). Ist der Feed leer, fehlt oder faellt der Abruf aus, bleibt
 * der hartcodierte Fallback im Markup stehen — die Rotation ist also nie leer.
 * Baut den Original-Satz + einen aria-hidden-Duplikat-Satz auf, wie es
 * initSponsorMarquee erwartet (dieser misst perSet an den nicht-aria-Items).
 */
async function hydrateSponsorMarquee() {
    const track = document.querySelector(".sponsor-marquee-track");
    if (!track) return;

    let sponsors;
    try {
        const res = await fetch("data/sponsoren.json", { cache: "no-cache" });
        if (!res.ok) return;                 // kein Feed -> Fallback bleibt
        sponsors = await res.json();
    } catch (e) {
        return;                              // Netzwerk-/Parsefehler -> Fallback bleibt
    }
    if (!Array.isArray(sponsors) || sponsors.length === 0) return;

    const makeItem = (s, hidden) => {
        const item = document.createElement("div");
        item.className = "sponsor-item";
        if (hidden) item.setAttribute("aria-hidden", "true");

        const img = document.createElement("img");
        img.src = s.logo;
        img.loading = "lazy";
        img.alt = hidden ? "" : (s.name || "");

        let node = img;
        if (s.url) {
            const a = document.createElement("a");
            a.href = s.url;
            a.target = "_blank";
            a.rel = "noopener noreferrer";
            if (hidden) a.setAttribute("tabindex", "-1");
            else a.title = s.name || "";
            a.appendChild(img);
            node = a;
        }
        item.appendChild(node);
        return item;
    };

    track.innerHTML = "";
    sponsors.forEach((s) => track.appendChild(makeItem(s, false)));
    sponsors.forEach((s) => track.appendChild(makeItem(s, true)));
}

function initSponsorMarquee() {
    const wrap = document.querySelector('.sponsor-marquee-wrap');
    const track = wrap && wrap.querySelector('.sponsor-marquee-track');
    if (!wrap || !track) return;

    const SPEED = 28;          // Sekunden pro Halbrunde — wie die CSS-Animation
    const RESUME_DELAY = 2000; // ms Ruhe nach einem Drag, bevor es weiterlaeuft
    const DRAG_THRESHOLD = 5;  // px, ab dann gilt es als Ziehen und nicht als Klick

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    wrap.classList.add('is-draggable');

    // Ein "Satz" ist die Logo-Folge, die sich wiederholt — im Markup liegt
    // sie doppelt vor. perSet wird vor dem Klonen bestimmt, denn die Kopien
    // tragen alle aria-hidden.
    const perSet = track.querySelectorAll('.sponsor-item:not([aria-hidden])').length;
    let period = 0;

    // Abstand zwischen einem Item und seinem Gegenstueck im naechsten Satz.
    // Bewusst ueber offsetLeft und nicht scrollWidth/2: der Abstand enthaelt
    // die Luecke zwischen den Saetzen, scrollWidth/2 nicht.
    function measurePeriod() {
        const items = track.children;
        if (perSet > 0 && items.length > perSet) {
            period = items[perSet].offsetLeft - items[0].offsetLeft;
        }
    }

    // scrollLeft begrenzt der Browser auf scrollWidth - clientWidth. Ist der
    // Track nicht mindestens period + clientWidth breit, liegt die
    // Sprungmarke hinter dem Anschlag und der Durchlauf bleibt dort stehen.
    // Auf breiten Monitoren reichen zwei Saetze nicht — dann weitere anhaengen.
    function ensureWidth() {
        if (perSet <= 0 || period <= 0) return;
        const base = Array.prototype.slice.call(track.children, 0, perSet);
        let guard = 0;
        while (track.scrollWidth - wrap.clientWidth < period && guard++ < 12) {
            base.forEach(function(item) {
                const copy = item.cloneNode(true);
                copy.setAttribute('aria-hidden', 'true');
                copy.querySelectorAll('a').forEach(function(a) { a.setAttribute('tabindex', '-1'); });
                copy.querySelectorAll('img').forEach(function(img) { img.setAttribute('alt', ''); });
                track.appendChild(copy);
            });
        }
    }

    function measure() {
        measurePeriod();
        ensureWidth();
    }
    measure();
    window.addEventListener('resize', measure);
    // Bilder liefern ihre Maße teils erst nach dem Laden nach
    window.addEventListener('load', measure);

    // Haelt scrollLeft im ersten Satz — die Position wirkt dadurch endlos.
    // Beim Ruecksprung bewusst auf period-1 statt +period: landete er genau
    // auf period, wuerde die Vorwaerts-Bedingung sofort wieder greifen und
    // die Position in jedem Frame zwischen 0 und period hin- und herkippen.
    function normalize() {
        if (period <= 0) return;
        if (wrap.scrollLeft >= period) wrap.scrollLeft -= period;
        else if (wrap.scrollLeft <= 0) wrap.scrollLeft = period - 1;
    }

    let hovering = false;
    let dragging = false;
    let touching = false;
    let resumeAt = 0;
    let startX = 0;
    let startScroll = 0;
    let moved = 0;
    let last = 0;
    let pos = 0;   // Autoplay-Position als Fliesskommawert, siehe frame()

    function running(now) {
        return !dragging && !touching && !hovering && !document.hidden &&
               !reduceMotion.matches && now >= resumeAt;
    }

    function frame(now) {
        const dt = last ? Math.min((now - last) / 1000, 0.1) : 0;
        last = now;
        if (running(now) && period > 0) {
            // Position getrennt mitfuehren und erst dann zuweisen. Ein
            // "wrap.scrollLeft += x" verliert den Bruchteil bei jedem Frame,
            // sobald ein Browser scrollLeft auf ganze Pixel rundet — Safari
            // tut das. Bei 120 Hz waeren das 0,5 px pro Frame, die restlos
            // verworfen werden: der Durchlauf stand dort komplett still.
            pos += (period / SPEED) * dt;
            if (pos >= period) pos -= period;
            else if (pos < 0) pos += period;
            wrap.scrollLeft = pos;
        } else {
            // Hier fuehrt der Nutzer — seine Position uebernehmen
            normalize();
            pos = wrap.scrollLeft;
        }
        requestAnimationFrame(frame);
    }
    requestAnimationFrame(frame);

    wrap.addEventListener('mouseenter', function() { hovering = true; });
    wrap.addEventListener('mouseleave', function() { hovering = false; });

    wrap.addEventListener('pointerdown', function(e) {
        if (e.pointerType === 'mouse' && e.button !== 0) return;
        moved = 0;
        // Touch dem Browser lassen: der entscheidet die Achse und haelt
        // damit das vertikale Seiten-Scrollen frei
        if (e.pointerType === 'touch') { touching = true; return; }
        dragging = true;
        startX = e.clientX;
        startScroll = wrap.scrollLeft;
        wrap.classList.add('is-dragging');
        // Schlaegt der Capture fehl, laeuft das Ziehen ueber die normale
        // Event-Bubbling-Kette weiter — nur nicht ausserhalb des Elements
        try { wrap.setPointerCapture(e.pointerId); } catch (err) { /* ignore */ }
    });

    wrap.addEventListener('pointermove', function(e) {
        if (!dragging) return;
        const dx = e.clientX - startX;
        moved = Math.max(moved, Math.abs(dx));
        wrap.scrollLeft = startScroll - dx;
        normalize();
    });

    function endDrag(e) {
        resumeAt = performance.now() + RESUME_DELAY;
        if (e.pointerType === 'touch') { touching = false; return; }
        if (!dragging) return;
        dragging = false;
        wrap.classList.remove('is-dragging');
    }
    wrap.addEventListener('pointerup', endDrag);
    wrap.addEventListener('pointercancel', endDrag);

    // Ein Drag ueber ein Logo darf den Sponsor-Link nicht oeffnen
    wrap.addEventListener('click', function(e) {
        if (moved > DRAG_THRESHOLD) {
            e.preventDefault();
            e.stopPropagation();
            moved = 0;
        }
    }, true);

    // Natives Bild-Drag&Drop unterdruecken
    wrap.addEventListener('dragstart', function(e) { e.preventDefault(); });

    // Nach Mausrad/Trackpad kurz Ruhe geben, dann weiterlaufen
    wrap.addEventListener('wheel', function() {
        resumeAt = performance.now() + RESUME_DELAY;
    }, { passive: true });
}
