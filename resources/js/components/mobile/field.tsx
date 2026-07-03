import type { ReactNode } from 'react';

/** Shared control styling for the mobile filing forms. */
export const fieldControl =
    'w-full rounded-xl border-[1.5px] border-black/10 bg-white px-3 py-3 text-[15px] text-purple-950 outline-none focus:border-yellow-400';

export function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <label className="block min-w-0">
            <span className="mb-1.5 ml-0.5 block text-[11px] font-bold tracking-wide text-gray-800 uppercase">
                {label}
            </span>
            {children}
            {error && (
                <span className="mt-1 block text-xs font-medium text-red-600">
                    {error}
                </span>
            )}
        </label>
    );
}
