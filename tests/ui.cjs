"use strict";
const { chromium } = require("playwright");
const AxeBuilder = require("@axe-core/playwright").default;
const assert = require("node:assert/strict");
const crypto = require("node:crypto");
const fs = require("node:fs");
const baseURL = process.env.TEST_BASE_URL || "http://127.0.0.1:8000";
if (!process.env.DB_NAME?.endsWith("_test"))
    throw new Error("UI checks require a dedicated _test database.");
const results = [];
fs.mkdirSync("test-results/screenshots", { recursive: true });

async function inspect(page, name) {
    await page.locator("h1").waitFor();
    await page.evaluate(async () => {
        await Promise.all(
            [...document.images].map((img) => {
                img.loading = "eager";
                return img.complete
                    ? Promise.resolve()
                    : new Promise((resolve) => {
                          img.onload = resolve;
                          img.onerror = resolve;
                      });
            }),
        );
    });
    assert(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth + 1,
        ),
        `${name}: horizontal page overflow`,
    );
    assert.equal(
        await page
            .locator("img")
            .evaluateAll(
                (images) =>
                    images.filter((image) => !image.naturalWidth).length,
            ),
        0,
        `${name}: broken images`,
    );
    const accessibility = await new AxeBuilder({ page })
        .withTags(["wcag2a", "wcag2aa", "wcag21aa"])
        .analyze();
    const violations = accessibility.violations.map((v) => ({
        id: v.id,
        impact: v.impact,
        nodes: v.nodes.map((n) => ({
            target: n.target,
            summary: n.failureSummary,
        })),
    }));
    await page.screenshot({
        path: `test-results/screenshots/${name}.png`,
        fullPage: true,
    });
    results.push({ name, violations });
    fs.writeFileSync(
        "test-results/ui-report.json",
        JSON.stringify(results, null, 2),
    );
    assert.equal(
        violations.length,
        0,
        `${name}: ${JSON.stringify(violations)}`,
    );
}

async function login(page, admin = false) {
    await page.goto(admin ? "/admin/login.php" : "/auth/login.php");
    await page
        .locator('[name="identifier"]')
        .fill(admin ? "uiadmin" : "ui@example.test");
    await page.locator('[name="password"]').fill("UiPassword123!");
    await Promise.all([
        page.waitForURL((url) => !url.pathname.endsWith("login.php")),
        page.locator('.auth-panel button[type="submit"]').click(),
    ]);
}

(async () => {
    const browser = await chromium.launch();
    try {
        for (const [device, viewport] of [
            ["desktop", { width: 1440, height: 960 }],
            ["mobile", { width: 390, height: 844 }],
        ]) {
            const context = await browser.newContext({
                baseURL,
                viewport,
                isMobile: device === "mobile",
                hasTouch: device === "mobile",
            });
            const page = await context.newPage();
            const errors = [];
            page.on("pageerror", (error) => errors.push(error.message));
            page.on("console", (message) => {
                if (message.type() === "error") errors.push(message.text());
            });
            await page.goto("/");
            await inspect(page, `${device}-home`);
            if (device === "mobile") {
                const menu = page.locator(".nav-toggle");
                await menu.click();
                assert.equal(await menu.getAttribute("aria-expanded"), "true");
                await page.keyboard.press("Escape");
                assert.equal(await menu.getAttribute("aria-expanded"), "false");
            }
            await page.goto("/showtimes.php");
            await inspect(page, `${device}-showtimes`);
            await page.locator(".showtime-card .button").first().click();
            if (await page.locator('[name="identifier"]').count()) {
                await page
                    .locator('[name="identifier"]')
                    .fill("ui@example.test");
                await page.locator('[name="password"]').fill("UiPassword123!");
                await Promise.all([
                    page.waitForURL((url) =>
                        url.pathname.endsWith("booking.php"),
                    ),
                    page.locator('.auth-panel button[type="submit"]').click(),
                ]);
            }
            await inspect(page, `${device}-booking`);
            const seat = page
                .locator(".seat-choice:has(input:not(:disabled))")
                .first();
            await seat.click();
            assert.notEqual(
                await page.locator("[data-total]").textContent(),
                "0 ₫",
            );
            await page.locator('[name="method"]').selectOption("vnpay");
            await Promise.all([
                page.waitForURL((url) => url.pathname.endsWith("order.php")),
                page.locator("[data-booking-submit]").click(),
            ]);
            const orderURL = page.url();
            await inspect(page, `${device}-payment`);
            let gatewayURL;
            // Stub only the bank page. No request to a real merchant or bank is made.
            await context.route(
                "https://sandbox.vnpayment.vn/**",
                async (route) => {
                    gatewayURL = route.request().url();
                    await route.fulfill({
                        contentType: "text/html",
                        body: '<html lang="vi"><title>Test gateway</title><h1>VNPay sandbox stub</h1></html>',
                    });
                },
            );
            await Promise.all([
                page.waitForURL("https://sandbox.vnpayment.vn/**"),
                page.locator(".payment-start-form button").click(),
            ]);
            assert(gatewayURL, "Payment redirect was blocked or missing");
            await page.goBack();
            await page.locator(".payment-start-form button").waitFor();
            assert(
                await page.locator(".payment-start-form button").isEnabled(),
                "Back from VNPay left checkout disabled",
            );
            const provider = new URL(gatewayURL).searchParams;
            const callback = {
                vnp_Amount: provider.get("vnp_Amount"),
                vnp_TmnCode: provider.get("vnp_TmnCode"),
                vnp_TxnRef: provider.get("vnp_TxnRef"),
                vnp_BankCode: "NCB",
                vnp_TransactionNo: String(Date.now()),
                vnp_ResponseCode: "00",
                vnp_TransactionStatus: "00",
                vnp_PayDate: new Intl.DateTimeFormat("sv-SE", {
                    timeZone: "Asia/Ho_Chi_Minh",
                    year: "numeric",
                    month: "2-digit",
                    day: "2-digit",
                    hour: "2-digit",
                    minute: "2-digit",
                    second: "2-digit",
                    hourCycle: "h23",
                })
                    .format(new Date())
                    .replace(/\D/g, ""),
            };
            const encoded = Object.keys(callback)
                .sort()
                .map(
                    (key) =>
                        `${encodeURIComponent(key)}=${encodeURIComponent(callback[key]).replace(/%20/g, "+")}`,
                )
                .join("&");
            callback.vnp_SecureHash = crypto
                .createHmac("sha512", "test-only-vnpay-signing-secret")
                .update(encoded)
                .digest("hex");
            const ipn = await context.request.get(
                `${baseURL}/payment/ipn.php?${new URLSearchParams(callback)}`,
            );
            assert.equal((await ipn.json()).RspCode, "00");
            await page.goto(orderURL);
            await inspect(page, `${device}-paid-ticket`);
            await page.goto("/account/tickets.php");
            await inspect(page, `${device}-tickets`);
            await page.goto("/account/tickets.php?tab=invoices");
            await inspect(page, `${device}-old-invoices`);
            await page.locator(".archive-card .button").first().click();
            await inspect(page, `${device}-old-invoice-detail`);
            await login(page, true);
            await inspect(page, `${device}-admin-dashboard`);
            if (device === "mobile") {
                await page.locator(".nav-toggle").click();
                assert(
                    await page
                        .locator('.admin-sidebar a[href$="payments.php"]')
                        .isVisible(),
                );
                await page
                    .locator('.admin-sidebar a[href$="payments.php"]')
                    .click();
            } else {
                await page.goto("/admin/payments.php");
            }
            await inspect(page, `${device}-admin-payments`);
            await page.goto("/admin/history.php");
            await inspect(page, `${device}-admin-history`);
            await page.goto("/admin/revenue.php?mode=sandbox");
            await inspect(page, `${device}-admin-revenue`);
            assert.deepEqual(errors, [], `${device}: browser errors`);
            await context.close();
        }
        console.log(
            `PASS UI: ${results.length} desktop/mobile pages, checkout, stubbed VNPay/IPN, archive, navigation, accessibility, no browser errors.`,
        );
    } finally {
        await browser.close();
    }
})().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
