import { cn } from '@/lib/utils';
import { ReactNode } from 'react';

export interface Column<T> {
    header: string;
    cell: (row: T) => ReactNode;
    align?: 'left' | 'right';
    className?: string;
}

/**
 * A table on tablets/desktop that turns into a stack of cards on phones.
 * `mobile` renders one card's contents; the card shell is provided here.
 */
export function DataTable<T>({
    rows,
    rowKey,
    columns,
    mobile,
    empty,
}: {
    rows: T[];
    rowKey: (row: T) => string | number;
    columns: Column<T>[];
    mobile: (row: T) => ReactNode;
    empty: ReactNode;
}) {
    if (rows.length === 0) {
        return <>{empty}</>;
    }

    return (
        <>
            {/* Phones: cards */}
            <ul className="space-y-2.5 md:hidden">
                {rows.map((row) => (
                    <li key={rowKey(row)} className="rounded-[1.25rem] border border-tint/[0.07] bg-card p-4 shadow-[var(--shadow-card)]">
                        {mobile(row)}
                    </li>
                ))}
            </ul>

            {/* Tablet & desktop: table */}
            <div className="hidden overflow-hidden rounded-[1.5rem] border border-tint/[0.07] bg-card shadow-[var(--shadow-card)] md:block">
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-tint/[0.07] bg-tint/[0.02] text-left">
                                {columns.map((c, i) => (
                                    <th
                                        key={i}
                                        scope="col"
                                        className={cn(
                                            'whitespace-nowrap px-5 py-3.5 text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400',
                                            c.align === 'right' && 'text-right',
                                        )}
                                    >
                                        {c.header}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-tint/[0.06]">
                            {rows.map((row) => (
                                <tr key={rowKey(row)} className="transition-colors hover:bg-tint/[0.02]">
                                    {columns.map((c, i) => (
                                        <td key={i} className={cn('px-5 py-4 align-middle', c.align === 'right' && 'text-right', c.className)}>
                                            {c.cell(row)}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
