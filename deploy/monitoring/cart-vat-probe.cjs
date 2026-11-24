// Проба корзины сайта для признака НДС (24.11.2026, решение владельца: только автомат).
// Открывает карточку товара, нажимает «В корзину» (не «Купить в 1 клик», не «Заказать»), переходит в корзину и
// возвращает её текст. К оформлению не переходит, форм не заполняет и не отправляет.
// Запуск: node cart-vat-probe.cjs <url> [proxy]  → JSON {clicked, cart_url, cart_text, error}
const puppeteer = require('puppeteer');

const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

(async () => {
  const [url, proxy] = process.argv.slice(2);
  const args = ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu'];
  if (proxy) args.push('--proxy-server=' + proxy.replace(/^https?:\/\//, ''));
  const out = { clicked: null, cart_url: null, cart_text: '', error: null };
  let browser;
  try {
    browser = await puppeteer.launch({
      executablePath: process.env.CHROME_PATH || '/usr/bin/google-chrome-stable',
      headless: true,
      args,
    });
    const page = await browser.newPage();
    await page.setUserAgent(UA);
    await page.setViewport({ width: 1366, height: 900 });
    await page.goto(url, { waitUntil: 'networkidle2', timeout: 40000 });

    out.clicked = await page.evaluate(() => {
      const visible = (el) => {
        const r = el.getBoundingClientRect();
        const s = getComputedStyle(el);
        return r.width > 0 && r.height > 0 && s.visibility !== 'hidden' && s.display !== 'none';
      };
      const txt = (el) => (el.innerText || el.value || el.getAttribute('title') || el.getAttribute('aria-label') || '')
        .replace(/\s+/g, ' ').trim().toLowerCase();
      const els = [...document.querySelectorAll('button, a, input[type=submit], input[type=button], [role=button]')]
        .filter((el) => visible(el) && !/1\s*клик|один\s*клик|заказать|запрос|сравн|избран/i.test(txt(el)));
      const exact = els.find((el) => /^(в\s*корзину|добавить\s+в\s+корзину)$/i.test(txt(el)));
      const loose = els.find((el) => /в\s*корзину|^купить$/i.test(txt(el)) && txt(el).length < 30);
      const btn = exact || loose;
      if (!btn) return null;
      btn.scrollIntoView({ block: 'center' });
      btn.click();
      return txt(btn);
    });
    if (!out.clicked) throw new Error('no add-to-cart button');
    await sleep(3500);

    const origin = new URL(url).origin;
    const linked = await page.evaluate(() => {
      const a = [...document.querySelectorAll('a[href]')].map((x) => x.href)
        .find((h) => /\/(basket|cart|korzina|personal\/cart|checkout\/cart)(\/|\?|$)|route=checkout\/cart/i.test(h));
      return a || null;
    });
    const candidates = [...new Set([linked, origin + '/basket/', origin + '/cart/', origin + '/personal/cart/', origin + '/korzina/',
      origin + '/index.php?route=checkout/cart'].filter(Boolean))];
    for (const c of candidates) {
      try {
        const resp = await page.goto(c, { waitUntil: 'networkidle2', timeout: 30000 });
        if (!resp || resp.status() >= 400) continue;
        await sleep(1500);
        const text = await page.evaluate(() => document.body.innerText || '');
        if (/итого|сумма|в корзине|товаров на/i.test(text)) {
          out.cart_url = c;
          out.cart_text = text.slice(0, 20000);
          if (/ндс/i.test(text)) break;
        }
      } catch (e) {
        // следующий вариант адреса корзины
      }
    }
  } catch (e) {
    out.error = String((e && e.message) || e);
  } finally {
    if (browser) await browser.close();
  }
  process.stdout.write(JSON.stringify(out));
})();
