<?php

namespace Database\Seeders;

use App\Models\Container;
use App\Models\Role;
use App\Models\Terminal;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'operator' => Role::where('name', 'operator')->first(),
            'supervisor' => Role::where('name', 'supervisor')->first(),
            'security_admin' => Role::where('name', 'security_admin')->first(),
        ];

        foreach ($roles as $name => $role) {
            if (!$role) {
                throw new RuntimeException("Required role [{$name}] is missing. Run the role migration first.");
            }
        }

        $users = [
            [
                'name' => 'Terminal Operator',
                'email' => 'operator@aegisdock.local',
                'password' => 'Operator123!',
                'role' => $roles['operator'],
            ],
            [
                'name' => 'Operations Supervisor',
                'email' => 'supervisor@aegisdock.local',
                'password' => 'Supervisor123!',
                'role' => $roles['supervisor'],
            ],
            [
                'name' => 'Security Administrator',
                'email' => 'security@aegisdock.local',
                'password' => 'Security123!',
                'role' => $roles['security_admin'],
            ],
        ];

        foreach ($users as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make($userData['password']),
                ]
            );
            $user->syncRoles([$userData['role']]);
        }

        $terminals = [
            ['code' => 'T-01', 'name' => 'North Harbor Terminal', 'location' => 'Berth 1-5', 'status' => 'OPERATIONAL'],
            ['code' => 'T-04', 'name' => 'Central Docking Facility', 'location' => 'Berth 6-10', 'status' => 'OPERATIONAL'],
            ['code' => 'T-07', 'name' => 'South Container Terminal', 'location' => 'Berth 11-15', 'status' => 'OPERATIONAL'],
            ['code' => 'T-12', 'name' => 'East Terminal Extension', 'location' => 'Berth 16-20', 'status' => 'MAINTENANCE'],
        ];

        foreach ($terminals as $terminalData) {
            Terminal::updateOrCreate(['code' => $terminalData['code']], $terminalData);
        }

        $terminalIds = Terminal::query()->pluck('id', 'code');

        $containers = [
            [
                'container_number' => 'CONT-88421',
                'terminal_id' => $terminalIds['T-07'],
                'owner' => 'Global Shipping Lines',
                'cargo_type' => 'Electronics',
                'status' => 'READY',
            ],
            [
                'container_number' => 'CONT-91273',
                'terminal_id' => $terminalIds['T-07'],
                'owner' => 'Pacific Freight Corp',
                'cargo_type' => 'Automotive Parts',
                'status' => 'READY',
            ],
            [
                'container_number' => 'CONT-77102',
                'terminal_id' => $terminalIds['T-04'],
                'owner' => 'Atlantic Trade Co',
                'cargo_type' => 'Textiles',
                'status' => 'HELD',
            ],
            [
                'container_number' => 'CONT-44192',
                'terminal_id' => $terminalIds['T-01'],
                'owner' => 'Mediterranean Shipping',
                'cargo_type' => 'Machinery',
                'status' => 'RELEASED',
            ],
        ];

        foreach ($containers as $containerData) {
            Container::updateOrCreate(
                ['container_number' => $containerData['container_number']],
                $containerData
            );
        }
    }
}
