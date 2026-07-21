<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\EmployeeRepository;
use App\Repositories\PunchCorrectionHistoryRepository;
use App\Repositories\PunchRepository;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

final class PunchCorrectionService
{
    private const PUNCH_TYPES = [
        'clock_in' =>
            'Clock In',

        'clock_out' =>
            'Clock Out',

        'break_out' =>
            'Break Out',

        'break_in' =>
            'Break In',

        'meal_out' =>
            'Meal Out',

        'meal_in' =>
            'Meal In'
    ];


    private PDO $db;

    private PunchRepository $punches;

    private PunchCorrectionHistoryRepository $history;

    private EmployeeRepository $employees;

    private DateTimeZone $companyTimezone;

    private DateTimeZone $utcTimezone;


    public function __construct(
        PDO $db,
        PunchRepository $punches,
        PunchCorrectionHistoryRepository $history,
        EmployeeRepository $employees,
        ?string $companyTimezone = null
    )
    {
        $this->db =
            $db;


        $this->punches =
            $punches;


        $this->history =
            $history;


        $this->employees =
            $employees;


        $timezone =
            $companyTimezone
            ??
            date_default_timezone_get();


        if (
            !in_array(
                $timezone,
                timezone_identifiers_list(),
                true
            )
        ) {
            throw new RuntimeException(
                'The company timezone is invalid.'
            );
        }


        $this->companyTimezone =
            new DateTimeZone(
                $timezone
            );


        $this->utcTimezone =
            new DateTimeZone(
                'UTC'
            );
    }


    /**
     * @return array<string,string>
     */
    public function punchTypes(): array
    {
        return self::PUNCH_TYPES;
    }


    /**
     * @return array<int,array<string,mixed>>
     */
    public function punchesForEmployee(
        int $employeeId
    ): array
    {
        $punches =
            $this->punches->employeePunches(
                $employeeId
            );


        return array_map(
            fn (
                array $punch
            ): array =>
                $this->preparePunchForDisplay(
                    $punch
                ),
            $punches
        );
    }


    /**
     * @return array<int,array<string,mixed>>
     */
    public function historyForEmployee(
        int $employeeId
    ): array
    {
        $history =
            $this->history->forEmployee(
                $employeeId
            );


        return array_map(
            fn (
                array $entry
            ): array =>
                $this->prepareHistoryForDisplay(
                    $entry
                ),
            $history
        );
    }


    public function find(
        int $punchId
    ): ?array
    {
        $punch =
            $this->punches->find(
                $punchId
            );


        if (!$punch) {

            return null;
        }


        return $this->preparePunchForDisplay(
            $punch
        );
    }


    /**
     * @return array<string,mixed>
     */
    public function create(
        int $employeeId,
        array $data,
        int $userId
    ): array
    {
        $employee =
            $this->employees->find(
                $employeeId
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


        if ($userId <= 0) {

            return [
                'success' =>
                    false,

                'errors' => [
                    'authorization' =>
                        'A supervisor login is required.'
                ]
            ];
        }


        $validated =
            $this->validatePunchInput(
                $data
            );


        if (!$validated['success']) {

            return $validated;
        }


        try {

            $this->db->beginTransaction();


            $punchId =
                $this->punches->createManual(
                    $employeeId,
                    $validated['data']['punch_time_utc'],
                    $validated['data']['punch_type'],
                    $validated['data']['notes'],
                    $userId,
                    $validated['data']['reason']
                );


            if ($punchId <= 0) {

                throw new RuntimeException(
                    'The manual punch could not be created.'
                );
            }


            $sequenceErrors =
                $this->validateSequence(
                    $this->punches->employeePunches(
                        $employeeId
                    )
                );


            if ($sequenceErrors !== []) {

                $this->db->rollBack();


                return [
                    'success' =>
                        false,

                    'errors' => [
                        'sequence' =>
                            $sequenceErrors[0]
                    ]
                ];
            }


            $historyId =
                $this->history->create(
                    $punchId,
                    $employeeId,
                    (string)$employee['employee_number'],
                    $this->employeeName(
                        $employee
                    ),
                    'created',
                    null,
                    null,
                    $validated['data']['punch_time_utc'],
                    $validated['data']['punch_type'],
                    $validated['data']['reason'],
                    $userId
                );


            if ($historyId <= 0) {

                throw new RuntimeException(
                    'Punch-correction history could not be recorded.'
                );
            }


            $this->db->commit();


            return [
                'success' =>
                    true,

                'punch_id' =>
                    $punchId,

                'errors' =>
                    []
            ];

        } catch (Throwable $exception) {

            if (
                $this->db->inTransaction()
            ) {
                $this->db->rollBack();
            }


            return [
                'success' =>
                    false,

                'errors' => [
                    'punch' =>
                        'Unable to add the punch correction: '
                        .
                        $exception->getMessage()
                ]
            ];
        }
    }


    /**
     * @return array<string,mixed>
     */
    public function update(
        int $punchId,
        array $data,
        int $userId
    ): array
    {
        $existing =
            $this->punches->find(
                $punchId
            );


        if (!$existing) {

            return [
                'success' =>
                    false,

                'errors' => [
                    'punch' =>
                        'Punch record not found.'
                ]
            ];
        }


        if ($userId <= 0) {

            return [
                'success' =>
                    false,

                'errors' => [
                    'authorization' =>
                        'A supervisor login is required.'
                ]
            ];
        }


        $validated =
            $this->validatePunchInput(
                $data
            );


        if (!$validated['success']) {

            return $validated;
        }


        $employeeId =
            (int)$existing['employee_id'];


        try {

            $this->db->beginTransaction();


            $updated =
                $this->punches->updateManual(
                    $punchId,
                    $validated['data']['punch_time_utc'],
                    $validated['data']['punch_type'],
                    $validated['data']['notes'],
                    $userId,
                    $validated['data']['reason']
                );


            if (!$updated) {

                throw new RuntimeException(
                    'The punch record could not be updated.'
                );
            }


            $sequenceErrors =
                $this->validateSequence(
                    $this->punches->employeePunches(
                        $employeeId
                    )
                );


            if ($sequenceErrors !== []) {

                $this->db->rollBack();


                return [
                    'success' =>
                        false,

                    'errors' => [
                        'sequence' =>
                            $sequenceErrors[0]
                    ]
                ];
            }


            $historyId =
                $this->history->create(
                    $punchId,
                    $employeeId,
                    (string)$existing['employee_number'],
                    trim(
                        (string)$existing['first_name']
                        .
                        ' '
                        .
                        (string)$existing['last_name']
                    ),
                    'updated',
                    (string)$existing['punch_time'],
                    (string)$existing['punch_type'],
                    $validated['data']['punch_time_utc'],
                    $validated['data']['punch_type'],
                    $validated['data']['reason'],
                    $userId
                );


            if ($historyId <= 0) {

                throw new RuntimeException(
                    'Punch-correction history could not be recorded.'
                );
            }


            $this->db->commit();


            return [
                'success' =>
                    true,

                'punch_id' =>
                    $punchId,

                'employee_id' =>
                    $employeeId,

                'errors' =>
                    []
            ];

        } catch (Throwable $exception) {

            if (
                $this->db->inTransaction()
            ) {
                $this->db->rollBack();
            }


            return [
                'success' =>
                    false,

                'errors' => [
                    'punch' =>
                        'Unable to update the punch: '
                        .
                        $exception->getMessage()
                ]
            ];
        }
    }


    /**
     * @return array<string,mixed>
     */
    public function delete(
        int $punchId,
        string $reason,
        int $userId
    ): array
    {
        $existing =
            $this->punches->find(
                $punchId
            );


        if (!$existing) {

            return [
                'success' =>
                    false,

                'errors' => [
                    'punch' =>
                        'Punch record not found.'
                ]
            ];
        }


        if ($userId <= 0) {

            return [
                'success' =>
                    false,

                'errors' => [
                    'authorization' =>
                        'A supervisor login is required.'
                ]
            ];
        }


        $reasonError =
            $this->validateReason(
                $reason
            );


        if ($reasonError !== null) {

            return [
                'success' =>
                    false,

                'errors' => [
                    'reason' =>
                        $reasonError
                ]
            ];
        }


        $reason =
            trim(
                $reason
            );


        $employeeId =
            (int)$existing['employee_id'];


        try {

            $this->db->beginTransaction();


            $deleted =
                $this->punches->delete(
                    $punchId
                );


            if (!$deleted) {

                throw new RuntimeException(
                    'The punch record could not be deleted.'
                );
            }


            $sequenceErrors =
                $this->validateSequence(
                    $this->punches->employeePunches(
                        $employeeId
                    )
                );


            if ($sequenceErrors !== []) {

                $this->db->rollBack();


                return [
                    'success' =>
                        false,

                    'errors' => [
                        'sequence' =>
                            $sequenceErrors[0]
                    ]
                ];
            }


            $historyId =
                $this->history->create(
                    $punchId,
                    $employeeId,
                    (string)$existing['employee_number'],
                    trim(
                        (string)$existing['first_name']
                        .
                        ' '
                        .
                        (string)$existing['last_name']
                    ),
                    'deleted',
                    (string)$existing['punch_time'],
                    (string)$existing['punch_type'],
                    null,
                    null,
                    $reason,
                    $userId
                );


            if ($historyId <= 0) {

                throw new RuntimeException(
                    'Punch-correction history could not be recorded.'
                );
            }


            $this->db->commit();


            return [
                'success' =>
                    true,

                'employee_id' =>
                    $employeeId,

                'errors' =>
                    []
            ];

        } catch (Throwable $exception) {

            if (
                $this->db->inTransaction()
            ) {
                $this->db->rollBack();
            }


            return [
                'success' =>
                    false,

                'errors' => [
                    'punch' =>
                        'Unable to delete the punch: '
                        .
                        $exception->getMessage()
                ]
            ];
        }
    }


    /**
     * @return array<string,mixed>
     */
    private function validatePunchInput(
        array $data
    ): array
    {
        $errors = [];


        $punchType =
            trim(
                (string)(
                    $data['punch_type']
                    ??
                    ''
                )
            );


        $localTime =
            trim(
                (string)(
                    $data['punch_time']
                    ??
                    ''
                )
            );


        $notes =
            trim(
                (string)(
                    $data['notes']
                    ??
                    ''
                )
            );


        $reason =
            trim(
                (string)(
                    $data['reason']
                    ??
                    ''
                )
            );


        if (
            !array_key_exists(
                $punchType,
                self::PUNCH_TYPES
            )
        ) {
            $errors['punch_type'] =
                'Select a valid punch type.';
        }


        $punchTimeUtc =
            $this->localInputToUtc(
                $localTime
            );


        if ($punchTimeUtc === null) {

            $errors['punch_time'] =
                'Enter a valid punch date and time.';

        } else {

            $futureLimit =
                new DateTimeImmutable(
                    '+5 minutes',
                    $this->utcTimezone
                );


            $submittedUtc =
                new DateTimeImmutable(
                    $punchTimeUtc,
                    $this->utcTimezone
                );


            if (
                $submittedUtc
                >
                $futureLimit
            ) {
                $errors['punch_time'] =
                    'Punch time cannot be in the future.';
            }
        }


        if (
            mb_strlen(
                $notes
            )
            >
            500
        ) {
            $errors['notes'] =
                'Notes cannot exceed 500 characters.';
        }


        $reasonError =
            $this->validateReason(
                $reason
            );


        if ($reasonError !== null) {

            $errors['reason'] =
                $reasonError;
        }


        if ($errors !== []) {

            return [
                'success' =>
                    false,

                'errors' =>
                    $errors
            ];
        }


        return [
            'success' =>
                true,

            'errors' =>
                [],

            'data' => [
                'punch_type' =>
                    $punchType,

                'punch_time_utc' =>
                    $punchTimeUtc,

                'notes' =>
                    $notes,

                'reason' =>
                    $reason
            ]
        ];
    }


    private function validateReason(
        string $reason
    ): ?string
    {
        $reason =
            trim(
                $reason
            );


        if ($reason === '') {

            return
                'A correction reason is required.';
        }


        if (
            mb_strlen(
                $reason
            )
            <
            5
        ) {
            return
                'Correction reason must contain at least 5 characters.';
        }


        if (
            mb_strlen(
                $reason
            )
            >
            500
        ) {
            return
                'Correction reason cannot exceed 500 characters.';
        }


        return null;
    }


    private function localInputToUtc(
        string $localTime
    ): ?string
    {
        if (
            preg_match(
                '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/',
                $localTime
            )
            !==
            1
        ) {
            return null;
        }


        $date =
            DateTimeImmutable::createFromFormat(
                '!Y-m-d\TH:i',
                $localTime,
                $this->companyTimezone
            );


        $errors =
            DateTimeImmutable::getLastErrors();


        if (
            $date === false
            ||
            (
                is_array(
                    $errors
                )
                &&
                (
                    $errors['warning_count'] > 0
                    ||
                    $errors['error_count'] > 0
                )
            )
            ||
            $date->format(
                'Y-m-d\TH:i'
            )
            !==
            $localTime
        ) {
            return null;
        }


        return $date
            ->setTimezone(
                $this->utcTimezone
            )
            ->format(
                'Y-m-d H:i:s'
            );
    }


    /**
     * @param array<int,array<string,mixed>> $punches
     *
     * @return array<int,string>
     */
    private function validateSequence(
        array $punches
    ): array
    {
        $errors = [];

        $state =
            'off';

        $previousTimestamp =
            null;


        foreach ($punches as $punch) {

            $punchType =
                (string)(
                    $punch['punch_type']
                    ??
                    ''
                );


            $punchTime =
                (string)(
                    $punch['punch_time']
                    ??
                    ''
                );


            if (
                !array_key_exists(
                    $punchType,
                    self::PUNCH_TYPES
                )
            ) {
                return [
                    'The punch sequence contains an unsupported punch type.'
                ];
            }


            if (
                $previousTimestamp !== null
                &&
                $previousTimestamp
                ===
                $punchTime
            ) {
                return [
                    'Two punches cannot use the same date and time.'
                ];
            }


            $previousTimestamp =
                $punchTime;


            switch ($punchType) {

                case 'clock_in':

                    if ($state !== 'off') {

                        $errors[] =
                            'Clock In must follow a Clock Out or be the employee’s first punch.';
                    }


                    $state =
                        'working';

                    break;


                case 'clock_out':

                    if ($state === 'break') {

                        $errors[] =
                            'Break In is required before Clock Out.';
                    }


                    if ($state === 'meal') {

                        $errors[] =
                            'Meal In is required before Clock Out.';
                    }


                    if ($state !== 'working') {

                        $errors[] =
                            'Clock Out must follow Clock In.';
                    }


                    $state =
                        'off';

                    break;


                case 'break_out':

                    if ($state !== 'working') {

                        $errors[] =
                            'Break Out is only valid while the employee is clocked in.';
                    }


                    $state =
                        'break';

                    break;


                case 'break_in':

                    if ($state !== 'break') {

                        $errors[] =
                            'Break In must follow Break Out.';
                    }


                    $state =
                        'working';

                    break;


                case 'meal_out':

                    if ($state !== 'working') {

                        $errors[] =
                            'Meal Out is only valid while the employee is clocked in.';
                    }


                    $state =
                        'meal';

                    break;


                case 'meal_in':

                    if ($state !== 'meal') {

                        $errors[] =
                            'Meal In must follow Meal Out.';
                    }


                    $state =
                        'working';

                    break;
            }


            if ($errors !== []) {

                return [
                    $errors[0]
                    .
                    ' Problem near '
                    .
                    $this->utcToLocalDisplay(
                        $punchTime
                    )
                    .
                    '.'
                ];
            }
        }


        return [];
    }


    /**
     * @param array<string,mixed> $punch
     *
     * @return array<string,mixed>
     */
    private function preparePunchForDisplay(
        array $punch
    ): array
    {
        $punchType =
            (string)(
                $punch['punch_type']
                ??
                ''
            );


        $punch['punch_type_label'] =
            self::PUNCH_TYPES[$punchType]
            ??
            $punchType;


        $punch['punch_time_local'] =
            $this->utcToLocalDisplay(
                (string)$punch['punch_time']
            );


        $punch['punch_time_input'] =
            $this->utcToLocalInput(
                (string)$punch['punch_time']
            );


        $punch['corrected_at_local'] =
            empty(
                $punch['corrected_at']
            )
                ? null
                : $this->utcToLocalDisplay(
                    (string)$punch['corrected_at']
                );


        return $punch;
    }


    /**
     * @param array<string,mixed> $entry
     *
     * @return array<string,mixed>
     */
    private function prepareHistoryForDisplay(
        array $entry
    ): array
    {
        $entry['old_punch_time_local'] =
            empty(
                $entry['old_punch_time']
            )
                ? null
                : $this->utcToLocalDisplay(
                    (string)$entry['old_punch_time']
                );


        $entry['new_punch_time_local'] =
            empty(
                $entry['new_punch_time']
            )
                ? null
                : $this->utcToLocalDisplay(
                    (string)$entry['new_punch_time']
                );


        $entry['created_at_local'] =
            empty(
                $entry['created_at']
            )
                ? null
                : $this->utcToLocalDisplay(
                    (string)$entry['created_at']
                );


        $entry['old_punch_type_label'] =
            self::PUNCH_TYPES[
                (string)(
                    $entry['old_punch_type']
                    ??
                    ''
                )
            ]
            ??
            (
                $entry['old_punch_type']
                ??
                null
            );


        $entry['new_punch_type_label'] =
            self::PUNCH_TYPES[
                (string)(
                    $entry['new_punch_type']
                    ??
                    ''
                )
            ]
            ??
            (
                $entry['new_punch_type']
                ??
                null
            );


        return $entry;
    }


    private function utcToLocalDisplay(
        string $utcTime
    ): string
    {
        return (
            new DateTimeImmutable(
                $utcTime,
                $this->utcTimezone
            )
        )
            ->setTimezone(
                $this->companyTimezone
            )
            ->format(
                'Y-m-d g:i A T'
            );
    }


    private function utcToLocalInput(
        string $utcTime
    ): string
    {
        return (
            new DateTimeImmutable(
                $utcTime,
                $this->utcTimezone
            )
        )
            ->setTimezone(
                $this->companyTimezone
            )
            ->format(
                'Y-m-d\TH:i'
            );
    }


    /**
     * @param array<string,mixed> $employee
     */
    private function employeeName(
        array $employee
    ): string
    {
        return trim(
            (string)(
                $employee['first_name']
                ??
                ''
            )
            .
            ' '
            .
            (string)(
                $employee['last_name']
                ??
                ''
            )
        );
    }
}
