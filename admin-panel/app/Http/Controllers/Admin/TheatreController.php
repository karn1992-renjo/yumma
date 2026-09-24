<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Theatre;
use App\Models\TheatreRunner;
use App\Models\User;
use App\Services\TheatreManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * swaad-only: platform-side management of theatres (the movie-theatre
 * in-seat food ordering module). Mirrors Admin\BranchController's shape but
 * deliberately leaner -- no wallet/settlement/payout/zone-mapping here, see
 * TheatreManagementService for why.
 */
class TheatreController extends Controller
{
    public function __construct(private TheatreManagementService $theatres)
    {
    }

    public function index(Request $request)
    {
        $query = Theatre::query()->with('owner')->withCount(['restaurants', 'screens', 'runnerRoster']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('owner_name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $theatres = $query->latest()->paginate(20)->withQueryString();
        $stats = [
            'total' => Theatre::count(),
            'active' => Theatre::where('status', 'active')->count(),
            'mapped_restaurants' => DB::table('theatre_restaurants')->where('is_active', true)->distinct('restaurant_id')->count('restaurant_id'),
            'runners' => TheatreRunner::where('approval_status', 'active')->count(),
        ];

        return view('admin.theatres.index', compact('theatres', 'stats'));
    }

    public function create()
    {
        return view('admin.theatres.create');
    }

    public function store(Request $request)
    {
        $data = $this->validatedTheatre($request);

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('theatres', 'public');
        }

        $theatre = $this->theatres->createTheatre($data, $request->user());

        return redirect()->route('admin.theatres.show', $theatre)
            ->with('success', 'Theatre created with owner login and permissions.');
    }

    public function show(Theatre $theatre)
    {
        $theatre->load(['owner', 'screens', 'restaurants']);
        $runners = TheatreRunner::with('runner')->where('theatre_id', $theatre->id)->get();

        return view('admin.theatres.show', compact('theatre', 'runners'));
    }

    public function edit(Theatre $theatre)
    {
        return view('admin.theatres.edit', compact('theatre'));
    }

    public function update(Request $request, Theatre $theatre)
    {
        $data = $this->validatedTheatre($request, $theatre);

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('theatres', 'public');
        }

        $old = $theatre->toArray();
        $theatre->update($data);
        $this->theatres->audit($theatre, $request->user(), 'theatre.updated', $theatre, $old, $theatre->fresh()->toArray());

        return redirect()->route('admin.theatres.show', $theatre)->with('success', 'Theatre updated.');
    }

    public function destroy(Theatre $theatre)
    {
        $theatre->delete();

        return redirect()->route('admin.theatres.index')->with('success', 'Theatre archived.');
    }

    /**
     * One-click fix for the exact class of bug BranchController::resyncOwnerPermissions()
     * exists for -- an owner whose stored permissions predate a feature added
     * after their TheatreUser row was created, or the row missing entirely.
     */
    public function resyncOwnerPermissions(Theatre $theatre)
    {
        abort_unless($theatre->owner_user_id, 404, 'This theatre has no owner account yet.');
        $owner = User::findOrFail($theatre->owner_user_id);

        $this->theatres->resyncPermissions($theatre, $owner, TheatreManagementService::OWNER_ROLE);

        return redirect()->route('admin.theatres.edit', $theatre)
            ->with('success', "{$owner->name}'s permissions were reset to the default owner set.");
    }

    private function validatedTheatre(Request $request, ?Theatre $theatre = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('theatres', 'code')->ignore($theatre)],
            'logo' => ['nullable', 'image', 'max:2048'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255', Rule::unique('theatres', 'owner_email')->ignore($theatre)],
            'owner_phone' => ['required', 'string', 'max:30', Rule::unique('theatres', 'owner_phone')->ignore($theatre)],
            'owner_password' => [$theatre ? 'nullable' : 'required', 'nullable', 'string', 'min:8'],
            'country' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'geofence_radius_meters' => ['required', 'integer', 'min:30', 'max:1000'],
            'aisle_handoff_only' => ['nullable', 'boolean'],
            'tags' => ['nullable'],
            'custom_tags' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive', 'archived'])],
            'notes' => ['nullable', 'string'],
        ]);

        $tags = [];
        if ($request->has('tags')) {
            $raw = $request->input('tags');
            if (is_array($raw)) {
                $tags = $raw;
            } elseif (is_string($raw)) {
                $tags = array_map('trim', explode(',', $raw));
            }
        }
        if ($request->filled('custom_tags')) {
            $custom = array_map('trim', explode(',', (string) $request->input('custom_tags')));
            $tags = array_merge($tags, $custom);
        }
        $validated['tags'] = array_values(array_unique(array_filter($tags, fn($t) => filled($t))));
        unset($validated['custom_tags']);

        return $validated;
    }
}
