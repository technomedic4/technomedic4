<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    public function test_logged_in_user_sees_name_from_patient_profile(): void
    {
        Schema::create('patient_profiles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('full_name');
        });

        DB::table('patient_profiles')->insert([
            'user_id' => 1,
            'full_name' => 'Rina',
        ]);

        $user = new User;
        $user->id = 1;

        $this->actingAs($user)
            ->get('/home-page')
            ->assertOk()
            ->assertSeeText('Hello Rina..');
    }

    public function test_guest_sees_generic_greeting(): void
    {
        $this->get('/home-page')
            ->assertOk()
            ->assertSeeText('Hello User..');
    }
}
