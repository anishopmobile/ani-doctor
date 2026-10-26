const root = document.querySelector("[data-public-flow]");
if (root) {
    const step = root.querySelector("[data-patient-step]");
    const intro = root.querySelector("[data-selection-help]");
    const summary = root.querySelector("[data-booking-summary]");
    const title = root.querySelector("[data-patient-heading]");
    const badges = document.querySelectorAll("[data-flow-step]");
    const reset = () => {
        if (!step) return;
        step.disabled = true;
        step.hidden = true;
        intro.hidden = false;
        summary.hidden = true;
        badges.forEach((badge, index) => {
            badge.setAttribute("aria-current", index === 0 ? "step" : "false");
        });
    };
    root.addEventListener("booking:selection-reset", reset);
    root.addEventListener("booking:slot-selected", (event) => {
        if (!step) return;
        step.hidden = false;
        step.disabled = false;
        intro.hidden = true;
        summary.hidden = false;
        summary.querySelector("[data-summary-service]").textContent =
            event.detail.service;
        summary.querySelector("[data-summary-time]").textContent =
            event.detail.day.replaceAll("-", "/") + " · " + event.detail.time;
        badges.forEach((badge, index) => {
            badge.setAttribute("aria-current", index === 1 ? "step" : "false");
        });
    });
    root.querySelector("[data-go-patient]")?.addEventListener("click", () => {
        title?.focus();
        title?.scrollIntoView({
            block: "start",
            behavior: matchMedia("(prefers-reduced-motion: reduce)").matches
                ? "auto"
                : "smooth",
        });
    });
    reset();
}
