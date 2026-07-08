<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGuestRequest;
use App\Http\Requests\UpdateGuestRequest;
use App\Http\Resources\GuestResource;
use App\Models\Guest;

class GuestController extends Controller
{
    public function index()
    {
        return GuestResource::collection(Guest::latest()->get());
    }

    public function store(StoreGuestRequest $request)
    {
        $guest = Guest::create($request->validated());

        return new GuestResource($guest);
    }

    public function update(UpdateGuestRequest $request, Guest $guest)
    {
        $guest->update($request->validated());

        return new GuestResource($guest);
    }

    public function destroy(Guest $guest)
    {
        $guest->delete();

        return new GuestResource($guest);
    }
}
