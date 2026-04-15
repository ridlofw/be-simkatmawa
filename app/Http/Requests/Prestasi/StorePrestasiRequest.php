<?php

namespace App\Http\Requests\Prestasi;

use App\Enums\Bentuk;
use App\Enums\KategoriPrestasi;
use App\Enums\KelompokPrestasi;
use App\Enums\Level;
use App\Enums\Peringkat;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Validasi request untuk membuat pengajuan Prestasi Mandiri.
 * Rule mengikuti spesifikasi Kemdikbud + Kontrak_API_Frontend.md §C.6.
 */
class StorePrestasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Otorisasi ditangani oleh middleware role
    }

    public function rules(): array
    {
        return [
            'level' => ['required', Rule::enum(Level::class)],
            'kategori' => ['required', Rule::enum(KategoriPrestasi::class)],
            'lomba' => ['required', 'string', 'max:255'],
            'cabang' => ['required', 'string', 'max:255'],
            'penyelenggara' => ['required', 'string', 'max:255'],
            'peringkat' => ['required', Rule::enum(Peringkat::class)],
            'jumlah_unit_peserta' => ['required', 'integer', 'min:1'],
            'kelompok_prestasi' => ['required', Rule::enum(KelompokPrestasi::class)],
            'bentuk' => ['required', Rule::enum(Bentuk::class)],
            'url_peserta' => ['required', 'url', 'max:500'],
            'url_sertifikat' => ['required', 'url', 'max:500'],
            'tgl_sertifikat' => ['required', 'date', 'date_format:Y-m-d'],
            'url_foto_upp' => ['required', 'url', 'max:500'],
            'url_dokumen_undangan' => ['required', 'url', 'max:500'],
            'keterangan' => ['nullable', 'string', 'max:1000'],

            // Mahasiswa yang terlibat (array NIM)
            'mahasiswa' => ['required', 'array', 'min:1'],
            'mahasiswa.*.nim' => ['required', 'string'],
            'mahasiswa.*.nama' => ['required', 'string'],

            // Dosen pembimbing (opsional)
            'dosen' => ['nullable', 'array'],
            'dosen.*.nuptk' => ['required', 'string'],
            'dosen.*.nama' => ['required', 'string'],
            'dosen.*.url_surat_tugas' => ['required', 'url', 'max:500'],
        ];
    }

    /**
     * Format error validasi sesuai kontrak API (422).
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validasi gagal, periksa kembali input Anda.',
            'data' => null,
            'errors' => $validator->errors(),
        ], 422));
    }
}
