<?php

namespace App\Enums;

enum PaymentType: string
{
    case CUSTOMER = 'CUSTOMER';
    case SUPPLIER = 'SUPPLIER';
    /** صرف مستحقات خبير صيانة — لا يُخصَّص على فواتير، بل يُنقص رصيد الخبير. */
    case TECHNICIAN = 'TECHNICIAN';
}
