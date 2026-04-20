import { Legend, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';

interface JenisKelaminItem {
  label: string;
  total: number;
  percentage: number;
}

interface Props {
  data: JenisKelaminItem[];
}

// Gunakan warna yang konsisten dengan tema shadcn/ui
const COLORS = ['hsl(var(--chart-1))', 'hsl(var(--chart-2))'];

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
    const percentage = data.payload?.percentage ?? 0;

    return (
      <div className="bg-popover text-popover-foreground border border-border rounded-md px-3 py-2 shadow-sm">
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
      <div className="flex items-center justify-center h-[240px] text-muted-foreground">
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
          cy="50%"
          innerRadius={60}
          outerRadius={100}
          dataKey="value"
          label={(props: any) => `${props.percentage}%`}
          labelLine={false}
        >
          {chartData.map((entry, index) => (
            <Pie key={`pie-${index}`} fill={COLORS[index % COLORS.length]} />
          ))}
        </Pie>
        <Tooltip content={<CustomTooltip />} />
        <Legend />
      </PieChart>
    </ResponsiveContainer>
  );
}
