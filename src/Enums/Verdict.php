<?php

namespace TheShit\Review\Enums;

enum Verdict: string
{
    case Approve = 'approve';
    case RequestChanges = 'request_changes';
    case Comment = 'comment';
}
