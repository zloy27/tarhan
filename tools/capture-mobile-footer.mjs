import { chromium } from 'file:///C:/Users/zloy27/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs';
import fs from 'node:fs/promises';

const outputDir = 'C:/Users/zloy27/.codex/visualizations/2026/11/05/tarhan-mobile-footer';
await fs.mkdir(outputDir, { recursive: true });

const browser = await chromium.launch({
  executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
  headless: true,
});
const page = await browser.newPage({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 1 });
await page.goto('http://tarhan-wp.local/jury/', { waitUntil: 'networkidle', timeout: 30000 });
await page.addStyleTag({ content: '.tarhan-cookie-banner,.tarhan-cookies{display:none!important}' });

const footer = page.locator('.footer');
await footer.waitFor({ state: 'visible', timeout: 10000 });
await footer.scrollIntoViewIfNeeded();
const result = await footer.evaluate((element) => ({
  backgroundColor: getComputedStyle(element).backgroundColor,
  viewportWidth: window.innerWidth,
  stylesheetLoaded: Array.from(document.styleSheets).some((sheet) => sheet.href?.includes('footer-mobile-color.css')),
}));
await footer.screenshot({ path: `${outputDir}/footer-mobile-wp-local.png` });
console.log(JSON.stringify(result));
await browser.close();
