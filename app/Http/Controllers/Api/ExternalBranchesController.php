<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexExternalBranchesRequest;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;

class ExternalBranchesController extends Controller
{
    public function index(IndexExternalBranchesRequest $request): JsonResponse
    {
        $branches = Branch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'city', 'state', 'country', 'is_headquarters']);

        $data = $branches->map(static fn (Branch $branch): array => [
            'id' => $branch->id,
            'code' => $branch->code,
            'name' => $branch->name,
            'city' => $branch->city,
            'state' => $branch->state,
            'country' => $branch->country,
            'is_headquarters' => (bool) $branch->is_headquarters,
        ]);

        return response()->json([
            'data' => $data,
        ]);
    }
}
