<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MerchantController extends Controller
{
    // ลูกค้าสมัครเปิดร้าน (สร้าง merchants.status=submitted)
    public function apply(Request $request)
    {
        $data = $request->validate([
            'shop_name' => ['required','string','max:200'],
            'phone' => ['required','string','max:20'],
            'email' => ['nullable','email','max:150'],
        ]);

        $user = $request->user();

        // เฟส 1: 1 user = 1 ร้าน
        $exists = DB::table('merchants')->where('owner_user_id', $user->id)->exists();
        if ($exists) return response()->json(['message' => 'คุณมีร้านค้าอยู่แล้ว'], 422);

        $merchantId = DB::table('merchants')->insertGetId([
            'owner_user_id' => $user->id,
            'shop_name' => $data['shop_name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'status' => 'submitted',
            'admin_note' => null,
            'approved_at' => null,
            'rejected_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['merchant_id' => $merchantId, 'status' => 'submitted']);
    }

    // ส่ง KYC (เก็บเป็น JSON ของ URL/ข้อมูล)
    public function submitKyc(Request $request)
    {
        $data = $request->validate([
            'merchant_id' => ['required','integer'],
            'docs' => ['required','array'],
        ]);

        $user = $request->user();
        $m = DB::table('merchants')->where('id', $data['merchant_id'])->first();

        if (!$m || (int)$m->owner_user_id !== (int)$user->id) {
            return response()->json(['message' => 'not found'], 404);
        }

        DB::table('merchant_kyc')->updateOrInsert(
            ['merchant_id' => $data['merchant_id']],
            [
                'docs_json' => json_encode($data['docs'], JSON_UNESCAPED_UNICODE),
                'submitted_at' => now(),
                'reviewed_by' => null,
                'review_note' => null,
                'reviewed_at' => null,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return response()->json(['ok' => true]);
    }

    // เพิ่มจุดรับสินค้า (location)
    public function addLocation(Request $request)
    {
        $data = $request->validate([
            'merchant_id' => ['required','integer'],
            'name' => ['nullable','string','max:100'],
            'address_line1' => ['required','string','max:255'],
            'address_line2' => ['nullable','string','max:255'],
            'subdistrict' => ['nullable','string','max:100'],
            'district' => ['nullable','string','max:100'],
            'province' => ['nullable','string','max:100'],
            'postcode' => ['nullable','string','max:10'],
            'lat' => ['nullable','numeric'],
            'lng' => ['nullable','numeric'],
            'is_pickup_point' => ['nullable','boolean'],
        ]);

        $user = $request->user();
        $m = DB::table('merchants')->where('id', $data['merchant_id'])->first();

        if (!$m || (int)$m->owner_user_id !== (int)$user->id) {
            return response()->json(['message' => 'not found'], 404);
        }

        // บังคับ: ต้อง approved ก่อนถึงจะเพิ่ม location ได้ (คุณจะเปิด/ปิดเงื่อนไขนี้ก็ได้)
        if ($m->status !== 'approved') {
            return response()->json(['message' => 'ร้านค้ายังไม่อนุมัติ'], 403);
        }

        $id = DB::table('merchant_locations')->insertGetId([
            'merchant_id' => $data['merchant_id'],
            'name' => $data['name'] ?? null,
            'address_line1' => $data['address_line1'],
            'address_line2' => $data['address_line2'] ?? null,
            'subdistrict' => $data['subdistrict'] ?? null,
            'district' => $data['district'] ?? null,
            'province' => $data['province'] ?? null,
            'postcode' => $data['postcode'] ?? null,
            'country' => 'TH',
            'lat' => $data['lat'] ?? null,
            'lng' => $data['lng'] ?? null,
            'is_pickup_point' => $data['is_pickup_point'] ?? true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['location_id' => $id]);
    }

    // Admin อนุมัติ/ปฏิเสธ + ติด role merchant ให้อัตโนมัติ
    public function adminReview(Request $request)
    {
        $data = $request->validate([
            'merchant_id' => ['required','integer'],
            'action' => ['required','in:approve,reject'],
            'note' => ['nullable','string','max:255'],
        ]);

        $admin = $request->user();
        $m = DB::table('merchants')->where('id', $data['merchant_id'])->first();
        if (!$m) return response()->json(['message' => 'not found'], 404);

        if ($data['action'] === 'approve') {
            DB::transaction(function () use ($data, $admin, $m) {
                DB::table('merchants')->where('id', $data['merchant_id'])->update([
                    'status' => 'approved',
                    'admin_note' => $data['note'] ?? null,
                    'approved_at' => now(),
                    'rejected_at' => null,
                    'updated_at' => now(),
                ]);

                // ให้ role merchant
                $merchantRoleId = DB::table('roles')->where('name', 'merchant')->value('id');
                if ($merchantRoleId) {
                    DB::table('user_roles')->updateOrInsert(
                        ['user_id' => $m->owner_user_id, 'role_id' => $merchantRoleId],
                        ['created_at' => now(), 'updated_at' => now()]
                    );
                }

                // เก็บ reviewer ใน merchant_kyc (ถ้ามี)
                DB::table('merchant_kyc')->where('merchant_id', $data['merchant_id'])->update([
                    'reviewed_by' => $admin->id,
                    'review_note' => $data['note'] ?? null,
                    'reviewed_at' => now(),
                    'updated_at' => now(),
                ]);
            });

            return response()->json(['status' => 'approved']);
        }

        DB::transaction(function () use ($data, $admin) {
            DB::table('merchants')->where('id', $data['merchant_id'])->update([
                'status' => 'rejected',
                'admin_note' => $data['note'] ?? null,
                'rejected_at' => now(),
                'approved_at' => null,
                'updated_at' => now(),
            ]);

            DB::table('merchant_kyc')->where('merchant_id', $data['merchant_id'])->update([
                'reviewed_by' => $admin->id,
                'review_note' => $data['note'] ?? null,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return response()->json(['status' => 'rejected']);
}
        public function myMerchant(Request $request)
{
    $user = $request->user();

    $m = DB::table('merchants')->where('owner_user_id', $user->id)->first();
    if (!$m) return response()->json(['exists' => false]);

    $kyc = DB::table('merchant_kyc')->where('merchant_id', $m->id)->first();
    $locations = DB::table('merchant_locations')->where('merchant_id', $m->id)->get();

    return response()->json([
        'exists' => true,
        'merchant' => $m,
        'kyc' => $kyc ? json_decode($kyc->docs_json, true) : null,
        'locations' => $locations,
    ]);
}

public function adminMerchantDetail(Request $request, int $merchantId)
{
    $m = DB::table('merchants')->where('id', $merchantId)->first();
    if (!$m) return response()->json(['message' => 'not found'], 404);

    $owner = DB::table('users')->where('id', $m->owner_user_id)->first();
    $kyc = DB::table('merchant_kyc')->where('merchant_id', $merchantId)->first();
    $locations = DB::table('merchant_locations')->where('merchant_id', $merchantId)->get();

    return response()->json([
        'merchant' => $m,
        'owner' => $owner,
        'kyc' => $kyc ? json_decode($kyc->docs_json, true) : null,
        'locations' => $locations,
    ]);
}
    }
