<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\KeepAlive;

/** A keep-alive failure with its HL-KA-* code. */
final class KeepAliveError extends \RuntimeException
{
    private $hlCode;

    public function __construct(string $hlCode, string $detail)
    {
        parent::__construct($detail);
        $this->hlCode = $hlCode;
    }

    public function code(): string
    {
        return $this->hlCode;
    }
}
