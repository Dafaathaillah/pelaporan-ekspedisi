<?php

namespace App\Enums;

enum UserRole: string
{
    case DEVELOPER = 'developer';
    case EXPEDITION = 'expedition';
    case DRIVER = 'driver';
    case VENDOR = 'vendor';
    case COORDINATOR = 'coordinator';
}
