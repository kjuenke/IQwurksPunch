<?php
declare(strict_types=1);

namespace App\Exports;

use RuntimeException;

abstract class CsvExporter
{
    /**
     * @param array<int,array<int|string,float|int|string|null>> $rows
     */
    protected function createCsv(
        array $rows
    ): string
    {
        $stream =
            fopen(
                'php://temp',
                'w+'
            );


        if ($stream === false) {

            throw new RuntimeException(
                'Unable to create the CSV export.'
            );
        }


        /*
         * UTF-8 byte-order mark improves compatibility with Excel.
         */
        fwrite(
            $stream,
            "\xEF\xBB\xBF"
        );


        foreach ($rows as $row) {

            $written =
                fputcsv(
                    $stream,
                    $row
                );


            if ($written === false) {

                fclose(
                    $stream
                );


                throw new RuntimeException(
                    'Unable to write the CSV export.'
                );
            }
        }


        rewind(
            $stream
        );


        $csv =
            stream_get_contents(
                $stream
            );


        fclose(
            $stream
        );


        if ($csv === false) {

            throw new RuntimeException(
                'Unable to read the completed CSV export.'
            );
        }


        return $csv;
    }


    protected function status(
        mixed $complete
    ): string
    {
        return $complete
            ? 'Complete'
            : 'Needs Review';
    }


    /**
     * @param array<int,mixed> $errors
     */
    protected function errors(
        array $errors
    ): string
    {
        return implode(
            ' | ',
            array_map(
                static fn (
                    mixed $error
                ): string =>
                    (string)$error,
                $errors
            )
        );
    }
}
