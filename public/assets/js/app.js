"use strict";
const navToggle = document.querySelector(".nav-toggle");
const navBackdrop = document.querySelector(".nav-backdrop");
function setNavigation(open) {
    navToggle?.setAttribute("aria-expanded", String(open));
    document.getElementById("primary-nav")?.classList.toggle("is-open", open);
    document.body.classList.toggle("nav-open", open);
    if (navBackdrop) navBackdrop.hidden = !open;
}
navToggle?.addEventListener("click", () =>
    setNavigation(navToggle.getAttribute("aria-expanded") !== "true"),
);
navBackdrop?.addEventListener("click", () => {
    setNavigation(false);
    navToggle?.focus();
});
document.addEventListener("keydown", (event) => {
    if (
        event.key === "Escape" &&
        navToggle?.getAttribute("aria-expanded") === "true"
    ) {
        setNavigation(false);
        navToggle.focus();
    }
});
window
    .matchMedia("(min-width: 901px)")
    .addEventListener("change", () => setNavigation(false));
document
    .querySelectorAll("#primary-nav a")
    .forEach((link) =>
        link.addEventListener("click", () => setNavigation(false)),
    );
document.querySelectorAll(".account-menu").forEach((menu) => {
    document.addEventListener("click", (event) => {
        if (!menu.contains(event.target)) menu.open = false;
    });
    menu.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            menu.open = false;
            menu.querySelector("summary").focus();
        }
    });
});
document.querySelectorAll("[data-carousel]").forEach((carousel) => {
    const slides = [...carousel.querySelectorAll("[data-slide]")];
    const dots = [...carousel.querySelectorAll("[data-carousel-to]")];
    const controls = carousel.querySelector("[data-carousel-controls]");
    if (slides.length < 2 || !controls) return;
    let current = 0;
    const show = (index) => {
        current = (index + slides.length) % slides.length;
        slides.forEach((slide, position) => {
            slide.hidden = position !== current;
        });
        dots.forEach((dot, position) => {
            if (position === current) dot.setAttribute("aria-current", "true");
            else dot.removeAttribute("aria-current");
        });
        carousel.querySelector("[data-carousel-status]").textContent =
            `${current + 1} / ${slides.length}: ${slides[current].querySelector("h2").textContent}`;
    };
    carousel
        .querySelector("[data-carousel-prev]")
        .addEventListener("click", () => show(current - 1));
    carousel
        .querySelector("[data-carousel-next]")
        .addEventListener("click", () => show(current + 1));
    dots.forEach((dot, index) =>
        dot.addEventListener("click", () => show(index)),
    );
    controls.hidden = false;
});
document.querySelectorAll("[data-confirm]").forEach((button) =>
    button.addEventListener("click", (event) => {
        if (!window.confirm(button.dataset.confirm)) event.preventDefault();
    }),
);
document
    .querySelectorAll("[data-print]")
    .forEach((button) =>
        button.addEventListener("click", () => window.print()),
    );
const bookingForm = document.querySelector("[data-booking-form]");
if (bookingForm) {
    const seats = [...bookingForm.querySelectorAll('input[name="seats[]"]')];
    const combos = [...bookingForm.querySelectorAll("[data-combo-price]")];
    const submit = bookingForm.querySelector("[data-booking-submit]");
    const money = new Intl.NumberFormat("vi-VN", {
        style: "currency",
        currency: "VND",
    });
    let submitting = false;
    const update = () => {
        const selected = seats.filter(
            (input) => input.checked && !input.disabled,
        );
        let total = selected.reduce(
            (value, input) =>
                value +
                Number(bookingForm.dataset.basePrice) +
                Number(input.dataset.extra),
            0,
        );
        total += combos.reduce(
            (value, input) =>
                value +
                Math.max(0, Math.min(10, Number(input.value) || 0)) *
                    Number(input.dataset.comboPrice),
            0,
        );
        bookingForm.querySelector("[data-selected-seats]").textContent =
            selected.length
                ? selected.map((input) => input.dataset.label).join(", ")
                : "Chưa chọn ghế";
        bookingForm.querySelector("[data-total]").textContent =
            money.format(total);
        submit.disabled = submitting || selected.length === 0;
    };
    bookingForm.addEventListener("change", (event) => {
        if (
            event.target.matches('input[name="seats[]"]') &&
            seats.filter((input) => input.checked).length > 8
        ) {
            event.target.checked = false;
            bookingForm.querySelector("[data-seat-message]").textContent =
                "Bạn có thể chọn tối đa 8 ghế.";
        }
        update();
    });
    bookingForm.addEventListener("submit", () => {
        submitting = true;
        update();
    });
    async function refreshSeats() {
        if (submitting || document.hidden) return;
        try {
            const response = await fetch(bookingForm.dataset.seatUrl, {
                cache: "no-store",
            });
            if (!response.ok) return;
            const availability = new Map(
                (await response.json()).map((seat) => [
                    String(seat.id),
                    seat.reserved,
                ]),
            );
            let lost = false;
            seats.forEach((input) => {
                if (!availability.has(input.value)) return;
                const reserved = availability.get(input.value);
                if (reserved && input.checked) {
                    input.checked = false;
                    lost = true;
                }
                input.disabled = reserved;
            });
            if (lost)
                bookingForm.querySelector("[data-seat-message]").textContent =
                    "Một ghế vừa được người khác đặt. Vui lòng chọn ghế khác.";
            update();
        } catch {
            /* Server validates availability again during checkout. */
        }
    }
    setInterval(refreshSeats, 15000);
    update();
}

document.querySelectorAll("[data-countdown]").forEach((timer) => {
    const deadline = Date.parse(timer.dataset.countdown);
    if (!Number.isFinite(deadline)) return;
    const output = timer.querySelector("[data-time-left]");
    const tick = () => {
        const remaining = Math.max(
            0,
            Math.ceil((deadline - Date.now()) / 1000),
        );
        output.textContent = `${String(Math.floor(remaining / 60)).padStart(2, "0")}:${String(remaining % 60).padStart(2, "0")}`;
        if (remaining === 0) {
            clearInterval(interval);
            window.location.reload();
        }
    };
    const interval = setInterval(tick, 1000);
    tick();
});
const paymentProgress = document.querySelector("[data-order-poll]");
if (paymentProgress) {
    let polls = 0;
    const interval = setInterval(async () => {
        if (document.hidden) return;
        if (++polls > 180) {
            clearInterval(interval);
            return;
        }
        try {
            const response = await fetch(paymentProgress.dataset.orderPoll, {
                cache: "no-store",
            });
            if (!response.ok) return;
            const state = await response.json();
            if (
                state.status !== "pending" ||
                state.payment_status !== paymentProgress.dataset.paymentState
            ) {
                clearInterval(interval);
                window.location.reload();
            }
        } catch {
            /* The visible order page remains the source of truth. */
        }
    }, 5000);
}
document.querySelectorAll(".payment-start-form").forEach((form) => {
    const button = form.querySelector('button[type="submit"]');
    const original = button.innerHTML;
    window.addEventListener("pageshow", (event) => {
        if (event.persisted) {
            button.disabled = false;
            button.innerHTML = original;
        }
    });
    form.addEventListener("submit", () => {
        button.disabled = true;
        button.textContent = "Đang chuyển đến VNPay…";
    });
});
