<?php

namespace App\Http\Controllers\Dokter;

use App\Http\Controllers\Controller;
use App\Models\JadwalPeriksa;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class JadwalPeriksaController extends Controller
{
    private const HARI = [
        'Senin',
        'Selasa',
        'Rabu',
        'Kamis',
        'Jumat',
        'Sabtu',
        'Minggu',
    ];

    public function index()
    {
        $jadwals = auth()->user()
            ->jadwalPeriksas()
            ->orderBy('hari')
            ->orderBy('jam_mulai')
            ->get();

        return view('dokter.jadwal-periksa.index', compact('jadwals'));
    }

    public function create()
    {
        return view('dokter.jadwal-periksa.create', [
            'hariOptions' => self::HARI,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateJadwal($request);

        $this->ensureNoOverlap(
            $validated['hari'],
            $validated['jam_mulai'],
            $validated['jam_selesai']
        );

        $request->user()->jadwalPeriksas()->create($validated);

        return redirect()
            ->route('dokter.jadwal-periksa.index')
            ->with('success', 'Jadwal periksa berhasil ditambahkan');
    }

    public function edit(JadwalPeriksa $jadwalPeriksa)
    {
        $this->ensureOwnedByAuthenticatedDoctor($jadwalPeriksa);

        return view('dokter.jadwal-periksa.edit', [
            'jadwalPeriksa' => $jadwalPeriksa,
            'hariOptions' => self::HARI,
        ]);
    }

    public function update(Request $request, JadwalPeriksa $jadwalPeriksa)
    {
        $this->ensureOwnedByAuthenticatedDoctor($jadwalPeriksa);

        $validated = $this->validateJadwal($request);

        $this->ensureNoOverlap(
            $validated['hari'],
            $validated['jam_mulai'],
            $validated['jam_selesai'],
            $jadwalPeriksa->id
        );

        $jadwalPeriksa->update($validated);

        return redirect()
            ->route('dokter.jadwal-periksa.index')
            ->with('success', 'Jadwal periksa berhasil diperbarui');
    }

    public function destroy(JadwalPeriksa $jadwalPeriksa)
    {
        $this->ensureOwnedByAuthenticatedDoctor($jadwalPeriksa);

        if ($jadwalPeriksa->daftarPolis()->exists()) {
            return redirect()
                ->route('dokter.jadwal-periksa.index')
                ->with('error', 'Jadwal tidak dapat dihapus karena sudah memiliki pendaftaran pasien.');
        }

        $jadwalPeriksa->delete();

        return redirect()
            ->route('dokter.jadwal-periksa.index')
            ->with('success', 'Jadwal periksa berhasil dihapus');
    }

    private function validateJadwal(Request $request): array
    {
        return $request->validate([
            'hari' => ['required', Rule::in(self::HARI)],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i', 'after:jam_mulai'],
        ]);
    }

    private function ensureNoOverlap(
        string $hari,
        string $jamMulai,
        string $jamSelesai,
        ?int $ignoreId = null
    ): void {
        $query = auth()->user()
            ->jadwalPeriksas()
            ->where('hari', $hari)
            ->where('jam_mulai', '<', $jamSelesai)
            ->where('jam_selesai', '>', $jamMulai);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'jam_mulai' => 'Jadwal bertabrakan dengan jadwal lain pada hari yang sama.',
            ]);
        }
    }

    private function ensureOwnedByAuthenticatedDoctor(JadwalPeriksa $jadwalPeriksa): void
    {
        abort_unless(
            (int) $jadwalPeriksa->id_dokter === (int) auth()->id(),
            403,
            'Anda tidak memiliki akses ke jadwal ini.'
        );
    }
}
