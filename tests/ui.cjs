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

function signParams(params) {
    const encode = (value) =>
        encodeURIComponent(value)
            .replace(
                /[!'()*]/g,
                (character) =>
                    `%${character.charCodeAt(0).toString(16).toUpperCase()}`,
            )
            .replace(/%20/g, "+");
    const encoded = Object.keys(params)
        .sort()
        .map((key) => `${encode(key)}=${encode(params[key])}`)
        .join("&");
    return crypto
        .createHmac("sha512", "test-only-vnpay-signing-secret")
        .update(encoded)
        .digest("hex");
}

async function inspect(page, name) {
    await page.locator("h1").waitFor();
    await page.evaluate(async () => {
        await Promise.all(
            [...document.images].map(async (img) => {
                img.loading = "eager";
                if (!img.complete) {
                    await new Promise((resolve) => {
                        img.onload = resolve;
                        img.onerror = resolve;
                    });
                }
                if (img.naturalWidth) await img.decode();
            }),
        );
    });
    assert(
        (await page.evaluate(() => document.documentElement.scrollWidth)) <=
            page.viewportSize().width + 1,
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
                serviceWorkers: "block",
            });
            const page = await context.newPage();
            const errors = [];
            page.on("pageerror", (error) => errors.push(error.message));
            page.on("console", (message) => {
                if (message.type() === "error") errors.push(message.text());
            });
            await page.goto("/");
            await inspect(page, `${device}-home`);
            assert.equal(
                await page.locator("#movies .movie-card").count(),
                4,
                "Missing original showing films",
            );
            assert.equal(
                await page.locator("#phimsapchieu .movie-card").count(),
                4,
                "Missing original upcoming films",
            );
            const track = page.locator(".news-track");
            await page.locator("[data-news-next]").click();
            await page.waitForFunction(
                () => document.querySelector(".news-track").scrollLeft > 20,
            );
            await page.locator("[data-news-prev]").click();
            await page.waitForFunction(
                () => document.querySelector(".news-track").scrollLeft < 2,
            );
            if (device === "mobile") {
                await track.scrollIntoViewIfNeeded();
                const bounds = await track.boundingBox();
                const session = await context.newCDPSession(page);
                const start = bounds.x + bounds.width * 0.85;
                const y = bounds.y + 80;
                await session.send("Input.dispatchTouchEvent", {
                    type: "touchStart",
                    touchPoints: [{ x: start, y, id: 0 }],
                });
                for (let step = 1; step <= 6; step++) {
                    await session.send("Input.dispatchTouchEvent", {
                        type: "touchMove",
                        touchPoints: [
                            { x: start - step * bounds.width * 0.1, y, id: 0 },
                        ],
                    });
                    await new Promise((resolve) => setTimeout(resolve, 16));
                }
                await session.send("Input.dispatchTouchEvent", {
                    type: "touchEnd",
                    touchPoints: [],
                });
                await session.detach();
                await page.waitForFunction(
                    () => document.querySelector(".news-track").scrollLeft > 20,
                );
            }
            await track.focus();
            await page.keyboard.press("ArrowRight");
            await page.waitForFunction(
                () => document.querySelector(".news-track").scrollLeft > 20,
            );
            await page.locator(".news-card h3").first().click();
            await inspect(page, `${device}-news-detail`);
            await page.goto("/news.php");
            await inspect(page, `${device}-news-list`);
            await page.goto("/");
            if (device === "desktop") {
                for (const width of [320, 768, 900, 1024]) {
                    await page.setViewportSize({ width, height: 960 });
                    assert(
                        (await page.evaluate(
                            () => document.documentElement.scrollWidth,
                        )) <=
                            width + 1,
                        `Homepage overflows at ${width}px`,
                    );
                }
                await page.setViewportSize(viewport);
            }
            const slides = page.locator("[data-slide]");
            assert(
                (await slides.count()) >= 2,
                "Carousel fixture needs two films",
            );
            await page.locator("[data-carousel-next]").click();
            assert(await slides.nth(1).isVisible(), "Next film did not appear");
            assert(
                !(await slides.first().isVisible()),
                "Previous slide remained visible",
            );
            await page.locator("[data-carousel-prev]").click();
            assert(
                await slides.first().isVisible(),
                "Previous film did not return",
            );
            await page.locator('[data-carousel-to="2"]').focus();
            await page.keyboard.press("Enter");
            assert(
                await slides.nth(2).isVisible(),
                "Keyboard slide selection failed",
            );
            if (device === "mobile") {
                const menu = page.locator(".nav-toggle");
                await menu.click();
                assert.equal(await menu.getAttribute("aria-expanded"), "true");
                await page.keyboard.press("Escape");
                assert.equal(await menu.getAttribute("aria-expanded"), "false");
                await menu.click();
            }
            await page
                .locator(".header-search input[name=q]")
                .fill("Chú Thuật");
            await Promise.all([
                page.waitForURL(
                    (url) => url.searchParams.get("q") === "Chú Thuật",
                ),
                page.locator(".header-search button").click(),
            ]);
            assert.equal(
                await page.locator(".movie-card").count(),
                1,
                "Header search did not filter films",
            );
            await page.locator(".movie-card h3 a").click();
            await inspect(page, `${device}-movie-detail`);
            assert.equal(
                await page.locator("#trailer a").getAttribute("href"),
                "https://www.youtube.com/watch?v=cPs3j9LNCjY",
            );
            await page.goto("/?q=ui-no-such-film");
            await inspect(page, `${device}-empty-search`);
            assert.equal(await page.locator(".movie-card").count(), 0);
            assert.equal(
                await page.locator("[data-carousel-controls]").count(),
                0,
            );
            await page.goto("/auth/login.php");
            await inspect(page, `${device}-login`);
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
            if (device === "desktop") {
                await page.setViewportSize({ width: 320, height: 844 });
                await inspect(page, "narrow-booking");
                await page.setViewportSize(viewport);
            }
            const seat = page
                .locator(".seat-choice:has(input:not(:disabled))")
                .first();
            await seat.click();
            assert(
                await seat.locator("input").isChecked(),
                "Seat selection did not update checkbox",
            );
            const expectedPrice =
                Number(
                    await page
                        .locator("[data-booking-form]")
                        .getAttribute("data-base-price"),
                ) +
                Number(await seat.locator("input").getAttribute("data-extra"));
            const displayedPrice = Number(
                (await page.locator("[data-total]").textContent()).replace(
                    /[^0-9]/g,
                    "",
                ),
            );
            assert.equal(
                displayedPrice,
                expectedPrice,
                "Client summary does not match selected seat price",
            );
            await page.locator('[name="method"]').selectOption("vnpay");
            await Promise.all([
                page.waitForURL((url) => url.pathname.endsWith("order.php")),
                page.locator("[data-booking-submit]").click(),
            ]);
            const orderURL = page.url();
            await inspect(page, `${device}-payment`);
            let gatewayResponse;
            // Redirect chains are not intercepted at each URL. Fetch the real
            // start response without following it, then serve the bank stub.
            await context.route(
                (url) =>
                    url.origin === new URL(baseURL).origin &&
                    url.pathname.endsWith("/payment/start.php"),
                async (route) => {
                    const response = await route.fetch({ maxRedirects: 0 });
                    gatewayResponse = {
                        status: response.status(),
                        location: response.headers().location,
                    };
                    await route.fulfill({
                        status: 200,
                        contentType: "text/html",
                        body: '<!doctype html><html lang="vi"><meta charset="utf-8"><title>Test gateway</title><h1>VNPay sandbox stub</h1></html>',
                    });
                },
            );
            await context.route("https://sandbox.vnpayment.vn/**", (route) =>
                route.abort(),
            );
            await Promise.all([
                page.waitForURL((url) =>
                    url.pathname.endsWith("/payment/start.php"),
                ),
                page.locator(".payment-start-form button").click(),
            ]);
            await page
                .getByRole("heading", { name: "VNPay sandbox stub" })
                .waitFor();
            fs.writeFileSync(
                `test-results/${device}-gateway-debug.json`,
                JSON.stringify(gatewayResponse, null, 2),
            );
            assert.equal(gatewayResponse.status, 303);
            const gatewayURL = gatewayResponse.location;
            assert(
                gatewayURL.startsWith(
                    "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html?",
                ),
            );
            const provider = new URL(gatewayURL).searchParams;
            assert.equal(provider.get("vnp_TmnCode"), "TEST1234", gatewayURL);
            assert.match(provider.get("vnp_TxnRef"), /^[A-F0-9]{32}$/);
            assert.equal(
                Number(provider.get("vnp_Amount")),
                expectedPrice * 100,
            );
            const requestParams = Object.fromEntries(provider);
            delete requestParams.vnp_SecureHash;
            assert.equal(
                provider.get("vnp_SecureHash"),
                signParams(requestParams),
            );
            await page.goBack();
            await page.locator(".payment-start-form button").waitFor();
            assert(
                await page.locator(".payment-start-form button").isEnabled(),
                "Back from VNPay left checkout disabled",
            );
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
            callback.vnp_SecureHash = signParams(callback);
            const ipn = await context.request.get(
                `${baseURL}/payment/ipn.php?${new URLSearchParams(callback)}`,
            );
            assert.equal((await ipn.json()).RspCode, "00");
            await page.goto(orderURL);
            await inspect(page, `${device}-paid-ticket`);
            await page.goto("/account/tickets.php");
            await inspect(page, `${device}-tickets`);
            await page.locator(".account-menu summary").click();
            await page.keyboard.press("Escape");
            assert.equal(
                await page.locator(".account-menu").getAttribute("open"),
                null,
            );
            await page.locator(".account-menu summary").click();
            await page
                .locator('.account-dropdown a[href*="tab=invoices"]')
                .click();
            await inspect(page, `${device}-old-invoices`);
            await page.locator(".archive-card .button").first().click();
            await inspect(page, `${device}-old-invoice-detail`);
            await login(page, true);
            await inspect(page, `${device}-admin-dashboard`);
            if (device === "desktop") {
                await page.setViewportSize({ width: 320, height: 844 });
                await inspect(page, "narrow-admin-dashboard");
                await page.setViewportSize(viewport);
            }
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
            await page.goto("/admin/news.php");
            await inspect(page, `${device}-admin-news`);
            await page.goto("/admin/news-edit.php");
            await inspect(page, `${device}-admin-news-edit`);
            const headline = `Tin mới kiểm thử ${device}`;
            await page.locator('[name="title"]').fill(headline);
            await page
                .locator('[name="summary"]')
                .fill("Tin mới được cập nhật từ admin.");
            await page
                .locator('[name="body"]')
                .fill("Dòng thứ nhất.\nĐiều kiện chương trình ở dòng thứ hai.");
            await page.locator('[name="category"]').selectOption("news");
            await page.locator('[name="status"]').selectOption("draft");
            await page
                .locator('[name="published_at"]')
                .fill("2026-01-01T10:00");
            await page
                .locator('[name="stock_image"]')
                .selectOption("happyday.jpg");
            await Promise.all([
                page.waitForURL((url) => url.searchParams.has("id")),
                page.locator("[data-news-form] button[type=submit]").click(),
            ]);
            const editURL = page.url();
            const newsId = new URL(editURL).searchParams.get("id");
            assert.equal(
                (
                    await context.request.get(`/news-detail.php?id=${newsId}`)
                ).status(),
                404,
            );
            await page.locator('[name="status"]').selectOption("published");
            await Promise.all([
                page.waitForNavigation(),
                page.locator("[data-news-form] button[type=submit]").click(),
            ]);
            await page.goto(`/news-detail.php?id=${newsId}`);
            assert.equal(await page.locator("h1").textContent(), headline);
            await inspect(page, `${device}-published-news`);
            await page.goto(editURL);
            await page.locator('[name="title"]').fill(`${headline} đã sửa`);
            await page
                .locator('[name="image"]')
                .setInputFiles(
                    "public/assets/images/tinmoikhuyenmai/snack.jpg",
                );
            await Promise.all([
                page.waitForNavigation(),
                page.locator("[data-news-form] button[type=submit]").click(),
            ]);
            await page.goto(`/news-detail.php?id=${newsId}`);
            assert.equal(
                await page.locator("h1").textContent(),
                `${headline} đã sửa`,
            );
            assert(
                (
                    await page.locator(".news-cover").getAttribute("src")
                ).includes("media.php"),
            );
            await page.goto("/admin/news.php");
            const row = page.locator("tr", { hasText: `${headline} đã sửa` });
            page.once("dialog", (dialog) => dialog.accept());
            await Promise.all([
                page.waitForNavigation(),
                row.getByRole("button", { name: "Ẩn bài" }).click(),
            ]);
            assert.equal(
                (
                    await context.request.get(`/news-detail.php?id=${newsId}`)
                ).status(),
                404,
            );
            assert.deepEqual(errors, [], `${device}: browser errors`);
            await context.close();
        }
        const failures = results.filter((result) => result.violations.length);
        assert.equal(failures.length, 0, JSON.stringify(failures));
        console.log(
            `PASS UI: ${results.length} desktop/mobile pages, movies, news scrolling and admin publish/edit/image/hide, carousel, search, checkout, stubbed VNPay/IPN, archive, navigation, accessibility, no browser errors.`,
        );
    } finally {
        await browser.close();
    }
})().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
