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
    case OPENING = 'OPENING';
    case REVERSAL = 'REVERSAL';
    case ADJUSTMENT = 'ADJUSTMENT';
}
