<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UnknownController extends Controller
{


    public function __construct(
        protected User $user
    )
    {
    }

    public function cun(Request $request) {
        try {
            $name = $request->all();

            $user = $this->user->findOrFail($name['id']);

            $user->name = $name['name'];
            $user->save();

            return response()->json(
                ["user" => $user]
            );
        } catch (\Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "status" => 287
            ], 200);
        }

    }
}
