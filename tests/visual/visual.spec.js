const { test, expect } = require('@playwright/test');

// Öffentliche Kernseiten (A-3, erster Umfang). Dashboard hinter Login später.
const pages = [
  { name: 'startseite',       path: '/' },
  { name: 'datenschutz',      path: '/datenschutz.html' },
  { name: 'impressum',        path: '/impressum.html' },
  { name: 'helfer-anmeldung', path: '/helfer-anmeldung.php' },
];

for (const p of pages) {
  test(`optik: ${p.name}`, async ({ page }) => {
    await page.goto(p.path, { waitUntil: 'networkidle' });
    // Karten-Tiles / verzögerte Elemente kurz setzen lassen
    await page.waitForTimeout(1500);
    await expect(page).toHaveScreenshot(`${p.name}.png`, { fullPage: true });
  });
}
