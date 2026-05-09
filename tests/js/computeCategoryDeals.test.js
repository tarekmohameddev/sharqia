/**
 * Edge-case tests for computeCategoryDeals()
 *
 * Run with: node tests/js/computeCategoryDeals.test.js
 *
 * No external dependencies required.
 */

"use strict";

// ─── Inline the function under test (extracted verbatim from pos-script.js) ───

function computeCategoryDeals(items, categoryRulesMap) {
    const byCategory = {};
    items.forEach((item, index) => {
        if (!item || item.isGift) return;
        const catId = parseInt(item.categoryId || 0);
        if (!catId) return;
        if (!byCategory[catId]) {
            byCategory[catId] = { count: 0, firstIndex: index };
        }
        byCategory[catId].count += parseInt(item.quantity || 0);
    });

    const mixedCatIds = [];
    let firstMixedCategoryId = null;
    let firstMixedIndex = Infinity;

    Object.keys(byCategory).forEach(catId => {
        const catData = categoryRulesMap && categoryRulesMap[catId];
        if (catData && catData.allowMixedDiscount) {
            mixedCatIds.push(parseInt(catId));
            if (byCategory[catId].firstIndex < firstMixedIndex) {
                firstMixedIndex = byCategory[catId].firstIndex;
                firstMixedCategoryId = parseInt(catId);
            }
        }
    });

    let totalDiscount = 0;
    const gifts = [];
    const processedAsMixed = new Set();

    if (mixedCatIds.length > 1 && firstMixedCategoryId !== null) {
        let mixedTotalCount = 0;
        mixedCatIds.forEach(catId => {
            mixedTotalCount += byCategory[catId].count;
        });

        const catEntry = categoryRulesMap[firstMixedCategoryId];
        const rules = (catEntry && Array.isArray(catEntry.rules))
            ? catEntry.rules.slice().sort((a, b) => b.quantity - a.quantity)
            : [];

        if (rules.length && mixedTotalCount > 0) {
            let remaining = mixedTotalCount;
            for (const rule of rules) {
                if (remaining < rule.quantity) continue;
                const times = Math.floor(remaining / rule.quantity);
                if (times <= 0) continue;
                totalDiscount += parseFloat(rule.discountAmount || 0) * times;
                remaining = remaining % rule.quantity;
            }

            const applicableRule = rules.find(r => mixedTotalCount >= (parseInt(r.quantity) || 0));
            if (applicableRule && Array.isArray(applicableRule.giftProducts) && applicableRule.giftProducts.length > 0) {
                applicableRule.giftProducts.forEach(giftProduct => {
                    if (!giftProduct || !giftProduct.id) return;
                    gifts.push({
                        categoryId: firstMixedCategoryId,
                        ruleId: applicableRule.id,
                        gift: giftProduct,
                        quantity: 1
                    });
                });
            }
        }

        mixedCatIds.forEach(id => processedAsMixed.add(id));
    }

    Object.keys(byCategory).forEach(catId => {
        if (processedAsMixed.has(parseInt(catId))) return;

        const catEntry = categoryRulesMap && categoryRulesMap[catId];
        const rules = (catEntry && Array.isArray(catEntry.rules))
            ? catEntry.rules.slice().sort((a, b) => b.quantity - a.quantity)
            : [];
        if (!rules.length) return;

        const totalCount = byCategory[catId].count;
        let remaining = totalCount;
        for (const rule of rules) {
            if (remaining < rule.quantity) continue;
            const times = Math.floor(remaining / rule.quantity);
            if (times <= 0) continue;
            totalDiscount += parseFloat(rule.discountAmount || 0) * times;
            remaining = remaining % rule.quantity;
        }

        const applicableRule = rules.find(r => totalCount >= (parseInt(r.quantity) || 0));
        if (applicableRule && Array.isArray(applicableRule.giftProducts) && applicableRule.giftProducts.length > 0) {
            applicableRule.giftProducts.forEach(giftProduct => {
                if (!giftProduct || !giftProduct.id) return;
                gifts.push({
                    categoryId: parseInt(catId),
                    ruleId: applicableRule.id,
                    gift: giftProduct,
                    quantity: 1
                });
            });
        }
    });

    return { discountAmount: totalDiscount, giftsToEnsure: gifts };
}

// ─── Test helpers ────────────────────────────────────────────────────────────

let passed = 0;
let failed = 0;

function assert(condition, message) {
    if (condition) {
        console.log(`  ✓  ${message}`);
        passed++;
    } else {
        console.error(`  ✗  FAIL: ${message}`);
        failed++;
    }
}

function assertEqual(actual, expected, message) {
    const ok = JSON.stringify(actual) === JSON.stringify(expected);
    if (ok) {
        console.log(`  ✓  ${message}`);
        passed++;
    } else {
        console.error(`  ✗  FAIL: ${message}`);
        console.error(`       expected: ${JSON.stringify(expected)}`);
        console.error(`       actual  : ${JSON.stringify(actual)}`);
        failed++;
    }
}

function section(name) {
    console.log(`\n── ${name} ──`);
}

// ─── Fixture data ─────────────────────────────────────────────────────────────

const giftA = { id: 101, name: 'Gift A' };
const giftB = { id: 102, name: 'Gift B' };
const giftC = { id: 103, name: 'Gift C' };

// Category 1 (Butter): mixed ON; rules: qty=5 (20 off, giftA), qty=10 (50 off, giftB)
const CAT_BUTTER = 1;
// Category 2 (Cheese): mixed ON; rules: qty=5 (15 off), qty=10 (40 off, giftC)
const CAT_CHEESE = 2;
// Category 3 (Salt): mixed OFF; rules: qty=3 (5 off)
const CAT_SALT = 3;
// Category 4 (Sugar): mixed ON, rules: qty=5 (10 off) — used for 3-way mixed tests
const CAT_SUGAR = 4;

const rulesMap = {
    [CAT_BUTTER]: {
        allowMixedDiscount: true,
        rules: [
            { id: 10, quantity: 5,  discountAmount: 20, giftProducts: [giftA] },
            { id: 11, quantity: 10, discountAmount: 50, giftProducts: [giftB] },
        ]
    },
    [CAT_CHEESE]: {
        allowMixedDiscount: true,
        rules: [
            { id: 20, quantity: 5,  discountAmount: 15, giftProducts: [] },
            { id: 21, quantity: 10, discountAmount: 40, giftProducts: [giftC] },
        ]
    },
    [CAT_SALT]: {
        allowMixedDiscount: false,
        rules: [
            { id: 30, quantity: 3, discountAmount: 5, giftProducts: [] },
        ]
    },
    [CAT_SUGAR]: {
        allowMixedDiscount: true,
        rules: [
            { id: 40, quantity: 5, discountAmount: 10, giftProducts: [] },
        ]
    },
};

function item(catId, qty, isGift = false) {
    return { categoryId: catId, quantity: qty, isGift };
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 1 — Baseline single-category (no mixed)
// ─────────────────────────────────────────────────────────────────────────────
section('1. Baseline single-category (no mixing involved)');

{
    // 5 butter items → 5-item rule fires once (20 off, gift A)
    const r = computeCategoryDeals([item(CAT_BUTTER, 5)], rulesMap);
    assert(r.discountAmount === 20, '5 butter → discount = 20');
    assert(r.giftsToEnsure.length === 1 && r.giftsToEnsure[0].gift.id === giftA.id,
        '5 butter → gift A');
}

{
    // 10 butter items → 10-item rule fires once (50 off, gift B). Remainder 0.
    const r = computeCategoryDeals([item(CAT_BUTTER, 10)], rulesMap);
    assert(r.discountAmount === 50, '10 butter → discount = 50');
    assert(r.giftsToEnsure.length === 1 && r.giftsToEnsure[0].gift.id === giftB.id,
        '10 butter → gift B (highest rule only)');
}

{
    // 15 butter items → 10-item rule fires once (50), remainder 5 → 5-item rule fires once (20). Total = 70.
    // Gift: highest applicable rule is 10-item → gift B only.
    const r = computeCategoryDeals([item(CAT_BUTTER, 15)], rulesMap);
    assert(r.discountAmount === 70, '15 butter → discount = 70 (greedy multiples)');
    assert(r.giftsToEnsure.length === 1 && r.giftsToEnsure[0].gift.id === giftB.id,
        '15 butter → gift B (highest applicable rule)');
}

{
    // 20 butter → 10*2=20, so rule fires twice (50*2=100). remainder=0. Gift: rule 10 → giftB.
    const r = computeCategoryDeals([item(CAT_BUTTER, 20)], rulesMap);
    assert(r.discountAmount === 100, '20 butter → discount = 100 (rule fires twice)');
    assert(r.giftsToEnsure.length === 1, '20 butter → still one gift entry (not doubled)');
}

{
    // 4 butter → below all thresholds → 0 discount, 0 gifts
    const r = computeCategoryDeals([item(CAT_BUTTER, 4)], rulesMap);
    assert(r.discountAmount === 0, '4 butter → no discount');
    assert(r.giftsToEnsure.length === 0, '4 butter → no gifts');
}

{
    // Salt (mixed OFF), 3 items → 5 off, no gifts
    const r = computeCategoryDeals([item(CAT_SALT, 3)], rulesMap);
    assert(r.discountAmount === 5, '3 salt (mixed off) → discount = 5');
    assert(r.giftsToEnsure.length === 0, '3 salt → no gifts');
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 2 — Mixed categories: core scenario
// ─────────────────────────────────────────────────────────────────────────────
section('2. Mixed categories — core scenario (Butter first)');

{
    // 5 butter (added first) + 5 cheese → combined 10 → 10-item rule from BUTTER governs
    // discount = 50, gift = giftB (butter's 10-item gift)
    const items = [item(CAT_BUTTER, 5), item(CAT_CHEESE, 5)];
    const r = computeCategoryDeals(items, rulesMap);
    assert(r.discountAmount === 50, 'B5+C5 → discount = 50 (Butter 10-item rule)');
    assert(r.giftsToEnsure.length === 1 && r.giftsToEnsure[0].gift.id === giftB.id,
        'B5+C5 → gift B from Butter\'s 10-item rule');
    assert(r.giftsToEnsure[0].categoryId === CAT_BUTTER, 'gift categoryId = Butter');
}

{
    // Cheese added first this time; 5 cheese + 5 butter → 10 combined
    // discount from CHEESE's 10-item rule = 40, gift = giftC
    const items = [item(CAT_CHEESE, 5), item(CAT_BUTTER, 5)];
    const r = computeCategoryDeals(items, rulesMap);
    assert(r.discountAmount === 40, 'C5+B5 (cheese first) → discount = 40 (Cheese 10-item rule)');
    assert(r.giftsToEnsure.length === 1 && r.giftsToEnsure[0].gift.id === giftC.id,
        'C5+B5 → gift C from Cheese\'s 10-item rule');
}

{
    // 3 butter + 3 cheese → combined 6, above 5-item threshold but below 10
    // Butter's 5-item rule → 20 off, giftA
    const items = [item(CAT_BUTTER, 3), item(CAT_CHEESE, 3)];
    const r = computeCategoryDeals(items, rulesMap);
    assert(r.discountAmount === 20, 'B3+C3 → discount = 20 (Butter 5-item rule, 6 combined)');
    assert(r.giftsToEnsure.length === 1 && r.giftsToEnsure[0].gift.id === giftA.id,
        'B3+C3 → gift A from Butter\'s 5-item rule');
}

{
    // 2 butter + 2 cheese → combined 4, below all thresholds → 0 discount
    const items = [item(CAT_BUTTER, 2), item(CAT_CHEESE, 2)];
    const r = computeCategoryDeals(items, rulesMap);
    assert(r.discountAmount === 0, 'B2+C2 → combined 4, below threshold → 0 discount');
    assert(r.giftsToEnsure.length === 0, 'B2+C2 → no gifts');
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 3 — Mixed + non-mixed stacking
// ─────────────────────────────────────────────────────────────────────────────
section('3. Mixed categories + independent non-mixed category (Salt)');

{
    // 5 butter + 5 cheese (mixed, 10 combined → Butter 50 off + giftB)
    // + 3 salt (independent → 5 off)
    // total discount = 55
    const items = [item(CAT_BUTTER, 5), item(CAT_CHEESE, 5), item(CAT_SALT, 3)];
    const r = computeCategoryDeals(items, rulesMap);
    assert(r.discountAmount === 55, 'B5+C5+S3 → discount = 55 (50 mixed + 5 salt)');
    assert(r.giftsToEnsure.length === 1 && r.giftsToEnsure[0].gift.id === giftB.id,
        'B5+C5+S3 → only giftB (salt has no gifts)');
}

{
    // Only salt (no mixed partner) → treated individually
    const r = computeCategoryDeals([item(CAT_SALT, 6)], rulesMap);
    // 6 items, rule at qty=3: 6/3=2 times, 2*5=10 off
    assert(r.discountAmount === 10, '6 salt → discount = 10 (greedy: 2 × 5-off)');
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 4 — Single mixed-enabled category alone (no partner)
// ─────────────────────────────────────────────────────────────────────────────
section('4. Single mixed-enabled category with no mixed partner → independent rules');

{
    // Only butter in cart (no other mixed cat present) → treated independently
    const r = computeCategoryDeals([item(CAT_BUTTER, 5)], rulesMap);
    assert(r.discountAmount === 20, 'Butter alone (mixed ON but solo) → own 5-item rule = 20');
    assert(r.giftsToEnsure.length === 1 && r.giftsToEnsure[0].gift.id === giftA.id,
        'Butter alone → giftA from own rule');
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 5 — Three-way mixed group
// ─────────────────────────────────────────────────────────────────────────────
section('5. Three mixed categories — Butter, Cheese, Sugar all mixed ON');

{
    // 3 butter (first) + 3 cheese + 4 sugar → combined = 10
    // Butter's 10-item rule: 50 off, giftB
    const items = [item(CAT_BUTTER, 3), item(CAT_CHEESE, 3), item(CAT_SUGAR, 4)];
    const r = computeCategoryDeals(items, rulesMap);
    assert(r.discountAmount === 50, 'B3+C3+Sg4 → discount = 50 (Butter 10-item rule, 10 combined)');
    assert(r.giftsToEnsure.length === 1 && r.giftsToEnsure[0].gift.id === giftB.id,
        'B3+C3+Sg4 → giftB');
}

{
    // Sugar added first (3 sugar + 3 butter + 4 cheese = 10 combined)
    // Sugar's only rule is qty=5 (10 off, no gifts). Combined=10 → 10/5=2 times → 20 off.
    const items = [item(CAT_SUGAR, 3), item(CAT_BUTTER, 3), item(CAT_CHEESE, 4)];
    const r = computeCategoryDeals(items, rulesMap);
    assert(r.discountAmount === 20, 'Sg3+B3+C4 (sugar first) → 10 combined, Sugar rule 5×2=20');
    assert(r.giftsToEnsure.length === 0, 'Sg3+B3+C4 → no gifts (Sugar rules have none)');
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 6 — Greedy multiples within mixed group
// ─────────────────────────────────────────────────────────────────────────────
section('6. Greedy multiples within the mixed group');

{
    // 10 butter + 10 cheese → combined 20, Butter's rules: 10 fires twice (100), rem=0
    const items = [item(CAT_BUTTER, 10), item(CAT_CHEESE, 10)];
    const r = computeCategoryDeals(items, rulesMap);
    assert(r.discountAmount === 100, 'B10+C10 → 20 combined, 10-rule × 2 = 100');
    // Gift: highest rule applicable at count=20 is still the 10-item rule → giftB once
    assert(r.giftsToEnsure.length === 1, 'B10+C10 → gift still applied once, not twice');
}

{
    // 8 butter + 7 cheese → combined 15; 10-item fires once (50), rem=5 → 5-item fires once (20) = 70
    const items = [item(CAT_BUTTER, 8), item(CAT_CHEESE, 7)];
    const r = computeCategoryDeals(items, rulesMap);
    assert(r.discountAmount === 70, 'B8+C7 → 15 combined → 50+20 = 70');
    assert(r.giftsToEnsure[0].gift.id === giftB.id, 'B8+C7 → giftB (highest rule at 15 is 10-item)');
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 7 — Gift items in cart are excluded from count
// ─────────────────────────────────────────────────────────────────────────────
section('7. Gift items in cart must not be counted toward thresholds');

{
    const giftItem = { categoryId: CAT_BUTTER, quantity: 5, isGift: true };
    const r = computeCategoryDeals([item(CAT_BUTTER, 4), giftItem], rulesMap);
    assert(r.discountAmount === 0, 'Gift items excluded from count: 4 real + 5 gift → 4 counted → 0 discount');
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 8 — Edge: empty / null inputs
// ─────────────────────────────────────────────────────────────────────────────
section('8. Edge: empty / null / malformed inputs');

{
    const r = computeCategoryDeals([], rulesMap);
    assert(r.discountAmount === 0 && r.giftsToEnsure.length === 0,
        'Empty items array → 0 discount, 0 gifts');
}

{
    const r = computeCategoryDeals([item(CAT_BUTTER, 5)], {});
    assert(r.discountAmount === 0 && r.giftsToEnsure.length === 0,
        'Empty rulesMap → 0 discount, 0 gifts');
}

{
    const r = computeCategoryDeals([item(CAT_BUTTER, 5)], null);
    assert(r.discountAmount === 0 && r.giftsToEnsure.length === 0,
        'null rulesMap → 0 discount, 0 gifts');
}

{
    // Item with no categoryId
    const r = computeCategoryDeals([{ quantity: 5 }], rulesMap);
    assert(r.discountAmount === 0, 'Item missing categoryId → 0 discount');
}

{
    // null item in array
    const r = computeCategoryDeals([null, item(CAT_BUTTER, 5)], rulesMap);
    assert(r.discountAmount === 20, 'null item in array is safely skipped');
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 9 — Exact threshold boundary
// ─────────────────────────────────────────────────────────────────────────────
section('9. Exact threshold boundaries');

{
    // Exactly at the 5-item threshold
    const r = computeCategoryDeals([item(CAT_BUTTER, 5)], rulesMap);
    assert(r.discountAmount === 20, 'Exactly 5 butter → rule fires');
}

{
    // One below threshold (4 items)
    const r = computeCategoryDeals([item(CAT_BUTTER, 4)], rulesMap);
    assert(r.discountAmount === 0, 'One below threshold (4) → no discount');
}

{
    // Mixed: exactly at 10 threshold (5+5)
    const r = computeCategoryDeals([item(CAT_BUTTER, 5), item(CAT_CHEESE, 5)], rulesMap);
    assert(r.discountAmount === 50, 'Mixed exactly at 10 threshold → 10-item rule fires');
}

{
    // Mixed: one below 10 threshold (4+5=9) → falls to 5-item rule
    const r = computeCategoryDeals([item(CAT_BUTTER, 4), item(CAT_CHEESE, 5)], rulesMap);
    assert(r.discountAmount === 20, 'Mixed 4+5=9 → below 10, falls back to 5-item rule');
    assert(r.giftsToEnsure[0].gift.id === giftA.id, '4+5=9 mixed → giftA from 5-item rule');
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 10 — Mixed where first category has NO rules that apply
// ─────────────────────────────────────────────────────────────────────────────
section('10. Mixed where combined count is below ALL rules of the first category');

{
    // 2 butter + 2 cheese = 4; both Butter rules require >= 5 → 0 discount
    const r = computeCategoryDeals([item(CAT_BUTTER, 2), item(CAT_CHEESE, 2)], rulesMap);
    assert(r.discountAmount === 0, '2+2=4 combined, all Butter rules need >=5 → 0 discount');
    assert(r.giftsToEnsure.length === 0, '2+2=4 → no gifts');
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 11 — Category in cart but no rules in rulesMap
// ─────────────────────────────────────────────────────────────────────────────
section('11. Category in cart with no entry in rulesMap');

{
    const r = computeCategoryDeals([item(999, 10)], rulesMap);
    assert(r.discountAmount === 0, 'Unknown category → 0 discount');
    assert(r.giftsToEnsure.length === 0, 'Unknown category → 0 gifts');
}

{
    // Mixed cat with no rules partner, plus unknown category
    const r = computeCategoryDeals([item(CAT_BUTTER, 5), item(999, 5)], rulesMap);
    // Cat 999 has no rulesMap entry → allowMixedDiscount false → not a mixed partner
    // Butter alone → independent 5-item rule = 20
    assert(r.discountAmount === 20,
        'Butter(mixed ON) + unknown cat → Butter treated alone → 20');
}

// ─────────────────────────────────────────────────────────────────────────────
// Final summary
// ─────────────────────────────────────────────────────────────────────────────
console.log(`\n${'─'.repeat(50)}`);
if (failed === 0) {
    console.log(`✅  All ${passed} tests passed.`);
} else {
    console.log(`❌  ${failed} test(s) FAILED  /  ${passed} passed.`);
    process.exitCode = 1;
}
