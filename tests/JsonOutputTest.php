<?php
namespace Psalm\Tests;

use Psalm\Checker\FileChecker;
use Psalm\Checker\ProjectChecker;
use Psalm\IssueBuffer;

class JsonOutputTest extends TestCase
{
    /**
     * @return void
     */
    public function setUp()
    {
        // `TestCase::setUp()` creates its own ProjectChecker and Config instance, but we don't want to do that in this
        // case, so don't run a `parent::setUp()` call here.
        FileChecker::clearCache();
        $this->file_provider = new Provider\FakeFileProvider();

        $this->project_checker = new \Psalm\Checker\ProjectChecker(
            $this->file_provider,
            new Provider\FakeCacheProvider(),
            false,
            true,
            ProjectChecker::TYPE_JSON
        );

        $config = new TestConfig();
        $config->throw_exception = false;
        $config->stop_on_first_error = false;
        $this->project_checker->setConfig($config);
    }

    /**
     * @dataProvider providerTestJsonOutputErrors
     *
     * @param string $code
     * @param string $message
     * @param int $line_number
     * @param string $error
     *
     * @return void
     */
    public function testJsonOutputErrors($code, $message, $line_number, $error)
    {
        $this->addFile('somefile.php', $code);

        $file_checker = new FileChecker('somefile.php', $this->project_checker);
        $file_checker->visitAndAnalyzeMethods();
        $issue_data = IssueBuffer::getIssuesData()[0];

        $this->assertSame('somefile.php', $issue_data['file_path']);
        $this->assertSame('error', $issue_data['severity']);
        $this->assertSame($message, $issue_data['message']);
        $this->assertSame($line_number, $issue_data['line_number']);
        $this->assertSame(
            $error,
            substr($code, $issue_data['from'], $issue_data['to'] - $issue_data['from'])
        );
    }

    /**
     * @return void
     */
    public function testJsonOutputForGetPsalmDotOrg()
    {
        $file_contents = '<?php
function psalmCanVerify(int $your_code) : ?string {
  return $as_you . "type";
}

// and it supports PHP 5.4 - 7.1
echo CHANGE_ME;

if (rand(0, 100) > 10) {
  $a = 5;
} else {
  //$a = 2;
}

echo $a;';

        $this->addFile(
            'somefile.php',
            $file_contents
        );

        $file_checker = new FileChecker('somefile.php', $this->project_checker);
        $file_checker->visitAndAnalyzeMethods();
        $issue_data = IssueBuffer::getIssuesData();
        $this->assertSame(
            [
                [
                    'severity' => 'error',
                    'line_number' => 7,
                    'type' => 'UndefinedConstant',
                    'message' => 'Const CHANGE_ME is not defined',
                    'file_name' => 'somefile.php',
                    'file_path' => 'somefile.php',
                    'snippet' => 'echo CHANGE_ME;',
                    'from' => 126,
                    'to' => 135,
                    'snippet_from' => 121,
                    'snippet_to' => 136,
                    'column' => 6,
                ],
                [
                    'severity' => 'error',
                    'line_number' => 15,
                    'type' => 'PossiblyUndefinedVariable',
                    'message' => 'Possibly undefined variable $a, first seen on line 10',
                    'file_name' => 'somefile.php',
                    'file_path' => 'somefile.php',
                    'snippet' => 'echo $a',
                    'from' => 202,
                    'to' => 204,
                    'snippet_from' => 197,
                    'snippet_to' => 204,
                    'column' => 6,
                ],
                [
                    'severity' => 'error',
                    'line_number' => 3,
                    'type' => 'UndefinedVariable',
                    'message' => 'Cannot find referenced variable $as_you',
                    'file_name' => 'somefile.php',
                    'file_path' => 'somefile.php',
                    'snippet' => '  return $as_you . "type";',
                    'from' => 67,
                    'to' => 74,
                    'snippet_from' => 58,
                    'snippet_to' => 84,
                    'column' => 10,
                ],
                [
                    'severity' => 'error',
                    'line_number' => 2,
                    'type' => 'MixedInferredReturnType',
                    'message' => 'Could not verify return type \'string|null\' for psalmCanVerify',
                    'file_name' => 'somefile.php',
                    'file_path' => 'somefile.php',
                    'snippet' => 'function psalmCanVerify(int $your_code) : ?string {
  return $as_you . "type";
}',
                    'from' => 48,
                    'to' => 55,
                    'snippet_from' => 6,
                    'snippet_to' => 86,
                    'column' => 43,
                ],
            ],
            $issue_data
        );
    }

    /**
     * @return array
     */
    public function providerTestJsonOutputErrors()
    {
        return [
            'returnTypeError' => [
                '<?php
                    function fooFoo(int $a) : string {
                        return $a + 1;
                    }',
                'message' => "The declared return type 'string' for fooFoo is incorrect, got 'int'",
                'line' => 2,
                'error' => 'string',
            ],
            'undefinedVar' => [
                '<?php
                    function fooFoo(int $a) : int {
                        return $b + 1;
                    }',
                'message' => 'Cannot find referenced variable $b',
                'line' => 3,
                'error' => '$b',
            ],
            'unknownParamClass' => [
                '<?php
                    function fooFoo(Badger\Bodger $a) : Badger\Bodger {
                        return $a;
                    }',
                'message' => 'Class or interface Badger\\Bodger does not exist',
                'line' => 2,
                'error' => 'Badger\\Bodger',
            ],
            'missingReturnType' => [
                '<?php
                    function fooFoo() {
                        return "hello";
                    }',
                'message' => 'Method fooFoo does not have a return type, expecting string',
                'line' => 2,
                'error' => 'function fooFoo() {',
            ],
            'wrongMultilineReturnType' => [
                '<?php
                    /**
                     * @return int
                     */
                    function fooFoo() {
                        return "hello";
                    }',
                'message' => 'The declared return type \'int\' for fooFoo is incorrect, got \'string\'',
                'line' => 3,
                'error' => '@return int',
            ],
            'wrongSingleLineReturnType' => [
                '<?php
                    /** @return int */
                    function fooFoo() {
                        return "hello";
                    }',
                'message' => 'The declared return type \'int\' for fooFoo is incorrect, got \'string\'',
                'line' => 2,
                'error' => '@return int',
            ],
        ];
    }
}
