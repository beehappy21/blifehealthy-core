<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class MemberCodeGenerator
{
    public function generate(): string
    {
        return DB::transaction(function () {
            $row = DB::table('sequences')->where('key', 'member_code_th')->lockForUpdate()->first();

            if (!$row) {
                DB::table('sequences')->insert([
                    'key' => 'member_code_th',
                    'current_value' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $current = 1;
            } else {
                $current = (int)$row->current_value + 1;
                DB::table('sequences')->where('key', 'member_code_th')->update([
                    'current_value' => $current,
                    'updated_at' => now(),
                ]);
            }

            return 'TH' . str_pad((string)$current, 7, '0', STR_PAD_LEFT);
        });
    }
}