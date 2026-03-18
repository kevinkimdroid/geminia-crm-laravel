<?php

namespace App\Console\Commands;

use App\Services\UserDepartmentService;
use Illuminate\Console\Command;

class SetUserDepartmentCommand extends Command
{
    protected $signature = 'user:set-department 
                            {user : Vtiger user ID (or search by username)}
                            {department : Department name (e.g. Customer Service)}';

    protected $description = 'Manually set a user\'s department (fixes accounts where Save does not work)';

    public function handle(UserDepartmentService $dept): int
    {
        $userArg = $this->argument('user');
        $department = trim($this->argument('department'));

        if ($department === '') {
            $this->error('Department cannot be empty.');
            return self::FAILURE;
        }

        $userId = is_numeric($userArg)
            ? (int) $userArg
            : $this->resolveUserByUsername($userArg);

        if (!$userId) {
            $this->error("User not found: {$userArg}");
            return self::FAILURE;
        }

        $dept->setDepartment($userId, $department);
        $this->info("Department for user ID {$userId} set to: {$department}");

        return self::SUCCESS;
    }

    private function resolveUserByUsername(string $username): ?int
    {
        $row = \DB::connection('vtiger')
            ->table('vtiger_users')
            ->where('user_name', $username)
            ->where('status', 'Active')
            ->value('id');
        return $row ? (int) $row : null;
    }
}
