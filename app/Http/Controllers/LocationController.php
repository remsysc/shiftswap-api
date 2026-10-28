<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLocationRequest;
use App\Http\Requests\UpdateLocationRequest;
use App\Http\Resources\LocationResource;
use App\Models\Business;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class LocationController extends Controller
{
    /**
     * List all locations for a business.
     */
    public function index(Request $request, Business $business): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Location::class, $business]);

        return LocationResource::collection($business->locations()->latest()->get());
    }

    /**
     * Create a new location for a business.
     */
    public function store(StoreLocationRequest $request, Business $business): JsonResponse
    {
        Gate::authorize('create', [Location::class, $business]);

        /** @var Location $location */
        $location = $business->locations()->create($request->validated());

        return (new LocationResource($location))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display a specific location.
     */
    public function show(Business $business, Location $location): LocationResource
    {
        abort_if($location->business_id !== $business->id, 403);
        Gate::authorize('view', $location);

        return new LocationResource($location);
    }

    /**
     * Update an existing location.
     */
    public function update(UpdateLocationRequest $request, Business $business, Location $location): LocationResource
    {
        abort_if($location->business_id !== $business->id, 403);
        Gate::authorize('update', $location);

        $location->update($request->validated());

        return new LocationResource($location);
    }

    /**
     * Delete an existing location.
     */
    public function destroy(Business $business, Location $location): Response
    {
        abort_if($location->business_id !== $business->id, 403);
        Gate::authorize('delete', $location);

        $location->delete();

        return response()->noContent();
    }
}
