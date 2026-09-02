/*
 * Real login, real pages, iPhone-width viewport — the same check that caught
 * the Filament table running 1277px wide and the logo clipping against its
 * rounded tile: measure the DOM, don't eyeball a screenshot.
 *
 *   php artisan serve --port=8123 &
 *   php tests/Browser/setup_mobile_crm.php > /tmp/mobile_crm.json
 *   node tests/Browser/mobile_crm_check.mjs
 */
import { chromium, devices } from 'playwright';
import fs from 'node:fs';

const J = JSON.parse(fs.readFileSync('/tmp/mobile_crm.json', 'utf8'));
// Must match config('app.url') exactly (http://localhost:8000 locally) — the
// global URL::forceRootUrl fix rewrites every redirect Laravel builds for
// itself to that host, so a 127.0.0.1 client would silently drop its session
// cookie the first time it followed one, and land back on Filament's own
// dashboard on every subsequent request. Not a bug in the app: there is only
// one real hostname in production.
const BASE = 'http://localhost:8000';
const SHOTS = '/tmp/mobile_crm_shots';
fs.mkdirSync(SHOTS, { recursive: true });

let failures = 0;
const check = (label, ok, extra = '') => {
  console.log(`  ${ok ? 'PASS' : 'FAIL'}  ${label}${extra ? ' — ' + extra : ''}`);
  if (!ok) failures++;
};

// The sandbox sets HTTPS_PROXY for outbound traffic; Chromium turns that into
// a --proxy-server flag that (unlike env-based proxy detection) does not
// respect NO_PROXY, so a request for 127.0.0.1 gets sent to the proxy and
// refused. Strip the proxy vars for the browser's own process only.
const env = { ...process.env };
for (const k of ['HTTPS_PROXY', 'https_proxy', 'HTTP_PROXY', 'http_proxy', 'ALL_PROXY', 'all_proxy']) delete env[k];

const browser = await chromium.launch({
  executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome',
  args: ['--no-sandbox', '--disable-gpu'],
  env,
});
const ctx = await browser.newContext({ ...devices['iPhone 13'] });
const page = await ctx.newPage();
page.on('pageerror', (e) => { console.log('  !! page error: ' + e.message); failures++; });

console.log('\n— LOG IN —');
await page.goto(`${BASE}/staff`);
await page.fill('input[type=email]', J.admin_email);
await page.fill('input[type=password]', J.admin_password);
await page.click('button[type=submit]');
await page.waitForLoadState('networkidle');
check('landed on the dashboard, not the login page', page.url().includes('/staff'), page.url());

const pages = [
  ['Dashboard', '/staff'],
  ['Leads', '/calls'],
  [`Lead profile`, `/calls/${J.lead_id}`],
  ['Records — registrations', '/records'],
  ['Records — learners', '/records?tab=learners'],
  ['Records — invoices', '/records?tab=invoices'],
  ['Learner profile', `/records/learners/${J.learner_id}`],
  ['More', '/more'],
];

for (const [label, path] of pages) {
  await page.goto(`${BASE}${path}`, { waitUntil: 'networkidle' });
  console.log(`  -> ${page.url()}`);
  const title = await page.title();
  check(`${label} loaded`, !title.toLowerCase().includes('error') && !title.toLowerCase().includes('exception'), title);

  const overflow = await page.evaluate(() => {
    const doc = document.documentElement;
    return { scrollWidth: doc.scrollWidth, clientWidth: doc.clientWidth };
  });
  check(`${label} has no horizontal overflow`, overflow.scrollWidth <= overflow.clientWidth + 1,
    `scroll ${overflow.scrollWidth}px vs viewport ${overflow.clientWidth}px`);

  const logoOk = await page.evaluate(() => {
    const img = document.querySelector('img[src*="bohlale-logo"]');
    if (!img) return true; // detail screens carry no logo — that's correct
    return img.complete && img.naturalWidth > 0;
  });
  check(`${label} logo image actually loaded`, logoOk);

  await page.screenshot({ path: `${SHOTS}/${path.replace(/[\/?=]/g, '_') || 'root'}.png`, fullPage: true });
}

await browser.close();
console.log(failures === 0 ? '\nAll checks passed.' : `\n${failures} check(s) failed.`);
process.exit(failures === 0 ? 0 : 1);
