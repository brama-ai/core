<?php

declare(strict_types=1);

namespace App\CoderAgent;

enum TaskStage: string
{
    case Planner = 'planner';
    case Architect = 'architect';
    case Coder = 'coder';
    case Auditor = 'auditor';
    case Validator = 'validator';
    case Tester = 'tester';
    case Documenter = 'documenter';
    case Summarizer = 'summarizer';
}
