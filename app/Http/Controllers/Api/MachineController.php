<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\MachineFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MachineController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Machine::select('machines.*')
            ->with([
                'branch',
                'type',
                'files',
                'machineComponents.component.componentCategory',
            ])
            ->leftJoin('branches', 'branches.id', '=', 'machines.branch_id')
            ->leftJoin('machine_types', 'machine_types.id', '=', 'machines.machine_type_id');

        if (!$user->isSuperAdmin()) {
            $query->where('machines.branch_id', $user->branch_id);
        } elseif ($request->filled('branch_id')) {
            $query->where('machines.branch_id', $request->input('branch_id'));
        }

        if ($request->filled('machine_type_id')) {
            $query->where('machines.machine_type_id', $request->input('machine_type_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('machines.is_active', $request->boolean('is_active'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');

            $query->where(function ($q) use ($search) {
                $q->where('machines.machine_number', 'like', "%{$search}%")
                    ->orWhere('machines.description', 'like', "%{$search}%")
                    ->orWhere('branches.name', 'like', "%{$search}%")
                    ->orWhere('machine_types.name', 'like', "%{$search}%");
            });
        }

        $sortBy = $request->input('sort_by', 'id');
        $sortDir = strtolower($request->input('sort_dir', 'desc'));
        $allowedSorts = ['id', 'created_at', 'machine_number', 'branch', 'machine_type', 'base_price', 'weight', 'is_active'];

        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'id';
        }

        if (!in_array($sortDir, ['asc', 'desc'])) {
            $sortDir = 'desc';
        }

        if ($sortBy === 'branch') {
            $query->orderBy('branches.name', $sortDir);
        } elseif ($sortBy === 'machine_type') {
            $query->orderBy('machine_types.name', $sortDir);
        } else {
            $query->orderBy('machines.' . $sortBy, $sortDir);
        }

        $perPage = min($request->input('per_page', 15), 100);

        return response()->json(
            $query
                ->addSelect([
                    'branch_name' => function ($query) {
                        $query->select('name')
                            ->from('branches')
                            ->whereColumn('branches.id', 'machines.branch_id')
                            ->limit(1);
                    },
                ])
                ->addSelect([
                    'machine_type_name' => function ($query) {
                        $query->select('name')
                            ->from('machine_types')
                            ->whereColumn('machine_types.id', 'machines.machine_type_id')
                            ->limit(1);
                    },
                ])
                ->paginate($perPage)
        );
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $query = Machine::with([
            'branch',
            'type',
            'files',
            'machineComponents.component.componentCategory',
        ]);

        if (!$user->isSuperAdmin()) {
            $query->where('branch_id', $user->branch_id);
        }

        $machine = $query->find($id);

        if (!$machine) {
            return response()->json(['message' => 'Data tidak ditemukan atau Anda tidak memiliki akses'], 404);
        }

        $machine->branch_name = optional($machine->branch)->name;
        $machine->machine_type_name = optional($machine->type)->name;

        return response()->json($machine);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'machine_number' => 'required|string|max:255|unique:machines,machine_number',
            'machine_type_id' => 'required|exists:machine_types,id',
            'branch_id' => $user->isSuperAdmin() ? 'required|exists:branches,id' : 'nullable',
            'base_price' => 'required|numeric|min:0',
            'weight' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'files' => 'nullable|array|max:10',
            'files.*' => 'file|max:20480|mimes:pdf,dxf,dwg,ai,cdr,svg,png,jpg,jpeg,doc,docx,xls,xlsx',
            'components' => 'nullable|array',
            'components.*.component_id' => 'required|exists:components,id',
            'components.*.qty' => 'required|integer|min:1',
        ]);

        $branchId = $user->isSuperAdmin() ? $validated['branch_id'] : $user->branch_id;

        DB::beginTransaction();
        try {
            $machine = Machine::create([
                'machine_number' => $validated['machine_number'],
                'branch_id' => $branchId,
                'machine_type_id' => $validated['machine_type_id'],
                'base_price' => $validated['base_price'],
                'weight' => $validated['weight'],
                'description' => $this->sanitizeDescription($validated['description'] ?? null),
                'is_active' => $validated['is_active'] ?? true,
            ]);

            $this->syncComponents($machine, $validated['components'] ?? [], $user);
            $this->storeFiles($request, $machine);

            DB::commit();

            return response()->json([
                'message' => 'Mesin berhasil dibuat',
                'data' => $machine->load([
                    'branch',
                    'type',
                    'files',
                    'machineComponents.component.componentCategory',
                ]),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal membuat mesin: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $machine = Machine::find($id);

        if (!$machine) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        if (!$user->isSuperAdmin() && $machine->branch_id !== $user->branch_id) {
            return response()->json(['message' => 'Forbidden: Anda tidak memiliki akses ke data cabang lain'], 403);
        }

        $validated = $request->validate([
            'machine_number' => 'required|string|max:255|unique:machines,machine_number,' . $id,
            'machine_type_id' => 'required|exists:machine_types,id',
            'branch_id' => $user->isSuperAdmin() ? 'required|exists:branches,id' : 'nullable',
            'base_price' => 'required|numeric|min:0',
            'weight' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'is_active' => 'required|boolean',
            'files' => 'nullable|array|max:10',
            'files.*' => 'file|max:20480|mimes:pdf,dxf,dwg,ai,cdr,svg,png,jpg,jpeg,doc,docx,xls,xlsx',
            'components' => 'nullable|array',
            'components.*.component_id' => 'required|exists:components,id',
            'components.*.qty' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            $machine->update([
                'machine_number' => $validated['machine_number'],
                'branch_id' => $user->isSuperAdmin() ? $validated['branch_id'] : $machine->branch_id,
                'machine_type_id' => $validated['machine_type_id'],
                'base_price' => $validated['base_price'],
                'weight' => $validated['weight'],
                'description' => $this->sanitizeDescription($validated['description'] ?? null),
                'is_active' => $validated['is_active'],
            ]);

            $this->syncComponents($machine, $validated['components'] ?? [], $user);
            $this->storeFiles($request, $machine);

            DB::commit();

            return response()->json([
                'message' => 'Mesin berhasil diupdate',
                'data' => $machine->load([
                    'branch',
                    'type',
                    'files',
                    'machineComponents.component.componentCategory',
                ]),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal mengupdate mesin: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $machine = Machine::find($id);

        if (!$machine) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        if (!$user->isSuperAdmin() && $machine->branch_id !== $user->branch_id) {
            return response()->json(['message' => 'Forbidden: Anda tidak bisa menghapus data cabang lain'], 403);
        }

        $machine->delete();

        return response()->json(['message' => 'Mesin berhasil dihapus']);
    }

    public function downloadFile(Request $request, $machineId, $fileId)
    {
        $user = $request->user();
        $machine = Machine::with('files')->find($machineId);

        if (!$machine) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        if (!$user->isSuperAdmin() && $machine->branch_id !== $user->branch_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $file = $machine->files->firstWhere('id', (int) $fileId);

        if (!$file) {
            return response()->json(['message' => 'File tidak ditemukan'], 404);
        }

        if (!Storage::disk('public')->exists($file->file_path)) {
            return response()->json(['message' => 'Berkas fisik tidak ditemukan'], 404);
        }

        return Storage::disk('public')->download(
            $file->file_path,
            $file->file_name ?: basename($file->file_path)
        );
    }

    public function destroyFile(Request $request, $machineId, $fileId)
    {
        $user = $request->user();
        $machine = Machine::with('files')->find($machineId);

        if (!$machine) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        if (!$user->isSuperAdmin() && $machine->branch_id !== $user->branch_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $file = $machine->files->firstWhere('id', (int) $fileId);

        if (!$file) {
            return response()->json(['message' => 'File tidak ditemukan'], 404);
        }

        if ($file->file_path && Storage::disk('public')->exists($file->file_path)) {
            Storage::disk('public')->delete($file->file_path);
        }

        $file->delete();

        return response()->json(['message' => 'File mesin berhasil dihapus']);
    }

    private function syncComponents(Machine $machine, array $components, $user): void
    {
        $syncData = [];

        foreach ($components as $component) {
            $componentModel = DB::table('components')
                ->select('id', 'branch_id')
                ->where('id', $component['component_id'])
                ->first();

            if (!$componentModel) {
                continue;
            }

            if ((int) $componentModel->branch_id !== (int) $machine->branch_id) {
                continue;
            }

            $syncData[$component['component_id']] = [
                'qty' => $component['qty'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $machine->components()->sync($syncData);
    }

    private function storeFiles(Request $request, Machine $machine): void
    {
        foreach ($request->file('files', []) as $file) {
            $storedPath = $file->store("machines/{$machine->id}", 'public');

            MachineFile::create([
                'machine_id' => $machine->id,
                'file_path' => $storedPath,
                'file_name' => $file->getClientOriginalName(),
                'file_extension' => strtolower((string) $file->getClientOriginalExtension()),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getClientMimeType(),
            ]);
        }
    }

    private function sanitizeDescription(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $html = trim($html);

        if ($html === '') {
            return null;
        }

        $allowedTags = '<p><br><strong><b><em><i><u><s><ul><ol><li><h1><h2><h3><blockquote><a>';
        $sanitized = strip_tags($html, $allowedTags);

        $sanitized = preg_replace('/\son[a-z]+\s*=\s*("|\').*?\1/iu', '', $sanitized);
        $sanitized = preg_replace('/\son[a-z]+\s*=\s*[^\s>]+/iu', '', $sanitized);

        $sanitized = preg_replace_callback('/<a\b[^>]*>/iu', function ($matches) {
            $tag = $matches[0];
            preg_match('/href\s*=\s*("|\')(.*?)\1/iu', $tag, $hrefMatch);
            $href = $hrefMatch[2] ?? '#';
            $href = preg_replace('/^\s*javascript\s*:/iu', '#', $href);
            $href = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');

            return '<a href="' . $href . '" target="_blank" rel="noopener noreferrer">';
        }, $sanitized);

        $sanitized = preg_replace('/<(?!\/?a\b)([a-z0-9]+)\b[^>]*>/iu', '<$1>', $sanitized);
        $sanitized = preg_replace('/<p><br><\/p>/iu', '', $sanitized);
        $sanitized = trim($sanitized);

        return $sanitized !== '' ? $sanitized : null;
    }
}
