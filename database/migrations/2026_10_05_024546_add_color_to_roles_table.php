<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('color', 7)->after('display_name')->nullable()->unique();
        });

        DB::table('roles')->orderBy('id')->chunkById(100, function ($roles): void {
            foreach ($roles as $role) {
                do {
                    $color = sprintf('#%06x', random_int(0, 0xFFFFFF));
                } while (DB::table('roles')->where('color', $color)->exists());

                DB::table('roles')->where('id', $role->id)->update(['color' => $color]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['color']);
            $table->dropColumn('color');
        });
    }
};
