export type ClockStatus = 'not_started' | 'in_progress' | 'done';

export type ClockSnapshot = {
    status: ClockStatus;
    clock_in_at: string | null;
    clock_out_at: string | null;
    clock_in_iso: string | null;
    elapsed_human: string | null;
};

export type LeaveBalance = {
    type: string;
    label: string;
    icon: string;
    remaining: number | null;
    tracked: boolean;
};

export type OnCallNotice = {
    /** 'owner' = on-call all week, 'substitute' = covering today only. */
    type: 'owner' | 'substitute';
    range: string;
    covering_for: string | null;
};

export type RecentLeave = {
    id: number;
    label: string;
    icon: string;
    dates: string;
    status: string;
    status_label: string;
};

export type DtrRow = {
    day: string;
    date: string;
    time_in: string | null;
    time_out: string | null;
    hours: string;
    status: string;
};

export type TeamMember = {
    id: number;
    name: string;
    initials: string;
    label: string;
    status: 'in' | 'wfh' | 'leave' | 'sick' | 'out';
};

export type Alert = {
    id: string;
    title: string;
    body: string | null;
    time: string;
    read: boolean;
};
