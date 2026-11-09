import { money } from '@/lib/utils';
import {
    Area, AreaChart, Bar, BarChart, CartesianGrid, Cell, ResponsiveContainer,
    Tooltip, XAxis, YAxis,
} from 'recharts';

const BRAND = '#2f7dff';
const AXIS = '#7d849a';
const GRID = 'rgba(127,135,160,0.16)';

function fmtDay(d: string) {
    return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

/** Revenue (or any magnitude) over time — single-hue sequential area. */
export function RevenueTrend({ data, currency = 'UGX' }: { data: { date: string; revenue: number }[]; currency?: string }) {
    return (
        <ResponsiveContainer width="100%" height={260}>
            <AreaChart data={data} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                <defs>
                    <linearGradient id="rev" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stopColor={BRAND} stopOpacity={0.35} />
                        <stop offset="100%" stopColor={BRAND} stopOpacity={0} />
                    </linearGradient>
                </defs>
                <CartesianGrid vertical={false} stroke={GRID} />
                <XAxis dataKey="date" tickFormatter={fmtDay} tick={{ fill: AXIS, fontSize: 11 }} axisLine={false} tickLine={false} minTickGap={24} />
                <YAxis tickFormatter={(v) => (v >= 100000 ? `${v / 100000}k` : String(v / 100))} tick={{ fill: AXIS, fontSize: 11 }} axisLine={false} tickLine={false} width={44} />
                <Tooltip
                    cursor={{ stroke: BRAND, strokeOpacity: 0.3 }}
                    contentStyle={{ borderRadius: 12, border: '1px solid color-mix(in srgb, var(--color-tint) 10%, transparent)', background: 'var(--color-card)', color: 'var(--color-ink)', boxShadow: '0 20px 40px -20px rgb(0 0 0 / 0.9)', fontSize: 13 }}
                    labelFormatter={(l) => fmtDay(l as string)}
                    formatter={(v: number) => [money(v, currency), 'Revenue']}
                />
                <Area type="monotone" dataKey="revenue" stroke={BRAND} strokeWidth={2} fill="url(#rev)" />
            </AreaChart>
        </ResponsiveContainer>
    );
}

/** Horizontal magnitude bars (e.g. top events) — single hue, rounded ends. */
export function RankedBars({ data }: { data: { title: string; sold: number }[] }) {
    return (
        <ResponsiveContainer width="100%" height={Math.max(160, data.length * 46)}>
            <BarChart data={data} layout="vertical" margin={{ top: 4, right: 16, left: 8, bottom: 4 }}>
                <CartesianGrid horizontal={false} stroke={GRID} />
                <XAxis type="number" tick={{ fill: AXIS, fontSize: 11 }} axisLine={false} tickLine={false} allowDecimals={false} />
                <YAxis type="category" dataKey="title" tick={{ fill: '#8a92a8', fontSize: 12 }} axisLine={false} tickLine={false} width={120} />
                <Tooltip
                    cursor={{ fill: 'rgba(47,125,255,0.08)' }}
                    contentStyle={{ borderRadius: 12, border: '1px solid color-mix(in srgb, var(--color-tint) 10%, transparent)', background: 'var(--color-card)', color: 'var(--color-ink)', fontSize: 13 }}
                    formatter={(v: number) => [v, 'Tickets']}
                />
                <Bar dataKey="sold" radius={[0, 6, 6, 0]} barSize={16}>
                    {data.map((_, i) => <Cell key={i} fill={BRAND} />)}
                </Bar>
            </BarChart>
        </ResponsiveContainer>
    );
}
