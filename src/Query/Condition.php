<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Query;

/**
 * A condition of a query: created by the methods of meta fields (`EventDetailsFields::capacity()->greaterThan(10)`),
 * by Terms and grouped by Any and All. The framework implements it; a class of your own is rejected by the query.
 *
 * @api Posts and queries
 */
interface Condition {}
