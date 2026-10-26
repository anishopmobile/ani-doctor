import test from "node:test";
import assert from "node:assert/strict";
import { jalaliInputValue } from "../../public/assets/date-values.js";
import { PardisEngine } from "../../public/assets/vendor/pardis.js";

test("1405-08-05 is submitted as Jalali, never payload.iso Gregorian", () => {
    const payload = PardisEngine.buildDatePayload(1405, 8, 5);
    assert.equal(payload.iso, "2026-10-27");
    assert.equal(jalaliInputValue(payload), "1405-08-05");
});
test("leap Esfand remains Jalali across Gregorian year boundaries", () => {
    const payload = PardisEngine.buildDatePayload(1399, 12, 30);
    assert.equal(payload.iso, "2021-03-20");
    assert.equal(jalaliInputValue(payload), "1399-12-30");
});
test("Farvardin and zero padding are independent of browser timezone", () => {
    assert.equal(
        jalaliInputValue(PardisEngine.buildDatePayload(1405, 1, 1)),
        "1405-01-01",
    );
});
test("Gregorian-only and malformed payloads fail closed", () => {
    assert.throws(() => jalaliInputValue({ iso: "2026-10-27" }));
    assert.throws(() =>
        jalaliInputValue({ jalali: { year: 2026, month: 10, day: 27 } }),
    );
    assert.throws(() =>
        jalaliInputValue({ jalali: { year: 1405, month: 13, day: 1 } }),
    );
});
