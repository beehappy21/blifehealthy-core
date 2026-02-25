<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MemberCodeGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request, MemberCodeGenerator $generator)
    {
        $data = $request->validate([
            'name' => ['required','string','max:150'],
            'phone' => ['required','string','max:20'],
            'email' => ['nullable','email','max:150'],
            'password' => ['required','string','min:6'],
            'referrer_member_code' => ['nullable','string','size:9'], // TH0000001
            'ref_source' => ['nullable','in:link,manual,qr'],
        ]);

        $defaultSponsor = env('DEFAULT_SPONSOR_CODE', 'TH0000001');

        // หา ref ในระบบใหม่ ถ้าไม่พบ -> fallback
        $refInput = $data['referrer_member_code'] ?? null;
        $refFinal = $defaultSponsor;
        $refStatus = 'DEFAULTED';

        if ($refInput) {
            $exists = User::where('member_code', $refInput)->where('status', 'active')->exists();
            if ($exists) {
                $refFinal = $refInput;
                $refStatus = 'CONFIRMED';
            }
        }

        // กันเบอร์ซ้ำ (ถ้าต้องการให้ 1 เบอร์ = 1 บัญชี)
        $phoneExists = User::where('phone', $data['phone'])->exists();
        if ($phoneExists) {
            throw ValidationException::withMessages(['phone' => 'เบอร์นี้ถูกใช้งานแล้ว']);
        }

        $user = DB::transaction(function () use ($data, $generator, $refInput, $refFinal, $refStatus, $defaultSponsor) {
            $memberCode = $generator->generate();

            // ถ้า user กรอก ref เป็นตัวเอง (กันเคสแปลก)
            if ($refFinal === $memberCode) {
                $refFinal = $defaultSponsor;
                $refStatus = 'DEFAULTED';
            }

            $user = User::create([
                'member_code' => $memberCode,
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'password_hash' => Hash::make($data['password']),
                'status' => 'active',

                'ref_input_code' => $refInput,
                'referrer_member_code' => $refFinal,
                'ref_status' => $refStatus,
                'ref_source' => $data['ref_source'] ?? null,
                'ref_locked_at' => now(),
            ]);

            // log referral_links
            DB::table('referral_links')->insert([
                'new_member_code' => $user->member_code,
                'ref_input_code' => $refInput,
                'referrer_member_code_final' => $refFinal,
                'ref_status' => $refStatus,
                'ref_source' => $data['ref_source'] ?? null,
                'note' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // outbox: member.created (ไว้เชื่อมระบบเก่าทีหลัง)
            DB::table('integration_outbox')->insert([
                'event_type' => 'member.created',
                'payload_json' => json_encode([
                    'member_code' => $user->member_code,
                    'ref_input_code' => $refInput,
                    'referrer_member_code_final' => $refFinal,
                    'ref_status' => $refStatus,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'email' => $user->email,
                ], JSON_UNESCAPED_UNICODE),
                'status' => 'pending',
                'retry_count' => 0,
                'next_retry_at' => null,
                'last_error' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $user;
        });

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'member_code' => $user->member_code,
            'referrer_member_code' => $user->referrer_member_code,
            'ref_status' => $user->ref_status,
            'token' => $token,
        ]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required','string','max:20'],
            'password' => ['required','string'],
        ]);

        $user = User::where('phone', $data['phone'])->first();

        if (!$user || !Hash::check($data['password'], $user->password_hash)) {
            throw ValidationException::withMessages(['phone' => 'เบอร์หรือรหัสผ่านไม่ถูกต้อง']);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages(['phone' => 'บัญชีถูกระงับ']);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'member_code' => $user->member_code,
            'token' => $token,
        ]);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['ok' => true]);
    }
}