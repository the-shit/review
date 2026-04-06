<?php

namespace TheShit\Review\Enums;

enum Classification: string
{
    case BugFix = 'bug_fix';
    case Feature = 'feature';
    case Refactor = 'refactor';
    case Docs = 'docs';
    case Dependency = 'dependency';
    case Chore = 'chore';
}
