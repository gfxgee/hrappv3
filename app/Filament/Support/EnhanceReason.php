<?php

namespace App\Filament\Support;

use App\Enum\LeaveType;
use App\Services\ReasonEnhancer;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Throwable;

class EnhanceReason
{
    /**
     * Build the "Polish" and "Expand" AI hint actions for a text field,
     * scoped to the kind so the AI stays on topic.
     *
     * @param  'leave'|'overtime'|'praise'|'comment'  $kind
     * @param  string  $field  The form field to read from and write back to.
     * @return array<Action>
     */
    public static function for(string $kind, string $field = 'reason'): array
    {
        return [
            self::action('polish', 'Polish', 'heroicon-m-sparkles', $kind, $field),
            self::action('expand', 'Expand', 'heroicon-m-arrows-pointing-out', $kind, $field),
        ];
    }

    /**
     * Backwards-compatible alias — leave context, "reason" field.
     *
     * @return array<Action>
     */
    public static function hintActions(): array
    {
        return self::for('leave');
    }

    private static function action(string $mode, string $label, string $icon, string $kind, string $field): Action
    {
        return Action::make($mode.'_'.$field)
            ->label($label)
            ->icon($icon)
            ->visible(fn (): bool => app(ReasonEnhancer::class)->isConfigured())
            ->action(function (Get $get, Set $set) use ($mode, $kind, $field): void {
                $draft = trim((string) $get($field));

                if ($draft === '') {
                    Notification::make()
                        ->warning()
                        ->title('Write a draft first')
                        ->body('Type a few words, then let AI refine it.')
                        ->send();

                    return;
                }

                try {
                    $set($field, app(ReasonEnhancer::class)->enhance($draft, $mode, self::collectContext($kind, $get)));

                    Notification::make()
                        ->success()
                        ->title('Updated with AI')
                        ->send();
                } catch (Throwable $e) {
                    report($e);

                    Notification::make()
                        ->danger()
                        ->title('AI enhancement failed')
                        ->body('Please try again in a moment.')
                        ->send();
                }
            });
    }

    /**
     * Pull related form values to give the AI more context.
     *
     * @return array{kind: string, leave_type?: string, hours?: string}
     */
    private static function collectContext(string $kind, Get $get): array
    {
        $context = ['kind' => $kind];

        if ($kind === 'leave') {
            $value = $get('request_type');
            $type = is_string($value) ? LeaveType::tryFrom($value) : null;

            if ($type !== null) {
                $context['leave_type'] = $type->plainLabel();
            }
        }

        if ($kind === 'overtime') {
            $hours = $get('hours');

            if (is_numeric($hours)) {
                $context['hours'] = (string) $hours;
            }
        }

        return $context;
    }
}
