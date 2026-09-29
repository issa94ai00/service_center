/**
 * How money changes hands, in the wording the counter uses. Mirrors the
 * PaymentMethod enum on the server (CASH | BANK | OTHER).
 */
export const PAYMENT_METHODS = [
    { value: 'CASH', label: 'نقداً' },
    { value: 'BANK', label: 'حوالة / بنك' },
    { value: 'OTHER', label: 'أخرى' },
];

export function paymentMethodLabel(value) {
    return PAYMENT_METHODS.find((method) => method.value === value)?.label ?? value;
}
