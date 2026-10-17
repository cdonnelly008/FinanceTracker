<?php

use PHPUnit\Framework\TestCase;

// unit tests for the shared helpers in app/functions.php
final class FunctionsTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/ft-tests-' . bin2hex(random_bytes(4));
        mkdir($this->tempDir);
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tempDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->tempDir);
        $_SESSION = [];
    }

    // ---- ids ----

    public function testNextIdStartsAtOneForEmptyList(): void
    {
        $this->assertSame(1, getNextId([], 'userId'));
    }

    public function testNextIdIsOneMoreThanTheHighestId(): void
    {
        $items = [['userId' => 3], ['userId' => 7], ['userId' => 5]];
        $this->assertSame(8, getNextId($items, 'userId'));
    }

    // ---- pagination ----

    public function testPaginateReturnsTheRequestedPage(): void
    {
        $result = paginate(range(1, 45), 2, 20);

        $this->assertSame(range(21, 40), $result['items']);
        $this->assertSame(3, $result['totalPages']);
        $this->assertSame(21, $result['from']);
        $this->assertSame(40, $result['to']);
    }

    public function testPaginateClampsOutOfRangePages(): void
    {
        $this->assertSame(3, paginate(range(1, 45), 99, 20)['page']);
        $this->assertSame(1, paginate(range(1, 45), -5, 20)['page']);
    }

    public function testPaginateHandlesAnEmptyList(): void
    {
        $result = paginate([], 1, 20);

        $this->assertSame([], $result['items']);
        $this->assertSame(1, $result['totalPages']);
        $this->assertSame(0, $result['from']);
    }

    // ---- output and input cleaning ----

    public function testSanitiseOutputEscapesHtml(): void
    {
        $this->assertSame(
            '&lt;script&gt;alert(&#039;x&#039;)&lt;/script&gt;',
            sanitiseOutput("<script>alert('x')</script>")
        );
    }

    public function testNormaliseEmailTrimsAndLowercases(): void
    {
        $this->assertSame('conor@example.com', normaliseEmail('  Conor@Example.COM '));
    }

    public function testFindUserByEmailIgnoresCase(): void
    {
        $users = [
            ['userId' => 1, 'email' => 'a@example.com'],
            ['userId' => 2, 'email' => 'B@Example.com'],
        ];

        $this->assertSame(2, findUserByEmail($users, 'b@example.com')['userId']);
        $this->assertNull(findUserByEmail($users, 'missing@example.com'));
    }

    // ---- categories ----

    public function testUsersOnlySeeSharedAndTheirOwnCategories(): void
    {
        $categories = [
            ['categoryId' => 1, 'name' => 'Food', 'userId' => null],
            ['categoryId' => 2, 'name' => 'Mine', 'userId' => 1],
            ['categoryId' => 3, 'name' => 'Someone else', 'userId' => 2],
            ['categoryId' => 4, 'name' => 'Old default'],
        ];

        $names = array_column(getCategoriesForUser($categories, 1), 'name');

        $this->assertSame(['Food', 'Mine', 'Old default'], $names);
    }

    // ---- json storage ----

    public function testSaveThenLoadRoundTrips(): void
    {
        $file = $this->tempDir . '/data.json';
        $data = [['id' => 1, 'name' => 'Food'], ['id' => 2, 'name' => 'Bills']];

        $this->assertTrue(saveJsonData($file, $data));
        $this->assertSame($data, loadJsonData($file));
    }

    public function testSaveLeavesNoTempFilesBehind(): void
    {
        saveJsonData($this->tempDir . '/data.json', [['id' => 1]]);
        $this->assertSame([], glob($this->tempDir . '/*.tmp'));
    }

    public function testLoadReturnsEmptyArrayForMissingOrBrokenFile(): void
    {
        $this->assertSame([], loadJsonData($this->tempDir . '/nope.json'));

        file_put_contents($this->tempDir . '/broken.json', '{not json');
        $this->assertSame([], loadJsonData($this->tempDir . '/broken.json'));
    }

    // ---- csv export ----

    public function testCsvCellsThatLookLikeFormulasAreNeutralised(): void
    {
        $this->assertSame("'=HYPERLINK(\"http://evil\")", sanitiseCsvCell('=HYPERLINK("http://evil")'));
        $this->assertSame("'+cmd", sanitiseCsvCell('+cmd'));
        $this->assertSame("'@SUM(A1)", sanitiseCsvCell('@SUM(A1)'));
        $this->assertSame("'-2+3", sanitiseCsvCell('-2+3'));
    }

    public function testCsvLeavesNormalValuesAndNumbersAlone(): void
    {
        $this->assertSame('Tesco shop', sanitiseCsvCell('Tesco shop'));
        $this->assertSame('-12.50', sanitiseCsvCell('-12.50'));
        $this->assertSame('42', sanitiseCsvCell(42));
        $this->assertSame('', sanitiseCsvCell(''));
    }

    // ---- remember me ----

    public function testRememberMeCookieParsesSelectorAndValidator(): void
    {
        $token = generateRememberMeToken();
        $parsed = parseRememberMeCookie($token['selector'] . ':' . $token['validator']);

        $this->assertSame($token, $parsed);
    }

    public function testRememberMeCookieRejectsMalformedValues(): void
    {
        $this->assertNull(parseRememberMeCookie('no-colon-here'));
        $this->assertNull(parseRememberMeCookie('abc:not hex!'));
        $this->assertNull(parseRememberMeCookie(''));
    }

    public function testRememberMeTokensAreUnique(): void
    {
        $this->assertNotSame(generateRememberMeToken(), generateRememberMeToken());
    }

    // ---- csrf ----

    public function testCsrfTokenIsStableWithinASession(): void
    {
        $this->assertSame(getCsrfToken(), getCsrfToken());
        $this->assertSame(64, strlen(getCsrfToken()));
    }

    public function testCsrfValidationAcceptsOnlyTheSessionToken(): void
    {
        $token = getCsrfToken();

        $this->assertTrue(isValidCsrfToken($token));
        $this->assertFalse(isValidCsrfToken('wrong'));
        $this->assertFalse(isValidCsrfToken(null));
    }

    public function testCsrfValidationFailsWithNoSessionToken(): void
    {
        $this->assertFalse(isValidCsrfToken(''));
    }

    public function testCsrfFieldContainsTheToken(): void
    {
        $this->assertStringContainsString(getCsrfToken(), csrfField());
        $this->assertStringContainsString('name="csrf_token"', csrfField());
    }
}
