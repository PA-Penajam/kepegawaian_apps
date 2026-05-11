<?php

namespace App\Policies;

use App\Models\BerkasChecklist\BerkasChecklistTemplate;
use App\Models\Pegawai;

class ChecklistTemplatePolicy
{
    public function viewAny(Pegawai $pegawai): bool
    {
        return $pegawai->hasPermission('checklist.template.view');
    }

    public function view(Pegawai $pegawai, BerkasChecklistTemplate $template): bool
    {
        unset($template);

        return $pegawai->hasPermission('checklist.template.view');
    }

    public function create(Pegawai $pegawai): bool
    {
        return $pegawai->hasPermission('checklist.template.create');
    }

    public function update(Pegawai $pegawai, BerkasChecklistTemplate $template): bool
    {
        unset($template);

        return $pegawai->hasPermission('checklist.template.update');
    }

    public function delete(Pegawai $pegawai, BerkasChecklistTemplate $template): bool
    {
        unset($template);

        return $pegawai->hasPermission('checklist.template.delete');
    }

    public function restore(Pegawai $pegawai, BerkasChecklistTemplate $template): bool
    {
        unset($template);

        return $pegawai->hasPermission('checklist.template.delete');
    }

    public function forceDelete(Pegawai $pegawai, BerkasChecklistTemplate $template): bool
    {
        unset($template);

        return false;
    }
}
