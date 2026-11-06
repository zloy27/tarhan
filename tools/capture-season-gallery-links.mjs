import { chromium } from 'file:///C:/Users/zloy27/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs';
import fs from 'node:fs/promises';

const outputDir = 'C:/Users/zloy27/.codex/visualizations/2026/11/05/tarhan-season-gallery-links';
await fs.mkdir(outputDir, { recursive: true });
const expected = {
  '2021-2022': 'https://vk.ru/tarhan_konkurs?z=album-214070047_283641259',
  '2022-2023': 'https://vk.ru/tarhan_konkurs?z=album-214070047_309804854',
  '2023-2024': 'https://vk.ru/tarhan_konkurs?z=album-214070047_309805049',
  '2024-2025': 'https://vk.ru/tarhan_konkurs?z=album-214070047_309805127',
  '2025-2026': 'https://vk.ru/tarhan_konkurs?z=album-214070047_310359015',
};

const browser = await chromium.launch({ executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', headless: true });
const page = await browser.newPage({ viewport: { width: 1280, height: 720 }, deviceScaleFactor: 1 });

for (const [period, url] of Object.entries(expected)) {
  await page.goto(`http://tarhan-wp.local/archive/${period}/`, { waitUntil: 'networkidle', timeout: 30000 });
  await page.addStyleTag({ content: '.header,.tarhan-cookie-banner,.tarhan-cookies{display:none!important} *,*::before,*::after{animation:none!important;transition:none!important}' });
  const button = page.locator('.season__gallery-link a');
  await button.waitFor({ state: 'visible', timeout: 10000 });
  const href = await button.getAttribute('href');
  if (href !== url) throw new Error(`${period}: expected ${url}, got ${href}`);
  await button.scrollIntoViewIfNeeded();
  await page.evaluate(() => document.querySelectorAll('.season__gallery img').forEach((image) => { image.loading = 'eager'; image.src = image.currentSrc || image.src; }));
  await page.waitForFunction(() => Array.from(document.querySelectorAll('.season__gallery img')).every((image) => image.complete && image.naturalWidth > 0), null, { timeout: 15000 });
  const box = await button.boundingBox();
  const bodyHeight = await page.evaluate(() => document.documentElement.scrollHeight);
  const clipY = Math.max(0, Math.min(bodyHeight - 430, box.y - 300));
  await page.screenshot({ path: `${outputDir}/season-${period}.png`, clip: { x: 0, y: clipY, width: 1280, height: Math.min(430, bodyHeight - clipY) } });
  console.log(`${period}: ${href}`);
}

await browser.close();
