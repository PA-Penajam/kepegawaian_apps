<?php

namespace Tests\Feature\Monitoring;

use App\Exports\KgbMonitoringExport;
use App\Models\Pegawai;
use Database\Seeders\IamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class KgbExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(IamSeeder::class);
    }

    public function test_kgb_monitoring_export_class_exists(): void
    {
        $this->assertTrue(class_exists(KgbMonitoringExport::class));
    }

    public function test_kgb_monitoring_export_implements_required_interfaces(): void
    {
        $export = new KgbMonitoringExport;

        $this->assertInstanceOf(FromCollection::class, $export);
        $this->assertInstanceOf(WithHeadings::class, $export);
        $this->assertInstanceOf(WithMapping::class, $export);
    }

    public function test_kgb_monitoring_export_has_correct_headings(): void
    {
        $export = new KgbMonitoringExport;

        $expectedHeadings = [
            'NIP',
            'Nama Lengkap',
            'Unit Kerja',
            'Golongan',
            'Kenaikan Gaji Berkala Sebelumnya',
            'Kenaikan Gaji Berkala Berikutnya',
            'Sisa Waktu',
        ];

        $this->assertEquals($expectedHeadings, $export->headings());
    }

    public function test_kgb_monitoring_export_has_constructor_with_parameters(): void
    {
        // Test constructor dengan parameter default
        $export1 = new KgbMonitoringExport;
        $this->assertInstanceOf(KgbMonitoringExport::class, $export1);

        // Test constructor dengan semua parameter
        $export2 = new KgbMonitoringExport('unit-1', 'III/a', 'pns', 6);
        $this->assertInstanceOf(KgbMonitoringExport::class, $export2);
    }

    public function test_kgb_monitoring_export_has_collection_method(): void
    {
        $export = new KgbMonitoringExport;

        // Test bahwa method collection() ada
        $this->assertTrue(method_exists($export, 'collection'));
    }

    public function test_kgb_monitoring_export_has_map_method(): void
    {
        $export = new KgbMonitoringExport;

        // Test bahwa method map() ada
        $this->assertTrue(method_exists($export, 'map'));
    }

    public function test_kgb_export_map_includes_unit_kerja_data(): void
    {
        $export = new KgbMonitoringExport;

        $mockRow = (object) [
            'nip' => '1234567890',
            'nama_lengkap' => 'Test User',
            'unit_kerja' => 'Bagian Testing',
            'pangkat_gol' => 'III/a',
            'tmt_pangkat' => '2023-01-01',
            'tanggal_kgb_berikutnya' => '2025-01-01',
            'sisa_hari' => 30,
        ];

        $mapped = $export->map($mockRow);

        $this->assertEquals('Bagian Testing', $mapped[2]);
    }

    public function test_endpoint_export_kgb_tetap_bisa_di_download_walau_data_kosong(): void
    {
        Excel::fake();

        $this->actingAs(Pegawai::factory()->admin()->create())
            ->get(route('monitoring.kgb.export'))
            ->assertSuccessful();

        Excel::assertDownloaded('kgb-monitoring.xlsx');
    }
}
