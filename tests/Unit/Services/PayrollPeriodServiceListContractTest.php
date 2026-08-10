<?php
declare(strict_types=1);

use App\Services\PayrollPeriodService;
use PHPUnit\Framework\TestCase;

final class PayrollPeriodServiceListContractTest extends TestCase
{
    public function testServiceExposesActiveAndRemovedListMethods(): void
    {
        $service =
            new \ReflectionClass(
                PayrollPeriodService::class
            );


        foreach (
            [
                'active',
                'removed'
            ]
            as $methodName
        ) {
            self::assertTrue(
                $service->hasMethod(
                    $methodName
                ),
                "Missing payroll-period list method: {$methodName}"
            );


            $method =
                $service->getMethod(
                    $methodName
                );


            self::assertTrue(
                $method->isPublic()
            );


            self::assertSame(
                'array',
                (string)$method->getReturnType()
            );
        }
    }


    public function testListMethodsDelegateToTheRepository(): void
    {
        $root =
            dirname(
                __DIR__,
                3
            );


        $source =
            (string)file_get_contents(
                $root
                .
                '/app/Services/PayrollPeriodService.php'
            );


        self::assertMatchesRegularExpression(
            '/public function active\(\): array.*?->active\(\);/s',
            $source
        );


        self::assertMatchesRegularExpression(
            '/public function removed\(\): array.*?->removed\(\);/s',
            $source
        );
    }
}
