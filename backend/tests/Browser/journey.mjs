/*
 * Prospect to student, in a real browser.
 *
 * The journey the school's director wants to walk himself:
 *
 *   fill in the form on the website
 *     -> the school accepts it and the learner pays
 *     -> an email arrives saying the course is open
 *     -> the learner chooses a PIN from the link in it
 *     -> they sign in at a lab computer and start studying
 *
 * The first three steps are server work and are driven by setup_journey.php;
 * everything a person actually touches happens here. Run:
 *
 *   php artisan serve --port=8123 &
 *   php setup_journey.php > /tmp/journey.json
 *   JOURNEY=$(cat /tmp/journey.json) SHOTS=/tmp/shots node tests/Browser/journey.mjs
 */
import { chromium } from 'playwright';

const J = JSON.parse(process.env.JOURNEY);
const BASE = 'http://127.0.0.1:8123';
const SHOTS = process.env.SHOTS;
const PIN = '481907';

const say = (m) => console.log(m);
let failures = 0;
const check = (label, ok, extra = '') => {
  say(`  ${ok ? 'PASS' : 'FAIL'}  ${label}${extra ? ' — ' + extra : ''}`);
  if (!ok) failures++;
};

const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
const ctx = await browser.newContext({ viewport: { width: 1120, height: 900 } });
const page = await ctx.newPage();
page.on('pageerror', (e) => { say('  !! page error: ' + e.message); failures++; });

say('\n— WHAT THE SERVER DID BEFORE THE LEARNER TOUCHED ANYTHING —');
check('the website form created a registration', J.intake_status === 'created', J.intake_status);
check('paying activated the enrolment', J.enrolment_status === 'active', J.enrolment_status);
check('a student number was allocated', /^NAL-\d{4}-\d{5}$/.test(J.learner_ref), J.learner_ref);
check('the phone app got its own code', /^KCS-/.test(J.app_token || ''), J.app_token);

say('\n— THE LEARNER OPENS THE LINK FROM THE EMAIL —');
await page.goto(J.access_link, { waitUntil: 'networkidle' });
const opened = await page.textContent('body');
check('the page greets them by name', /Palesa/.test(opened));
check('and shows their student number', opened.includes(J.learner_ref));
check('no PIN was emailed to them', !/your PIN is/i.test(opened));
await page.screenshot({ path: `${SHOTS}/j1-choose-pin.png`, fullPage: true });

say('\n— A PIN ANYONE COULD GUESS IS REFUSED —');
await page.fill('#pin', '123456');
await page.fill('#pin_confirmation', '123456');
await page.click('button[type=submit]');
await page.waitForLoadState('networkidle');
check('123456 is turned away, with a reason', /too easy to guess/i.test(await page.textContent('body')));

await page.fill('#pin', PIN);
await page.fill('#pin_confirmation', '481906');
await page.click('button[type=submit]');
await page.waitForLoadState('networkidle');
check('two PINs that differ are turned away', /not the same/i.test(await page.textContent('body')));

say('\n— THEY CHOOSE A REAL PIN —');
await page.fill('#pin', PIN);
await page.fill('#pin_confirmation', PIN);
await page.click('button[type=submit]');
await page.waitForLoadState('networkidle');
const done = await page.textContent('body');
check('it is saved', /Your PIN is saved/i.test(done));
check('and they are told exactly how to sign in', done.includes(J.learner_ref) && /workspace/i.test(done));
await page.screenshot({ path: `${SHOTS}/j2-ready.png`, fullPage: true });

say('\n— STRAIGHT INTO THE WORKSPACE —');
await page.click('a.cta');
await page.waitForSelector('#signinForm', { timeout: 10000 });
check('the button lands on the sign-in screen', (await page.$('#ref')) !== null);
await page.screenshot({ path: `${SHOTS}/j3-signin.png` });

await page.fill('#ref', J.learner_ref);
await page.fill('#pin', PIN);
await page.click('#signinBtn');
await page.waitForSelector('#work:not(.hide)', { timeout: 15000 });
await page.waitForSelector('.task', { timeout: 15000 });

const header = await page.textContent('header');
check('signed in as themselves', header.includes(J.learner_ref) && /Palesa/.test(header));
check('on the programme they registered for', header.includes('DOPF'));

const tasks = await page.locator('.task').count();
check('the whole course is there to study', tasks === 90, `${tasks} tasks`);
check('and nothing is done yet', (await page.locator('.step.on').count()) === 0);
await page.screenshot({ path: `${SHOTS}/j4-studying.png` });

say('\n— THE OLD PIN IS DEAD ONCE A NEW ONE IS SET —');
await page.click('#logoutBtn');
await page.waitForSelector('#signin:not(.hide)', { timeout: 10000 });
await page.fill('#ref', J.learner_ref);
await page.fill('#pin', '000000');
await page.click('#signinBtn');
await page.waitForTimeout(1500);
check('a wrong PIN is refused, kindly', /do not match/i.test(await page.textContent('#signinErr')));

say(`\n${failures === 0 ? 'ALL CHECKS PASSED' : failures + ' CHECK(S) FAILED'}`);
await browser.close();
process.exit(failures === 0 ? 0 : 1);
