/**
 * KronosCMS Playwright Crawler
 * Logs into the dashboard and visits every menu page.
 * Reports: page title, PHP errors, blank pages, JS errors, HTTP status.
 *
 * Usage:
 *   cd tests && npm install && node crawl.js
 *
 * Optional env vars:
 *   BASE_URL       — defaults to http://localhost/KronosCMS/public
 *   KRONOS_USER    — defaults to irmaiden
 *   KRONOS_PASS    — defaults to 1q2w3e&*(
 */

const { chromium } = require('playwright');

const BASE     = process.env.BASE_URL    || 'http://localhost/KronosCMS/public';
const USERNAME = process.env.KRONOS_USER || 'irmaiden';
const PASSWORD = process.env.KRONOS_PASS || '1q2w3e&*(';

const PAGES = [
  { label: 'Overview',     path: '/dashboard' },
  { label: 'Content',      path: '/dashboard/content' },
  { label: 'Builder',      path: '/dashboard/builder/1' },
  { label: 'Analytics',    path: '/dashboard/analytics' },
  { label: 'AI Chat',      path: '/dashboard/ai' },
  { label: 'Marketplace',  path: '/dashboard/marketplace' },
  { label: 'Users',        path: '/dashboard/users' },
  { label: 'Settings',     path: '/dashboard/settings' },
];

// ANSI colours
const RED    = '\x1b[31m';
const GREEN  = '\x1b[32m';
const YELLOW = '\x1b[33m';
const CYAN   = '\x1b[36m';
const RESET  = '\x1b[0m';
const BOLD   = '\x1b[1m';

function ok(msg)   { console.log(`  ${GREEN}✓${RESET} ${msg}`); }
function fail(msg) { console.log(`  ${RED}✗${RESET} ${msg}`); }
function warn(msg) { console.log(`  ${YELLOW}⚠${RESET} ${msg}`); }
function info(msg) { console.log(`  ${CYAN}ℹ${RESET} ${msg}`); }

async function run() {
  console.log(`\n${BOLD}⚡ KronosCMS Dashboard Crawler${RESET}`);
  console.log(`${CYAN}Base URL: ${BASE}${RESET}\n`);

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    ignoreHTTPSErrors: true,
    viewport: { width: 1280, height: 900 },
  });
  const page = await context.newPage();

  const results = [];
  let loginOk = false;

  // ── 1. Login ────────────────────────────────────────────────────
  console.log(`${BOLD}[1/2] Logging in...${RESET}`);
  try {
    const jsErrors = [];
    page.on('pageerror', e => jsErrors.push(e.message));

    const resp = await page.goto(`${BASE}/dashboard/login`, { waitUntil: 'domcontentloaded', timeout: 15000 });
    const status = resp?.status() ?? 0;
    if (status >= 400) {
      fail(`Login page returned HTTP ${status}`);
      await browser.close();
      return;
    }

    // Detect PHP fatal errors on the login page
    const bodyText0 = await page.locator('body').innerText().catch(() => '');
    const phpError0 = extractPhpError(bodyText0);
    if (phpError0) {
      fail(`PHP error on login page:\n    ${phpError0}`);
      await browser.close();
      return;
    }

    const usernameField = page.locator('input[name="username"]');
    const passwordField = page.locator('input[name="password"]');

    await usernameField.fill(USERNAME);
    await passwordField.fill(PASSWORD);

    // Intercept the POST response
    const [response] = await Promise.all([
      page.waitForResponse(r => r.url().includes('/dashboard/login') && r.request().method() === 'POST', { timeout: 15000 }),
      page.locator('button[type="submit"], input[type="submit"]').click(),
    ]);
    await page.waitForLoadState('domcontentloaded');

    const finalUrl = page.url();
    const bodyAfter = await page.locator('body').innerText().catch(() => '');

    if (finalUrl.includes('/dashboard/login')) {
      const alertText = await page.locator('.alert').innerText().catch(() => '');
      fail(`Login failed. URL: ${finalUrl.replace(BASE, '')}\n  Alert: "${alertText}"\n  Body: "${bodyAfter.substring(0, 400)}"`);
      await browser.close();
      return;
    }

    ok(`Logged in → ${finalUrl.replace(BASE, '')}`);
    loginOk = true;
  } catch (e) {
    fail(`Login threw: ${e.message}`);
    await browser.close();
    return;
  }

  // ── 2. Crawl pages ──────────────────────────────────────────────
  console.log(`\n${BOLD}[2/2] Crawling ${PAGES.length} dashboard pages...${RESET}\n`);

  for (const p of PAGES) {
    const url = BASE + p.path;
    const jsErrors = [];
    const consoleErrors = [];
    page.removeAllListeners('pageerror');
    page.removeAllListeners('console');
    page.on('pageerror', e => jsErrors.push(e.message));
    page.on('console',   e => { if (e.type() === 'error') consoleErrors.push(e.text()); });

    let result = { label: p.label, path: p.path, status: 'ok', issues: [] };

    try {
      const resp = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 15000 });
      const httpStatus = resp?.status() ?? 0;

      // Redirected away from dashboard?
      const finalUrl = page.url();
      if (!finalUrl.includes('/dashboard') || finalUrl.includes('/login')) {
        result.issues.push(`Redirected away to: ${finalUrl.replace(BASE, '')}`);
        result.status = 'fail';
      }

      // HTTP error
      if (httpStatus >= 400) {
        result.issues.push(`HTTP ${httpStatus}`);
        result.status = 'fail';
      }

      // Grab page body
      const bodyHtml = await page.content();
      const bodyText = await page.locator('body').innerText().catch(() => '');
      const textLen  = bodyText.trim().length;

      // PHP errors / warnings in body
      const phpError = extractPhpError(bodyText + bodyHtml);
      if (phpError) {
        result.issues.push(`PHP: ${phpError}`);
        result.status = 'fail';
      }

      // Blank page check — body text under 100 chars with no PHP error
      if (textLen < 100 && !phpError) {
        result.issues.push(`BLANK PAGE (body text: ${textLen} chars)`);
        result.status = result.status === 'ok' ? 'warn' : result.status;
      }

      // Page title
      const title = await page.title().catch(() => '');
      result.title = title || '(no title)';

      // Check for sidebar (confirms layout rendered)
      const hasSidebar = await page.locator('.sidebar, #sidebar').count() > 0;
      if (!hasSidebar) {
        result.issues.push('Sidebar / layout not rendered');
        result.status = result.status === 'ok' ? 'warn' : result.status;
      }

      // JS errors
      if (jsErrors.length) {
        result.issues.push(...jsErrors.slice(0, 3).map(e => `JS: ${e}`));
        result.status = result.status === 'ok' ? 'warn' : result.status;
      }

    } catch (e) {
      result.issues.push(`Exception: ${e.message}`);
      result.status = 'fail';
    }

    results.push(result);

    // Print immediately
    const icon = result.status === 'ok' ? `${GREEN}✓${RESET}` : result.status === 'warn' ? `${YELLOW}⚠${RESET}` : `${RED}✗${RESET}`;
    console.log(`${icon} ${BOLD}${result.label}${RESET}  ${CYAN}${result.path}${RESET}  (${result.title ?? ''})`);
    for (const issue of result.issues) {
      console.log(`    └─ ${issue}`);
    }
  }

  // ── Summary ──────────────────────────────────────────────────────
  const passed = results.filter(r => r.status === 'ok').length;
  const warned = results.filter(r => r.status === 'warn').length;
  const failed = results.filter(r => r.status === 'fail').length;

  console.log(`\n${BOLD}────────────────────────────────────────${RESET}`);
  console.log(`${BOLD}Results:${RESET} ${GREEN}${passed} passed${RESET}  ${YELLOW}${warned} warnings${RESET}  ${RED}${failed} failed${RESET}`);

  if (failed > 0 || warned > 0) {
    console.log(`\n${BOLD}Issues to fix:${RESET}`);
    for (const r of results) {
      if (r.issues.length) {
        console.log(`  ${r.label} (${r.path}):`);
        r.issues.forEach(i => console.log(`    • ${i}`));
      }
    }
  } else {
    console.log(`\n${GREEN}${BOLD}All pages healthy!${RESET}`);
  }

  await browser.close();
}

/**
 * Extract a PHP error message from raw page text/HTML.
 */
function extractPhpError(text) {
  const patterns = [
    /(?:Fatal error|Parse error|Warning|Notice|TypeError|ValueError):\s+[A-Z].+?(?=\n|<br|Stack trace)/i,
    /Uncaught [\w\\]+:\s+[A-Z].+?(?=\n|<br|Stack trace)/i,
    /SQLSTATE\[.+?\]:.+?(?=\n|<)/i,
  ];
  for (const pat of patterns) {
    const m = text.match(pat);
    if (m) return m[0].replace(/<[^>]+>/g, '').trim().substring(0, 200);
  }
  return null;
}

run().catch(e => { console.error(e); process.exit(1); });
