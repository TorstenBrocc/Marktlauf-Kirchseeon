/**
 * ATSV Marktlauf Kirchseeon 2026 - Main JavaScript
 * Handles: Mobile Menu, Tabs, Language Switching (External JSON), and i18n content updates.
 */

document.addEventListener("DOMContentLoaded", async () => {
    initMobileMenu();
    initTabs();
    initHeaderScroll();
    initCountdown();
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
    const tabBtns = document.querySelectorAll(".tab-btn");
    const tabContents = document.querySelectorAll(".tab-content");
    if (!tabBtns.length || !tabContents.length) return;

    tabBtns.forEach(btn => {
        btn.addEventListener("click", () => {
            const target = btn.getAttribute("data-tab");

            tabBtns.forEach(b => b.classList.remove("active"));
            tabContents.forEach(c => c.classList.remove("active"));

            btn.classList.add("active");
            document.getElementById(`tab-${target}`)?.classList.add("active");
        });
    });
}

/**
 * Header Scroll Logic - disabled, header always white
 */
function initHeaderScroll() {
    // No longer needed - header is always in "scrolled" state
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
