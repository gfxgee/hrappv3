import { Form } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import OvertimeController from '@/actions/App/Http/Controllers/Mobile/OvertimeController';
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

export function OvertimeSheet({ trigger }: { trigger: ReactNode }) {
    const [open, setOpen] = useState(false);

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>{trigger}</SheetTrigger>
            <SheetContent
                side="bottom"
                className="mx-auto max-h-[92svh] overflow-y-auto rounded-t-3xl bg-beige-100 sm:max-w-[430px]"
            >
                <Form
                    action={OvertimeController.store.url()}
                    method="post"
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="p-4 pb-[calc(1.5rem+env(safe-area-inset-bottom))]"
                >
                    {({ processing, errors }) => (
                        <div className="flex flex-col gap-3.5">
                            <SheetHeader className="p-0 pb-1">
                                <SheetTitle className="text-lg text-purple-950">
                                    Log overtime
                                </SheetTitle>
                            </SheetHeader>

                            <div className="grid grid-cols-2 gap-2.5">
                                <Field label="Date" error={errors.request_date}>
                                    <input
                                        type="date"
                                        name="request_date"
                                        defaultValue={today}
                                        className={fieldControl}
                                        required
                                    />
                                </Field>
                                <Field label="Hours" error={errors.hours}>
                                    <input
                                        type="number"
                                        name="hours"
                                        min={0.5}
                                        step={0.5}
                                        defaultValue={2}
                                        className={fieldControl}
                                        required
                                    />
                                </Field>
                            </div>

                            <Field label="Reason" error={errors.reason}>
                                <textarea
                                    name="reason"
                                    rows={3}
                                    placeholder="What did you work on?"
                                    className={cn(fieldControl, 'resize-none')}
                                    required
                                />
                            </Field>

                            <button
                                type="submit"
                                disabled={processing}
                                className="mt-1 w-full rounded-2xl bg-yellow-400 py-4 text-base font-bold text-yellow-950 active:scale-[0.98] disabled:opacity-60"
                            >
                                {processing ? 'Submitting…' : 'Submit request'}
                            </button>
                        </div>
                    )}
                </Form>
            </SheetContent>
        </Sheet>
    );
}
