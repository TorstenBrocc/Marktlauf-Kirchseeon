/**
 * Marktlauf Kirchseeon 2026 - Main JavaScript
 * Handles UI interactions and mobile navigation
 */

document.addEventListener('DOMContentLoaded', () => {
    initMobileMenu();
    initSmoothScroll();
    initMapLifecycle();
    initContactForm();
    initNewsletterForm();
    initLanguageSwitcher();
    initTabSwitcher();
});

/**
 * Mobile Menu Toggle Logic
 */
function initMobileMenu() {
    const menuToggle = document.querySelector('.mobile-menu-toggle');
    const navLinks = document.querySelector('.nav-links');

    if (!menuToggle || !navLinks) return;

    menuToggle.addEventListener('click', () => {
        const isActive = navLinks.classList.toggle('active');
        
        // Update accessibility attribute
        menuToggle.setAttribute('aria-expanded', isActive);
        menuToggle.setAttribute('aria-label', isActive ? 'Menü schließen' : 'Menü öffnen');
    });

    // Close menu when clicking a link
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', () => {
            navLinks.classList.remove('active');
            menuToggle.setAttribute('aria-expanded', 'false');
            menuToggle.setAttribute('aria-label', 'Menü öffnen');
        });
    });
}

/**
 * Language Switching Logic
 */
function initTabSwitcher() {
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    if (!tabBtns.length || !tabContents.length) return;

    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const targetTab = btn.getAttribute('data-tab');

            // Update active button
            tabBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            // Update active content
            tabContents.forEach(content => {
                content.classList.remove('active');
                if (content.id === `tab-${targetTab}`) {
                    content.classList.add('active');
                }
            });
        });
    });
}

function initLanguageSwitcher() {
    const translations = {
        de: {
            'meta.title': 'Marktlauf Kirchseeon 2026 | ATS Kirchseeon e.V.',
            'meta.description': 'Marktlauf Kirchseeon 2026 - Ein sportliches Event für die ganze Familie. Jetzt anmelden!',
            'nav.laeufe': 'Läufe',
            'nav.zeitplan': 'Zeitplan',
            'nav.strecke': 'Strecke',
            'nav.sponsoren': 'Sponsoren',
            'nav.kontakt': 'Kontakt',
            'nav.anmeldung': 'Jetzt anmelden',
            'hero.badge': 'ATSV Kirchseeon präsentiert zusammen mit der Gemeinde',
            'hero.title': 'Marktlauf <br><span class="text-highlight">Kirchseeon 2026</span>',
            'hero.date': '20. September 2026',
            'hero.cta_primary': 'Jetzt anmelden',
            'hero.cta_secondary': 'Alle Läufe',
            'distanzen.title': 'Die Läufe',
            'distanzen.bambini.category': 'Kinder',
            'distanzen.bambini.desc': 'Der perfekte Einstieg für die kleinsten Sportler. Ein kurzer, spannender Lauf für alle Bambinis.',
            'distanzen.schueler.category': 'Jugend',
            'distanzen.schueler.desc': 'Herausforderungen für Schüler in zwei verschiedenen Distanzen, um die eigenen Grenzen zu testen.',
            'distanzen.elite.category': 'Erwachsene',
            'distanzen.elite.desc': 'Die Königsdisziplinen für ambitionierte Läufer und Hobbysportler. Wer holt sich den Sieg in Kirchseeon?',
            'ablauf.title': 'Zeitplan',
            'ablauf.time1': '09:00 Uhr',
            'ablauf.event1': 'Bambini Lauf (500m)',
            'ablauf.time2': '09:30 Uhr',
            'ablauf.event2': 'Schüler Läufe (1000m & 2000m)',
            'ablauf.time3': '10:00 Uhr',
            'ablauf.event3': 'Elite Läufe (5km & 10km)',
            'ablauf.time4': '11:00 Uhr',
            'ablauf.event4': 'Siegerehrung Bambini und Schüler',
            'ablauf.time5': '12:00 Uhr',
            'ablauf.event5': 'Siegerehrung Elite',
            'strecke.title': 'Start & Ziel',
            'strecke.address': 'Start und Ziel befinden sich am Westring 6, 85614 Kirchseeon.',
            'strecke.routes_title': 'Die Strecken',
            'strecke.route1': 'Bambini Lauf (500m)',
            'strecke.route2': 'Schüler Lauf (1km/2km)',
            'strecke.route3': 'Elite Lauf (5km/10km)',
            'strecke.gpx_download': 'GPX Download',
            'anmeldung.title': 'Anmeldung',
            'anmeldung.text': 'Sichere dir jetzt deinen Startplatz für den Marktlauf 2026!',
            'anmeldung.placeholder_title': 'RaceResult Anmeldung',
            'anmeldung.placeholder_text': 'Hier wird das RaceResult-Anmelde-Snippet eingebunden.',
            'anmeldung.placeholder_note': '(Bitte RaceResult-Iframe/Script hier einfügen)',
            'sponsoren.title': 'Unsere Sponsoren',
            'kontakt.title': 'Schreiben Sie uns – wir helfen Ihnen gerne weiter!',
            'kontakt.faq_text': 'Haben Sie eine schnelle Frage? ',
            'kontakt.faq_link': 'Besuchen Sie unsere FAQ',
            'kontakt.form.vorname': 'Vorname *',
            'kontakt.form.nachname': 'Nachname *',
            'kontakt.form.email': 'E-Mail-Adresse *',
            'kontakt.form.betreff': 'Betreff / Anliegen *',
            'kontakt.form.betreff_placeholder': 'Bitte wählen Sie ein Thema aus',
            'kontakt.form.opt_anmeldung': 'Fragen zur Anmeldung / Startgebühr',
            'kontakt.form.opt_strecke': 'Streckenverlauf & Zeitnahme',
            'kontakt.form.opt_sponsoring': 'Sponsoring & Partner',
            'kontakt.form.opt_helfer': 'Helfer / Volunteers',
            'kontakt.form.opt_sonstiges': 'Sonstiges',
            'kontakt.form.nachricht': 'Nachricht *',
            'kontakt.form.privacy': 'Ich habe die Datenschutzerklärung gelesen und stimme der Verarbeitung meiner Daten zu. *',
            'kontakt.captcha': '[Spamschutz: Friendly Captcha / reCAPTCHA Integration]',
            'kontakt.form.submit': 'Nachricht senden',
            'footer.title': 'Marktlauf 2026',
            'footer.text': 'Ein sportliches Highlight in Kirchseeon für die ganze Familie. Wir freuen uns auf euch!',
            'footer.links_title': 'Quick Links',
            'footer.legal_title': 'Rechtliches',
            'footer.legal.impressum': 'Impressum',
            'footer.legal.privacy': 'Datenschutz',
            'footer.copyright': '© 2026 ATS Kirchseeon e.V. | Alle Rechte vorbehalten.'
        },
        en: {
            'meta.title': 'Kirchseeon Town Run 2026 | ATS Kirchseeon e.V.',
            'meta.description': 'Kirchseeon Town Run 2026 - A sporting event for the whole family. Register now!',
            'nav.laeufe': 'Runs',
            'nav.zeitplan': 'Schedule',
            'nav.strecke': 'Route',
            'nav.sponsoren': 'Sponsors',
            'nav.kontakt': 'Contact',
            'nav.anmeldung': 'Register Now',
            'hero.badge': 'ATSV Kirchseeon presents together with the municipality',
            'hero.title': 'Town Run <br><span class="text-highlight">Kirchseeon 2026</span>',
            'hero.date': 'September 20, 2026',
            'hero.cta_primary': 'Register Now',
            'hero.cta_secondary': 'All Runs',
            'distanzen.title': 'The Runs',
            'distanzen.bambini.category': 'Children',
            'distanzen.bambini.desc': 'The perfect start for the youngest athletes. A short, exciting run for all bambinis.',
            'distanzen.schueler.category': 'Youth',
            'distanzen.schueler.desc': 'Challenges for students in two different distances to test their own limits.',
            'distanzen.elite.category': 'Adults',
            'distanzen.elite.desc': 'The crown disciplines for ambitious runners and hobby athletes. Who will take the victory in Kirchseeon?',
            'ablauf.title': 'Schedule',
            'ablauf.time1': '09:00 AM',
            'ablauf.event1': 'Bambini Run (500m)',
            'ablauf.time2': '09:30 AM',
            'ablauf.event2': 'Student Runs (1000m & 2000m)',
            'ablauf.time3': '10:00 AM',
            'ablauf.event3': 'Elite Runs (5km & 10km)',
            'ablauf.time4': '11:00 AM',
            'ablauf.event4': 'Award Ceremony Bambini & Students',
            'ablauf.time5': '12:00 PM',
            'ablauf.event5': 'Award Ceremony Elite',
            'strecke.title': 'Start & Finish',
            'strecke.address': 'Start and finish are located at Westring 6, 85614 Kirchseeon.',
            'strecke.routes_title': 'The Routes',
            'strecke.route1': 'Bambini Lauf (500m)',
            'strecke.route2': 'Student Run (1km/2km)',
            'strecke.route3': 'Elite Run (5km/10km)',
            'strecke.gpx_download': 'GPX Download',
            'anmeldung.title': 'Registration',
            'anmeldung.text': 'Secure your starting place for the Town Run 2026 now!',
            'anmeldung.placeholder_title': 'RaceResult Registration',
            'anmeldung.placeholder_text': 'The RaceResult registration snippet will be embedded here.',
            'anmeldung.placeholder_note': '(Please insert RaceResult iframe/script here)',
            'sponsoren.title': 'Our Sponsors',
            'kontakt.title': 'Write to us – we are happy to help!',
            'kontakt.faq_text': 'Have a quick question? ',
            'kontakt.faq_link': 'Visit our FAQ',
            'kontakt.form.vorname': 'First Name *',
            'kontakt.form.nachname': 'Last Name *',
            'kontakt.form.email': 'Email Address *',
            'kontakt.form.betreff': 'Subject / Inquiry *',
            'kontakt.form.betreff_placeholder': 'Please select a topic',
            'kontakt.form.opt_anmeldung': 'Questions about registration / entry fee',
            'kontakt.form.opt_strecke': 'Route & timing',
            'kontakt.form.opt_sponsoring': 'Sponsoring & Partners',
            'kontakt.form.opt_helfer': 'Helpers / Volunteers',
            'kontakt.form.opt_sonstiges': 'Other',
            'kontakt.form.nachricht': 'Message *',
            'kontakt.form.privacy': 'I have read the privacy policy and agree to the processing of my data. *',
            'kontakt.captcha': '[Spam protection: Friendly Captcha / reCAPTCHA Integration]',
            'kontakt.form.submit': 'Send Message',
            'footer.title': 'Town Run 2026',
            'footer.text': 'A sporting highlight in Kirchseeon for the whole family. We look forward to seeing you!',
            'footer.links_title': 'Quick Links',
            'footer.legal_title': 'Legal',
            'footer.legal.impressum': 'Imprint',
            'footer.legal.privacy': 'Privacy Policy',
            'footer.copyright': '© 2026 ATS Kirchseeon e.V. | All rights reserved.'
        }
    };

    const toggleBtn = document.getElementById('lang-toggle');
    const langFlag = document.getElementById('lang-flag');
    let currentLang = 'de';

    const updateContent = (lang) => {
        document.documentElement.lang = lang;
        document.querySelectorAll('[data-i18n]').forEach(el => {
            const key = el.getAttribute('data-i18n');
            if (translations[lang][key]) {
                if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                    el.placeholder = translations[lang][key];
                } else {
                    el.innerHTML = translations[lang][key];
                }
            }
        });
    };

    toggleBtn.addEventListener('click', () => {
        currentLang = currentLang === 'de' ? 'en' : 'de';
        
        // Swap image and alt text: Show the flag of the language to switch TO
        const flagSrc = currentLang === 'de' ? 'assets/images/allemand.png' : 'assets/images/anglais.png';
        const flagAlt = currentLang === 'de' ? 'Deutsch' : 'English';
        
        // Wait, the user said: "Das Fahnenzeichen sollte bei deutscher Version das englische sein und umgekehrt."
        // If currentLang is 'de', show 'anglais.png'.
        // If currentLang is 'en', show 'allemand.png'.
        
        const targetFlagSrc = currentLang === 'de' ? 'assets/images/allemand.png' : 'assets/images/anglais.png';
        // Let's re-read carefully: "Das Fahnenzeichen sollte bei deutscher Version das englische sein"
        // If site is in German (currentLang === 'de'), flag should be English.
        // But in the code above, currentLang is toggled FIRST.
        
        // Let's correct the logic:
        // If site is now 'en', show 'de' flag? No, usually it's: "Current is DE, show EN flag to switch".
        // Let's implement: if currentLang === 'de' -> show 'anglais.png'. If currentLang === 'en' -> show 'allemand.png'.
        
        const finalFlagSrc = currentLang === 'de' ? 'assets/images/allemand.png' : 'assets/images/anglais.png';
        // I'll use the state AFTER the toggle. 
        // If after toggle currentLang is 'en', we are in English mode, so we should show the German flag to switch back.
        
        langFlag.src = currentLang === 'en' ? 'assets/images/allemand.png' : 'assets/images/anglais.png';
        langFlag.alt = currentLang === 'en' ? 'Deutsch' : 'English';
        
        updateContent(currentLang);
    });
}

/**
 * Smooth Scrolling for anchor links
 */
function initSmoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;

            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                const headerOffset = 70; // Height of the sticky header
                const elementPosition = targetElement.getBoundingClientRect().top;
                const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

                window.scrollTo({
                    top: offsetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });
}

/**
 * Map Precision & Lifecycle Management
 * Note: Since we are using a Google Maps iframe, the pin is managed by Google.
 * This function provides the structure for future JS API integration.
 */
function initMapLifecycle() {
    const mapContainer = document.getElementById('map-container');
    const mapIframe = document.getElementById('main-map');

    if (!mapContainer || !mapIframe) return;

    // Handle map resizing if the container was hidden or changed size
    const handleResize = () => {
        // For JS API: map.invalidateSize();
        // For Iframe: We refresh the src to ensure the center is correct
        const currentSrc = mapIframe.src;
        mapIframe.src = '';
        mapIframe.src = currentSrc;
    };

    window.addEventListener('resize', handleResize);
    
    // Trigger once on load to ensure correct centering
    setTimeout(handleResize, 500);
}

/**
 * Contact Form Handler
 * Intercepts form submission to prevent mailto: and simulate/integrate 
 * an automatic email sending service.
 */
function initContactForm() {
    const contactForm = document.getElementById('contact-form');
    if (!contactForm) return;

    contactForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.textContent;
        
        // Create feedback message element
        const messageDiv = document.createElement('div');
        messageDiv.className = 'form-message';
        form.prepend(messageDiv);

        try {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Sende Nachricht...';

            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            // Using Formspree for automatic email delivery
            // Note: The user needs to replace 'your-form-id' with their actual Formspree ID
            const response = await fetch('https://formspree.io/f/info@atsv-kirchseeon-marktlauf.de', {
                method: 'POST',
                body: JSON.stringify(data),
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }
            });

            if (!response.ok) {
                throw new Error('Submission failed');
            }
            
            messageDiv.textContent = 'Vielen Dank für Ihre Nachricht! Wir haben Ihr Anliegen erhalten und melden uns innerhalb von 24 Stunden bei Ihnen.';
            messageDiv.classList.add('success');
            form.reset();
        } catch (error) {
            messageDiv.textContent = 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.';
            messageDiv.classList.add('error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = originalBtnText;
        }
    });
}

/**
 * Newsletter Form Handler
 * Handles Brevo subscription and provides user-friendly error messages 
 * when blocked by AdBlockers.
 */
function initNewsletterForm() {
    const newsletterForm = document.getElementById('sib-form');
    if (!newsletterForm) return;

    newsletterForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const form = e.target;
        const errorDiv = document.getElementById('error-message');
        const successDiv = document.getElementById('success-message');
        const submitBtn = form.querySelector('button[type="submit"]');
        
        if (errorDiv) errorDiv.style.display = 'none';
        if (successDiv) successDiv.style.display = 'none';
        
        try {
            submitBtn.disabled = true;
            const formData = new FormData(form);
            
            // Call Brevo API
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                // Using 'cors' to ensure we can check response.ok
                mode: 'cors'
            });

            if (response.ok) {
                // Redirect to confirmation page only on success
                window.location.href = 'newsletter-bestaetigung.html';
            } else {
                throw new Error('Submission failed');
            }
        } catch (error) {
            // Handle AdBlocker or Network Errors
            if (errorDiv) {
                errorDiv.style.display = 'block';
                const innerText = errorDiv.querySelector('.sib-form-message-panel__inner-text');
                if (innerText) {
                    innerText.textContent = 'Hinweis: Die Anmeldung wurde blockiert. Bitte deaktiviere deinen AdBlocker und versuche es erneut.';
                }
            }
        } finally {
            submitBtn.disabled = false;
        }
    });
}