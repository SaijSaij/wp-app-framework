<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Rest;

/**
 * String formats supported by the WordPress REST schema
 * (rest_validate_value_from_schema() and rest_sanitize_value_from_schema()).
 *
 * WordPress validates hex-color, date-time, email, ip and uuid; uri, text-field
 * and textarea-field only sanitize the value.
 */
enum Format: string
{
    case HexColor = 'hex-color';
    case DateTime = 'date-time';
    case Email = 'email';
    case Uri = 'uri';
    case Ip = 'ip';
    case Uuid = 'uuid';
    case TextField = 'text-field';
    case TextareaField = 'textarea-field';
}
