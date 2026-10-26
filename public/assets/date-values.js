// Pardis payload.iso is Gregorian; named Persian form fields must use jalali parts.
export function jalaliInputValue(payload) {
    const { year, month, day } = payload.jalali ?? {};
    if (
        ![year, month, day].every(Number.isInteger) ||
        year < 1300 ||
        year > 1499 ||
        month < 1 ||
        month > 12 ||
        day < 1 ||
        day > 31
    ) {
        throw new Error("Invalid Jalali picker payload");
    }
    return `${year}-${String(month).padStart(2, "0")}-${String(day).padStart(2, "0")}`;
}
