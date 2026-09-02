<?php

namespace App\Filament\Widgets;

use App\Models\Pegawai;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SystemOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalPegawaiAktif = Pegawai::query()
            ->where('status_pegawai', 'aktif')
            ->count();

        return [
            Stat::make('Pegawai Aktif', number_format($totalPegawaiAktif))
                ->description('Total pegawai berstatus aktif di SIMPEG')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),
        ];
    }
}
