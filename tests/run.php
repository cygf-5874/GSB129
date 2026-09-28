<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use Tplscan\Scanner;
use Tplscan\Token;

/**
 * 既有回归用例：覆盖文本 / 变量 / 标签 / 注释的识别、字节覆盖、`. `与 `[]` 路径、
 * 非法变量名报错、行号。
 *
 * 用法：php tests/run.php —— 全绿退 0，否则退 1。
 */

// `ScanException` 与 `Scanner` 定义在同一个文件里，先实例化一次把它带进来。
new Scanner();

const SCAN_EXCEPTION = 'Tplscan\\ScanException';

final class CaseFailure extends \Exception
{
    public function __construct(
        public readonly string $expected,
        public readonly string $actual,
        string $case
    ) {
        parent::__construct($case);
    }
}

function show(mixed $value): string
{
    $text = is_string($value) ? $value : var_export($value, true);
    $text = str_replace(["\n", "\r", "\t"], ['\n', '\r', '\t'], $text);

    return strlen($text) > 300 ? substr($text, 0, 300) . '…' : $text;
}

function expectSame(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new CaseFailure(show($expected), show($actual), 'assertion failed');
    }
}

function expectTrue(bool $ok, string $expected, string $actual): void
{
    if (!$ok) {
        throw new CaseFailure($expected, $actual, 'assertion failed');
    }
}

/** @return list<Token> */
function tokensOf(string $tpl): array
{
    Scanner::reset();
    $tokens = (new Scanner())->scan($tpl);

    if (!is_array($tokens)) {
        throw new CaseFailure('list<Token>', show($tokens), 'scan 没有返回 token 列表');
    }

    return $tokens;
}

function rawJoin(array $tokens): string
{
    $out = '';
    foreach ($tokens as $token) {
        $out .= $token->raw;
    }

    return $out;
}

/** @return list<string> */
function typesOf(array $tokens): array
{
    return array_map(static fn (Token $t): string => $t->type, $tokens);
}

/** @return list<string> */
function valuesOf(array $tokens): array
{
    return array_map(static fn (Token $t): string => $t->value, $tokens);
}

function thrownClass(callable $fn): string
{
    try {
        $fn();
    } catch (\Throwable $error) {
        return get_class($error);
    }

    return '';
}

$cases = [];

$cases[] = [
    '纯文本整体是一个 text token',
    static function (): void {
        $tokens = tokensOf('hello world');

        expectSame(1, count($tokens));
        expectSame(['text'], typesOf($tokens));
        expectSame('hello world', $tokens[0]->raw);
        expectSame('hello world', $tokens[0]->value);
    },
];

$cases[] = [
    '变量定界符 {{ }} 成对识别',
    static function (): void {
        $tokens = tokensOf('{{ name }}');

        expectSame(1, count($tokens));
        expectSame(Token::VAR, $tokens[0]->type);
        expectSame('{{ name }}', $tokens[0]->raw);
        expectSame('name', $tokens[0]->value);
    },
];

$cases[] = [
    '标签定界符 {% %} 成对识别',
    static function (): void {
        $tokens = tokensOf('{% if a %}');

        expectSame(1, count($tokens));
        expectSame(Token::TAG, $tokens[0]->type);
        expectSame('{% if a %}', $tokens[0]->raw);
        expectSame('if a', $tokens[0]->value);
    },
];

$cases[] = [
    '注释 {# #} 的内容原样保留',
    static function (): void {
        $tokens = tokensOf('{#  keep  #}');

        expectSame(1, count($tokens));
        expectSame(Token::COMMENT, $tokens[0]->type);
        expectSame('{#  keep  #}', $tokens[0]->raw);
        expectSame('  keep  ', $tokens[0]->value);
    },
];

$cases[] = [
    '文本 / 变量 / 标签 / 注释混排顺序正确',
    static function (): void {
        $tokens = tokensOf('A{{ v }}B{% t %}C{# c #}D');

        expectSame(
            ['A', 'v', 'B', 't', 'C', ' c ', 'D'],
            valuesOf($tokens)
        );
        expectSame(
            ['text', 'var', 'text', 'tag', 'text', 'comment', 'text'],
            typesOf($tokens)
        );
    },
];

$cases[] = [
    '所有 token 的 raw 拼接逐字节等于原模板',
    static function (): void {
        $tpl = 'x{{y}}z{%p%}q{#r#}s{t';

        $tokens = tokensOf($tpl);

        expectSame($tpl, rawJoin($tokens));
    },
];

$cases[] = [
    '普通 { 不是定界符，仍算文本',
    static function (): void {
        $tpl = 'a { b';

        $tokens = tokensOf($tpl);

        expectSame($tpl, rawJoin($tokens));
        foreach (typesOf($tokens) as $type) {
            expectSame(Token::TEXT, $type);
        }
    },
];

$cases[] = [
    '变量名支持 . 与 [] 路径',
    static function (): void {
        $first = tokensOf('{{ user.name }}');
        expectSame('user.name', $first[0]->value);

        $second = tokensOf('{{ items[0].id }}');
        expectSame('items[0].id', $second[0]->value);
    },
];

$cases[] = [
    '非法变量名抛 ScanException',
    static function (): void {
        expectSame(
            SCAN_EXCEPTION,
            thrownClass(static function (): void {
                tokensOf('{{ a-b }}');
            })
        );
    },
];

$cases[] = [
    '多行模板的行号从 1 起',
    static function (): void {
        $twoLines = tokensOf("a\n{{ v }}\nb");

        expectSame(3, count($twoLines));
        expectSame(1, $twoLines[0]->line);
        expectSame(Token::VAR, $twoLines[1]->type);
        expectSame(2, $twoLines[1]->line);
        expectSame("a\n{{ v }}\nb", rawJoin($twoLines));

        $threeLines = tokensOf("a\nb\n{{ v }}");

        expectSame(2, count($threeLines));
        expectSame(1, $threeLines[0]->line);
        expectSame(3, $threeLines[1]->line);
    },
];

$cases[] = [
    '返回值是 list（键从 0 连续编号）',
    static function (): void {
        $tokens = tokensOf('A{{ v }}B');

        expectSame([0, 1, 2], array_keys($tokens));
    },
];

$cases[] = [
    'token 的字段类型稳定，且 raw 非空',
    static function (): void {
        $tokens = tokensOf('A{{ v }}B');

        foreach ($tokens as $token) {
            expectTrue(is_string($token->type), 'type 是 string', gettype($token->type));
            expectTrue(is_string($token->raw), 'raw 是 string', gettype($token->raw));
            expectTrue(is_string($token->value), 'value 是 string', gettype($token->value));
            expectTrue(is_int($token->line), 'line 是 int', gettype($token->line));
            expectTrue(is_int($token->column), 'column 是 int', gettype($token->column));
            expectTrue($token->raw !== '', 'raw 非空', '空串');
        }
    },
];

$passed = 0;
$total = count($cases);

foreach ($cases as $index => $case) {
    $number = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
    $name = $case[0];

    try {
        $case[1]();
        $passed++;
        echo "PASS  $number $name\n";
    } catch (\Throwable $error) {
        $expected = $error instanceof CaseFailure ? $error->expected : '(无异常)';
        $actual = $error instanceof CaseFailure ? $error->actual : $error->getMessage();
        echo "FAIL  $number $name  期望=$expected 实际=$actual\n";
    }
}

echo "通过 $passed/$total\n";

exit($passed === $total ? 0 : 1);
