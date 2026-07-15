<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Validator;
use App\Repositories\EmployeeRepository;

class EmployeeService
{
    private EmployeeRepository $employees;


    public function __construct(
        EmployeeRepository $employees
    )
    {
        $this->employees =
            $employees;
    }


    /**
     * @return array<int,array<string,mixed>>
     */
    public function all(): array
    {
        $employees =
            $this->employees->all();


        foreach ($employees as &$employee) {

            $employee['punch_count'] =
                $this->employees->punchCount(
                    (int)$employee['id']
                );


            $employee['can_delete'] =
                $employee['punch_count'] === 0;
        }

        unset($employee);


        return $employees;
    }


    /**
     * @return array<string,mixed>
     */
    public function create(
        array $data
    ): array
    {
        $validator =
            new Validator();


        $employeeNumber =
            trim(
                (string)(
                    $data['employee_number']
                    ??
                    ''
                )
            );


        $firstName =
            trim(
                (string)(
                    $data['first_name']
                    ??
                    ''
                )
            );


        $lastName =
            trim(
                (string)(
                    $data['last_name']
                    ??
                    ''
                )
            );


        $pin =
            (string)(
                $data['pin']
                ??
                ''
            );


        $confirmation =
            (string)(
                $data['confirm_pin']
                ??
                ''
            );


        $validator
            ->required(
                'employee_number',
                $employeeNumber,
                'Employee number is required.'
            )
            ->required(
                'first_name',
                $firstName,
                'First name is required.'
            )
            ->required(
                'last_name',
                $lastName,
                'Last name is required.'
            )
            ->required(
                'pin',
                $pin,
                'PIN is required.'
            )
            ->digits(
                'pin',
                $pin,
                4,
                'PIN must be exactly 4 digits.'
            )
            ->matches(
                'confirm_pin',
                $pin,
                $confirmation,
                'PINs do not match.'
            );


        if (
            $employeeNumber !== ''
            &&
            $this->employees->employeeNumberExists(
                $employeeNumber
            )
        ) {
            return [
                'success' =>
                    false,

                'errors' => [
                    'employee_number' =>
                        'Employee number already exists.'
                ]
            ];
        }


        if ($validator->fails()) {

            return [
                'success' =>
                    false,

                'errors' =>
                    $validator->errors()
            ];
        }


        $employeeId =
            $this->employees->create(
                [
                    'employee_number' =>
                        $employeeNumber,

                    'first_name' =>
                        $firstName,

                    'last_name' =>
                        $lastName,

                    'pin_hash' =>
                        password_hash(
                            $pin,
                            PASSWORD_DEFAULT
                        ),

                    'department' =>
                        trim(
                            (string)(
                                $data['department']
                                ??
                                ''
                            )
                        ),

                    'active' =>
                        isset(
                            $data['active']
                        )
                            ? 1
                            : 0,

                    'notes' =>
                        trim(
                            (string)(
                                $data['notes']
                                ??
                                ''
                            )
                        )
                ]
            );


        return [
            'success' =>
                $employeeId > 0,

            'employee_id' =>
                $employeeId,

            'errors' =>
                []
        ];
    }


    /**
     * @return array<string,mixed>
     */
    public function update(
        int $id,
        array $data
    ): array
    {
        $employee =
            $this->employees->find(
                $id
            );


        if (!$employee) {

            return [
                'success' =>
                    false,

                'errors' => [
                    'employee' =>
                        'Employee not found.'
                ]
            ];
        }


        $validator =
            new Validator();


        $employeeNumber =
            trim(
                (string)(
                    $data['employee_number']
                    ??
                    ''
                )
            );


        $firstName =
            trim(
                (string)(
                    $data['first_name']
                    ??
                    ''
                )
            );


        $lastName =
            trim(
                (string)(
                    $data['last_name']
                    ??
                    ''
                )
            );


        $validator
            ->required(
                'employee_number',
                $employeeNumber,
                'Employee number is required.'
            )
            ->required(
                'first_name',
                $firstName,
                'First name is required.'
            )
            ->required(
                'last_name',
                $lastName,
                'Last name is required.'
            );


        if (
            $employeeNumber !== ''
            &&
            $this->employees->employeeNumberExists(
                $employeeNumber,
                $id
            )
        ) {
            return [
                'success' =>
                    false,

                'errors' => [
                    'employee_number' =>
                        'Employee number already exists.'
                ]
            ];
        }


        if ($validator->fails()) {

            return [
                'success' =>
                    false,

                'errors' =>
                    $validator->errors()
            ];
        }


        $updated =
            $this->employees->update(
                $id,
                [
                    'employee_number' =>
                        $employeeNumber,

                    'first_name' =>
                        $firstName,

                    'last_name' =>
                        $lastName,

                    'department' =>
                        trim(
                            (string)(
                                $data['department']
                                ??
                                ''
                            )
                        ),

                    'active' =>
                        isset(
                            $data['active']
                        )
                            ? 1
                            : 0,

                    'notes' =>
                        trim(
                            (string)(
                                $data['notes']
                                ??
                                ''
                            )
                        )
                ]
            );


        return [
            'success' =>
                $updated,

            'errors' =>
                $updated
                    ? []
                    : [
                        'employee' =>
                            'Unable to update the employee.'
                    ]
        ];
    }


    public function find(
        int $id
    ): ?array
    {
        $employee =
            $this->employees->find(
                $id
            );


        if (!$employee) {

            return null;
        }


        $employee['punch_count'] =
            $this->employees->punchCount(
                $id
            );


        $employee['can_delete'] =
            $employee['punch_count'] === 0;


        return $employee;
    }


    /**
     * @return array<string,mixed>
     */
    public function activate(
        int $id
    ): array
    {
        return $this->setActive(
            $id,
            true
        );
    }


    /**
     * @return array<string,mixed>
     */
    public function deactivate(
        int $id
    ): array
    {
        return $this->setActive(
            $id,
            false
        );
    }


    /**
     * @return array<string,mixed>
     */
    public function delete(
        int $id
    ): array
    {
        $employee =
            $this->employees->find(
                $id
            );


        if (!$employee) {

            return [
                'success' =>
                    false,

                'errors' => [
                    'employee' =>
                        'Employee not found.'
                ]
            ];
        }


        $punchCount =
            $this->employees->punchCount(
                $id
            );


        if ($punchCount > 0) {

            return [
                'success' =>
                    false,

                'errors' => [
                    'employee' =>
                        'This employee cannot be permanently deleted because punch history exists. Deactivate the employee instead.'
                ],

                'punch_count' =>
                    $punchCount
            ];
        }


        $deleted =
            $this->employees->delete(
                $id
            );


        return [
            'success' =>
                $deleted,

            'errors' =>
                $deleted
                    ? []
                    : [
                        'employee' =>
                            'Unable to delete the employee.'
                    ]
        ];
    }


    /**
     * @return array<string,mixed>
     */
    public function updatePin(
        int $id,
        string $pin,
        string $confirmation
    ): array
    {
        $employee =
            $this->employees->find(
                $id
            );


        if (!$employee) {

            return [
                'success' =>
                    false,

                'errors' => [
                    'Employee not found.'
                ]
            ];
        }


        if (
            !preg_match(
                '/^[0-9]{4}$/',
                $pin
            )
        ) {
            return [
                'success' =>
                    false,

                'errors' => [
                    'PIN must be exactly 4 digits.'
                ]
            ];
        }


        if ($pin !== $confirmation) {

            return [
                'success' =>
                    false,

                'errors' => [
                    'PIN confirmation does not match.'
                ]
            ];
        }


        $updated =
            $this->employees->updatePin(
                $id,
                password_hash(
                    $pin,
                    PASSWORD_DEFAULT
                )
            );


        return [
            'success' =>
                $updated,

            'errors' =>
                $updated
                    ? []
                    : [
                        'Unable to update the employee PIN.'
                    ]
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
            empty(
                $employee['pin_hash']
            )
        ) {
            return false;
        }


        return password_verify(
            $pin,
            $employee['pin_hash']
        );
    }


    /**
     * @return array<string,mixed>
     */
    private function setActive(
        int $id,
        bool $active
    ): array
    {
        $employee =
            $this->employees->find(
                $id
            );


        if (!$employee) {

            return [
                'success' =>
                    false,

                'errors' => [
                    'employee' =>
                        'Employee not found.'
                ]
            ];
        }


        $updated =
            $active
                ? $this->employees->activate(
                    $id
                )
                : $this->employees->deactivate(
                    $id
                );


        return [
            'success' =>
                $updated,

            'errors' =>
                $updated
                    ? []
                    : [
                        'employee' =>
                            'Unable to update the employee status.'
                    ]
        ];
    }
}
