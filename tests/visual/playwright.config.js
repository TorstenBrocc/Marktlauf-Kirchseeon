// @ts-check
const { defineConfig } = require('@playwright/test');

// Zielbasis: standardmäßig die Live-Seite (kein lokales PHP nötig).
// Überschreibbar via VISUAL_BASE_URL.
const BASE_URL = process.env.VISUAL_BASE_URL || 'https://atsv-kirchseeon-marktlauf.de';

module.exports = defineConfig({
  testDir: '.',
  snapshotDir: './__snapshots__',
  outputDir: './test-results',
  reporter: [['list']],
  use: {
    baseURL: BASE_URL,
    // deterministischer rendern
    reducedMotion: 'reduce',
  },
  // kleine Toleranz gegen Font-/Antialiasing-Rauschen
  expect: {
    toHaveScreenshot: { maxDiffPixelRatio: 0.02, animations: 'disabled' },
  },
  projects: [
    { name: 'mobile',  use: { viewport: { width: 375,  height: 812 } } },
    { name: 'tablet',  use: { viewport: { width: 768,  height: 1024 } } },
    { name: 'desktop', use: { viewport: { width: 1280, height: 900 } } },
  ],
});
