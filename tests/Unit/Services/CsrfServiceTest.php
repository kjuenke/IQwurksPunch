<?php
declare(strict_types=1);

use App\Services\CsrfService;
use PHPUnit\Framework\TestCase;

final class CsrfServiceTest extends TestCase
{
    private CsrfService $csrf;


    protected function setUp(): void
    {
        parent::setUp();


        $_SESSION = [];


        $this->csrf =
            new CsrfService();
    }


    protected function tearDown(): void
    {
        $_SESSION = [];


        parent::tearDown();
    }


    public function testTokenCreatesAValidSessionToken(): void
    {
        $token =
            $this->csrf
                ->token();


        self::assertSame(
            64,
            strlen(
                $token
            )
        );


        self::assertMatchesRegularExpression(
            '/^[a-f0-9]{64}$/',
            $token
        );


        self::assertSame(
            $token,
            $_SESSION['csrf_token']
        );
    }


    public function testTokenRemainsStableForTheSession(): void
    {
        $firstToken =
            $this->csrf
                ->token();


        $secondToken =
            $this->csrf
                ->token();


        self::assertSame(
            $firstToken,
            $secondToken
        );
    }


    public function testRotateReplacesTheExistingToken(): void
    {
        $firstToken =
            $this->csrf
                ->token();


        $secondToken =
            $this->csrf
                ->rotate();


        self::assertNotSame(
            $firstToken,
            $secondToken
        );


        self::assertSame(
            $secondToken,
            $_SESSION['csrf_token']
        );


        self::assertTrue(
            $this->csrf
                ->validate(
                    $secondToken
                )
        );


        self::assertFalse(
            $this->csrf
                ->validate(
                    $firstToken
                )
        );
    }


    public function testValidateAcceptsTheCurrentToken(): void
    {
        $token =
            $this->csrf
                ->token();


        self::assertTrue(
            $this->csrf
                ->validate(
                    $token
                )
        );
    }


    public function testValidateRejectsMissingMalformedAndIncorrectTokens(): void
    {
        $this->csrf
            ->token();


        self::assertFalse(
            $this->csrf
                ->validate(
                    null
                )
        );


        self::assertFalse(
            $this->csrf
                ->validate(
                    ''
                )
        );


        self::assertFalse(
            $this->csrf
                ->validate(
                    'not-a-valid-token'
                )
        );


        self::assertFalse(
            $this->csrf
                ->validate(
                    str_repeat(
                        '0',
                        64
                    )
                )
        );
    }


    public function testRequireValidTokenAcceptsValidFormInput(): void
    {
        $token =
            $this->csrf
                ->token();


        $this->csrf
            ->requireValidToken(
                [
                    CsrfService::FIELD_NAME =>
                        $token
                ]
            );


        self::addToAssertionCount(
            1
        );
    }


    public function testRequireValidTokenRejectsInvalidFormInput(): void
    {
        $this->csrf
            ->token();


        $this->expectException(
            RuntimeException::class
        );


        $this->expectExceptionMessage(
            'The form security token is missing or invalid.'
        );


        $this->csrf
            ->requireValidToken(
                []
            );
    }


    public function testClearRemovesTheSessionToken(): void
    {
        $token =
            $this->csrf
                ->token();


        $this->csrf
            ->clear();


        self::assertArrayNotHasKey(
            'csrf_token',
            $_SESSION
        );


        self::assertFalse(
            $this->csrf
                ->validate(
                    $token
                )
        );
    }
}
