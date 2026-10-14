# International SaaS price monitoring / competitive price intelligence: pricing models and tariffs (as of Oct 2026)

Scope: Prisync, Price2Spy, Pricefy, Priceva, Boardfy, Dealavo, Omnia Retail, Minderest, Skuuudle, Netrivals, Competera, Wiser, Intelligence Node, DataWeave, Pricefx/PricingHUB, plus scraping-API cost floors (Zyte, Bright Data, Apify). Benchmark target: a B2B product monitoring public prices on supplier sites for 10–30k items.

Source quality legend: **[official]** = vendor pricing page fetched Oct 2026; **[store]** = vendor's own marketplace listing; **[aggregator]** = Capterra/GetApp/third-party review or competitor comparison page (treat as indicative; may be stale or wrong).

## 1. Pricing units: what vendors charge for

### Takeaway
The market splits into (a) transparent self-serve tools that price by **number of products/SKUs or product URLs** in fixed tiers (Prisync, Price2Spy, Pricefy, Boardfy), with a minority pricing by **price checks** (Priceva) or **per competitor** (Boardfy direct-website plan); and (b) enterprise "price intelligence" vendors (Competera, Wiser, Intelligence Node, DataWeave, Minderest, Skuuudle, Omnia Enterprise) that price by scope (SKUs × markets × channels × features) via custom quotes, typically from ~€5–10k/year up to six figures. Setup fees are rarely published; self-serve tools advertise free onboarding.

### Cited Findings
**Self-serve, per-product/URL tiers**
- Prisync (URL-based model): Professional $99/mo up to 100 products, unlimited competitors, 3x daily updates; Premium $199/mo up to 1,000 products (dynamic pricing engine, daily alerts, API*); Platinum $399/mo up to 5,000 products (instant notifications, price history, MAP monitoring). Enterprise on request above 5,000 products. "API access will be charged for an extra 20% on top of your monthly subscription fee." No setup fee mentioned; 14-day trial; no annual price listed — [Prisync pricing, official](https://prisync.com/pricing/)
- Prisync also sells a "Channel based" model ($199 / $399 / $599 for 100 / 1,000 / 5,000 products, 1 channel included, +$100–200/mo per additional channel, daily updates) and a "Hybrid" model ($299 / $599 / $799, 3x daily) — [Prisync pricing, official](https://prisync.com/pricing/)
- Price2Spy: price is per **product URL** monitored. Basic 2K = $157.95/mo, up to 2,000 product URLs, up to 80 websites, standard price checks; Premium 10K = $788.95/mo, up to 10,000 URLs, unlimited websites, "multiple price checks per day"; billed USD on 30-day cycle; 14-day trial — [Price2Spy on Shopify App Store, store](https://apps.shopify.com/price2spy)
- Price2Spy's own pricing page (Oct 2026) no longer shows numbers: Starter / Basic / Premium tiers, Premium "Price upon request", up to 8 price checks daily and API only in Premium; Starter limited to 10 competitors — [Price2Spy pricing, official](https://www.price2spy.com/pricing.html). Reviewer confirms pricing has become "quote-led"; directories still listing a $39.95/mo 500-URL starter tier are outdated — [Altosight Price2Spy review, aggregator, verified Sep 2026](https://altosight.com/price2spy-review/)
- Pricefy (per SKU, unlimited competitors, 2x daily updates): Free $0 – 50 SKUs, 5 competitors, 1x daily; Starter $49/mo ($37 annual) – 100 SKUs; Pro $99/mo ($74 annual) – 2,000 SKUs; Business $189/mo ($142 annual) – 15,000 SKUs; Enterprise $499/mo ($374 annual) – 25,000 SKUs; above that custom. Annual saves 25%; "free onboarding and setup" — [Pricefy pricing, official](https://www.pricefy.io/pricing)
- Boardfy (e-commerce plans, per product): Standard €39/mo (€35 annual) 200 products; Advanced €79 (€69) 1,000 products; Professional €159 (€129) 2,500 products; Enterprise from €199/mo. Brand (MAP/MSRP) plans: €69/100 products, €129/250, €199/500, Enterprise from €249 for >500 — [GetApp/Capterra/search summary, aggregator](https://www.capterra.com/p/177435/Boardfy/)
- Boardfy's current official page shows a different structure: marketplaces/price-comparison sites priced by "products per channel/country"; **direct websites priced per competitor: base €39.90/month per competitor**; annual = 2 free months; add-ons Feed Optimizer / Dynamic Pricing (per channel/country) and API (price not shown); updates every 30 min to 3 h; 7-day trial; no setup fee mentioned — [Boardfy pricing, official](https://www.boardfy.com/ecommerce-pricing/). Note: conflicts with the per-product tiers above (likely older).

**Per price check (closest to a usage/frequency model)**
- Priceva: Starter free (6,200 checks, 20 products, 20 sites); Business $99/mo – 35,000 price checks/mo, unlimited products, 30 sites, 2 regions; Pro $199/mo – 100,000 checks, 40 sites, unlimited regions; Enterprise custom 700K+ checks with SLA. Overage $0.0029/check (Business), $0.002/check (Pro); extra site $2.5; auto-matching by codes "from $25 per site"; manual matching assistance $0.1 per item; API and 90-day history in all plans — [Priceva subscription, official](https://priceva.com/subscription)

**Mid-market subscriptions with published starting points**
- Omnia Retail: SMB from €399/month (single shop, up to 5 users, AI price monitoring + AI dynamic pricing); Enterprise custom (multi-shop, pricing consultants). "The total cost will depend on how large of a product assortment you may have, how many markets it is in and the size of your business." No SKU limits, frequency or setup fees published — [Omnia Retail pricing, official](https://www.omniaretail.com/pricing)
- Dealavo: from €220/month (Capterra wording "per feature per month"), 7-day trial; one source says it covers "1,000 product prices across 5 websites" — [Software Advice / GetApp / Capterra listings, aggregator](https://www.softwareadvice.com/competitor-price-monitoring/dealavo-profile/) (official pricing URL returned 404 in Oct 2026)
- Minderest: "starts at €500 per month per feature on the Annual Plan", billed monthly; real pricing quote-only after demo — [Software Advice / Capterra summary, aggregator](https://www.softwareadvice.com/pricing-optimization/minderest-profile/) (Capterra page blocked fetch; not independently verified)

**Enterprise / custom quote**
- Competera: no public prices; scope-based (SKUs, markets, channels, edition, add-ons, deployment). "Competitive Data" product = **pay-per-use billing**, SLA-controlled monitoring, AI product matching, "live within 14 days"; Pricing Platform implementation up to 60 days — [Competera pricing, official](https://competera.ai/products/pricing-platform/pricing). Third-party cites $50K–$300K/year, unverified by vendor — [checkthat.ai / search summary, aggregator](https://checkthat.ai/brands/competera)
- Intelligence Node: "begins at a $5,000/month enterprise commitment" — [checkthat.ai via search, aggregator, unverified](https://checkthat.ai/brands/dataweave); also cited as "Intelligence Node from $5,000" — [3my.org MAP cost article, aggregator](https://3my.org/map-monitoring-software-cost/)
- DataWeave: sales-led, no public tiers/minimums; deal sizes assumed six figures (speculative) — [checkthat.ai, aggregator](https://checkthat.ai/brands/dataweave)
- Wiser: no published pricing, "Enterprise pricing available upon request"; claims updates "as often as every 15 minutes", 600,000+ stores — [Wiser price execution page / G2 summary](https://www.wiser.com/products/price-execution/)
- Skuuudle: custom quotes "starting at $10k/year" — [Pricefy vs Skuuudle comparison, competitor page, updated Apr 2026](https://www.pricefy.io/comparisons/skuuudle-vs-pricefy); customers pay "between $10k/£10k per year and $1m/£1m", typical first-time customer "low tens of thousands", large retailers six figures — [search summary of Skuuudle/aggregator pages](https://www.capterra.com/p/171527/Skuuudle/). A search snippet also claimed "Single £89/mo, SME £999/mo" tiers — **unverified and possibly confused**; official pricing page returned HTTP 500.
- Netrivals: acquired by Lengow (April 2022); now a module of Lengow, sold/billed through Lengow; netrivals.com/pricing redirects to Lengow demo form — no public price — [TechCrunch](https://techcrunch.com/2022/04/26/lengow-acquires-e-commerce-intelligence-startup-netrivals/); [Dealavo NetRivals review](https://dealavo.com/en/netrivals-review-and-alternatives/)
- Pricefx (price management/optimization, not monitoring): subscriptions $100,000–$3.5M/yr tied to Revenue Under Management (triangulated, Apr 2026); other estimate $50k–150k/yr SaaS, $150–250k first year incl. implementation — [G2 / SelectHub / datatoolindex summaries, aggregator](https://www.g2.com/products/pricefx/pricing)
- General: "enterprise platforms in this category almost all quote custom, while the self-service tools publish plans or a defined per-SKU model" — [GrowByData MAP tools comparison](https://growbydata.com/best-map-monitoring-software-10-tools-compared/); named starting prices run "$0 a month to about $800 a month"; enterprise MetricsCart from $1,000/mo, Intelligence Node from $5,000 — [3my.org, aggregator](https://3my.org/map-monitoring-software-cost/)

### Inferences
- Dominant transparent unit is "monitored product (your SKU)" with competitors unlimited (Prisync, Pricefy) or "monitored URL" (Price2Spy = SKU × competitor). Frequency is a tier attribute, not a separate meter (except Priceva, where it is implicit via checks).
- Public self-serve price lists effectively stop at 5k (Prisync), 10k (Price2Spy), 25k (Pricefy) items; anything at 30k+ goes to custom quote everywhere.

### Gaps
- PricingHUB: no pricing information found.
- Official Dealavo, Skuuudle, Minderest, Intelligence Node pricing pages not retrievable; figures rely on aggregators.
- Setup/onboarding fees for enterprise vendors are not public anywhere found.

## 2. Concrete prices at ~1k / ~10k / ~30k items and effective price per SKU / URL per month

### Takeaway
Effective list price per monitored item per month: ~$0.20 (Prisync at 1k) → ~$0.08 (Prisync 5k, Price2Spy 2k–10k) → ~$0.013–0.02 (Pricefy 15–25k, unlimited competitors). For a check-based model (Priceva), daily monitoring of 10k–30k URLs costs ~$0.06/URL/month. Enterprise vendors start at ~€400–5,000/month regardless of size.

### Cited Findings (list prices) and computed unit prices
| Vendor (model) | ~1,000 items | ~10,000 items | ~30,000 items | Frequency |
|---|---|---|---|---|
| Prisync URL-based [official](https://prisync.com/pricing/) | $199/mo → $0.199/product | n/a (Platinum $399 = 5,000 → $0.080/product); 10k = enterprise quote | enterprise quote | 3x daily (Professional); "unlimited competitors" so per-URL lower |
| Price2Spy [store](https://apps.shopify.com/price2spy) | Basic 2K $157.95 → $0.079/URL (at full 2k) | Premium 10K $788.95 → $0.079/URL | not published (custom) | Basic: standard; Premium: multiple/day (up to 8/day per [official](https://www.price2spy.com/pricing.html)) |
| Pricefy [official](https://www.pricefy.io/pricing) | Pro $99 (2,000 SKUs) → $0.050/SKU at cap; $0.099 if only 1k used | Business $189 (15,000) → $0.019/SKU at 10k use, $0.0126 at cap | Enterprise $499 (25,000) → $0.020/SKU; >25k custom | 2x daily |
| Boardfy e-com [aggregator](https://www.capterra.com/p/177435/Boardfy/) | Advanced €79 (€69 annual) → €0.079/product | Enterprise from €199 (custom) | custom | 30 min–3 h per [official](https://www.boardfy.com/ecommerce-pricing/) |
| Boardfy direct-website [official](https://www.boardfy.com/ecommerce-pricing/) | €39.90 per competitor/mo (×10 competitors = €399) | product dependency not shown | — | — |
| Omnia SMB [official](https://www.omniaretail.com/pricing) | from €399/mo | custom | custom | "real-time" claim |
| Dealavo [aggregator](https://www.softwareadvice.com/competitor-price-monitoring/dealavo-profile/) | from €220/mo (~1,000 products × 5 sites cited) → ~€0.044/URL | custom | custom | — |
| Minderest [aggregator](https://www.softwareadvice.com/pricing-optimization/minderest-profile/) | from €500/mo per feature (annual) | custom | custom | — |
| Intelligence Node [aggregator](https://3my.org/map-monitoring-software-cost/) | from $5,000/mo | | | |
| Skuuudle [competitor page](https://www.pricefy.io/comparisons/skuuudle-vs-pricefy) | from $10k/yr (~$833/mo) | | | |

**Priceva (check-based) computed from [official](https://priceva.com/subscription) prices** (assume 1 URL = 1 check per update, 30 days):
- 1,000 URLs daily = 30,000 checks → Business $99 → ~$0.099/URL/mo.
- 10,000 URLs daily = 300,000 checks → Pro $199 + 200,000 × $0.002 = **$599/mo → ~$0.060/URL/mo**; weekly (~43,000 checks) → Business $99 + ~8,000 × $0.0029 ≈ $122/mo → ~$0.012/URL/mo.
- 30,000 URLs daily = 900,000 checks → $199 + 800,000 × $0.002 = **$1,799/mo → ~$0.060/URL/mo** (Enterprise 700K+ tier would likely be negotiated lower); weekly (~129,000 checks) → Pro $199 + 29,000 × $0.002 ≈ $257/mo → ~$0.009/URL/mo.
- Whether 30 sites/40 sites caps bind depends on supplier count (extra site $2.5 each).

**Price2Spy add-ons** [store](https://apps.shopify.com/price2spy): Basic 2K — Automatch $54, Indirect repricing $50, Direct repricing $100, Screenshots $100; Premium 10K — Automatch $135, Indirect repricing $125, Direct repricing $250, Screenshots $250. Worked example: 200 products × 5 sites on Basic 2K with 3 modules = $411.95/mo — [Altosight](https://altosight.com/price2spy-review/). With Automatch, 10k URLs = $923.95/mo → $0.092/URL.

**Prisync API surcharge**: +20% of subscription → Platinum with API $478.80/mo (computed from [official](https://prisync.com/pricing/)).

### Inferences
- Note the unit mismatch: Pricefy/Prisync count *own SKUs* with unlimited competitors, so cost per (SKU×competitor) URL may be 3–10× lower than shown; Price2Spy and Priceva count URLs/checks, i.e., true per-observation cost.
- Extrapolating Price2Spy linearly to 30k URLs ≈ $2,370/mo (no published tier; real quote likely discounted).
- For a 10–30k-item B2B catalog with daily checks, transparent-tool list prices cluster at **$200–800/mo for 10k and ~$500–2,400/mo for 30k**, i.e., roughly **$0.02–0.08 per item/URL per month**; enterprise vendors sit at $800–5,000+/mo minimums.

### Gaps
- No vendor publishes price scaling by frequency directly (e.g., daily vs weekly price for the same catalog); only Priceva's check meter allows computing it.
- No public prices at 30k items for any monitoring-only vendor except via extrapolation.

## 3. Who finds competitors / URLs: client-provided vs automatic matching and discovery

### Takeaway
Matching is the main hidden cost: cheap tools either include AI auto-matching (Pricefy, Boardfy) or charge it as an add-on/service (Price2Spy Automatch $54–135/mo, Priceva $25/site code-matching or $0.10/item manual). Enterprise vendors bundle matching into the quote.

### Cited Findings
- Pricefy: all plans include "AI product matching", "AI Automatch and Competitor Discovery" — [Pricefy pricing, official](https://www.pricefy.io/pricing)
- Price2Spy: "quick match" and bulk import in Starter; Automatch is a paid module (Basic $54/mo, Premium $135/mo); "Product Matching" also offered as a service — [Price2Spy official](https://www.price2spy.com/pricing.html); [Shopify listing](https://apps.shopify.com/price2spy)
- Priceva: auto-matching by codes "from $25 per site"; manual matching assistance "$0.1" per item; "analog comparison" in Business+ — [Priceva official](https://priceva.com/subscription)
- Boardfy: "Products matching service included (free or paid professional matching available)" — [Boardfy official](https://www.boardfy.com/ecommerce-pricing/)
- Competera Competitive Data: "AI-powered product matching", live within 14 days, pay-per-use — [Competera official](https://competera.ai/products/pricing-platform/pricing)
- Prisync "URL based" model implies client-supplied competitor URLs; "Channel based" monitors per sales channel (+$100–200/mo per extra channel) — [Prisync official](https://prisync.com/pricing/)
- Dealavo tracks Amazon, Google Shopping, Idealo — [Software Advice, aggregator](https://www.softwareadvice.com/competitor-price-monitoring/dealavo-profile/); Dealavo offers a free Google Shopping monitoring report — [Dealavo](https://dealavo.com/en/google-shopping-monitoring/)

### Inferences
- Prisync's "Channel" model likely refers to marketplace/Google Shopping channels (inference from naming; page summary did not define channels).
- For B2B supplier sites (no GTIN/EAN, vendor-specific part numbers), code-based auto-matching is weaker; expect manual/semimanual matching costs comparable to Priceva's $0.10/item (i.e., $1,000–3,000 one-off for 10–30k items).

### Gaps
- Whether Prisync charges for matching or offers discovery was not confirmed.
- Google-Shopping-based discovery pricing (Price2Spy auto-discovery specifically) not found.

## 4. What is included: history, API, alerts, repricing, MAP

### Takeaway
History and alerts are standard; API and MAP are usually gated to mid/upper tiers or charged extra; repricing is a paid upsell in most tools.

### Cited Findings
- Prisync: API in Premium+ at +20% surcharge; price history and MAP monitoring only in Platinum ($399) — [Prisync official](https://prisync.com/pricing/)
- Price2Spy: Basic adds historical reports, MAP monitoring, instant alerts, marketplace monitoring; API, up to 8 checks/day, 3 seats only in Premium (quote) — [Price2Spy official](https://www.price2spy.com/pricing.html); repricing $50–250/mo add-on — [Shopify listing](https://apps.shopify.com/price2spy)
- Pricefy: notifications/reports from Starter; dynamic repricing from Pro; autopilot repricing and MAP/MSRP monitoring from Business ($189) — [Pricefy official](https://www.pricefy.io/pricing)
- Priceva: API, 90-day history, repricing, marketplace monitoring in all plans incl. free — [Priceva official](https://priceva.com/subscription)
- Boardfy: all plans include MSRP monitoring, alerts, price history, unlimited users; API and dynamic pricing are add-ons — [Boardfy official](https://www.boardfy.com/ecommerce-pricing/)
- Omnia SMB bundles monitoring + AI dynamic pricing — [Omnia official](https://www.omniaretail.com/pricing)

### Inferences
- A B2B buyer who needs API + history + MAP at 5k items pays Prisync ~$479/mo vs Pricefy $189/mo vs Priceva $199+overage — API access is a meaningful price differentiator.

### Gaps
- Retention length of price history beyond Priceva (90 days) not published by others.

## 5. Cost floor: scraping-API pricing (build-your-own)

### Takeaway
Raw data acquisition for 10k URLs daily (≈300k requests/mo) costs ~$18–450/mo depending on site difficulty and provider, i.e., ~$0.002–0.045 per URL/month; JS-rendered/anti-bot sites push it to $150–2,300/mo. This is the floor beneath SaaS list prices of $0.02–0.08/URL.

### Cited Findings
- Zyte API per 1,000 successful responses — HTTP: PAYG $0.13–$1.27; $100/mo commitment $0.10–$0.95; $200 $0.08–$0.76; $500 $0.06–$0.61. Browser-rendered: PAYG $1.01–$16.08; $500 commitment $0.48–$7.68. $5 free credit; volume discounts via sales — [Zyte pricing, official](https://www.zyte.com/pricing/)
- Bright Data Web Scraper API: $1.50 per 1,000 records PAYG; Scale $499/mo ≈ 384,000 records, extra $1.30/1k; $999/mo ≈ 908,000; $1,999/mo ≈ 1,999,000 at $1.00/1k; 5,000 free records/mo (as of Sep 2026) — [costbench / katteb summaries, aggregator](https://costbench.com/software/web-scraping/bright-data/) (official page returned 403)
- Apify: Starter $19, Scale $199 ($179 annual), Business $999 ($899); compute $0.20/$0.16/$0.13 per CU; residential proxy $8/$7.50/$7 per GB; SERP proxy $2.50/$2/$1.70 per 1,000 SERPs — [Apify pricing, official](https://apify.com/pricing); Store actors mostly $1–10 per 1,000 results — [search summary of Apify docs](https://docs.apify.com/academy/actor-marketing-playbook/store-basics/how-actor-monetization-works)
- ScrapeWise.ai pay-as-you-go €0.15 per 1,000 pages — [scrapewise.ai blog, vendor-authored](https://scrapewise.ai/blogs/competitive-price-monitoring-tools-2026)

### Computed floors (from cited unit prices)
| Volume | Requests/mo | Zyte HTTP ($500 tier, simple–advanced) | Zyte browser ($500 tier) | Bright Data |
|---|---|---|---|---|
| 10k URLs daily | 300,000 | $18–183 | $144–2,304 | ~$450 PAYG / $499 Scale plan |
| 30k URLs daily | 900,000 | $54–549 | $432–6,912 | $999 plan (908k) |
| 10k URLs weekly | ~43,000 | ~$3–26 | ~$21–330 | ~$65 PAYG |
| 30k URLs weekly | ~129,000 | ~$8–79 | ~$62–991 | ~$194 PAYG |
Per URL/month at daily frequency: Zyte HTTP $0.002–0.018; Bright Data ~$0.045; browser rendering up to ~$0.23.

### Inferences
- Simple B2B supplier sites (static HTML catalogs) fall in the cheapest tiers; SaaS gross margin is in matching, parsing maintenance, UI/alerts, not in fetch cost.
- Priceva's $0.002/check overage is close to scraping-API cost, signalling commodity pricing at the low end.

### Gaps
- Bright Data official e-commerce dataset pricing not verified directly (403).
- No published per-record "price monitoring" bundle at Zyte (AI product extraction price not shown on page).

## 6. B2B / industrial vs B2C e-commerce orientation

### Takeaway
Nearly all listed tools target B2C e-commerce retailers and brands (Shopify/marketplace integrations, Google Shopping, Amazon, repricing). B2B/industrial is served mostly via MAP monitoring for manufacturers (Price2Spy, Minderest, Boardfy brand plans) or by heavy price-management suites (Pricefx, Vendavo, PROS) that do not do web monitoring per se. No vendor found targets monitoring public prices on many small supplier/distributor websites for spare parts as its core use case.

### Cited Findings
- Price2Spy is described as best "for B2B brands monitoring how resellers price their products", stealth IP scraping up to 8x daily, across wholesalers, dealer networks, regional distributors — [search summary citing B2B/MAP articles, e.g. PricingHunter](https://www.pricinghunter.com/resources/best-b2b-price-comparison-software) (snippet attribution; page not fetched)
- Priceva positions for "growing e-commerce stores to enterprise distributors with millions of SKUs" — [priceva.com](https://priceva.com/)
- Minderest MAP monitoring "automatically flag[s] retailers violating minimum advertised price policy across thousands of SKUs" — [search summary of MAP tool articles](https://growbydata.com/best-map-monitoring-software-10-tools-compared/)
- Boardfy has separate "Brands" plans (MSRP infraction detection) — [Capterra/GetApp, aggregator](https://www.capterra.com/p/177435/Boardfy/)
- Skuuudle labelled "B2B-focused" — [scrapewise.ai](https://scrapewise.ai/blogs/competitive-price-monitoring-tools-2026)
- Vendavo "best for industrial B2B" (manufacturers, distributors); PROS for manufacturing/automotive/distribution — but these are price management/optimization, not web monitoring — [withorb / search summary](https://www.withorb.com/blog/competitive-pricing-tools)
- Wiser coverage pitched as 600,000+ stores incl. Amazon, Walmart, Best Buy (retail) — [Wiser](https://www.wiser.com/products/price-execution/)
- Pricefx priced on Revenue Under Management, $100k+/yr — enterprise B2B price management — [G2 summary](https://www.g2.com/products/pricefx/pricing)

### Inferences
- A B2B supplier-site monitor for 10–30k items has a pricing gap to exploit: self-serve B2C tools cap at 5–25k SKUs and assume EAN/marketplace matching; enterprise tools start at $10k–60k/yr. A price point around $0.02–0.06 per item/month (≈$300–1,800/mo for 10–30k) would sit between Pricefy/Priceva (low) and Price2Spy/Prisync-enterprise (mid).
- Matching on part numbers/analogues ("analog comparison" at Priceva) is the B2B-specific value add likely worth charging separately (one-off setup), as Priceva ($0.10/item) and Price2Spy (Automatch module) already do.

### Gaps
- No pricing found for dedicated industrial/spare-parts price monitoring vendors; no data on per-supplier-site onboarding fees in B2B.
- Stale-data flags: Boardfy per-product tiers (aggregator, date unknown, conflicts with official per-competitor page); Price2Spy $39.95/500-URL tier (outdated per Sep 2026 review); Skuuudle £89/£999 tiers (unverified); Minderest €500, Dealavo €220, Intelligence Node $5k (aggregators, undated).
