<?php

namespace App\Enums;

enum UserRole: string
{
    case EXPEDITION = 'expedition';
    case VENDOR = 'vendor';
    case COORDINATOR = 'coordinator';
}
