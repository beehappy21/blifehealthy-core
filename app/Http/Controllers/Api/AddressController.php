<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        $items = DB::table('user_addresses')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();

        return response()->json(['ok' => true, 'items' => $items]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:50'],
            'receiver_name' => ['required', 'string', 'max:150'],
            'receiver_phone' => ['required', 'string', 'max:20'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'subdistrict' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'string', 'max:10'],
            'country' => ['nullable', 'string', 'max:50'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $userId = (int)$request->user()->id;
        $isDefault = (bool)($data['is_default'] ?? false);

        $id = DB::transaction(function () use ($data, $userId, $isDefault) {
            if ($isDefault) {
                DB::table('user_addresses')->where('user_id', $userId)->update(['is_default' => false, 'updated_at' => now()]);
            }

            return DB::table('user_addresses')->insertGetId([
                'user_id' => $userId,
                'label' => $data['label'] ?? null,
                'receiver_name' => $data['receiver_name'],
                'receiver_phone' => $data['receiver_phone'],
                'address_line1' => $data['address_line1'],
                'address_line2' => $data['address_line2'] ?? null,
                'subdistrict' => $data['subdistrict'] ?? null,
                'district' => $data['district'] ?? null,
                'province' => $data['province'] ?? null,
                'postcode' => $data['postcode'] ?? null,
                'country' => $data['country'] ?? 'TH',
                'lat' => $data['lat'] ?? null,
                'lng' => $data['lng'] ?? null,
                'is_default' => $isDefault,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $item = DB::table('user_addresses')->where('id', $id)->first();

        return response()->json(['ok' => true, 'item' => $item], 201);
    }

    public function update(Request $request, $id)
    {
        $address = DB::table('user_addresses')
            ->where('id', (int)$id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$address) {
            return response()->json(['ok' => false, 'message' => 'address not found'], 404);
        }

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:50'],
            'receiver_name' => ['sometimes', 'required', 'string', 'max:150'],
            'receiver_phone' => ['sometimes', 'required', 'string', 'max:20'],
            'address_line1' => ['sometimes', 'required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'subdistrict' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'string', 'max:10'],
            'country' => ['nullable', 'string', 'max:50'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $userId = (int)$request->user()->id;

        DB::transaction(function () use ($data, $id, $userId) {
            if (array_key_exists('is_default', $data) && $data['is_default']) {
                DB::table('user_addresses')->where('user_id', $userId)->update(['is_default' => false, 'updated_at' => now()]);
            }

            $update = [];
            $fields = ['label', 'receiver_name', 'receiver_phone', 'address_line1', 'address_line2', 'subdistrict', 'district', 'province', 'postcode', 'country', 'lat', 'lng', 'is_default'];
            foreach ($fields as $field) {
                if (array_key_exists($field, $data)) {
                    $update[$field] = $data[$field];
                }
            }
            $update['updated_at'] = now();

            DB::table('user_addresses')
                ->where('id', (int)$id)
                ->where('user_id', $userId)
                ->update($update);
        });

        $item = DB::table('user_addresses')->where('id', (int)$id)->first();

        return response()->json(['ok' => true, 'item' => $item]);
    }

    public function destroy(Request $request, $id)
    {
        $deleted = DB::table('user_addresses')
            ->where('id', (int)$id)
            ->where('user_id', $request->user()->id)
            ->delete();

        if (!$deleted) {
            return response()->json(['ok' => false, 'message' => 'address not found'], 404);
        }

        return response()->json(['ok' => true]);
    }
}
