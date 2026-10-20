<?php

namespace App\Enums;

enum UserRole: string
{
    case DEVELOPER = 'developer';
    case EXPEDITION = 'expedition';
    case DRIVER = 'driver';
    case PURCHASING_ADMIN = 'purchasing_admin';
    case VENDOR = 'vendor';
    case COORDINATOR = 'coordinator';
    case FINANCE = 'finance';
}
