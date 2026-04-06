<?php

namespace TheShit\Review\Enums;

enum ChangeType: string
{
    case SignatureChanged = 'signature_changed';
    case MethodAdded = 'method_added';
    case MethodRemoved = 'method_removed';
    case VisibilityChanged = 'visibility_changed';
    case ReturnTypeChanged = 'return_type_changed';
    case ConstructorChanged = 'constructor_changed';
    case ExceptionHandlingAdded = 'exception_handling_added';
    case ExceptionHandlingRemoved = 'exception_handling_removed';
    case InterfaceChanged = 'interface_changed';
    case ClassAdded = 'class_added';
    case ClassRemoved = 'class_removed';

    public function affectsPublicApi(): bool
    {
        return match ($this) {
            self::SignatureChanged,
            self::MethodRemoved,
            self::VisibilityChanged,
            self::ReturnTypeChanged,
            self::InterfaceChanged,
            self::ClassRemoved => true,
            default => false,
        };
    }
}
