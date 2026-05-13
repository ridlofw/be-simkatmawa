<?php

namespace App\Http\Requests\Rekognisi;

use App\Enums\JenisRekognisi;
use App\Enums\Level;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi pengajuan Rekognisi.
 * Memiliki field tambahan: jenis (JenisRekognisi enum).
 */
class StoreRekognisiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'level' => ['required', 'string', Rule::in(array_column(Level::cases(), 'value'))],
            'jenis' => ['required', 'string', Rule::in(array_column(JenisRekognisi::cases(), 'value'))],
            'nama' => ['required', 'string', 'max:255'],
            'penyelenggara' => ['required', 'string', 'max:255'],
            'tgl_sertifikat' => ['required', 'date_format:Y-m-d'],
            'url_peserta' => ['required', 'url', 'max:500'],
            'url_sertifikat' => ['required', 'url', 'max:500'],
            'url_foto_upp' => ['required', 'url', 'max:500'],
            'url_dokumen_undangan' => ['required', 'url', 'max:500'],
            'keterangan' => ['nullable', 'string', 'max:2000'],

            'mahasiswa' => ['required', 'array', 'min:1'],
            'mahasiswa.*.nim' => ['required', 'string', 'exists:mahasiswa,nim'],
            'mahasiswa.*.nama' => ['required', 'string', 'max:255'],

            'dosen' => ['required', 'array', 'min:1'],
            'dosen.*.nuptk' => ['required', 'string', 'exists:dosen,nuptk'],
            'dosen.*.nama' => ['required', 'string', 'max:255'],
            'dosen.*.url_surat_tugas' => ['required', 'url', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $mahasiswa = $this->input('mahasiswa', []);
            $dosen = $this->input('dosen', []);

            $nims = collect($mahasiswa)->pluck('nim')->filter();
            if ($nims->count() !== $nims->unique()->count()) {
                $validator->errors()->add('mahasiswa', 'NIM mahasiswa tidak boleh duplikat dalam satu pengajuan.');
            }

            $nuptks = collect($dosen)->pluck('nuptk')->filter();
            if ($nuptks->count() !== $nuptks->unique()->count()) {
                $validator->errors()->add('dosen', 'NUPTK dosen tidak boleh duplikat dalam satu pengajuan.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'jenis.required' => 'Jenis rekognisi wajib diisi.',
            'jenis.in' => 'Jenis rekognisi tidak valid.',
            'nama.required' => 'Nama kegiatan rekognisi wajib diisi.',
            'tgl_sertifikat.date_format' => 'Format tanggal harus YYYY-MM-DD.',
            'mahasiswa.*.nim.exists' => 'NIM :input tidak terdaftar di database.',
            'dosen.*.nuptk.exists' => 'NUPTK :input tidak terdaftar di database.',
        ];
    }
}
