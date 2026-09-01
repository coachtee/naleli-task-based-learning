/*
 * The full learner journey, in a real browser.
 *
 * PHPUnit cannot reach any of this: IndexedDB, a file input, a service worker
 * and a logout button are where a learner's afternoon actually gets lost.
 *
 *   php artisan migrate:fresh --seed && php artisan lab:demo-learners
 *   php artisan serve --port=8123 &
 *   npm i --no-save playwright && SHOTS=/tmp/shots node uat.mjs
 */
import { chromium } from 'playwright';
const BASE = 'http://127.0.0.1:8123/workspace';
const SHOTS = process.env.SHOTS;
const PINS = JSON.parse(process.env.PINS);
const say = (m) => console.log(m);
let failures = 0;
const check = (label, ok, extra = '') => {
  say(`  ${ok ? 'PASS' : 'FAIL'}  ${label}${extra ? ' — ' + extra : ''}`);
  if (!ok) failures++;
};

const login = async (p, ref) => {
  await p.fill('#ref', ref); await p.fill('#pin', PINS[ref]);
  await p.click('#signinBtn');
  await p.waitForSelector('#work:not(.hide)', { timeout: 10000 });
  await p.waitForSelector('.task', { timeout: 10000 });
};
const tab = async (p, name) => { await p.click(`[data-tab="${name}"]`); await p.waitForTimeout(250); };
const pill = (p) => p.textContent('#syncText');
const logout = async (p) => { await p.click('#logoutBtn'); await p.waitForSelector('#signin:not(.hide)', { timeout: 10000 }); };

const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, acceptDownloads: true });
const page = await ctx.newPage();
page.on('pageerror', (e) => { say('  !! page error: ' + e.message); failures++; });

await page.goto(BASE, { waitUntil: 'networkidle' });

say('\n— READING THE LESSON —');
await login(page, 'DEMO-2026-00001');
await tab(page, 'learn');
const learn = await page.textContent('#tabBody');
check('the lesson text is there to read', /digital literacy/i.test(learn));
check('it says what must be handed in', /What you must hand in/.test(learn));
check('it has self-check questions', /Check yourself/.test(learn));
await page.screenshot({ path: `${SHOTS}/u1-learn.png` });

say('\n— HAND IN IS BLOCKED UNTIL THERE IS SOMETHING TO ASSESS —');
await tab(page, 'handin');
check('the submit button is disabled', await page.isDisabled('#submitBtn'));
const why = await page.textContent('.blocked');
check('it says exactly what is missing', /steps are not ticked/.test(why) && /not attached any evidence/.test(why), why?.trim());
check('the assessor criteria are shown up front', /All 4 steps of the day are complete/.test(await page.textContent('#tabBody')));
await page.screenshot({ path: `${SHOTS}/u2-handin-blocked.png` });

say('\n— DOING THE WORK —');
await tab(page, 'steps');
const stepCount = await page.locator('.step').count();
for (let i = 0; i < stepCount; i++) {
  await page.locator('.step').nth(i).click();
  await page.waitForTimeout(250);
}
await page.waitForTimeout(1500);
check('every step is ticked', (await page.locator('.step.on').count()) === stepCount, `${stepCount} steps`);

say('\n— WRITING AN ANSWER —');
await tab(page, 'evidence');
await page.fill('#answer', 'Input is what I put in, processing is what the computer does with it, storage is where it keeps it, and output is what I get back.');
await page.click('#saveAnswer');
await page.waitForTimeout(2000);
check('the answer is saved as evidence', /written-answer\.txt/.test(await page.textContent('#tabBody')));
check('and it reached the server', (await pill(page)) === 'Saved', await pill(page));

say('\n— ATTACHING A FILE WHILE THE LINE IS DOWN —');
await ctx.setOffline(true);
await page.evaluate(() => window.dispatchEvent(new Event('offline')));
await page.setInputFiles('#file', {
  name: 'my-practice-sheet.png', mimeType: 'image/png',
  buffer: Buffer.from('89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a49444154789c6360000002000100' +
                      '05fe02fa0000000049454e44ae426082', 'hex'),
});
await page.click('#saveFile');
await page.waitForTimeout(1200);
const evBody = await page.textContent('#tabBody');
check('the file shows immediately', /my-practice-sheet\.png/.test(evBody));
check('and is marked as not sent yet', /Waiting to send/.test(evBody));
check('the header says work is waiting', /waiting/.test(await pill(page)), await pill(page));
await page.screenshot({ path: `${SHOTS}/u3-evidence-offline.png` });

say('\n— THE LINE COMES BACK —');
await ctx.setOffline(false);
await page.evaluate(() => window.dispatchEvent(new Event('online')));
await page.waitForTimeout(3500);
check('the file uploaded on its own', (await pill(page)) === 'Saved', await pill(page));
await tab(page, 'evidence');
const after = await page.textContent('#tabBody');
check('it is no longer waiting', !/Waiting to send/.test(after));
check('and can be opened back', /Open/.test(after));
await page.screenshot({ path: `${SHOTS}/u4-evidence-sent.png` });

say('\n— HANDING IT IN —');
await tab(page, 'handin');
check('the submit button is now live', !(await page.isDisabled('#submitBtn')));
await page.click('[data-n="4"]');
await page.waitForTimeout(200);
await page.click('#submitBtn');
await page.waitForTimeout(2500);
await tab(page, 'handin');
check('it says it is with the assessor', /Waiting for your assessor/.test(await page.textContent('#tabBody')));
check('no percentage anywhere on the page', !/\d+\s*%/.test(await page.textContent('section')));
await page.screenshot({ path: `${SHOTS}/u5-handed-in.png` });

await tab(page, 'steps');
check('the steps are locked once handed in', /already handed in|cannot change/i.test(await page.textContent('#tabBody')));

say('\n— THE NEXT CLASS SITS DOWN —');
await logout(page);
await login(page, 'DEMO-2026-00002');
const sipho = await page.textContent('section');
check('Sipho sees none of it', !/written-answer|my-practice-sheet/.test(sipho));
check('his own steps are untouched', (await page.locator('.step.on').count()) === 0);
await logout(page);

say('\n— NALEDI ON A DIFFERENT COMPUTER —');
const ctx2 = await browser.newContext({ viewport: { width: 1280, height: 900 } });
const page2 = await ctx2.newPage();
page2.on('pageerror', (e) => { say('  !! page error: ' + e.message); failures++; });
await page2.goto(BASE, { waitUntil: 'networkidle' });
await login(page2, 'DEMO-2026-00001');

// A fresh machine opens on the NEXT thing to do, which is Day 2 — the right
// behaviour, and the reason this has to say which day it is looking at.
const opensOn = await page2.textContent('section h1');
check('it opens on the next unfinished task, not the one she finished', /Lesson 1B/.test(opensOn), opensOn);
await page2.locator('.task').nth(0).click();
await page2.waitForTimeout(300);
await tab(page2, 'evidence');
const moved = await page2.textContent('#tabBody');
check('her written answer followed her', /written-answer\.txt/.test(moved));
check('her photo followed her too', /my-practice-sheet\.png/.test(moved));
await tab(page2, 'handin');
check('and the hand-in followed her', /Waiting for your assessor/.test(await page2.textContent('#tabBody')));
await page2.screenshot({ path: `${SHOTS}/u6-second-pc.png` });

say(`\n${failures === 0 ? 'ALL CHECKS PASSED' : failures + ' CHECK(S) FAILED'}`);
await browser.close();
process.exit(failures === 0 ? 0 : 1);
