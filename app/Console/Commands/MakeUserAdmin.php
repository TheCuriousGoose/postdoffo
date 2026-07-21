<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('user:make-admin {user : The ID or email of the user to make an admin}')]
#[Description('Make a user an admin')]
class MakeUserAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $user = User::where('id', $this->argument('user'))
            ->orWhere('email', $this->argument('user'))
            ->first();

        if ($user) {
            $user->update(['role' => 'admin']);
            $this->info('User has been made an admin.');
        } else {
            $this->error('User not found.');
        }
    }
}
