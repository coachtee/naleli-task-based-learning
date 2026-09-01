/*
 * The lab rotation, in a real browser.
 *
 * Three learners share one PC, the line goes down mid-lesson, and somebody
 * tries to log out with unsaved work. PHPUnit cannot reach any of that — the
 * part that loses a learner's afternoon is IndexedDB, a service worker and a
 * logout button, and none of them exist server-side.
 *
 * Not in CI (it needs a running server and a browser). Run it by hand when
 * the workspace changes:
 *
 *   php artisan migrate --seed
 *   php artisan lab:demo-learners          # prints three student numbers + PINs
 *   php artisan serve --port=8123 &
 *   npm i --no-save playwright
 *   SHOTS=/tmp/shots node tests/Browser/rotation.mjs
 *
 * The PINs below are whatever `lab:demo-learners` last printed — it reuses the
 * same three demo learners, so paste the new ones in when they change.
 */

import { chromium } from 'playwright';

const BASE = 'http://127.0.0.1:8123/workspace';
const SHOTS = process.env.SHOTS;
const say = (m) => console.log(m);

const login = async (page, ref, pin) => {
  await page.fill('#ref', ref);
  await page.fill('#pin', pin);
  await page.click('#signinBtn');
  await page.waitForSelector('#work:not(.hide)', { timeout: 10000 });
  await page.waitForSelector('.task', { timeout: 10000 });
};
const doneCount = (page) => page.$$eval('.step.on', (n) => n.length);
const pill = (page) => page.textContent('#syncText');
const logout = async (page) => {
  await page.click('#logoutBtn');
  await page.waitForSelector('#signin:not(.hide)', { timeout: 10000 });
};

const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 860 } });
const page = await ctx.newPage();
page.on('pageerror', (e) => say('  !! page error: ' + e.message));

let failures = 0;
const check = (label, ok, extra = '') => {
  say(`  ${ok ? 'PASS' : 'FAIL'}  ${label}${extra ? ' — ' + extra : ''}`);
  if (!ok) failures++;
};

await page.goto(BASE, { waitUntil: 'networkidle' });
await page.screenshot({ path: `${SHOTS}/1-login.png` });
say('\n08:00 — CLASS ONE: Naledi');
await login(page, 'DEMO-2026-00001', '274143');
check('starts with nothing done', (await doneCount(page)) === 0);
await page.screenshot({ path: `${SHOTS}/2-workspace.png` });

await page.locator('.step').nth(0).click();
await page.waitForTimeout(400);
await page.locator('.step').nth(1).click();
await page.waitForTimeout(2000);
check('two steps ticked', (await doneCount(page)) === 2);
check('says Saved', (await pill(page)) === 'Saved', await pill(page));
await page.screenshot({ path: `${SHOTS}/3-working.png` });
await logout(page);

say('\n10:00 — CLASS TWO: Sipho on the same PC');
await login(page, 'DEMO-2026-00002', '652476');
const siphoDone = await doneCount(page);
check('sees a clean slate, not Naledi\'s work', siphoDone === 0, `${siphoDone} ticked`);
const who = await page.textContent('#whoName');
check('the header shows Sipho', who.includes('Sipho'), who);
await page.screenshot({ path: `${SHOTS}/4-second-student.png` });
await page.locator('.step').nth(0).click();
await page.waitForTimeout(2000);
await logout(page);

say('\n12:00 — CLASS THREE: Lerato');
await login(page, 'DEMO-2026-00003', '623620');
check('also a clean slate', (await doneCount(page)) === 0);
await logout(page);

say('\nNEXT DAY — Naledi comes back to the same PC');
await login(page, 'DEMO-2026-00001', '274143');
check('her two steps are still there', (await doneCount(page)) === 2, `${await doneCount(page)} ticked`);

say('\nTHE LINE GOES DOWN mid-lesson');
await ctx.setOffline(true);
await page.evaluate(() => window.dispatchEvent(new Event('offline')));
await page.locator('.step').nth(2).click();
await page.waitForTimeout(1200);
check('the tick still shows', (await doneCount(page)) === 3);
const offlinePill = await pill(page);
check('the screen says work is waiting', /waiting/.test(offlinePill), offlinePill);
await page.screenshot({ path: `${SHOTS}/5-offline.png` });

say('\nSHE TRIES TO LOG OUT with unsaved work');
await page.click('#logoutBtn');
await page.waitForSelector('#modal:not(.hide)', { timeout: 5000 });
const warning = await page.textContent('#sheet');
check('logout is blocked with an explanation', /not saved yet/.test(warning));
check('it offers to keep her signed in', (await page.$('#stay')) !== null);
await page.screenshot({ path: `${SHOTS}/6-logout-blocked.png` });

say('\nTHE LINE COMES BACK');
await page.click('#stay');
await ctx.setOffline(false);
await page.evaluate(() => window.dispatchEvent(new Event('online')));
await page.waitForTimeout(2500);
const backPill = await pill(page);
check('it saved itself with no prompting', backPill === 'Saved', backPill);
await page.screenshot({ path: `${SHOTS}/7-recovered.png` });
await logout(page);

say('\nA DIFFERENT COMPUTER (fresh browser, nothing cached)');
const ctx2 = await browser.newContext({ viewport: { width: 1280, height: 860 } });
const page2 = await ctx2.newPage();
await page2.goto(BASE, { waitUntil: 'networkidle' });
await login(page2, 'DEMO-2026-00001', '274143');
const onPc2 = await doneCount(page2);
check('all three steps followed her across', onPc2 === 3, `${onPc2} ticked`);
await page2.screenshot({ path: `${SHOTS}/8-second-pc.png` });

say(`\n${failures === 0 ? 'ALL CHECKS PASSED' : failures + ' CHECK(S) FAILED'}`);
await browser.close();
process.exit(failures === 0 ? 0 : 1);
