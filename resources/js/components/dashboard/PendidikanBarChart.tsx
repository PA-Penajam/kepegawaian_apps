    Bar,
    BarChart,
    CartesianGrid,
    Cell,
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

const COLORS = ['#6366f1', '#8b5cf6', '#ec4899', '#f43f5e', '#f97316', '#eab308', '#22c55e', '#14b8a6', '#0ea5e9'];

export function PendidikanBarChart({ data }: Props) {
    const chartData = data.map((item, index) => ({
        name: item.pendidikan,
        value: item.count,
        pct: item.percentage,
        color: COLORS[index % COLORS.length],
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
                <Bar dataKey="value" radius={[0, 4, 4, 0]} label={{ position: 'right', fontSize: 12 }}>
                    {chartData.map((entry, index) => (
                        <Cell key={`cell-${index}`} fill={entry.color} />
                    ))}
                </Bar>
            </BarChart>
        </ResponsiveContainer>
    );
}
