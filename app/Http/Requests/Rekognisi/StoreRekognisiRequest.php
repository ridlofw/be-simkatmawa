<?php

namespace App\Http\Requests\Rekognisi;

use App\Enums\JenisRekognisi;
use App\Enums\Level;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Validasi request untuk membuat pengajuan Rekognisi.
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
            'level' => ['required', Rule::enum(Level::class)],
            'jenis' => ['required', Rule::enum(JenisRekognisi::class)],
            'nama' => ['required', 'string', 'max:255'],
            'penyelenggara' => ['required', 'string', 'max:255'],
            'url_peserta' => ['required', 'url', 'max:500'],
            'url_sertifikat' => ['required', 'url', 'max:500'],
            'tgl_sertifikat' => ['required', 'date', 'date_format:Y-m-d'],
            'url_foto_upp' => ['required', 'url', 'max:500'],
            'url_dokumen_undangan' => ['required', 'url', 'max:500'],
            'keterangan' => ['nullable', 'string', 'max:1000'],

            'mahasiswa' => ['required', 'array', 'min:1'],
            'mahasiswa.*.nim' => ['required', 'string'],
            'mahasiswa.*.nama' => ['required', 'string'],

            'dosen' => ['nullable', 'array'],
            'dosen.*.nuptk' => ['required', 'string'],
            'dosen.*.nama' => ['required', 'string'],
            'dosen.*.url_surat_tugas' => ['required', 'url', 'max:500'],
        ];
    }

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
