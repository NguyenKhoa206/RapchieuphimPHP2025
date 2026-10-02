"use strict";
document.querySelector(".nav-toggle")?.addEventListener("click", (event) => {
    const button = event.currentTarget;
    const expanded = button.getAttribute("aria-expanded") !== "true";
    button.setAttribute("aria-expanded", String(expanded));
    document
        .getElementById("primary-nav")
        ?.classList.toggle("is-open", expanded);
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
