# Pricing models in price monitoring and the unit economics of website price monitoring (for IQOT-Lite)

Context for the writer: IQOT-Lite = B2B, 10–30k industrial items, the system itself finds suppliers/sites and matches products (not client-supplied URLs), refresh every 10–30 days (baseline 14 days). Research date: 14.10.2026. Exchange rate USD/RUB was NOT verified in this research. Calculations below use an assumed range of 80–100 ₽/$ (flagged as an assumption).

---

## 1. Taxonomy of pricing models (what exists, who uses it, pros/cons)

### Takeaway
The market has five working models: (1) volume-tiered subscription by SKU count, (2) billing per tracked URL ("product × competitor page" pair), (3) usage/credits per page capture, (4) enterprise quote (catalog + coverage + services + modules), (5) add-on modules on top of the base (matching, API, screenshots, repricing). I found no pure outcome-based model ("pay per found price / per matched offer") among price-monitoring SaaS. The closest to it is a per-link fee for manual link matching (Priceva) and a per-capture credit (Extralt). Refresh frequency is almost always built into the tier, not a separate multiplier. Analysts estimate the real-time premium over daily refresh at +40–60%.

### Cited Findings
- A five-model taxonomy is described: SKU-tier pricing ("pay by monitored product count", suits fixed catalogs; risk: long-tail variants push you into a higher tier); URL/page pricing ("pay by monitored product or seller URLs"; marketplace seller pages multiply cost); enterprise quote (by catalog, coverage, services and modules, "opaque until procurement"); add-on modules (screenshots, product matching, API, reports, repricing, users cost extra); usage-based data layer (pay per page capture plus enrichment) — [Extralt: MAP monitoring cost](https://extralt.com/blog/map-monitoring-cost)
- Nine cost drivers in such products: number of SKUs, variants, source coverage, refresh cadence, marketplace depth, evidence capture, product matching, export/API, services/setup. Volume example: "500 variants × 8 seller pages × 30 daily checks = 120,000 observations per month" — [Extralt](https://extralt.com/blog/map-monitoring-cost)
- Usage/credit model (Extralt): Scale 10k $30 / 10,000 credits; 50k $100; 200k $300; 1M $1,000; 1 credit = 1 product-page capture, enrichment +1 credit. That is $3.0 → $1.0 per 1,000 captures — [Extralt](https://extralt.com/blog/map-monitoring-cost)
- Four market model types: tiered subscription (entry $99–299/mo; mid-market 5,000–25,000 SKUs typically $500–2,000/mo); usage-based (per SKU or API call, "$0.001–$0.05 per data point depending on update frequency and source complexity"); custom contracts ($30k to $150k+ per year, multi-year contracts give 15–25% discount); modular (base plus paid repricing/MAP/product-matching modules) — [PriceIntelGuru](https://www.priceintelguru.com/article/price-intelligence-software-cost) (aggregator/vendor article, numbers are ranges, not audited)
- Freshness drives price: real-time costs "40–60% more" than daily refresh — [PriceIntelGuru](https://www.priceintelguru.com/article/price-intelligence-software-cost)
- Price2Spy bills by URL: "price plans have been created based on the monitored number of URLs (a product URL is a unique product page on a monitored site)"; up to 8 checks per day on the top plan — [Price2Spy FAQ](https://www.price2spy.com/faq.html)
- Price2Spy add-ons billed separately: repricing, screenshots, GA4 integration, product matching service, Stealth IP traffic (usage-based), extra seats, custom development. Premium = "price upon request" — [Price2Spy pricing](https://www.price2spy.com/pricing.html)
- Prisync runs three parallel lines (URL-based / Channel-based / Hybrid) with the same SKU tiers (100 / 1,000 / 5,000). API = +20% of the fee; channels (marketplaces) are an add-on at +$100–200/mo per channel; above 5,000 SKUs it's custom — [Prisync pricing](https://prisync.com/pricing/)
- Frequency is bundled into the tier: Prisync URL-based Professional (100 SKUs) gets 3× daily, Premium/Platinum get daily; Pricefy gets 2× daily on all paid plans and 1× on Free — [Prisync](https://prisync.com/pricing/); [Pricefy pricing](https://www.pricefy.io/pricing)
- Russian market (Priceva): one site is analyzed free, the subscription starts "from $50/mo"; according to the search snippet of the official site, "manual link selection costs 5 rubles, an extra site costs 300 rubles" (the official tariff page returned 403 and could not be verified directly) — [startpack/search snippet](https://startpack.ru/application/priceva); [picktech (data dated about 2023, stale)](https://picktech.ru/product/priceva/)

### Inferences
- The industry standard is a subscription with a tier by catalog volume (SKU or URL) and bundled frequency. Per-URL billing is natural when the client supplies the links themselves. Per-SKU billing is natural when the vendor does the matching (the client doesn't care how many competitor pages are behind a SKU).
- For IQOT-Lite, where the system finds sources itself, per-URL billing is a poor fit for the client: the number of URLs is set by our discovery, and the client can't control or forecast it. A per-SKU tier (or per SKU with a "found at least N offers" guarantee) is more natural.
- The low refresh frequency (every 14–30 days, against the market's daily/2×/8× standard) is the opposite pole of the "frequency multiplier". By the same logic, the price per SKU should sit below the "daily" tiers, or the difference should be used as a margin buffer for the expensive discovery.
- An outcome-based element (per found/matched offer) has no direct analogues in this market. That's both a risk (the buyer isn't used to it) and a differentiator. The closest precedent is Priceva's paid manual link selection (≈5 ₽ per link).

### Gaps
- I couldn't confirm the current official Priceva tariffs (403). The "5 ₽ per link / 300 ₽ per site" numbers come from a snippet and an aggregator.
- I found no vendor that publicly charges per found price/offer (outcome-based) in price monitoring.

---

## 2. How matching/discovery is priced, and what coverage vendors claim

### Takeaway
Matching is sold in three ways: (a) included in the subscription as AI automatch (Pricefy, Price2Spy Quick Match for large marketplaces), (b) as a separate paid manual/hybrid service (Price2Spy, Priceva — per link), (c) inside an enterprise contract with human validation (Competera, Intelligence Node, TGN). Vendors claim 95–99%, but these are mostly precision or coverage on retail with EAN/GTIN identifiers. B2B/industrial specifics (no barcodes, custom article numbers) are not addressed in these claims. "Search for suppliers/sites" as a service (discovery) is essentially absent among the vendors: everywhere the client names the competitors.

### Cited Findings
- Price2Spy offers three matching modes: Automatch (AI; for Amazon, Google Shopping, Idealo, Prisjakt there's "Quick Match" — "setup fee waived, matching done free"), manual (done by the Price2Spy team), and hybrid. The client provides a product list, and Price2Spy finds the competitor cards — [Price2Spy FAQ](https://www.price2spy.com/faq.html)
- For Price2Spy's manual matching, aggregators cite 99.8% accuracy, but the per-URL price isn't published (quote-led) — [search summary: Altosight/PricingHunter etc.](https://altosight.com/price2spy-review/); [PricingHunter](https://www.pricinghunter.com/resources/price2spy-pricing) ("repricing, automatch, and additional reports often come at an extra cost")
- Pricefy includes AI Automatch in all plans, including Free. Two engines: Code Automatch (EAN/GTIN/UPC/SKU) and Quick Automatch (ML on name/brand/description/image) — [Pricefy pricing](https://www.pricefy.io/pricing); [Pricefy Automatch](https://www.pricefy.io/features/products-automatch)
- Vendor claims: Pricefy 95%+ match rate; Competera "95% quality matching" (AI plus human validation); Intelligence Node 99% matching accuracy; ScrapeWise 97% SKU coverage (Amazon EU) — [search summary across pricefy.io/competera.ai/scrapewise.ai](https://scrapewise.ai/blogs/product-matching-price-monitoring-2026); [Competera](https://competera.ai/products/competitive-data)
- Methodological caveat: you need to measure match rate (the share of the catalog with at least one link) AND precision (the share of correct links) together. Example vendor claims are 95% and 85% coverage, but "any vendor can inflate coverage by accepting looser matches, which silently destroys precision". Matching is "a continuous process, not a one-time setup task". The article covers only retail (electronics, fashion, pharmacy, etc.) and does NOT address B2B/industrial — [TGN Data](https://tgndata.com/how-product-matching-works-in-price-monitoring)
- The standard method stack: EAN/GTIN → SKU/MPN extraction → attribute comparison → AI/image, with human validation for low confidence — [TGN Data](https://tgndata.com/how-product-matching-works-in-price-monitoring)

### Inferences
- The 95–99% claims are retail figures with barcodes and known competitor sets. They can't be carried over to industrial items without EANs (IQOT's case: the supplier's article numbers ≠ ours, as noted in the project's own experience with name-based matching). For IQOT-Lite you should expect a noticeably lower share of items with at least one public price, and plan pricing around actual coverage.
- Hence the case for a "base fee + payment for actually covered items" or a "price per covered item" model: the client doesn't pay for items where no public price was found (common in B2B: "price on request").
- Discovery (finding suppliers) is a unique cost component with no analogue in vendor price lists. It's reasonable to price it either as a one-time setup fee (onboarding a catalog) or to amortize it into the subscription with a minimum term (annual contract).

### Gaps
- I found no public coverage figures (share of SKUs with at least one found price) for B2B/industrial catalogs without identifiers.
- No published per-URL price for Price2Spy's manual matching service.

---

## 3. Volume discounts: how the per-SKU price falls with volume; annual vs monthly

### Takeaway
The per-SKU price falls by an order of magnitude between 100 and 1,000 SKUs and by another 2–5× by 5–15k. Above 5–25k SKUs almost everyone moves to a custom quote. At 10–30k SKUs, public "cheap" SaaS (Pricefy) charges about $0.01–0.02 per SKU per month, while mid-market/enterprise charges about $0.02–0.10+. The annual discount is typically 15–25%.

### Cited Findings
- Pricefy (monthly / annual): Starter $49 / $37 for 100 SKUs; Pro $99 / $74 for 2,000; Business $189 / $142 for 15,000; Enterprise $499 / $374 for 25,000. Annual = −25%. Per SKU on annual billing: $0.37 → $0.037 → $0.009 → $0.015 (non-monotonic: Enterprise is more expensive per SKU than Business) — [Pricefy pricing](https://www.pricefy.io/pricing)
- Prisync URL-based: $99 / 100 SKUs ($0.99), $199 / 1,000 ($0.20), $399 / 5,000 ($0.08). Hybrid: $299 / $599 / $799 ($2.99 → $0.60 → $0.16). Annual prices aren't on the page. >5,000 is custom. 14-day trial without a card — [Prisync pricing](https://prisync.com/pricing/)
- Price2Spy (as of May 2026): Starter $39.95 / 500 URLs ($0.08/URL), Starter upgraded $57.95 / 2,000 URLs ($0.029), Basic $157.95 / 2,000 URLs ($0.079), Premium custom — [PricingHunter, upd. 06.05.2026](https://www.pricinghunter.com/resources/price2spy-pricing). Price2Spy itself no longer shows prices on its site — [Price2Spy pricing](https://www.price2spy.com/pricing.html). It has a "special offer for 1-year upfront payments" — [Price2Spy FAQ](https://www.price2spy.com/faq.html)
- Market ranges: up to 1,000 SKUs $99–499/mo; 1,000–25,000 SKUs $500–2,500/mo; 25,000–250,000 SKUs $2,500–8,000/mo; multi-year contracts −15–25% — [PriceIntelGuru](https://www.priceintelguru.com/article/price-intelligence-software-cost)

### Inferences
- Implied per-SKU price from the PriceIntelGuru ranges for 10–30k SKUs: about $0.02–0.25 per SKU per month (enterprise level). "Self-serve cheap" (Pricefy) is $0.01–0.02. That's the corridor IQOT-Lite will be compared against, even though those products don't do discovery and refresh daily.
- The steep fall in unit price with volume is mostly a fall in the vendor's margin and fixed costs (onboarding, support), not in variable cost. Variable cost per SKU is almost linear (see section 4). So the floor for IQOT-Lite's per-SKU price is set by variable cost, not by competitors.
- An annual contract with a 15–25% discount is the market norm, and it's especially useful for IQOT-Lite: it lets the expensive initial discovery be amortized.

### Gaps
- No data on Russian vendors' volume discounts (Priceva and others): public tariffs are unavailable or stale.

---

## 4. Cost side: unit economics of website monitoring (RUB)

### Takeaway
At a 14-day refresh, variable cost is roughly 1–3 ₽ per item per month (with discovery amortized), i.e. about 20–90k ₽ per month for 20k items, provided that: search runs in Yandex Search API deferred mode, LLM extraction uses gpt-4o-mini/gpt-5-nano (ideally Batch −50%), and most B2B supplier sites are fetched without residential proxies. The biggest items are LLM tokens per page and page volume during discovery. Search in deferred mode costs almost nothing. Search in sync mode is 16× more expensive.

### Cited Findings — official tariffs
- **Yandex Search API** (billing unit: one request; prices per 1,000, incl. VAT): synchronous — day **488 ₽**, night (00:00–07:59 MSK) **366 ₽**; **deferred — day 30.5 ₽, night 25.41 ₽**; image search 915 ₽; "smart snippets" (RU) 1,500 ₽; synchronous with a generative answer 5,080 ₽. Requests that fail with a server or authorization error aren't billed. No free tier or grant is mentioned on the page — [Yandex AI Studio: Search API pricing](https://aistudio.yandex.ru/docs/ru/search-api/pricing) (the docs moved from yandex.cloud/ru/docs/search-api/pricing via a 301 redirect)
- **SerpApi** (Google etc.): Free 250/mo; Starter $25 / 1,000 ($25 per 1k); Developer $75 / 5,000 ($15); Production $150 / 15,000 ($10); Big Data $275 / 30,000 ($9.17); Searcher $725 / 100,000 ($7.25); Volume $1,475 / 250k ($5.90); Infrastructure $2,750 / 500k ($5.50); Enterprise from $3,750 / 1M ($3.75) down to ~$1.96 per 1k — [SerpApi pricing](https://serpapi.com/pricing)
- **OpenAI** (per 1M tokens, input / cached / output): gpt-4o-mini $0.15 / $0.075 / $0.60; gpt-4.1-mini $0.40 / $0.10 / $1.60; gpt-4.1-nano $0.10 / $0.025 / $0.40; gpt-5-mini $0.25 / $0.025 / $2.00; gpt-5-nano $0.05 / $0.005 / $0.40. Batch/Flex = −50% (gpt-4o-mini batch: $0.075 / $0.0375 / $0.30) — [OpenAI API pricing](https://developers.openai.com/api/docs/pricing)
- **Residential proxies with RU geo**: RuCaptcha 330 ₽/GB (1 GB) → 300 (10 GB) → 250 (30 GB) → 190 (100 GB) → 170 (200 GB) → 150 (1 TB) → 120 (3–5 TB) → 100 ₽/GB (10 TB) — [RuCaptcha residential](https://rucaptcha.com/proxy/residential-proxies). Market overview: RU-geo residential from $5–7/GB; Proxy-Seller $3.5 (290 ₽)/GB — [toolfox / search summary](https://toolfox.ru/services/proksi-servisy/proksi-rossiya)
- Benchmark "capture as a service": $1–3 per 1,000 product-page captures (Extralt credits) — [Extralt](https://extralt.com/blog/map-monitoring-cost)

### Model calculation (my inferences, assumptions stated explicitly)
Assumptions: 80–100 ₽/$ (NOT verified); 1 page → text truncated to ~4,000 input tokens plus ~300 output tokens; plain HTTP fetch of a page ≈ 0.1–0.3 MB of HTML; headless with media blocked ≈ 0.5–1 MB.

| Component | Calculation | ₽ per unit |
|---|---|---|
| Yandex search, deferred (day) | 30.5 ₽ / 1,000 | **0.03 ₽ / query** |
| Yandex search, sync (day) | 488 ₽ / 1,000 | 0.49 ₽ / query |
| Google via SerpApi (Searcher) | $7.25 / 1,000 | ≈0.6–0.7 ₽ / query |
| LLM extraction gpt-4o-mini, standard | 4,000×0.15 + 300×0.60 = $0.00078 | ≈0.06–0.08 ₽ / page |
| same in Batch (−50%) | $0.00039 | ≈0.03–0.04 ₽ / page |
| gpt-5-nano, standard (excluding reasoning tokens) | 4,000×0.05 + 300×0.40 = $0.00032 | ≈0.03 ₽ / page |
| residential proxy, plain HTML 0.2 MB at 250 ₽/GB | 0.2/1024×250 | ≈0.05 ₽ / page |
| residential proxy, headless 0.75 MB at 250 ₽/GB | | ≈0.18 ₽ / page |
| own IP (no proxy) | | ~0 ₽ (only compute) |

**Discovery (one-time per item):** 2–3 search queries (deferred: ~0.1 ₽; sync: ~1–1.5 ₽) + 8–10 candidate pages × (fetch + LLM matching 0.06–0.25 ₽) ≈ **0.6–2.5 ₽ per item**. With rediscovery once per quarter that's ≈0.2–0.8 ₽ per item per month.

**Refresh (14 days ≈ 2.17 cycles/month):** with K = 3 matched offers per item, each 1 fetch + LLM extraction ≈ 0.06–0.25 ₽ → 0.2–0.75 ₽ per cycle → **≈0.4–1.6 ₽ per item per month**. If after the first extraction the price is pulled by a learned selector/regex and the LLM is only a fallback, the LLM part drops by several times.

**Total variable cost:** ≈ **0.6–2.5 ₽ per item per month** (mid-range ≈1–1.5 ₽). For 20,000 items that's ≈ **12–50k ₽ per month** (mid-range ≈20–30k ₽), plus server/headless Chrome compute (Chrome is memory-hungry: the project already saw it needs RAM ≥ 3.8 GB for one renderer, per the project's CLAUDE.md) and operator time on low-confidence matching (not counted).

### Inferences
- Variable cost (~1–1.5 ₽ per item per month at 80–100 ₽/$ ≈ $0.01–0.02) is in the same order as the retail price of cheap Western SaaS for 15–25k SKUs (Pricefy $0.009–0.015 per SKU per month on annual billing). So IQOT-Lite can't compete on "cheap per SKU". The price has to be anchored on the value of discovery plus B2B coverage, at the enterprise level ($0.02–0.25 per SKU per month according to PriceIntelGuru), i.e. roughly 2–25 ₽ per item per month, giving a gross margin of 50–90%.
- The key cost levers are: (1) deferred mode only for Yandex search (16× cheaper than sync; latency isn't critical at a 14-day refresh); (2) Batch API for LLM (−50%); (3) a cascade "cheap HTTP → headless → residential proxy" (the project already has HTTP-first + headless fallback in WebPageFetcher); (4) caching of selectors/extraction per site, since many items sit on the same supplier site.
- Cost scales mainly with K (offers per item) and page volume during discovery, not with refresh frequency. Moving from 30 to 10 days roughly triples only the refresh part. That supports a frequency multiplier in pricing (e.g. 30 days = base, 14 days ×1.3–1.5, 10 days ×1.7–2.0), in the spirit of the market's "+40–60% for freshness".

### Gaps
- The USD/RUB rate for October 2026 wasn't verified. All ₽ conversions of $ prices use an assumed 80–100 ₽/$.
- I didn't check the latency/SLA of Yandex Search API deferred mode or its limits (RPS/daily quotas).
- The project's OpenAI proxy markup (ai.lazylift.ru) is unknown, so real LLM costs may be higher than list price.
- No data on page size and the share of "JS/anti-bot" sites among Russian B2B suppliers. The fetch/proxy cost is an estimate.
- Google Programmable Search JSON API: current pricing wasn't checked (the SerpApi benchmark was used instead).

---

## 5. Freemium/trials and how B2B buyers prefer to buy (subscription vs pay-per-result)

### Takeaway
In price monitoring, a free trial (14 days, no card) or a small freemium plan (Pricefy 50 SKUs / Priceva one site) is standard, and payment is almost always a subscription. Across SaaS more broadly, usage-based pricing is growing, but most (61%) usage-based companies use a hybrid "subscription with included volume + overage", because procurement in large B2B wants budget predictability.

### Cited Findings
- 14-day free trial without a card: Prisync ("14 days for free. No credit cards or contracts needed"), Price2Spy — [Prisync](https://prisync.com/pricing/); [Price2Spy FAQ](https://www.price2spy.com/faq.html)
- Freemium: Pricefy Free — 50 SKUs, 5 competitors, daily, no card, free onboarding — [Pricefy pricing](https://www.pricefy.io/pricing); Priceva — one site free — [startpack](https://startpack.ru/application/priceva)
- Per OpenView, 38% of SaaS companies use some form of usage-based pricing (27% in 2021); 61% of companies with a usage component use a hybrid model; large customers' procurement wants predictability and control over the bill — [search summary: OpenView/culta.ai/revops.io](https://openviewpartners.com/blog/usage-based-pricing-trends/); [RevOps: Kyle Poyar (OpenView)](https://www.revops.io/blog/usage-based-pricing-with-kyle-poyar-of-openview) (the 38% figure was quoted in a secondary source, [culta.ai](https://culta.ai/blog/usage-based-pricing-saas); the original OpenView survey is from 2022–2023, so it's **stale**, and OpenView has stopped publishing)

### Inferences
- For IQOT-Lite the optimal shape is a hybrid: an annual or monthly subscription by tier of "covered items" (or catalog items with a coverage guarantee) plus a one-time onboarding/discovery fee for the initial matching, plus a frequency multiplier. Pure pay-per-found-price is unusual for buyers in this category and hurts budget predictability for the client's purchasing/procurement department.
- Trial format: a free pilot on a sample of 200–500 client items showing actual coverage (the share of items with a found price). That directly addresses the main B2B risk of "how many of my items will actually be found", which retail vendors cover with 95%+ claims.

### Gaps
- No direct surveys of B2B industrial buyers (procurement departments in RU) on their preferences in buying price-monitoring tools.
- The OpenView figures are from 2022–2023. I found no fresher primary data for 2025–2026.
