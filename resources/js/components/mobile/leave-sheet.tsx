import { Form } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import LeaveController from '@/actions/App/Http/Controllers/Mobile/LeaveController';
import { Field, fieldControl } from '@/components/mobile/field';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import type { LeaveBalance } from '@/types/mobile';

const today = new Date().toISOString().slice(0, 10);

export function LeaveSheet({
    balances,
    trigger,
}: {
    balances: LeaveBalance[];
    trigger: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const [selected, setSelected] = useState<LeaveBalance | null>(null);
    const [wholeDay, setWholeDay] = useState(true);

    function reset() {
        setSelected(null);
        setWholeDay(true);
    }

    return (
        <Sheet
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (!next) {
                    setTimeout(reset, 250);
                }
            }}
        >
            <SheetTrigger asChild>{trigger}</SheetTrigger>
            <SheetContent
                side="bottom"
                className="mx-auto max-h-[92svh] gap-0 overflow-y-auto rounded-t-3xl bg-beige-100 sm:max-w-[430px]"
            >
                {!selected ? (
                    <>
                        <SheetHeader className="items-center pb-2">
                            <SheetTitle className="text-lg text-purple-950">
                                What kind of leave?
                            </SheetTitle>
                            <p className="text-sm text-gray-800">
                                Your remaining balance is shown on each
                            </p>
                        </SheetHeader>
                        <div className="grid grid-cols-2 gap-2.5 px-4 pt-0 pb-[calc(1.5rem+env(safe-area-inset-bottom))]">
                            {balances.map((balance) => (
                                <button
                                    key={balance.type}
                                    type="button"
                                    onClick={() => setSelected(balance)}
                                    className="flex items-center gap-3 rounded-2xl bg-white p-3 text-left shadow-sm active:scale-[0.98]"
                                >
                                    <span className="text-xl">
                                        {balance.icon}
                                    </span>
                                    <span className="min-w-0">
                                        <span className="block text-[13.5px] leading-tight font-bold text-purple-950">
                                            {balance.label}
                                        </span>
                                        <span className="block text-[11px] text-gray-800">
                                            {balance.tracked ? (
                                                <>
                                                    <b className="text-status-complete">
                                                        {balance.remaining}
                                                    </b>{' '}
                                                    days left
                                                </>
                                            ) : (
                                                'No balance limit'
                                            )}
                                        </span>
                                    </span>
                                </button>
                            ))}
                        </div>
                    </>
                ) : (
                    <Form
                        action={LeaveController.store.url()}
                        method="post"
                        options={{ preserveScroll: true }}
                        onSuccess={() => setOpen(false)}
                        className="p-4 pb-[calc(1.5rem+env(safe-area-inset-bottom))]"
                    >
                        {({ processing, errors }) => (
                            <div className="flex flex-col gap-3.5">
                                <SheetHeader className="p-0 pb-1">
                                    <SheetTitle className="text-lg text-purple-950">
                                        File a leave
                                    </SheetTitle>
                                </SheetHeader>

                                <input
                                    type="hidden"
                                    name="request_type"
                                    value={selected.type}
                                />
                                <div className="flex items-center gap-3 rounded-2xl bg-yellow-50 px-4 py-3">
                                    <span className="text-xl">
                                        {selected.icon}
                                    </span>
                                    <span>
                                        <span className="block text-[15px] font-bold text-purple-950">
                                            {selected.label}
                                        </span>
                                        <span className="block text-[11.5px] font-semibold text-yellow-700">
                                            {selected.tracked
                                                ? `${selected.remaining} days available`
                                                : 'No balance limit'}
                                        </span>
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() => setSelected(null)}
                                        className="ml-auto text-xs font-semibold text-yellow-700 underline"
                                    >
                                        Change
                                    </button>
                                </div>

                                <label className="flex items-center justify-between rounded-xl border-[1.5px] border-black/10 bg-white px-4 py-3">
                                    <span className="text-sm font-semibold text-purple-950">
                                        Whole day
                                    </span>
                                    <input
                                        type="checkbox"
                                        checked={wholeDay}
                                        onChange={(event) =>
                                            setWholeDay(event.target.checked)
                                        }
                                        className="size-5 accent-status-complete"
                                    />
                                </label>

                                <div className="grid grid-cols-2 gap-2.5">
                                    <Field
                                        label="Start date"
                                        error={errors.start_date}
                                    >
                                        <input
                                            type="date"
                                            name="start_date"
                                            defaultValue={today}
                                            className={fieldControl}
                                            required
                                        />
                                    </Field>
                                    <Field
                                        label="End date"
                                        error={errors.end_date}
                                    >
                                        <input
                                            type="date"
                                            name="end_date"
                                            defaultValue={today}
                                            className={fieldControl}
                                            required
                                        />
                                    </Field>
                                </div>

                                {!wholeDay && (
                                    <div className="grid grid-cols-2 gap-2.5">
                                        <Field label="Start time">
                                            <input
                                                type="time"
                                                name="start_time"
                                                defaultValue="10:00"
                                                className={fieldControl}
                                            />
                                        </Field>
                                        <Field label="End time">
                                            <input
                                                type="time"
                                                name="end_time"
                                                defaultValue="18:00"
                                                className={fieldControl}
                                            />
                                        </Field>
                                    </div>
                                )}

                                <Field label="Reason" error={errors.reason}>
                                    <textarea
                                        name="reason"
                                        rows={3}
                                        placeholder="A short note for your approver"
                                        className={cn(
                                            fieldControl,
                                            'resize-none',
                                        )}
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
                                        : 'Submit request'}
                                </button>
                            </div>
                        )}
                    </Form>
                )}
            </SheetContent>
        </Sheet>
    );
}
