<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Exceptions;

/** 503, the client can retry. */
class ExternalServiceUnavailable extends Fault {}
