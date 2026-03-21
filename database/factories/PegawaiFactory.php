<?php

namespace Database\Factories;

use App\Enums\Agama;
use App\Enums\GolonganDarah;
use App\Enums\JenisKelamin;
use App\Enums\StatusKepegawaian;
use App\Enums\StatusPegawai;
use App\Enums\StatusPerkawinan;
use App\Models\IamApplication;
use App\Models\IamRole;
use App\Models\IamUserRole;
use App\Models\Pegawai;
use App\Models\RefJabatan;
use App\Models\RefPangkat;
use App\Models\RefUnitKerja;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Pegawai>
 */
class PegawaiFactory extends Factory
{
    protected static ?string $password = null;

    public function definition(): array
    {
        $fakerId = fake('id_ID');
        $tanggalLahir = $fakerId->dateTimeBetween('1960-01-01', '1995-12-31');
        $tahunMasuk = $fakerId->numberBetween(1990, 2020);
        $bulanMasuk = $fakerId->numberBetween(1, 12);
        $tanggalMasuk = $fakerId->dateTimeBetween(sprintf('%d-%02d-01', $tahunMasuk, $bulanMasuk), sprintf('%d-%02d-28', $tahunMasuk, $bulanMasuk));

        $tahunCpns = max($tahunMasuk - 1, 1980);
        $tmtCpns = $fakerId->dateTimeBetween(sprintf('%d-01-01', $tahunCpns), sprintf('%d-12-28', $tahunCpns));
        $tmtPns = $fakerId->dateTimeBetween($tmtCpns, $tanggalMasuk);

        $nip = sprintf(
            '%s%s%d%03d',
            $tanggalLahir->format('Ymd'),
            $tanggalMasuk->format('Ym'),
            $fakerId->numberBetween(1, 2),
            $fakerId->numberBetween(1, 999),
        );

        return [
            'nip' => $nip,
            'nip_lama' => (string) $fakerId->numberBetween(100000000, 999999999),
            'nama_lengkap' => $fakerId->name(),
            'tempat_lahir' => $fakerId->city(),
            'tanggal_lahir' => $tanggalLahir->format('Y-m-d'),
            'jenis_kelamin' => $fakerId->randomElement(JenisKelamin::cases())->value,
            'agama' => Agama::Islam->value,
            'status_perkawinan' => $fakerId->randomElement(StatusPerkawinan::cases())->value,
            'golongan_darah' => $fakerId->optional()->randomElement(GolonganDarah::cases())?->value,
            'alamat' => $fakerId->address(),
            'no_telepon' => $fakerId->optional()->numerify('08##########'),
            'email' => $fakerId->unique()->safeEmail(),
            'email_verified_at' => now(),
            'status_kepegawaian' => StatusKepegawaian::PNS->value,
            'status_pegawai' => StatusPegawai::Aktif->value,
            'tmt_cpns' => $tmtCpns->format('Y-m-d'),
            'tmt_pns' => $tmtPns->format('Y-m-d'),
            'pendidikan_terakhir' => $fakerId->randomElement(['SMA', 'D3', 'S1', 'S2']),
            'tanggal_masuk' => $tanggalMasuk->format('Y-m-d'),
            'tanggal_pensiun_bup' => (clone $tanggalLahir)->modify('+60 years')->format('Y-m-d'),
            'ref_pangkat_id' => RefPangkat::query()->inRandomOrder()->value('id'),
            'ref_jabatan_id' => RefJabatan::query()->inRandomOrder()->value('id'),
            'ref_unit_kerja_id' => RefUnitKerja::query()->inRandomOrder()->value('id'),
            'no_karpeg' => $fakerId->optional()->numerify('##########'),
            'no_karis_karsu' => $fakerId->optional()->numerify('##########'),
            'npwp' => $fakerId->optional()->numerify('##.###.###.#-###.###'),
            'no_bpjs_kesehatan' => $fakerId->optional()->numerify('#############'),
            'no_bpjs_ketenagakerjaan' => $fakerId->optional()->numerify('#############'),
            'no_taspen' => $fakerId->optional()->numerify('##########'),
            'foto' => $fakerId->optional()->randomElement(['pegawai/default-1.jpg', 'pegawai/default-2.jpg']),
            'keterangan' => $fakerId->optional()->sentence(),
            'password' => static::$password ??= Hash::make('password'),
        ];
    }

    /**
     * Auto-assign viewer role setelah create (agar lolos middleware iam.permission).
     * Role state spesifik (admin/operator) menambahkan role di atas viewer.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Pegawai $pegawai) {
            $kepegawaian = IamApplication::where('slug', 'kepegawaian')->first();
            $role = $kepegawaian
                ? IamRole::where('iam_application_id', $kepegawaian->id)->where('slug', 'viewer')->first()
                : null;
            if ($role) {
                IamUserRole::firstOrCreate(
                    ['user_id' => $pegawai->id, 'iam_role_id' => $role->id],
                    ['assigned_at' => now()]
                );
            }
        });
    }

    public function admin(): static
    {
        return $this->afterCreating(function (Pegawai $pegawai) {
            $kepegawaian = IamApplication::where('slug', 'kepegawaian')->first();
            $role = $kepegawaian ? IamRole::where('iam_application_id', $kepegawaian->id)->where('slug', 'admin')->first() : null;
            if ($role) {
                IamUserRole::firstOrCreate(
                    ['user_id' => $pegawai->id, 'iam_role_id' => $role->id],
                    ['assigned_at' => now()]
                );
            }
        });
    }

    public function operator(): static
    {
        return $this->afterCreating(function (Pegawai $pegawai) {
            $kepegawaian = IamApplication::where('slug', 'kepegawaian')->first();
            $role = $kepegawaian ? IamRole::where('iam_application_id', $kepegawaian->id)->where('slug', 'operator')->first() : null;
            if ($role) {
                IamUserRole::firstOrCreate(
                    ['user_id' => $pegawai->id, 'iam_role_id' => $role->id],
                    ['assigned_at' => now()]
                );
            }
        });
    }

    public function viewer(): static
    {
        return $this->afterCreating(function (Pegawai $pegawai) {
            $kepegawaian = IamApplication::where('slug', 'kepegawaian')->first();
            $role = $kepegawaian ? IamRole::where('iam_application_id', $kepegawaian->id)->where('slug', 'viewer')->first() : null;
            if ($role) {
                IamUserRole::firstOrCreate(
                    ['user_id' => $pegawai->id, 'iam_role_id' => $role->id],
                    ['assigned_at' => now()]
                );
            }
        });
    }

    public function unverified(): static
    {
        return $this->state(fn () => [
            'email_verified_at' => null,
        ]);
    }

    public function withTwoFactor(): static
    {
        return $this->state(fn () => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
