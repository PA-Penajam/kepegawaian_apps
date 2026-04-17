<?php

namespace App\Enums;

enum StatusPengajuanPerubahanData: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
