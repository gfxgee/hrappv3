import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import type { ReactNode } from 'react';
import { toast } from 'sonner';
import { BottomNav } from '@/components/mobile/bottom-nav';

type MobilePageProps = {
    flash?: {
        success?: string | null;
        info?: string | null;
        error?: string | null;
    };
    unread?: number;
};

export default function MobileLayout({ children }: { children: ReactNode }) {
    const { props } = usePage<MobilePageProps>();
    const flash = props.flash;
    const shown = useRef<string>('');

    useEffect(() => {
        if (!flash) {
            return;
        }

        const message = flash.success ?? flash.error ?? flash.info;

        // Guard against re-toasting the same flash on prop-preserving reloads.
        if (!message || shown.current === message) {
            return;
        }

        shown.current = message;

        if (flash.success) {
            toast.success(flash.success);
        } else if (flash.error) {
            toast.error(flash.error);
        } else if (flash.info) {
            toast(flash.info);
        }
    }, [flash]);

    return (
        <div className="flex min-h-svh justify-center bg-beige-200 sm:py-6">
            <div className="flex h-svh w-full max-w-[430px] flex-col overflow-hidden bg-beige-100 sm:h-[860px] sm:rounded-[2.25rem] sm:shadow-2xl sm:ring-1 sm:ring-black/10">
                <main className="flex-1 overflow-x-hidden overflow-y-auto">
                    {children}
                </main>
                <BottomNav unread={props.unread} />
            </div>
        </div>
    );
}
