<?php

namespace App\Http\Controllers;

use App\Models\ProgramSection;
use App\Support\ListPageSize;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SectionController extends Controller
{
    private array $programs = ['BSIT', 'BSBA', 'BSHM', 'BSED', 'BEED'];

    public function index(Request $request)
    {
        $filterProg = $request->program ?? '';
        $filterYear = $request->year ?? '';

        // Grouped sections (same section name across multiple years)
        $allSections = ProgramSection::orderBy('program')->orderBy('section')->orderBy('year_level')->get();

        $grouped = [];
        foreach ($allSections as $row) {
            $key = $row->program.'|'.$row->section;
            if (! isset($grouped[$key])) {
                $grouped[$key] = ['id' => $row->id, 'program' => $row->program, 'section' => $row->section, 'years' => [], 'created_at' => $row->created_at];
            }
            $grouped[$key]['years'][] = (int) $row->year_level;
            $grouped[$key]['id'] = $row->id;
        }

        // Filter
        $filtered = collect(array_values($grouped))->filter(function ($s) use ($filterProg, $filterYear) {
            if ($filterProg && $s['program'] !== $filterProg) {
                return false;
            }
            if ($filterYear && ! in_array($filterYear, $s['years'])) {
                return false;
            }

            return true;
        })->values();

        // Summary per program+year
        $summary = ProgramSection::select('program', 'year_level', DB::raw('count(*) as cnt'))
            ->groupBy('program', 'year_level')
            ->get()
            ->groupBy('program')
            ->map(fn ($rows) => $rows->pluck('cnt', 'year_level'));

        $programs = $this->programs;
        $total = count($grouped);
        $progCount = collect($grouped)->pluck('program')->unique()->count();

        // Section groups are assembled in PHP rather than queried, so the page
        // has to slice them itself to get the same pager as every other listing.
        $filtered = $this->paginate($filtered, $request);

        return view('mainAdmin.sections.index', compact('filtered', 'grouped', 'summary', 'programs', 'filterProg', 'filterYear', 'total', 'progCount'));
    }

    /**
     * Page an in-memory collection the way a query builder would, so the shared
     * table pager works on it unchanged.
     *
     * @param  \Illuminate\Support\Collection<int, mixed>  $items
     */
    private function paginate($items, Request $request): LengthAwarePaginator
    {
        $perPage = ListPageSize::from($request->limit);
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }

    public function store(Request $request)
    {
        $this->normalizeSectionInput($request);
        $data = $request->validate([
            'program' => 'required|in:BSIT,BSBA,BSHM,BSED,BEED',
            'year_levels' => 'required|array|min:1|max:4',
            'year_levels.*' => 'integer|distinct|in:1,2,3,4',
            'section' => 'required|string|max:50',
        ]);

        $this->ensureUniqueAssignments($data);

        foreach ($data['year_levels'] as $year) {
            ProgramSection::create([
                'program' => $data['program'],
                'year_level' => $year,
                'section' => $data['section'],
            ]);
        }

        return redirect()->route('sections.index')->with('flash', [
            'type' => 'success',
            'message' => "Section {$data['section']} added for ".count($data['year_levels']).' year level(s).',
        ]);
    }

    public function update(Request $request, $id)
    {
        $anchor = ProgramSection::findOrFail($id);
        $originalIds = ProgramSection::where('program', $anchor->program)
            ->where('section', $anchor->section)
            ->pluck('id');

        $this->normalizeSectionInput($request);
        $data = $request->validate([
            'program' => 'required|in:BSIT,BSBA,BSHM,BSED,BEED',
            'year_levels' => 'required|array|min:1|max:4',
            'year_levels.*' => 'integer|distinct|in:1,2,3,4',
            'section' => 'required|string|max:50',
        ]);

        $this->ensureUniqueAssignments($data, $originalIds->all());

        DB::transaction(function () use ($anchor, $data) {
            ProgramSection::where('program', $anchor->program)
                ->where('section', $anchor->section)
                ->delete();

            foreach ($data['year_levels'] as $year) {
                ProgramSection::create([
                    'program' => $data['program'],
                    'year_level' => $year,
                    'section' => $data['section'],
                ]);
            }
        });

        return redirect()->route('sections.index')->with('flash', ['type' => 'success', 'message' => 'Section updated.']);
    }

    public function destroy($id)
    {
        $anchor = ProgramSection::findOrFail($id);
        ProgramSection::where('program', $anchor->program)
            ->where('section', $anchor->section)
            ->delete();

        return redirect()->route('sections.index')->with('flash', ['type' => 'success', 'message' => 'Section deleted.']);
    }

    private function normalizeSectionInput(Request $request): void
    {
        $years = $request->input('year_levels', []);
        if (is_array($years)) {
            $years = array_values(array_unique(array_map('intval', $years)));
            sort($years);
        }

        $request->merge([
            'section' => strtoupper(trim((string) $request->input('section'))),
            'year_levels' => $years,
        ]);
    }

    private function ensureUniqueAssignments(array $data, array $ignoreIds = []): void
    {
        $query = ProgramSection::where('program', $data['program'])
            ->where('section', $data['section'])
            ->whereIn('year_level', $data['year_levels']);

        if ($ignoreIds !== []) {
            $query->whereNotIn('id', $ignoreIds);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'section' => 'This section already exists for one or more selected year levels in this program.',
            ]);
        }
    }

    // AJAX — replaces get_sections.php
    public function getSections(Request $request)
    {
        $sections = ProgramSection::orderBy('program')->orderBy('year_level')->orderBy('section')
            ->get(['program', 'year_level', 'section']);

        return response()->json($sections);
    }
}
