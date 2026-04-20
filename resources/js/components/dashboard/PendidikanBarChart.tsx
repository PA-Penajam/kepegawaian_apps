import {
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

interface PendidikanItem {
    pendidikan: string;
    count: number;
    percentage: number;
}

interface Props {
    data: PendidikanItem[];
}

interface CustomTooltipProps {
    active?: boolean;
    payload?: Array<{
        name: string;
        value: number;
        payload?: {
            pct: number;
        };
    }>;
    label?: string;
}

function CustomTooltip({ active, payload, label }: CustomTooltipProps) {
    if (active && payload && payload.length) {
        const data = payload[0];
        const percentage = data.payload?.pct ?? 0;

        return (
            <div className="bg-popover text-popover-foreground border border-border rounded-md px-3 py-2 shadow-sm">
                <p className="font-semibold">{label}</p>
                <p className="text-sm text-muted-foreground">
                    {data.value} pegawai ({percentage}%)
                </p>
            </div>
        );
    }

    return null;
}

export function PendidikanBarChart({ data }: Props) {
    const chartData = data.map((item) => ({
        name: item.pendidikan,
        value: item.count,
        pct: item.percentage,
    }));

    return (
        <ResponsiveContainer width="100%" height={Math.max(200, chartData.length * 40)}>
            <BarChart
                data={chartData}
                layout="vertical"
                margin={{ top: 4, right: 48, left: 8, bottom: 0 }}
            >
                <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                <XAxis type="number" tick={{ fontSize: 12 }} allowDecimals={false} />
                <YAxis
                    type="category"
                    dataKey="name"
                    tick={{ fontSize: 12 }}
                    width={80}
                />
                <Tooltip content={<CustomTooltip />} />
                <Bar dataKey="value" fill="hsl(var(--chart-1))" radius={[0, 4, 4, 0]} label={{ position: 'right', fontSize: 12 }} />
            </BarChart>
        </ResponsiveContainer>
    );
}
