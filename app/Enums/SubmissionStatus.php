<?php

namespace App\Enums;

enum SubmissionStatus: string
{
    case SUBMITTED = 'SUBMITTED';
    case APPROVED = 'APPROVED';
    case REVISION = 'REVISION';
    case REJECTED = 'REJECTED';
    case RELEASED = 'RELEASED';
}
