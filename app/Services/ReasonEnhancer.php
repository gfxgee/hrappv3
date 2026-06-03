<?php

namespace App\Services;

use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;

class ReasonEnhancer
{
    /**
     * Whether the configured AI provider has an API key set.
     */
    public function isConfigured(): bool
    {
        return filled(config('prism.providers.'.$this->providerName().'.api_key'));
    }

    /**
     * Rewrite a draft "reason" using the configured AI provider, with a
     * system prompt tailored to the request context.
     *
     * @param  'polish'|'expand'  $mode
     * @param  array{kind?: 'leave'|'overtime', leave_type?: string, hours?: float|string}  $context
     */
    public function enhance(string $draft, string $mode, array $context = []): string
    {
        $response = Prism::text()
            ->using($this->provider(), $this->model())
            ->withSystemPrompt($this->systemPrompt($mode, $context))
            ->withPrompt($draft)
            ->asText();

        return trim($response->text);
    }

    /**
     * @param  array{kind?: 'leave'|'overtime'|'praise'|'comment', leave_type?: string, hours?: float|string}  $context
     */
    private function systemPrompt(string $mode, array $context): string
    {
        $kind = $context['kind'] ?? 'leave';

        $base = match ($kind) {
            'overtime' => 'You help an employee write the reason for a workplace overtime request. '
                .'Write in the first person, professional and respectful, suitable for HR.',
            'praise' => 'You help an employee write a workplace recognition message praising a colleague. '
                .'Write warmly and specifically in the first person, highlighting what the colleague did well. '
                .'Keep it genuine, positive, and professional.',
            'comment' => 'You help an employee write a short, supportive comment on a colleague\'s recognition post. '
                .'Keep it friendly, encouraging, and brief.',
            default => 'You help an employee write the reason for a workplace leave request. '
                .'Write in the first person, professional and respectful, suitable for HR.',
        };

        $base .= ' Stay strictly on topic for this message. '
            .'Do not invent specific facts, names, or dates that are not implied by the input. '
            .'Return ONLY the text itself, with no preamble, quotes, or labels.';

        // Add specifics from the form so the AI can stay on-topic.
        $details = [];

        if ($kind === 'leave' && filled($context['leave_type'] ?? null)) {
            $details[] = 'Leave type: '.$context['leave_type'];
        }

        if ($kind === 'overtime' && filled($context['hours'] ?? null)) {
            $details[] = 'Hours requested: '.$context['hours'];
        }

        if ($details !== []) {
            $base .= ' Context: '.implode('; ', $details).'.';
        }

        return match ($mode) {
            'expand' => $base.' Expand the brief notes into a complete message of 1 to 3 sentences.',
            default => $base.' Polish the draft into clear, concise wording of 1 to 2 sentences, keeping the original meaning.',
        };
    }

    private function providerName(): string
    {
        return (string) config('ai.enhance.provider', 'gemini');
    }

    private function provider(): Provider
    {
        return Provider::from($this->providerName());
    }

    private function model(): string
    {
        return (string) config('ai.enhance.model', 'gemini-2.5-flash');
    }
}
