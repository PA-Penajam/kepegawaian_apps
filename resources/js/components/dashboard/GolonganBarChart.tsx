import {
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';

interface GolonganItem {
  golongan: string;
  count: number;
  percentage: number;
}

interface GolonganBarChartProps {
  data: GolonganItem[];
}

const COLORS = ['#8884d8', '#82ca9d', '#ffc658', '#ff7c7c'];

interface CustomTooltipProps {
  active?: boolean;
  payload?: Array<{
    name: string;
    value: number;
    payload?: {
      percentage: number;
    };
  }>;
  label?: string;
}

function CustomTooltip({ active, payload, label }: CustomTooltipProps) {
  if (active && payload && payload.length) {
    const data = payload[0];
    const percentage = data.payload?.percentage ?? 0;

    return (
      <div className="bg-white border border-gray-300 rounded px-3 py-2 shadow-sm">
        <p className="font-semibold">{label}</p>
        <p className="text-sm text-gray-600">
          {data.value} pegawai ({percentage}%)
        </p>
      </div>
    );
  }

  return null;
}

export function GolonganBarChart({ data }: GolonganBarChartProps) {
  const chartData = data.map((item, index) => ({
    name: item.golongan,
    value: item.count,
    percentage: item.percentage,
    color: COLORS[index % COLORS.length],
  }));

  return (
    <ResponsiveContainer width="100%" height={200}>
      <BarChart data={chartData} margin={{ top: 5, right: 30, left: 20, bottom: 5 }}>
        <CartesianGrid strokeDasharray="3 3" />
        <XAxis dataKey="name" />
        <YAxis />
        <Tooltip content={<CustomTooltip />} />
        <Bar dataKey="value">
          {chartData.map((entry, index) => (
            <Cell key={`cell-${index}`} fill={entry.color} />
          ))}
        </Bar>
      </BarChart>
    </ResponsiveContainer>
  );
}
