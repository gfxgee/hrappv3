import { Form } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import AttendanceController from '@/actions/App/Http/Controllers/Mobile/AttendanceController';
import { Field, fieldControl } from '@/components/mobile/field';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { cn } from '@/lib/utils';

const today = new Date().toISOString().slice(0, 10);

export function CorrectionSheet({
    types,
    trigger,
}: {
    types: Record<string, string>;
    trigger: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const [type, setType] = useState('missing_clockin');

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>{trigger}</SheetTrigger>
            <SheetContent
                side="bottom"
                className="mx-auto max-h-[92svh] overflow-y-auto rounded-t-3xl bg-beige-100 sm:max-w-[430px]"
            >
                <Form
                    action={AttendanceController.storeCorrection.url()}
                    method="post"
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="p-4"
                >
                    {({ processing, errors }) => (
                        <div className="flex flex-col gap-3.5">
                            <SheetHeader className="p-0 pb-1">
                                <SheetTitle className="text-lg text-purple-950">
                                    Report a correction
                                </SheetTitle>
                            </SheetHeader>

                            <Field
                                label="What's wrong?"
                                error={errors.correction_type}
                            >
                                <select
                                    name="correction_type"
                                    value={type}
                                    onChange={(event) =>
                                        setType(event.target.value)
                                    }
                                    className={fieldControl}
                                >
                                    {Object.entries(types).map(
                                        ([value, label]) => (
                                            <option key={value} value={value}>
                                                {label}
                                            </option>
                                        ),
                                    )}
                                </select>
                            </Field>

                            {type === 'wrong_time' && (
                                <Field
                                    label="Which punch?"
                                    error={errors.target_log_type}
                                >
                                    <select
                                        name="target_log_type"
                                        defaultValue="clockin"
                                        className={fieldControl}
                                    >
                                        <option value="clockin">
                                            Clock-in
                                        </option>
                                        <option value="clockout">
                                            Clock-out
                                        </option>
                                    </select>
                                </Field>
                            )}

                            <Field
                                label="Correct date & time"
                                error={errors.corrected_at}
                            >
                                <input
                                    type="datetime-local"
                                    name="corrected_at"
                                    defaultValue={`${today}T18:00`}
                                    className={fieldControl}
                                    required
                                />
                            </Field>

                            <Field label="Reason" error={errors.reason}>
                                <textarea
                                    name="reason"
                                    rows={3}
                                    placeholder="Explain what happened"
                                    className={cn(fieldControl, 'resize-none')}
                                    required
                                />
                            </Field>

                            <button
                                type="submit"
                                disabled={processing}
                                className="mt-1 w-full rounded-2xl bg-yellow-400 py-4 text-base font-bold text-yellow-950 active:scale-[0.98] disabled:opacity-60"
                            >
                                {processing
                                    ? 'Submitting…'
                                    : 'Submit correction'}
                            </button>
                        </div>
                    )}
                </Form>
            </SheetContent>
        </Sheet>
    );
}
