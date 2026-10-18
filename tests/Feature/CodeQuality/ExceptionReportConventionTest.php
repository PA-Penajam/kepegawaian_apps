<?php

/*
|--------------------------------------------------------------------------
| Konvensi: catch generik di controller wajib melaporkan exception
|--------------------------------------------------------------------------
|
| Setiap `catch (\Exception $e)` / `catch (\Throwable $e)` di controller
| harus memanggil `report($e)`, `Log::`, atau pencatat khusus agar error
| tak terduga tidak hilang diam-diam (hanya flash message ke user).
| Domain exception spesifik (AuthorizationException, TransitionTidakValid,
| QueryException untuk flow control, dsb) dikecualikan karena pesannya
| memang user-friendly dan alurnya expected.
*/

it('semua catch generik di controller melaporkan exception', function () {
    $base = app_path('Http/Controllers');
    $pelanggaran = [];

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $baris = file($file->getPathname());

        foreach ($baris as $i => $teks) {
            if (! preg_match('/catch\s*\(\s*\\\\(Exception|Throwable)\s+\$/', $teks)) {
                continue;
            }

            $konteks = implode('', array_slice($baris, $i, 8));

            $dilaporkan = str_contains($konteks, 'report(')
                || str_contains($konteks, 'Log::')
                || str_contains($konteks, 'logFailure(');

            if (! $dilaporkan) {
                $pelanggaran[] = $file->getPathname().':'.($i + 1);
            }
        }
    }

    expect($pelanggaran)->toBeEmpty();
});
