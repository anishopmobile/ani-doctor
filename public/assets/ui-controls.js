import { PardisDatepicker } from "./vendor/pardis.js";

const instances = new Map();
let sequence = 0;
const digits = (value) =>
    String(value).replace(/[۰-۹٠-٩]/g, (char) =>
        "۰۱۲۳۴۵۶۷۸۹".includes(char)
            ? "۰۱۲۳۴۵۶۷۸۹".indexOf(char)
            : "٠١٢٣٤٥٦٧٨٩".indexOf(char),
    );
const normalizeDate = (value) => digits(value).replaceAll("/", "-");
const enabled = (input) => !input.matches(":disabled");
function fieldGroup(control) {
    const label = control.closest("label");
    if (label && !label.parentElement.classList.contains("ui-field")) {
        const group = document.createElement("div");
        group.className = "ui-field";
        label.before(group);
        group.append(label);
    }
}
function labelText(control) {
    const label = control.labels?.[0];
    if (!label) return control.getAttribute("aria-label") || "انتخاب گزینه";
    const clone = label.cloneNode(true);
    clone
        .querySelectorAll("input,select,textarea,button")
        .forEach((item) => item.remove());
    return clone.textContent.trim() || "انتخاب گزینه";
}
function notify(control) {
    control.dispatchEvent(new Event("input", { bubbles: true }));
    control.dispatchEvent(new Event("change", { bubbles: true }));
}

function enhanceSingle(select) {
    fieldGroup(select);
    const placeholder =
        select.options[0]?.value === ""
            ? select.options[0].text
            : "انتخاب کنید";
    const api = window.hcgSelect(select, {
        placeholder,
        searchPlaceholder: "جستجو در گزینه‌ها…",
        noResultsText: "گزینه‌ای پیدا نشد",
        clearable: select.options[0]?.value === "",
    });
    api.element
        .querySelector(".hcg-select-clear")
        .setAttribute("aria-label", "پاک‌کردن انتخاب");
    const control = api.element.querySelector(".hcg-select-control");
    const refresh = () => {
        // HCG observes its own options. Rebuilding its list on unrelated form
        // mutations would remove the clicked option between pointerdown and click.
        const disabled = !enabled(select);
        api.element.classList.toggle("is-disabled", disabled);
        control.setAttribute("aria-disabled", String(disabled));
        control.tabIndex = disabled ? -1 : 0;
        if (disabled) api.close();
        for (const name of ["aria-describedby", "aria-invalid"]) {
            if (select.hasAttribute(name))
                control.setAttribute(name, select.getAttribute(name));
        }
    };
    ["pointerdown", "click", "keydown"].forEach((type) =>
        api.element.addEventListener(
            type,
            (event) => {
                if (!enabled(select)) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                }
            },
            true,
        ),
    );
    select.addEventListener("invalid", (event) => {
        event.preventDefault();
        control.setAttribute("aria-invalid", "true");
        control.focus();
    });
    instances.set(select, { refresh, destroy: () => api.destroy() });
    refresh();
}

// HCG intentionally handles single selects only. Keep native multiple values intact
// and add a small checkbox/search companion rather than silently dropping selections.
function enhanceMultiple(select) {
    fieldGroup(select);
    const label = select.closest("label");
    const wrap = document.createElement("details");
    wrap.className = "ui-multi";
    const summary = document.createElement("summary");
    summary.id = "ui-multi-" + ++sequence;
    summary.setAttribute("aria-label", labelText(select));
    const panel = document.createElement("div");
    panel.className = "ui-multi-panel";
    const search = document.createElement("input");
    search.type = "search";
    search.className = "form-control";
    search.placeholder = "جستجو در گزینه‌ها…";
    search.setAttribute("aria-label", "جستجو در " + labelText(select));
    const options = document.createElement("div");
    options.className = "ui-multi-options";
    const empty = document.createElement("p");
    empty.textContent = "گزینه‌ای پیدا نشد";
    empty.setAttribute("role", "status");
    panel.append(search, options, empty);
    wrap.append(summary, panel);
    (label || select).after(wrap);
    select.classList.add("hcg-select-native");
    select.tabIndex = -1;
    select.setAttribute("aria-hidden", "true");
    select.inert = true;
    const filter = () => {
        let count = 0;
        options.querySelectorAll("label").forEach((row) => {
            row.hidden = !row.dataset.text.includes(
                search.value.trim().toLocaleLowerCase("fa"),
            );
            if (!row.hidden) count++;
        });
        empty.hidden = count > 0;
    };
    let signature = "";
    const refresh = () => {
        const selected = Array.from(select.selectedOptions).map(
            (option) => option.text,
        );
        summary.textContent = selected.length
            ? selected.join("، ")
            : "انتخاب کنید";
        summary.setAttribute("aria-disabled", String(!enabled(select)));
        summary.tabIndex = enabled(select) ? 0 : -1;
        if (!enabled(select)) wrap.open = false;
        const next = JSON.stringify([
            enabled(select),
            ...Array.from(select.options).map((option) => [
                option.text,
                option.value,
                option.disabled,
                option.selected,
            ]),
        ]);
        if (next !== signature) {
            signature = next;
            options.replaceChildren();
            Array.from(select.options).forEach((option) => {
                const row = document.createElement("label");
                row.dataset.text = option.text.toLocaleLowerCase("fa");
                const checkbox = document.createElement("input");
                checkbox.type = "checkbox";
                checkbox.className = "form-check-input";
                checkbox.checked = option.selected;
                checkbox.disabled =
                    !enabled(select) ||
                    option.disabled ||
                    !!option.closest("optgroup")?.disabled;
                const text = document.createElement("span");
                text.textContent = option.text;
                row.append(checkbox, text);
                checkbox.addEventListener("change", () => {
                    if (!enabled(select)) return;
                    option.selected = checkbox.checked;
                    notify(select);
                    refresh();
                });
                options.append(row);
            });
            filter();
        }
    };
    search.addEventListener("input", filter);
    wrap.addEventListener("toggle", () => {
        if (!enabled(select)) {
            wrap.open = false;
            return;
        }
        if (wrap.open) search.focus();
    });
    summary.addEventListener("click", (event) => {
        if (!enabled(select)) event.preventDefault();
    });
    wrap.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            wrap.open = false;
            summary.focus();
        }
    });
    const outsideClick = (event) => {
        if (!wrap.contains(event.target)) wrap.open = false;
    };
    document.addEventListener("click", outsideClick);
    select.addEventListener("change", refresh);
    select.addEventListener("invalid", (event) => {
        event.preventDefault();
        summary.setAttribute("aria-invalid", "true");
        summary.focus();
    });
    select.form?.addEventListener("reset", () => setTimeout(refresh));
    instances.set(select, {
        refresh,
        destroy: () => {
            document.removeEventListener("click", outsideClick);
            wrap.remove();
        },
    });
    refresh();
}

let dateDialog,
    datePicker,
    activeDate,
    seeding = false;
function createDateDialog() {
    if (dateDialog) return;
    dateDialog = document.createElement("dialog");
    dateDialog.className = "ui-date-dialog";
    dateDialog.setAttribute("aria-labelledby", "ui-date-title");
    const header = document.createElement("div");
    header.className = "ui-date-header";
    const title = document.createElement("h2");
    title.id = "ui-date-title";
    title.className = "h4";
    title.textContent = "انتخاب تاریخ شمسی";
    const close = document.createElement("button");
    close.type = "button";
    close.className = "btn btn-outline-secondary";
    close.textContent = "بستن";
    close.addEventListener("click", () => dateDialog.close());
    header.append(title, close);
    const host = document.createElement("div");
    dateDialog.append(header, host);
    document.body.append(dateDialog);
    datePicker = new PardisDatepicker(host, {
        inline: true,
        numeralType: "persian",
        onChange: (payload) => {
            if (seeding || !activeDate || !enabled(activeDate)) return;
            activeDate.value = payload.iso;
            notify(activeDate);
            dateDialog.close();
        },
    });
    dateDialog.addEventListener("click", (event) => {
        if (event.target === dateDialog) {
            const r = dateDialog.getBoundingClientRect();
            if (
                event.clientX < r.left ||
                event.clientX > r.right ||
                event.clientY < r.top ||
                event.clientY > r.bottom
            )
                dateDialog.close();
        }
    });
}
function enhanceDate(input) {
    fieldGroup(input);
    const wrap = document.createElement("div");
    wrap.className = "ui-date-field";
    input.before(wrap);
    wrap.append(input);
    const choose = document.createElement("button");
    choose.type = "button";
    choose.className = "btn btn-outline-primary";
    choose.textContent = "▦";
    choose.title = "انتخاب تاریخ شمسی";
    choose.setAttribute("aria-label", "انتخاب تاریخ: " + labelText(input));
    choose.setAttribute("aria-haspopup", "dialog");
    const clear = document.createElement("button");
    clear.type = "button";
    clear.className = "btn btn-outline-secondary";
    clear.textContent = "×";
    clear.title = "پاک‌کردن تاریخ";
    clear.setAttribute("aria-label", "پاک‌کردن تاریخ");
    wrap.append(choose, clear);
    input.inputMode = "none";
    input.setAttribute("autocomplete", "off");
    const open = () => {
        if (!enabled(input)) return;
        createDateDialog();
        activeDate = input;
        const date = normalizeDate(input.value).match(
            /^(\d{4})-(\d{2})-(\d{2})$/,
        );
        seeding = true;
        try {
            if (date) datePicker.setValue(...date.slice(1).map(Number));
            else datePicker.engine.goToToday();
        } finally {
            seeding = false;
        }
        dateDialog.showModal();
    };
    choose.addEventListener("click", open);
    input.addEventListener("click", open);
    input.addEventListener("keydown", (event) => {
        if (["Enter", " ", "ArrowDown"].includes(event.key)) {
            event.preventDefault();
            open();
        }
    });
    clear.addEventListener("click", () => {
        if (enabled(input)) {
            input.value = "";
            notify(input);
            input.focus();
        }
    });
    const refresh = () => {
        choose.disabled = !enabled(input);
        clear.disabled = !enabled(input) || !input.value;
    };
    input.addEventListener("change", refresh);
    input.form?.addEventListener("reset", () => setTimeout(refresh));
    instances.set(input, {
        refresh,
        destroy: () => {
            if (activeDate === input) {
                dateDialog?.close();
                activeDate = null;
            }
        },
    });
    refresh();
}

function enhance(root) {
    const controls = [
        ...(root.matches?.("select,input[data-persian-date]") ? [root] : []),
        ...root.querySelectorAll("select,input[data-persian-date]"),
    ];
    for (const control of controls) {
        if (
            instances.has(control) ||
            control.closest("template") ||
            control.closest("[data-ui-native]")
        )
            continue;
        if (control.matches("input[data-persian-date]")) enhanceDate(control);
        else if (control.multiple) enhanceMultiple(control);
        else if (window.hcgSelect) enhanceSingle(control);
    }
}
enhance(document);
let refreshQueued = false;
const scheduleRefresh = () => {
    if (refreshQueued) return;
    refreshQueued = true;
    queueMicrotask(() => {
        refreshQueued = false;
        for (const [control, api] of instances) {
            if (!control.isConnected) {
                api.destroy();
                instances.delete(control);
            } else api.refresh();
        }
    });
};
new MutationObserver((records) => {
    for (const record of records) {
        if (
            record.type === "attributes" &&
            record.target.matches("select,fieldset,input[data-persian-date]")
        )
            scheduleRefresh();
        for (const node of record.addedNodes) {
            if (node.nodeType === 1) enhance(node);
        }
        if (
            Array.from(record.removedNodes).some(
                (node) =>
                    node.nodeType === 1 &&
                    (node.matches("select,input[data-persian-date]") ||
                        node.querySelector("select,input[data-persian-date]")),
            )
        )
            scheduleRefresh();
    }
}).observe(document.body, {
    childList: true,
    subtree: true,
    attributes: true,
    attributeFilter: ["disabled", "required"],
});
document.addEventListener(
    "submit",
    (event) => {
        event.target
            .querySelectorAll("input[data-persian-date]")
            .forEach((input) => {
                input.value = normalizeDate(input.value);
            });
    },
    true,
);
