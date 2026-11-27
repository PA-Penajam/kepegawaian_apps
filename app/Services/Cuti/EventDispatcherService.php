<?php

namespace App\Services\Cuti;

use App\Models\Cuti\CutiEvent;
use App\Models\Cuti\CutiEventDelivery;
use App\Models\Cuti\CutiPengajuan;

class EventDispatcherService
{
    public function __construct(private ConsumerRegistry $consumerRegistry) {}

    /**
     * Dispatch event ke outbox table dan buat delivery record untuk setiap consumer.
     */
    public function dispatch(string $eventType, CutiPengajuan $aggregate): CutiEvent
    {
        $event = $this->createOutboxEvent($eventType, $aggregate);

        foreach ($this->consumerRegistry->allIds() as $consumerId) {
            CutiEventDelivery::firstOrCreate(
                ['event_id' => $event->id, 'consumer_id' => $consumerId],
                ['status' => 'pending']
            );
        }

        return $event;
    }

    /**
     * Mencatat event outbox dan membuat delivery khusus untuk satu consumer target.
     */
    public function dispatchForConsumer(string $eventType, CutiPengajuan $aggregate, string $consumerId): CutiEvent
    {
        $event = $this->createOutboxEvent($eventType, $aggregate);

        CutiEventDelivery::firstOrCreate(
            ['event_id' => $event->id, 'consumer_id' => $consumerId],
            ['status' => 'pending']
        );

        return $event;
    }

    /**
     * Membuat baris outbox beserta payload ber-event_id tanpa membuat delivery.
     */
    private function createOutboxEvent(string $eventType, CutiPengajuan $aggregate): CutiEvent
    {
        $event = CutiEvent::create([
            'aggregate_type' => 'PengajuanCuti',
            'aggregate_id' => $aggregate->id,
            'event_type' => $eventType,
            'payload' => $this->buildPayload($eventType, $aggregate),
            'occurred_at' => now(),
        ]);

        // Update payload dengan event_id setelah create
        $payload = $event->payload;
        $payload['event_id'] = $event->id;
        $event->update(['payload' => $payload]);

        return $event;
    }

    /**
     * Membangun payload event berdasarkan tipe dan data pengajuan.
     *
     * @return array<string, mixed>
     */
    private function buildPayload(string $type, CutiPengajuan $pengajuan): array
    {
        $pengajuan->loadMissing('pegawai');

        return [
            'event_type' => $type,
            'occurred_at' => now()->toIso8601String(),
            'data' => [
                'pengajuan_id' => $pengajuan->id,
                'nomor_pengajuan' => $pengajuan->nomor_pengajuan,
                'pegawai_nip' => $pengajuan->pegawai_nip,
                'pegawai_nama' => $pengajuan->pegawai?->nama_lengkap,
                'jenis_cuti' => $pengajuan->jenis_cuti_kode,
                'state' => $pengajuan->state->getValue(),
                'tanggal_mulai' => $pengajuan->tanggal_mulai->toDateString(),
                'tanggal_selesai' => $pengajuan->tanggal_selesai->toDateString(),
                'jumlah_hari_kerja' => $pengajuan->jumlah_hari_kerja,
            ],
        ];
    }
}
