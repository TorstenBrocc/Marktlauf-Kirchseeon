/**
 * Marktlauf Kirchseeon 2026 - Main JavaScript
 * Handles: Mobile Menu, Tabs, Language Switching (External JSON), and i18n content updates.
 */

document.addEventListener('DOMContentLoaded', async () => {
    initMobileMenu();
    initTabs();
    await initLanguage();
});

/**
 * Mobile Menu Logic
 */
function initMobileMenu() {
    const menuToggle = document.querySelector('.mobile-menu-toggle');
    const navLinks = document.querySelector('.nav-links');
    if (!menuToggle || !navLinks) return;

    let isActive = false;

    menuToggle.addEventListener('click', () => {
        isActive = !isActive;
        menuToggle.setAttribute('aria-expanded', isActive);
        menuToggle.setAttribute('aria-label', isActive ? 'Menü schließen' : 'Menü öffnen');
        navLinks.classList.toggle('active');
    });

    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', () => {
            isActive = false;
            menuToggle.setAttribute('aria-expanded', 'false');
            menuToggle.setAttribute('aria-label', 'Menü öffnen');
            navLinks.classList.remove('active');
        });
    });
}

/**
 * Tab Logic (Newsletter/Contact)
 */
function initTabs() {
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    if (!tabBtns.length || !tabContents.length) return;

    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const target = btn.getAttribute('data-tab');

            tabBtns.forEach(b => b.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));

            btn.classList.add('active');
            document.getElementById(`tab-${target}`)?.classList.add('active');
        });
    });
}

/**
 * Language & i18n Logic
 */
let currentLanguage = 'de';
let translations = {};

async function initLanguage() {
    // 1. Determine initial language (default to 'de', or check localStorage)
    const savedLang = localStorage.getItem('preferredLang');
    currentLanguage = savedLang || 'de';

    // 2. Load translations and apply
    await loadTranslations(currentLanguage);
    applyTranslations();
    updateLanguageUI();

    // 3. Setup language toggle
    const langToggle = document.getElementById('lang-toggle');
    if (langToggle) {
        langToggle.addEventListener('click', async () => {
            const newLang = currentLanguage === 'de' ? 'en' : 'de';
            await switchLanguage(newLang);
        });
    }
}

async function loadTranslations(lang) {
    try {
        const response = await fetch(`lang/${lang}.json`);
        if (!response.ok) throw new Error(`Could not load ${lang}.json`);
        translations = await response.json();
    } catch (error) {
        console.error('Translation Error:', error);
        // Fallback to a minimal set or alert user
    }
}

async function switchLanguage(lang) {
    currentLanguage = lang;
    localStorage.setItem('preferredLang', lang);
    await loadTranslations(lang);
    applyTranslations();
    updateLanguageUI();
}

function applyTranslations() {
    // Update all elements with data-i18n attribute
    document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        if (translations[key]) {
            if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                el.placeholder = translations[key];
            } else {
                el.innerHTML = translations[key];
            }
        }
    });

    // Update Page Title and Meta Description
    if (translations['meta.title']) document.title = translations['meta.title'];
    if (translations['meta.description']) {
        const metaDesc = document.querySelector('meta[name="description"]');
        if (metaDesc) metaDesc.setAttribute('content', translations['meta.description']);
    }

    // Update HTML lang attribute
    document.documentElement.lang = currentLanguage;
}

function updateLanguageUI() {
    const langFlag = document.getElementById('lang-flag');
    if (!langFlag) return;

    // If current is 'de', show English flag (to switch to en)
    // If current is 'en', show German flag (to switch to de)
    const flagSrc = currentLanguage === 'de' 
        ? 'assets/images/anglais.png' 
        : 'assets/images/allemand.png';
    
    langFlag.src = flagSrc;
    langFlag.alt = currentLanguage === 'de' ? 'English' : 'Deutsch';
}