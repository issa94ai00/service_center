<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case DRAFT = 'DRAFT';
    case POSTED = 'POSTED';
    case CANCELLED = 'CANCELLED';
}
