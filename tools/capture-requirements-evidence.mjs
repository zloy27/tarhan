import { chromium } from 'file:///C:/Users/zloy27/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs';
import fs from 'node:fs/promises';

const outputDir = 'C:/Users/zloy27/.codex/visualizations/2026/11/05/tarhan-requirements-evidence-all';
await fs.mkdir(outputDir, { recursive: true });

const browser = await chromium.launch({
  executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
  headless: true,
});
const page = await browser.newPage({ viewport: { width: 1280, height: 900 }, deviceScaleFactor: 1 });

async function open(url, width = 1280, height = 900) {
  await page.setViewportSize({ width, height });
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 20000 });
  await page.addStyleTag({ content: `
    *,*::before,*::after{animation:none!important;transition:none!important;opacity:1!important;transform:none!important}
    .tarhan-cookie-banner,.tarhan-cookies{display:none!important}
  ` });
  await page.waitForTimeout(500);
}

async function hideHeader() {
  const header = page.locator('.header');
  if (await header.count()) await header.evaluate((el) => { el.style.display = 'none'; });
}

async function shot(locator, filename) {
  await locator.first().waitFor({ state: 'attached', timeout: 10000 });
  await locator.first().evaluate((el) => {
    el.style.setProperty('visibility', 'visible', 'important');
    el.style.setProperty('opacity', '1', 'important');
    if (el.classList.contains('jury-main__card')) el.classList.add('jury-main__card--visible');
    if (el.classList.contains('seasons__card')) el.classList.add('seasons__card--visible');
  });
  await locator.first().scrollIntoViewIfNeeded();
  await locator.first().screenshot({ path: `${outputDir}/${filename}` });
  console.log(filename, await locator.first().evaluate((el) => ({
    tag: el.tagName,
    className: el.className,
    text: (el.innerText || '').trim().slice(0, 240),
  })));
}

await open('http://tarhan-wp.local/');
await shot(page.locator('.competition-hero'), 'home-hero.png');
await shot(page.locator('.header__logos'), 'header-logos.png');
await hideHeader();
await shot(page.locator('.competition-prize__main'), 'home-prize.png');
await shot(page.locator('.competition-prize__bunner'), 'home-audience.png');
await shot(page.locator('.competition-process__banner'), 'home-statistics.png');

await open('http://tarhan-wp.local/information/');
await hideHeader();
await shot(page.locator('.contacts__box'), 'information-organizers.png');
await shot(page.locator('.docs__box'), 'information-documents.png');

await open('http://tarhan-wp.local/participant/');
await hideHeader();
await shot(page.locator('.preparation__card').filter({ hasText: 'История вместе с преподавателем КФУ' }), 'participant-lectures.png');
await shot(page.locator('.preparation__cards'), 'participant-preparation-links.png');

await open('http://tarhan-wp.local/archive/');
await hideHeader();
await shot(page.locator('.seasons__cards'), 'archive-seasons.png');
await shot(page.locator('.seasons__card').first(), 'archive-season-2025-2026.png');

for (const year of ['2021-2022', '2022-2023', '2023-2024', '2024-2025', '2025-2026']) {
  await open(`http://tarhan-wp.local/archive/${year}/`);
  await hideHeader();
  await shot(page.locator('.season__section').filter({ has: page.locator('#season-watch') }), `season-watch-${year}.png`);
}

await open('http://tarhan-wp.local/jury/');
await hideHeader();
for (const [name, filename] of [
  ['Фахрутдинов', 'jury-fakhrutdinov.png'],
  ['Файзрахманов', 'jury-faizrakhmanov.png'],
  ['Натфуллин', 'jury-natfullin.png'],
  ['Щелкунов', 'jury-shchelkunov.png'],
  ['Хузин', 'jury-khuzin.png'],
]) {
  await shot(page.locator('article.jury-main__card').filter({ hasText: name }), filename);
}

await open('http://tarhan-wp.local/registration/', 390, 844);
await hideHeader();
const region = page.locator('select[name="region_id"]');
await region.waitFor({ state: 'visible', timeout: 10000 });
await page.waitForFunction(() => {
  const select = document.querySelector('select[name="region_id"]');
  return select && !select.disabled && select.options.length > 2;
}, { timeout: 15000 });
await region.selectOption({ index: 1 });
const locality = page.locator('input[name="locality"]');
await locality.waitFor({ state: 'visible' });
await page.waitForFunction(() => {
  const input = document.querySelector('input[name="locality"]');
  return input && !input.disabled;
}, { timeout: 15000 });
await locality.fill('Ка');
const suggestion = page.locator('#address-results li').first();
await suggestion.waitFor({ state: 'visible', timeout: 15000 });
await suggestion.click();
await page.waitForFunction(() => {
  const input = document.querySelector('input[name="locality"]');
  const district = document.querySelector('input[name="district"]');
  return input && input.value.trim().length > 2 && district && !district.disabled;
}, { timeout: 10000 });
await shot(page.locator('.registration__group').nth(1), 'registration-mobile-address.png');

await browser.close();
