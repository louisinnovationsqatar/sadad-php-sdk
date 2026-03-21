<?php
// SADAD Payment Gateway SDK for PHP
// Built by Louis Innovations (www.louis-innovations.com)

namespace LouisInnovations\Sadad\Checkout;

class CheckoutResult
{
    /**
     * @param string               $url    The checkout URL (action for the HTML form).
     * @param array<string, mixed> $params All checkout parameters (including signature/checksumhash).
     */
    public function __construct(
        public readonly string $url,
        public readonly array $params,
    ) {
    }

    /**
     * Generate an HTML form that posts all checkout parameters to the SADAD gateway.
     *
     * Array values (e.g. productdetail) are expanded into indexed inputs:
     *   productdetail[0][order_id], productdetail[0][amount], etc.
     *
     * @param string $formId     The HTML id attribute for the <form> element.
     * @param bool   $autoSubmit Whether to append a JS auto-submit script.
     * @return string            Complete HTML form markup.
     */
    public function toHtmlForm(string $formId = 'sadad-checkout-form', bool $autoSubmit = true): string
    {
        $inputs = $this->buildInputs($this->params);

        $html  = sprintf('<form id="%s" method="POST" action="%s">', htmlspecialchars($formId, ENT_QUOTES), htmlspecialchars($this->url, ENT_QUOTES));
        $html .= "\n";

        foreach ($inputs as [$name, $value]) {
            $html .= sprintf(
                '    <input type="hidden" name="%s" value="%s">',
                htmlspecialchars($name, ENT_QUOTES),
                htmlspecialchars((string) $value, ENT_QUOTES)
            );
            $html .= "\n";
        }

        $html .= '</form>';

        if ($autoSubmit) {
            $html .= "\n";
            $html .= sprintf('<script>document.getElementById("%s").submit();</script>', htmlspecialchars($formId, ENT_QUOTES));
        }

        return $html;
    }

    /**
     * Recursively flatten params into (name, value) pairs for hidden inputs.
     *
     * @param  array<string, mixed> $params
     * @param  string               $prefix
     * @return list<array{string, scalar}>
     */
    private function buildInputs(array $params, string $prefix = ''): array
    {
        $inputs = [];

        foreach ($params as $key => $value) {
            $inputName = $prefix === '' ? (string) $key : $prefix . '[' . $key . ']';

            if (is_array($value)) {
                $inputs = array_merge($inputs, $this->buildInputs($value, $inputName));
            } else {
                $inputs[] = [$inputName, $value];
            }
        }

        return $inputs;
    }
}
