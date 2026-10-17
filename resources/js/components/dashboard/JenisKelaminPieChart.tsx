import {
    Cell,
    Legend,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
} from 'recharts';

interface JenisKelaminItem {
    label: string;
    total: number;
    percentage: number;
}

interface Props {
    data: JenisKelaminItem[];
}

// Warna cerah untuk grafik: Biru (Laki-laki) dan Pink (Perempuan)
const COLORS = ['#3b82f6', '#ec4899'];

interface CustomTooltipProps {
    active?: boolean;
    payload?: Array<{
        name: string;
        value: number;
        payload?: {
            percentage: number;
        };
    }>;
}

function CustomTooltip({ active, payload }: CustomTooltipProps) {
    if (active && payload && payload.length) {
        const data = payload[0];

        if (!data) {
            return null;
        }

        const percentage = data.payload?.percentage ?? 0;

        return (
            <div className="rounded-md border border-border bg-popover px-3 py-2 text-popover-foreground shadow-sm">
                <p className="font-semibold">{data.name}</p>
                <p className="text-sm text-muted-foreground">
                    {data.value} pegawai ({percentage}%)
                </p>
            </div>
        );
    }

    return null;
}

export function JenisKelaminPieChart({ data }: Props) {
    if (!data || data.length === 0) {
        return (
            <div className="flex h-[240px] items-center justify-center text-muted-foreground">
                Tidak ada data
            </div>
        );
    }

    const chartData = data.map((item) => ({
        name: item.label,
        value: item.total,
        percentage: item.percentage,
    }));

    return (
        <ResponsiveContainer width="100%" height={240}>
            <PieChart>
                <Pie
                    data={chartData}
                    cx="50%"
                    cy="45%"
                    innerRadius={50}
                    outerRadius={80}
                    dataKey="value"
                    label={(props: any) => `${props.percentage}%`}
                    labelLine={false}
                >
                    {chartData.map((entry, index) => (
                        <Cell
                            key={`cell-${index}`}
                            fill={COLORS[index % COLORS.length] ?? COLORS[0]}
                        />
                    ))}
                </Pie>
                <Tooltip content={<CustomTooltip />} />
                <Legend
                    verticalAlign="bottom"
                    height={36}
                    wrapperStyle={{ paddingTop: '20px' }}
                />
            </PieChart>
        </ResponsiveContainer>
    );
}
