<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Validator;
use App\Repositories\EmployeeRepository;

class EmployeeService
{
    private EmployeeRepository $employees;

    public function __construct(EmployeeRepository $employees)
    {
        $this->employees = $employees;
    }

    public function all(): array
    {
        return $this->employees->all();
    }

    public function create(array $data): array
    {
        $validator = new Validator();

        $validator
            ->required(
                'employee_number',
                $data['employee_number'] ?? '',
                'Employee number is required.'
            )
            ->required(
                'first_name',
                $data['first_name'] ?? '',
                'First name is required.'
            )
            ->required(
                'last_name',
                $data['last_name'] ?? '',
                'Last name is required.'
            )
            ->required(
                'pin',
                $data['pin'] ?? '',
                'PIN is required.'
            )
            ->digits(
                'pin',
                $data['pin'] ?? '',
                4,
                'PIN must be exactly 4 digits.'
            )
            ->matches(
                'confirm_pin',
                $data['pin'] ?? '',
                $data['confirm_pin'] ?? '',
                'PINs do not match.'
            );

        if (
            $this->employees->employeeNumberExists(
                $data['employee_number'] ?? ''
            )
        ) {
            return [
                'success' => false,
                'errors' => [
                    'employee_number' =>
                        'Employee number already exists.'
                ]
            ];
        }

        if ($validator->fails()) {
            return [
                'success' => false,
                'errors' => $validator->errors()
            ];
        }

        $employeeId = $this->employees->create([
            'employee_number' => trim($data['employee_number']),
            'first_name'      => trim($data['first_name']),
            'last_name'       => trim($data['last_name']),
            'pin_hash'        => password_hash(
                $data['pin'],
                PASSWORD_DEFAULT
            ),
            'department'      => trim($data['department'] ?? ''),
            'active'          => isset($data['active']) ? 1 : 0,
            'notes'           => trim($data['notes'] ?? '')
        ]);

        return [
            'success' => true,
            'employee_id' => $employeeId
        ];
    }
    public function update(
        int $id,
        array $data
    ): array
    {
        $validator = new Validator();

        $validator
            ->required(
                'employee_number',
                $data['employee_number'] ?? '',
                'Employee number is required.'
            )
            ->required(
                'first_name',
                $data['first_name'] ?? '',
                'First name is required.'
            )
            ->required(
                'last_name',
                $data['last_name'] ?? '',
                'Last name is required.'
            );


        if ($validator->fails()) {

            return [
                'success' => false,
                'errors' => $validator->errors()
            ];

        }


        $updated = $this->employees->updateDetails(
            $id,
            [
                'employee_number' =>
                    trim($data['employee_number']),

                'first_name' =>
                    trim($data['first_name']),

                'last_name' =>
                    trim($data['last_name']),

                'department' =>
                    trim($data['department'] ?? ''),

                'active' =>
                    isset($data['active']) ? 1 : 0,

                'notes' =>
                    trim($data['notes'] ?? '')
            ]
        );


        return [
            'success' => $updated
        ];
    }

    public function find(int $id): ?array
    {
        return $this->employees->find($id);
    }

    public function updatePin(
        int $id,
        string $pin,
        string $confirmation
    ): array
    {
        if (
            !preg_match('/^[0-9]{4}$/', $pin)
        ) {

            return [
                'success' => false,
                'errors' => [
                    'PIN must be exactly 4 digits.'
                ]
            ];

        }


        if ($pin !== $confirmation) {

            return [
                'success' => false,
                'errors' => [
                    'PIN confirmation does not match.'
                ]
            ];

        }


        $hash = password_hash(
            $pin,
            PASSWORD_DEFAULT
        );


        $updated = $this->employees->updatePin(
            $id,
            $hash
        );


        return [
            'success' => $updated
        ];
    }

    public function findByEmployeeNumber(
        string $employeeNumber
    ): ?array
    {
        return $this->employees->findByEmployeeNumber(
            $employeeNumber
        );
    }


    public function verifyPin(
        array $employee,
        string $pin
    ): bool
    {
        if (
            empty($employee['pin_hash'])
        ) {
            return false;
        }


        return password_verify(
            $pin,
            $employee['pin_hash']
        );
    }

}
