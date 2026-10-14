# Ценовые модели: B2B/промышленный прайс-мониторинг и парсинг цен на заказ (RU + международно)

Research date: 2026-10-14. Prices are given in the currency the source used. Many vendors hide prices ("по запросу"), and several numbers below come from search-engine snippets or aggregators rather than the vendor's own page; each one is labelled. 31 tool calls were made; some primary pages (iDatica tariff table, Priceva prices, Nexar paid-plan prices, xmldatafeed catalog) could not be retrieved (truncated or HTTP 403).

## 1. Russian custom parsing / «мониторинг цен под ключ» — how much, and per what unit

### Takeaway
The Russian market prices custom parsing in three ways: (a) **per site per month** with an SKU cap (about 5.5–10k ₽/month for 1 site with up to 5k SKU, rising to 60k+ ₽/month for 3–5 protected sites with normalization and mapping); (b) **per check / per SKU-site** (iDatica about 0.1–0.5 ₽ per check by volume tier; Imprice 0.07–0.15 ₽ per SKU, minimum 12,500 ₽/month); (c) **one-off freelance gigs** on Kwork (500–5,000 ₽ per script). Setup or development is either bundled ("разработка + сбор данных") or billed hourly (about 2,500 ₽/h). The client always supplies the sources/competitor list. No Russian vendor found publishes a price for finding sources itself.

### Cited Findings
- **Parsingsite.ru** (official price page):
  - Simple parsing: from 10,000 ₽/month for 1 site, up to 5,000 SKU, "разработка + сбор данных".
  - Complex parsing: from 60,000 ₽/month for 3–5 sites, 5,000+ SKU, "разработка + обход защиты + сбор данных + нормализация + маппинг".
  - Price monitoring: from 5,500 ₽ for 1 resource up to 5,000 SKU. Competitor monitoring: from 9,900 ₽ for 1 source up to 5,000 SKU. Repricer: from 5,499 ₽.
  - Desktop parser: from 70,000 ₽. Catalog auto-fill: from 50,000 ₽ (up to 5k SKU) or from 100,000 ₽ (60k+ SKU). Developer + PM: 2,500 ₽/hour.
  — [parsingsite.ru/parsingceny](https://parsingsite.ru/parsingceny)
- **Imprice** (official page "Сбор цен конкурентов"):
  - 7–10 kopecks per SKU on individual competitor sites; 12–15 kopecks per SKU via Yandex.Market.
  - Their examples for 1,000 SKU: 1 site = 70–100 ₽/month; 50 competitors via their sites = 3,500–5,000 ₽; Yandex.Market (50–100 competitors) = 150 ₽.
  - Minimum spend 12,500 ₽/month. Checks as often as every 10 minutes.
  - Custom parsing of protected sites is priced individually after pre-testing.
  — [imprice.ru/pars](https://imprice.ru/pars)
- Imprice says its AI identifies "key competitors" after about 3 months of data collection. That is analysis of already-collected data, not discovery of new sites. — [imprice.ru/pars](https://imprice.ru/pars)
- An aggregator review lists Imprice at 190,000 ₽/month. This conflicts with Imprice's own per-SKU pricing (minimum 12,500 ₽/month); it is probably an enterprise package and is not reliable. — [vc.ru review 2025](https://vc.ru/services/1806441-obzor-servisov-po-monitoringu-cen-v-2025-godu)
- **iDatica** — each plan includes a fixed number of checks per month. A check is one data collection for one product in one store in one region.
  - Tier table (from a search snippet; the source page came back truncated, so not verified directly): 10,000 ₽/month = 20,000 checks; 19,000 ₽ = 100,000; 39,000 ₽ = 300,000; 60,000 ₽ = 500,000; 80,000 ₽ = 800,000; 1M+ = custom. 14-day trial.
  — [idatica.com/faq](https://idatica.com/faq/)
  - An aggregator gives "от 1500 руб., есть бесплатная версия". That conflicts with or is older than the tier table. — [picktech.ru](https://picktech.ru/product/idatica/)
- **Xmldatafeed**: no monthly subscription; you pay for the services you use. Minimum about 2,800 ₽ (aggregator, undated). The price depends on number of competitors, products and refresh rate. — [startpack.ru](https://startpack.ru/application/xmldatafeed)
  - A ready-made database costs about 1,499 ₽ (vc.ru review). — [vc.ru](https://vc.ru/services/1806441-obzor-servisov-po-monitoringu-cen-v-2025-godu)
  - The official site shows no prices; quotes are by inquiry. — [xmldatafeed.com](https://xmldatafeed.com/)
- **PartScanner**: subscription tiers Старт 5,800 ₽/month, Бизнес 15,600 ₽/month, PRO 39,000 ₽/month. These cover price-list processing and export, not custom development.
  - Single ready-made parsers cost 1,000–8,000 ₽. A new parser is quoted on request.
  - Its case studies include "170,000 auto parts" and an equipment distributor, so it is oriented toward spare parts and distributors.
  — [partscanner.ru/web-scraping](https://partscanner.ru/web-scraping)
- **1C module «Мониторинг цен»** (Moscowsoft / Infostart, from a search snippet): about 5,478 ₽ for 1,000 products for 1 month; 12-month packages 49,461–67,048 ₽ for 1,000 products. — [infostart.ru](https://infostart.ru/marketplace/1862832/), [moscowsoft.com](https://moscowsoft.com/catalog/konfiguratsii_1s_predpriyatie/monitoring-tsen-v-internete-ko/)
- **Kwork freelance gigs (fixed price):**
  - "Мониторинг цен конкурентов и поставщиков: отчёты и алерты" — 4,500 ₽. — [kwork](https://kwork.ru/script-programming/53553572/monitoring-tsen-konkurentov-i-postavshchikov-otchyoty-i-alerty)
  - Marketplace price monitoring into Google Sheets — 5,000 ₽. — [kwork](https://kwork.ru/analytics/53727558/monitoring-tsen-konkurentov-na-markepleysakh)
  - "Парсинг сайтов" — 1,500 ₽. — [kwork](https://kwork.ru/script-programming/46245617/parsing-saytov-parser-dannykh-i-saytov)
  - Ozon/WB prices into Excel — 500 ₽. — [kwork](https://kwork.ru/script-programming/51913438/soberu-tseny-konkurentov-s-ozon-i-wildberries-v-excel)
  - Kwork landing "парсинг сайтов на заказ от 500 руб." — [kwork.ru/land/parsing-saytov](https://kwork.ru/land/parsing-saytov)
- **AllRival article (July 2020, stale):** a one-off parsing project costs 20–35k ₽ total, or 12–23 hours of work; the average market setup was about 5,000 ₽. — [allrival.com](https://allrival.com/post/articles/skolko-stoit-parsing-i-pochemu)
- A Russian aggregator (Kokoc/Profi search snippets) mentions continuous parsing or monitoring "от 15 000 ₽/мес". Profi.ru keeps an average-price page for "парсинг сайтов" (2025); its exact numbers were not extracted. — [profi.ru](https://profi.ru/it_freelance/parsing-saitov/price/)

### Inferences
- For the IQOT-Lite profile (20k part numbers × about 10 supplier sites × 1 refresh every 10–30 days):
  - **Per-check model (iDatica):** about 200k checks per refresh → the 39,000 ₽/month tier (300k checks) at a monthly refresh.
  - **Per-SKU model (Imprice):** 200k SKU-site pairs × 0.07–0.10 ₽ ≈ 14–20k ₽/month. This assumes the client provides the URLs, and Imprice explicitly prices protected sites separately.
  - **Per-site model (Parsingsite, 10k ₽/site/month):** 10–30 sites means 100–300k+ ₽/month. B2B nomenclature spread over many small supplier sites is worst-case for per-site pricing.
- Russian market rates for the scraping itself are low (kopecks per SKU per check). The value IQOT adds — finding suppliers and matching the client's nomenclature to them — is not priced by any of these players. Their price lists assume a "you give the list of competitors/URLs" model.
- Setup or development fees for complex sources run from 50–100k ₽ one-off, or 2,500 ₽/hour. "Нормализация + маппинг" appears only in the expensive tier (from 60k ₽/month at Parsingsite). So matching is effectively priced as a premium.

### Gaps
- No concrete prices found for Parsing.pro, ScrapeIt or «Парсинг-Сервис» (not researched separately or not published). The full iDatica tariff table was not verified on its own page. FL.ru rates were not researched.
- No Russian vendor was found publishing a surcharge for matching ("сопоставление") or for finding competitor URLs.

## 2. B2B / industrial: spare parts, B2B marketplaces, tender analytics — how they charge

### Takeaway
B2B infrastructure in Russia is monetized through **subscriptions to a data/API platform** (ABCP — tens of thousands ₽/month for limited request volumes) or **seller-side subscriptions on marketplaces** (Supl.biz 11–29k ₽/month; tender services 23.6–58k ₽/year). No service was found that sells "industrial supplier price monitoring from the buyer's list of part numbers" as a separate product with a published price.

### Cited Findings
- **ABCP** (auto-parts e-commerce platform; numbers from a search snippet of the cross-reference database page):
  - API Crosses 1: 38,400 ₽/month for 5,000 requests/day.
  - API Crosses 2: 50,400 ₽/month for 17,000 requests/day.
  - API Crosses 3: 84,000 ₽/month for 34,000 requests/day.
  - Data on a single part from 68,000 ₽.
  - This is cross-reference data (analogues), not price monitoring.
  — [abcp.ru/crossbase](https://www.abcp.ru/crossbase)
  - ABCP's API searches by part number + brand (`search/articles`). — [ABCP wiki](https://www.abcp.ru/wiki/API.ABCP.Client)
- **Part-kom** and similar distributors provide a "проценка" API (part search with prices from their own stock and partners). Their prices are not published. — [part-kom.ru](https://www.part-kom.ru/webservices/rest/search/)
- **Supl.biz** (B2B wholesale platform, the seller pays):
  - «Поставщик Стандарт» from 10,950 ₽/month.
  - «Поставщик Премиум» from 15,600 ₽/month.
  - «Поставщик Экспортер» from 29,190 ₽/month (sales offer PDF dated 01.10.2025).
  - Free for buyers.
  — [Supl.biz КП PDF](https://cdn.supl.biz/media/attachments/2025/9/29/2383_KP_Supl_biz_Tarif_Postavshchik_Eksporter_Rossiia_s_01_10_25_2.pdf)
- **Kontur.Zakupki** (from a search snippet of a reseller page): Базовый 23,600 ₽/year, Эксперт 46,400 ₽/year, Командный 58,000 ₽/year. This is tender search and niche analytics; the official page is [zakupki.kontur.ru/price](https://zakupki.kontur.ru/price). — [pro-kontur.ru](https://pro-kontur.ru/zakupki/tarify/)
- WebDataGuru (US) offers industrial/MRO product matching on IDs, names, manufacturer and attributes with ML/NLP. It notes format variations such as "ABC-1000-SS vs ABC1000SS". Prices are not published. — [webdataguru.com](https://www.webdataguru.com/blog/product-matching-gets-harder-in-industrial-and-mro-catalogs)
- Zilliant (Price IQ / Price Manager) is positioned for MRO/spare-parts distributors as pricing software that reacts to competitor prices. Prices are not published. — [zilliant.com](https://zilliant.com/blog/essential-insight-for-spare-parts-mro-and-high-tech-companies)

### Inferences
- In Russian auto parts, "part number → sellers and prices" data is sold as per-platform API access (ABCP about 38–84k ₽/month). That is the closest analogue to "a 30k part-number list" — but it covers only suppliers connected to the platform, not arbitrary sites.
- B2B marketplaces (Supl.biz, ПромПортал, Пульс цен) charge sellers, not buyers. The buyer gets search for free, with no automatic refresh of a nomenclature list.

### Gaps
- Prices for «Пульс цен», ПромПортал, Agora and price analytics on tender platforms (ЕИС-based services such as "цены по контрактам") were not retrieved.
- ABCP figures come from a snippet only; the page itself was not opened.

## 3. Electronic component price APIs (Octopart/Nexar, TrustedParts, Findchips) — analogue of the "part number → sellers and prices" model

### Takeaway
This category is closest to IQOT-Lite in data shape. Leaders charge **by the number of matched parts per month**. Nexar's official tiers are Evaluation 100 parts (lifetime), Standard up to 2,000/month, Pro up to 15,000/month, plus Enterprise. Prices are not published; by user reports Standard is about $500/month and Pro about $2,000/month, i.e. about $0.13–0.25 per part per month. TrustedParts gives an API to authorized-distributor inventory for free. Findchips has no API (third-party scrapers about $0.65 per 1,000 results).

### Cited Findings
- **Nexar** (official): Evaluation up to 100 matched parts, Standard up to 2,000, Pro up to 15,000, Enterprise custom.
  - Standard and Pro include only Advanced Search, Pricing, Availability, Images & Descriptions.
  - Lead time, lifecycle, datasheets and specs are in Enterprise only (and in Evaluation).
  - Prices are not shown on the page.
  — [nexar.com/compare-plans](https://nexar.com/compare-plans)
- Nexar counts every part object returned by a supply query against the monthly limit; usage resets on the 1st of the month. The Evaluation tier's 100 parts are lifetime, not monthly. — [Nexar support: Part Limits](https://support.nexar.com/support/solutions/articles/101000476314-part-limits-and-how-they-work)
- From "customer reports" (secondary source; the original page returned 403, so this is from the snippet): Standard ≈ $500/month, Pro ≈ $2,000/month. — [zenode.ai, 2026](https://zenode.ai/posts/the-nexar-api-what-engineers-need-to-know-in-2026)
- **TrustedParts.com** Inventory API (ECIA, authorized distributors only) is "completely free in almost all circumstances". — [trustedparts.com/docs/api](https://www.trustedparts.com/en/docs/api)
- **Findchips** has no official API. An Apify scraper costs from $0.65 per 1,000 product results. — [apify.com](https://apify.com/crawloop/findchips-scraper/api)

### Inferences
- Unit economics for a 20k part-number client on Nexar: Pro (15k) is not enough → Enterprise. At reported Pro rates (about $0.13 per part per month, refreshed monthly), 20k parts is roughly $2.7k/month, or about 250–270k ₽ at a 90–100 ₽/$ rate (estimate). Nexar's data is a ready-made aggregator over distributors who send feeds, not parsing of arbitrary sites.
- Counting "per matched part returned" is a natural billing unit for IQOT-Lite: a part number with found prices. It is closer to client value than "per site" or "per check".

### Gaps
- Official Nexar paid-plan prices were not confirmed (they are shown only in the authenticated portal). Nexar overage rules were not found.

## 4. MAP/RRP and brand monitoring for manufacturers — pricing per seller/SKU

### Takeaway
RRP-compliance monitoring in Russia ranges from SaaS (Priceva free to 96,000 ₽/month) to managed services (Kitcube: launch from 250,000 ₽ plus from 150,000 ₽/month). Even in the managed format, the channels and dealers to monitor are set by the client; automatic discovery of unauthorized sellers is not offered as a product.

### Cited Findings
- **Priceva**: tiers from free to 96,000 ₽/month; RRP monitoring for brands, marketplace analytics. — [sostav.ru 2026 review](https://www.sostav.ru/blogs/285009/72556), [vc.ru](https://vc.ru/services/1806441-obzor-servisov-po-monitoringu-cen-v-2025-godu)
- **Kitcube**: launch from 250,000 ₽ (catalog audit, SKU mapping, source setup, dashboard). Support from 150,000 ₽/month (monitoring, rule updates, new channels, reports). The client defines SKU, sources, competitors, dealers and RRP/corridors. — [kitcube.ru](https://kitcube.ru/)
- AllRival (free tier, RRP control), Pricing.su and MPSpace are mentioned as RRP tools; their prices were not extracted. — [sostav.ru](https://www.sostav.ru/blogs/285009/72556)
- **Price2Spy** (international): Starter, Basic and Premium. The page shows no prices; Premium is "upon request". Product matching and custom development are offered as extra services with undisclosed pricing. — [price2spy.com/pricing](https://www.price2spy.com/pricing.html)

### Inferences
- Managed format with mapping (Kitcube) is priced at about 150k ₽/month plus 250k ₽ setup. This anchors the upper bound for a "под ключ" service with SKU matching in Russia, against 10–40k ₽/month for self-service SaaS.

### Gaps
- Prices for Brandmonitor-type services (per seller / per SKU) were not found. No per-seller billing unit was found.

## 5. Automatic discovery of sellers by part number (no client URLs) — is it available, and is it charged extra?

### Takeaway
None of the services reviewed publishes a separate price for **discovering** supplier sites and products by part number. Where matching or source setup exists, it is folded into an expensive custom tier ("нормализация + маппинг", "SKU mapping") or offered as a quote-only add-on. International custom-scraping vendors (ScrapeHero, Grepsr, PromptCloud) also price **per site** and assume a known list of sites. Discovery by part number exists only in closed aggregators (Nexar, TrustedParts, ABCP), which work from distributors' own feeds rather than web search.

### Cited Findings
- **ScrapeHero** (official; the vendor calls these figures a baseline):
  - On Demand: from $550 per site per refresh (up to 2 sites, 1–5k pages per site, one-time).
  - Business: $199/month per site (up to 2 sites, 1–5k pages, monthly or weekly, separate setup fee).
  - Enterprise Basic: from $1,500/month (up to 4 sites, extra pages $650+ per million).
  - Enterprise Premium: from $8,000/month (any sites, $500+ per million pages).
  — [scrapehero.com/pricing](https://www.scrapehero.com/pricing/)
- **Grepsr**: Starter Pack $350 one-off; recurring pricing quote-only (record-based model). **PromptCloud**: about $150–250 to $5,000+/month including setup and volume. These are aggregator estimates. — [forage.ai](https://forage.ai/blog/scrapehero-alternatives/), [capterra](https://www.capterra.com/p/176555/Grepsr/)
- A 2026 comparison reports a spread of "$1.35 to $12.25 for the same 1,000 pages" across scraping vendors (from the headline only; not opened). — [marketintelligencetools.com](https://marketintelligencetools.com/reports/web-scraping-pricing/)
- Parsingsite includes "маппинг" only in the "сложный парсинг" tier, from 60,000 ₽/month vs 10,000 ₽/month for the simple tier. — [parsingsite.ru](https://parsingsite.ru/parsingceny)
- Imprice "identifies key competitors" by AI only from already-collected data (after about 3 months). — [imprice.ru/pars](https://imprice.ru/pars)
- Kitcube needs the sources and dealers specified up front; SKU mapping is part of the 250k ₽ launch. — [kitcube.ru](https://kitcube.ru/)
- The vc.ru review says most retail SaaS tools (Priceva, AllRival, Zoomos and others) have automatic product matching. This means matching products on sites the client named, not discovering new sites. — [vc.ru](https://vc.ru/services/1806441-obzor-servisov-po-monitoringu-cen-v-2025-godu)

### Inferences
- Discovery plus matching across a 10–30k industrial nomenclature has no direct published price analogue. The nearest proxies:
  - (1) The premium for mapping/normalization in custom parsing: about 6× the simple tier at Parsingsite (10k → 60k ₽/month).
  - (2) A managed service with mapping: Kitcube at 150k ₽/month plus 250k ₽ setup.
  - (3) Per-matched-part billing from component aggregators: about $0.13–0.25 per part per month, by reports for Nexar.
- Positioning implication: pure scraping is commoditized at kopecks per SKU per check in Russia. Pricing IQOT-Lite against it means anchoring on "per found/matched part number" or on the "managed service with mapping" tier, not on per-site or per-check rates.

### Gaps
- No vendor found (Russian or international) with a published surcharge for "we find the sources ourselves". DataWeave, Intelligence Node, Competera and Minderest (Google Shopping-style discovery) were not researched — they have no public prices and are retail-oriented.
- No public 2026 data on FL.ru rates or on the average order value for "парсинг под ключ" for B2B industrial distributors.
