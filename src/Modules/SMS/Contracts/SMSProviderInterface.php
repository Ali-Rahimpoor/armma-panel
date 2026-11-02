<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\SMS\Contracts;

use Arma\ArmaPanel\Modules\SMS\SMSResult;

interface SMSProviderInterface {

    /**
     * Provider unique key (e.g. 'melipayamak').
     */
    public function getKey(): string;

    /**
     * Human readable name.
     */
    public function getTitle(): string;

    /**
     * Send an SMS using an approved pattern/template.
     *
     * The pattern identifier is a STRING because providers differ:
     * Melipayamak/SMS.ir use numeric IDs (cast internally), Kavenegar uses a
     * template NAME (e.g. "LoginOtp") and FarazSMS uses a pattern CODE (e.g.
     * "SJ3FgPrE0C"). Args are ordered values mapped to the provider's
     * variables in order (token/token2/token3, #Code#/#Code2#, %var1%...).
     *
     * @param string $to Recipient phone number.
     * @param string $pattern Pattern ID / template name / pattern code.
     * @param array<array-key, string|int> $args Ordered pattern variables.
     * @param array{param_names?: string[], line_number?: string, context?: string} $options
     *            Provider-specific extras: SMS.ir needs the template parameter
     *            NAMES matching args order; FarazSMS needs pattern variable
     *            names plus the sender line number. Providers that map args
     *            positionally (Melipayamak {0}{1}, Kavenegar token/token2)
     *            ignore this.
     * @return SMSResult
     */
    public function sendPattern(string $to, string $pattern, array $args = [], array $options = []): SMSResult;
}
