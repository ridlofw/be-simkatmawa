<?php

namespace App\Http\Requests\Prestasi;

use App\Enums\Bentuk;
use App\Enums\KategoriPrestasi;
use App\Enums\KelompokPrestasi;
use App\Enums\Level;
use App\Enums\Peringkat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi pengajuan Prestasi Mandiri (POST create / PUT update).
 * Validasi mencakup seluruh field Kemdikbud + aturan INDIVIDU/KELOMPOK.
 */
class StorePrestasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Otorisasi sudah dihandle oleh middleware Spatie
    }

    public function rules(): array
    {
        return [
            // ===== Field Kemdikbud (Wajib) =====
            'level' => ['required', 'string', Rule::in(array_column(Level::cases(), 'value'))],
            'kategori' => ['required', 'string', Rule::in(array_column(KategoriPrestasi::cases(), 'value'))],
            'lomba' => ['required', 'string', 'max:255'],
            'cabang' => ['required', 'string', 'max:255'],
            'penyelenggara' => ['required', 'string', 'max:255'],
            'peringkat' => ['required', 'string', Rule::in(array_column(Peringkat::cases(), 'value'))],
            'jumlah_unit_peserta' => ['required', 'integer', 'min:1'],
            'kelompok_prestasi' => ['required', 'string', Rule::in(array_column(KelompokPrestasi::cases(), 'value'))],
            'bentuk' => ['required', 'string', Rule::in(array_column(Bentuk::cases(), 'value'))],
            'tgl_sertifikat' => ['required', 'date_format:Y-m-d'],

            // ===== URLs (Stateless Link-Only — Arsitektur_Backend.md §7) =====
            'url_peserta' => ['required', 'url', 'max:500'],
            'url_sertifikat' => ['required', 'url', 'max:500'],
            'url_foto_upp' => ['required', 'url', 'max:500'],
            'url_dokumen_undangan' => ['required', 'url', 'max:500'],

            // ===== Opsional =====
            'keterangan' => ['nullable', 'string', 'max:2000'],

            // ===== Array Mahasiswa (Peserta) =====
            'mahasiswa' => ['required', 'array', 'min:1'],
            'mahasiswa.*.nim' => ['required', 'string', 'exists:mahasiswa,nim'], // NIM harus ada di DB
            'mahasiswa.*.nama' => ['required', 'string', 'max:255'],

            // ===== Array Dosen (Pembimbing) =====
            'dosen' => ['required', 'array', 'min:1'],
            'dosen.*.nuptk' => ['required', 'string', 'exists:dosen,nuptk'], // NUPTK harus ada di DB
            'dosen.*.nama' => ['required', 'string', 'max:255'],
            'dosen.*.url_surat_tugas' => ['required', 'url', 'max:500'],
        ];
    }

    /**
     * Validasi tambahan setelah rules dasar lolos:
     * - INDIVIDU: tepat 1 mahasiswa
     * - KELOMPOK: minimal 2 mahasiswa
     * - NIM harus unik (tidak boleh duplikat)
     * - NUPTK harus unik (tidak boleh duplikat)
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $kelompok = $this->input('kelompok_prestasi');
            $mahasiswa = $this->input('mahasiswa', []);
            $dosen = $this->input('dosen', []);

            // Validasi jumlah mahasiswa berdasarkan kelompok_prestasi
            if ($kelompok === KelompokPrestasi::INDIVIDU->value && count($mahasiswa) !== 1) {
                $validator->errors()->add(
                    'mahasiswa',
                    'Untuk prestasi INDIVIDU, jumlah mahasiswa harus tepat 1 orang.'
                );
            }

            if ($kelompok === KelompokPrestasi::KELOMPOK->value && count($mahasiswa) < 2) {
                $validator->errors()->add(
                    'mahasiswa',
                    'Untuk prestasi KELOMPOK, jumlah mahasiswa minimal 2 orang.'
                );
            }

            // Validasi NIM unik (tidak boleh duplikat dalam 1 pengajuan)
            $nims = collect($mahasiswa)->pluck('nim')->filter();
            if ($nims->count() !== $nims->unique()->count()) {
                $validator->errors()->add(
                    'mahasiswa',
                    'NIM mahasiswa tidak boleh duplikat dalam satu pengajuan.'
                );
            }

            // Validasi NUPTK unik
            $nuptks = collect($dosen)->pluck('nuptk')->filter();
            if ($nuptks->count() !== $nuptks->unique()->count()) {
                $validator->errors()->add(
                    'dosen',
                    'NUPTK dosen tidak boleh duplikat dalam satu pengajuan.'
                );
            }
        });
    }

    /**
     * Pesan error custom berbahasa Indonesia.
     */
    public function messages(): array
    {
        return [
            'level.required' => 'Level kegiatan wajib diisi.',
            'level.in' => 'Level harus salah satu dari: KAB, PROV, NAS, INT.',
            'kategori.required' => 'Kategori prestasi wajib diisi.',
            'kategori.in' => 'Kategori tidak valid.',
            'lomba.required' => 'Nama lomba wajib diisi.',
            'peringkat.required' => 'Peringkat wajib diisi.',
            'peringkat.in' => 'Peringkat tidak valid.',
            'kelompok_prestasi.required' => 'Kelompok prestasi (INDIVIDU/KELOMPOK) wajib dipilih.',
            'tgl_sertifikat.required' => 'Tanggal sertifikat wajib diisi.',
            'tgl_sertifikat.date_format' => 'Format tanggal harus YYYY-MM-DD.',
            'url_sertifikat.required' => 'URL sertifikat wajib diisi.',
            'url_sertifikat.url' => 'URL sertifikat harus berformat URL yang valid.',
            'url_foto_upp.url' => 'URL foto UPP harus berformat URL yang valid.',
            'mahasiswa.required' => 'Daftar mahasiswa peserta wajib diisi.',
            'mahasiswa.min' => 'Minimal 1 mahasiswa harus disertakan.',
            'mahasiswa.*.nim.required' => 'NIM mahasiswa wajib diisi.',
            'mahasiswa.*.nim.exists' => 'NIM :input tidak terdaftar di database.',
            'dosen.required' => 'Daftar dosen pembimbing wajib diisi.',
            'dosen.min' => 'Minimal 1 dosen pembimbing harus disertakan.',
            'dosen.*.nuptk.required' => 'NUPTK dosen wajib diisi.',
            'dosen.*.nuptk.exists' => 'NUPTK :input tidak terdaftar di database.',
            'dosen.*.url_surat_tugas.required' => 'URL surat tugas dosen wajib diisi.',
            'dosen.*.url_surat_tugas.url' => 'URL surat tugas harus berformat URL yang valid.',
        ];
    }
}
