<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function validateCode(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:9'], // TH0000001
        ]);

        $u = User::where('member_code', $data['code'])
            ->where('status', 'active')
            ->first();

        if (!$u) {
            return response()->json([
                'exists' => false,
                'member_code' => $data['code'],
            ]);
        }

        return response()->json([
            'exists' => true,
            'member_code' => $u->member_code,
            'name' => $u->name,
        ]);
    }
}