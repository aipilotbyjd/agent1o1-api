<?php

namespace App\Http\Controllers\Api\SuperAdmin\V1\Customers;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\SuperAdmin\Customers\CustomerSearch;
use Illuminate\Http\Request;

class CustomerSearchController extends Controller
{
    public function __construct(private readonly CustomerSearch $search) {}

    /**
     * One box for users, workspaces and runs, limited to what the admin may view.
     */
    public function __invoke(Request $request)
    {
        $request->validate(['q' => ['required', 'string', 'max:100']]);

        return ApiResponse::success($this->search->search($request->user('super-admin'), (string) $request->string('q')));
    }
}
