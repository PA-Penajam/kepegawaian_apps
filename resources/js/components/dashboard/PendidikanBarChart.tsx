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
                <Tooltip
                    formatter={(value: number, _name: string, props: any) => [
                        `${value} pegawai (${props?.payload?.pct ?? 0}%)`,
                        'Jumlah',
                    ]}
                />
                <Bar dataKey="value" fill="#6366f1" radius={[0, 4, 4, 0]} label={{ position: 'right', fontSize: 12 }} />
            </BarChart>
        </ResponsiveContainer>
    );
}
