<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use GuzzleHttp\Client as HttpClient;

class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:create {--name= : The name of the admin user}
                           {--email= : The email of the admin user}
                           {--password= : The password of the admin user}
                           {--generate-token : Generate a Passport token for the admin user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new admin user with admin role';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $name = $this->option('name');
        $email = $this->option('email');
        $password = $this->option('password');
        $generateToken = $this->option('generate-token');

        // If options are not provided, prompt for them
        if (!$name) {
            $name = $this->ask('What is the admin name?');
        }

        if (!$email) {
            $email = $this->ask('What is the admin email?');
        }

        if (!$password) {
            $password = $this->secret('What is the admin password?');
            $passwordConfirmation = $this->secret('Confirm the admin password');
            
            if ($password !== $passwordConfirmation) {
                $this->error('Passwords do not match!');
                return 1;
            }
        }

        // Validate input
        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return 1;
        }

        // Check if admin role exists
        $adminRole = Role::where('name', 'admin')->first();
        if (!$adminRole) {
            $this->error('Admin role does not exist. Please run the roles and permissions seeder first.');
            return 1;
        }

        // Create the admin user
        $this->info('Creating admin user...');
        
        try {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]);

            // Assign admin role
            $user->assignRole('admin');

           $token = $user->createToken($user->email)->accessToken;
            $this->info('Admin user created successfully!');
            $this->table(
                ['Name', 'Email', 'Role'],
                [[$user->name, $user->email, 'admin']]
            );
            
            // // Generate Passport token if requested
            // if ($generateToken || $this->confirm('Would you like to generate a Passport token for this admin user?')) {
            //     $this->generatePassportToken($user, $email, $password);
            // }
            
            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to create admin user: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Generate a Passport token for the user.
     *
     * @param  \App\Models\User  $user
     * @param  string  $email
     * @param  string  $password
     * @return void
     */
    protected function generatePassportToken(User $user, string $email, string $password)
    {
        $this->info('Generating Passport token...');

        try {
            // Check if we have a password grant client
            $client = Client::where('password_client', 1)->first();
            
            if (!$client) {
                // Create a password grant client if it doesn't exist
                $this->info('No password grant client found. Creating one...');
                $this->call('passport:client', [
                    '--password' => true,
                    '--name' => 'Admin Password Grant Client',
                    '--no-interaction' => true,
                ]);
                
                $client = Client::where('password_client', 1)->first();
                
                if (!$client) {
                    $this->error('Failed to create password grant client.');
                    return;
                }
            }
            
            // Get the client credentials
            $clientId = $client->id;
            $clientSecret = $client->secret;
            
            // Make a request to the OAuth token endpoint
            $http = new HttpClient;
            
            $response = $http->post(url('/oauth/token'), [
                'form_params' => [
                    'grant_type' => 'password',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'username' => $email,
                    'password' => $password,
                    'scope' => '',
                ],
            ]);
            
            $tokenData = json_decode((string) $response->getBody(), true);
            
            $this->info('Passport token generated successfully!');
            $this->info('Please save these tokens securely, they will not be shown again:');
            $this->line('Access Token: ' . $tokenData['access_token']);
            $this->line('Refresh Token: ' . $tokenData['refresh_token']);
            
            // Display token expiration
            $this->info('Token expires in: ' . $tokenData['expires_in'] . ' seconds');
            
            // Also show how to use the token
            $this->info('Use this token in your API requests with the following header:');
            $this->line('Authorization: Bearer ' . $tokenData['access_token']);
            
        } catch (\Exception $e) {
            $this->error('Failed to generate Passport token: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
        }
    }
}
