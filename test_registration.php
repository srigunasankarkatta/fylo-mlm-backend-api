<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

echo "=== EXISTING USERS ===\n";
foreach (User::select('id', 'name', 'referral_code')->get() as $user) {
    echo "ID: {$user->id}, Name: {$user->name}, Code: {$user->referral_code}\n";
}

echo "\n=== TESTING REGISTRATION ===\n";

// Test registration with referral code
$response = file_get_contents('http://localhost:8000/api/register', false, stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => json_encode([
            'name' => 'Test User 3',
            'email' => 'test3@example.com',
            'password' => '123456',
            'phone' => '9876543210',
            'referral_code' => 'U68D02C1EAD7F5' // Use existing referral code
        ])
    ]
]));

echo "Registration Response:\n";
echo $response . "\n";

// Check if user was created
echo "\n=== CHECKING NEW USER ===\n";
$newUser = User::where('email', 'test3@example.com')->first();
if ($newUser) {
    echo "New user created: ID {$newUser->id}, Referred by: {$newUser->referred_by}\n";
} else {
    echo "No new user found\n";
}
