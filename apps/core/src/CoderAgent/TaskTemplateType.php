<?php

declare(strict_types=1);

namespace App\CoderAgent;

enum TaskTemplateType: string
{
    case Feature = 'feature';
    case Bugfix = 'bugfix';
    case Refactor = 'refactor';
    case Custom = 'custom';
}
