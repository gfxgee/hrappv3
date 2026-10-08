<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A link an employee can be sent to for support — a web page, an email address,
 * or a phone number.
 *
 * Laravel's built-in `url` rule only accepts schemes with an authority
 * (`//host`), so it rejects `mailto:` and `tel:`. The scheme is also restricted
 * to a safe allow-list, since the value is rendered straight into an `href`
 * and something like `javascript:` would otherwise be clickable.
 */
class SupportLink implements ValidationRule
{
    /**
     * Schemes that are safe to render as a link.
     *
     * @var list<string>
     */
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Blank is handled by `nullable`/`required` — an empty link just hides
        // the button.
        if (blank($value)) {
            return;
        }

        $value = (string) $value;
        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        if (! in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            $fail('The :attribute must be a web address (https://…), an email link (mailto:…), or a phone link (tel:…).');

            return;
        }

        match ($scheme) {
            'mailto' => $this->validateMailto($value, $fail),
            'tel' => $this->validateTel($value, $fail),
            default => $this->validateWebAddress($value, $fail),
        };
    }

    /**
     * `mailto:someone@example.com`, optionally with `?subject=…` parameters.
     */
    private function validateMailto(string $value, Closure $fail): void
    {
        $address = explode('?', substr($value, strlen('mailto:')), 2)[0];

        if (! filter_var(rawurldecode($address), FILTER_VALIDATE_EMAIL)) {
            $fail('The :attribute must contain a valid email address after "mailto:".');
        }
    }

    /**
     * `tel:+639171234567` — digits, optionally with a leading + and separators.
     */
    private function validateTel(string $value, Closure $fail): void
    {
        $number = substr($value, strlen('tel:'));

        if (preg_match('/^\+?[0-9().\s-]{3,}$/', $number) !== 1) {
            $fail('The :attribute must contain a valid phone number after "tel:".');
        }
    }

    private function validateWebAddress(string $value, Closure $fail): void
    {
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            $fail('The :attribute must be a valid web address.');
        }
    }
}
