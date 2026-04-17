<?php

namespace Tests\Feature\Monitoring;

use App\Exports\KgbMonitoringExport;
use Tests\TestCase;

class KgbExportTest extends TestCase
{
    public function test_kgb_monitoring_export_class_exists(): void
    {
        $this->assertTrue(class_exists(KgbMonitoringExport::class));
    }

    public function test_kgb_monitoring_export_implements_required_interfaces(): void
    {
        $export = new KgbMonitoringExport();
        
        $this->assertInstanceOf(\Maatwebsite\Excel\Concerns\FromCollection::class, $export);
        $this->assertInstanceOf(\Maatwebsite\Excel\Concerns\WithHeadings::class, $export);
        $this->assertInstanceOf(\Maatwebsite\Excel\Concerns\WithMapping::class, $export);
    }

    public function test_kgb_monitoring_export_has_correct_headings(): void
    {
        $export = new KgbMonitoringExport();

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
        $export1 = new KgbMonitoringExport();
        $this->assertInstanceOf(KgbMonitoringExport::class, $export1);

        // Test constructor dengan semua parameter
        $export2 = new KgbMonitoringExport('unit-1', 'III/a', 'pns', 6);
        $this->assertInstanceOf(KgbMonitoringExport::class, $export2);
    }

    public function test_kgb_monitoring_export_has_collection_method(): void
    {
        $export = new KgbMonitoringExport();
        
        // Test bahwa method collection() ada
        $this->assertTrue(method_exists($export, 'collection'));
    }

    public function test_kgb_monitoring_export_has_map_method(): void
    {
        $export = new KgbMonitoringExport();
        
        // Test bahwa method map() ada
        $this->assertTrue(method_exists($export, 'map'));
    }
}