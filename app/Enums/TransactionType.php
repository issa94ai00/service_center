<?php

namespace App\Enums;

enum TransactionType: string
{
    case SALES_INVOICE = 'SALES_INVOICE';
    case PURCHASE_INVOICE = 'PURCHASE_INVOICE';
    case CUSTOMER_PAYMENT = 'CUSTOMER_PAYMENT';
    case SUPPLIER_PAYMENT = 'SUPPLIER_PAYMENT';
    /** عمولة خبير الصيانة عن بلاغ سُوِّي — دائنة في دفتره. */
    case REPAIR_COMMISSION = 'REPAIR_COMMISSION';
    /** دفعة صُرفت للخبير — مدينة في دفتره. */
    case TECHNICIAN_PAYMENT = 'TECHNICIAN_PAYMENT';
    /** بضاعة أعادها العميل — دائنة في حسابه. */
    case SALES_RETURN = 'SALES_RETURN';
    /** بضاعة أُعيدت إلى المورد — مدينة في حسابه. */
    case PURCHASE_RETURN = 'PURCHASE_RETURN';
    /** نقد رُدّ إلى العميل عن مرتجع سبق أن دفع ثمنه — مدين في حسابه. */
    case CUSTOMER_REFUND = 'CUSTOMER_REFUND';
    /** نقد ردّه المورد عن مرتجع سبق أن دُفع ثمنه — دائن في حسابه. */
    case SUPPLIER_REFUND = 'SUPPLIER_REFUND';
    case OPENING = 'OPENING';
    case REVERSAL = 'REVERSAL';
    case ADJUSTMENT = 'ADJUSTMENT';
}
