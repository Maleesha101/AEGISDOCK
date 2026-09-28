<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Terminal;
use App\Models\Container;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // Roles should already be created in migrations
        $operatorRole = \App\Models\Role::where('name', 'operator')->first();
        $supervisorRole = \App\Models\Role::where('name', 'supervisor')->first();
        $securityRole = \App\Models\Role::where('name', 'security_admin')->first();

        // Create users
        $users = [
            [
                'name' => 'Terminal Operator',
                'email' => 'operator@aegisdock.local',
                'password' => bcrypt('Operator123!'),
                'role' => $operatorRole,
            ],
            [
                'name' => 'Operations Supervisor',
                'email' => 'supervisor@aegisdock.local',
                'password' => bcrypt('Supervisor123!'),
                'role' => $supervisorRole,
            ],
            [
                'name' => 'Security Administrator',
                'email' => 'security@aegisdock.local',
                'password' => bcrypt('Security123!'),
                'role' => $securityRole,
            ],
        ];

        foreach ($users as $userData) {
            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => $userData['password'],
            ]);

            $user->assignRole($userData['role']);
        }

        // Create terminals
        $terminals = [
            [
                'code' => 'T-01',
                'name' => 'North Harbor Terminal',
                'location' => 'Berth 1-5',
                'status' => 'OPERATIONAL',
            ],
            [
                'code' => 'T-04',
                'name' => 'Central Docking Facility',
                'location' => 'Berth 6-10',
                'status' => 'OPERATIONAL',
            ],
            [
                'code' => 'T-07',
                'name' => 'South Container Terminal',
                'location' => 'Berth 11-15',
                'status' => 'OPERATIONAL',
            ],
            [
                'code' => 'T-12',
                'name' => 'East Terminal Extension',
                'location' => 'Berth 16-20',
                'status' => 'MAINTENANCE',
            ],
        ];

        foreach ($terminals as $terminalData) {
            Terminal::create($terminalData);
        }

        // Create containers - focusing on the key scenario T-07/CONT-88421
        $containers = [
            [
                'container_number' => 'CONT-88421',
                'terminal_id' => Terminal::where('code', 'T-07')->first()->id,
                'owner' => 'Global Shipping Lines',
                'cargo_type' => 'Electronics',
                'status' => 'READY',
            ],
            [
                'container_number' => 'CONT-91273',
                'terminal_id' => Terminal::where('code', 'T-07')->first()->id,
                'owner' => 'Pacific Freight Corp',
                'cargo_type' => 'Automotive Parts',
                'status' => 'READY',
            ],
            [
                'container_number' => 'CONT-77102',
                'terminal_id' => Terminal::where('code', 'T-04')->first()->id,
                'owner' => 'Atlantic Trade Co',
                'cargo_type' => 'Textiles',
                'status' => 'HELD',
            ],
            [
                'container_number' => 'CONT-44192',
                'terminal_id' => Terminal::where('code', 'T-01')->first()->id,
                'owner' => 'Mediterranean Shipping',
                'cargo_type' => 'Machinery',
                'status' => 'RELEASED',
            ],
        ];

        foreach ($containers as $containerData) {
            Container::create($containerData);
        }
    }
}