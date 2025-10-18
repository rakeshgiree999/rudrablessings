<?php
declare(strict_types=1);

if (!class_exists('RbApiTestResponse')) {
    /**
     * Lightweight carrier used when API scripts are executed under test mode.
     */
    class RbApiTestResponse extends RuntimeException
    {
        public array $payload;
        public int $statusCode;

        public function __construct(array $payload, int $statusCode)
        {
            parent::__construct('RB_API_TEST_RESPONSE', $statusCode);
            $this->payload = $payload;
            $this->statusCode = $statusCode;
        }
    }
}
